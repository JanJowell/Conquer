<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $accessibleEventIds = $user->managedEventIds();
        $paymentMethods = Category::paymentMethods();

        $categories = Category::with(['event.paymentMethods', 'startedBy'])
            ->withCount(['registrations', 'raceResults'])
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($accessibleEventIds) {
                $query->whereIn('event_id', $accessibleEventIds);
            })
            ->when($request->filled('event_id'), function ($query) use ($request) {
                $query->where('event_id', $request->integer('event_id'));
            })
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $events = Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->orderBy('event_date')
            ->get(['id', 'title', 'interest_type']);

        $selectedEvent = $request->filled('event_id')
            ? $events->firstWhere('id', $request->integer('event_id'))
            : null;

        return view('admin.categories.index', compact('categories', 'events', 'paymentMethods', 'selectedEvent'));
    }

    public function create(): View
    {
        $user = auth()->user();
        $paymentMethods = Category::paymentMethods();
        $events = Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->orderBy('event_date')
            ->get();

        return view('admin.categories.create', compact('events', 'paymentMethods'));
    }

    public function store(Request $request): RedirectResponse
    {
        $accessibleEventIds = $this->accessibleEventIds($request);
        $selectedEvent = Event::query()
            ->whereIn('id', $accessibleEventIds)
            ->find($request->input('event_id'));

        if ($selectedEvent) {
            $request->merge([
                'scheduled_start_date' => $request->input('scheduled_start_date', $selectedEvent->event_date?->format('Y-m-d')),
                'scheduled_end_date' => $request->input(
                    'scheduled_end_date',
                    $request->input('scheduled_start_date', $selectedEvent->event_date?->format('Y-m-d'))
                ),
            ]);
        }

        $usesSegmentedDistances = $this->usesSegmentedCategoryDistances($selectedEvent?->interest_type);

        $validated = $request->validate([
            'event_id' => ['required', Rule::in($accessibleEventIds)],
            'category_type' => ['required', Rule::in(array_keys($this->categoryTypes()))],
            'custom_category_name' => ['nullable', 'required_if:category_type,custom', 'string', 'max:255'],
            'distance_option' => [Rule::requiredIf(! $usesSegmentedDistances), 'nullable', Rule::in(array_keys($this->distanceOptions()))],
            'custom_distance_km' => ['nullable', 'required_if:distance_option,custom', 'numeric', 'min:0.01'],
            'scheduled_start_date' => ['required', 'date'],
            'scheduled_start_time' => ['required', 'date_format:H:i'],
            'scheduled_end_date' => ['required', 'date'],
            'scheduled_end_time' => ['required', 'date_format:H:i'],
            'description' => ['nullable', 'string'],
            'qualification_notes' => ['nullable', 'string', 'max:5000'],
            'requires_medical_certificate' => ['sometimes', 'boolean'],
            'checkpoint_map_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'slot_limit' => ['nullable', 'integer', 'min:1'],
            'price_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'price_currency' => ['required', 'string', 'size:3'],
            'payment_provider' => ['nullable', Rule::in(array_keys(Category::paymentMethods()))],
            'payment_account_name' => ['nullable', 'string', 'max:255'],
            'payment_account_number' => ['nullable', 'string', 'max:255'],
            'payment_instructions' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:open,closed,draft'],
            ...$this->categoryTypeDetailValidationRules($selectedEvent?->interest_type),
        ]);

        $event = Event::query()->whereIn('id', $accessibleEventIds)->findOrFail($validated['event_id']);

        if ($errors = $this->categoryScheduleErrors(
            $event,
            $validated['scheduled_start_date'],
            $validated['scheduled_start_time'],
            $validated['scheduled_end_date'],
            $validated['scheduled_end_time']
        )) {
            return back()->withErrors($errors)->withInput();
        }

        $validated['type_details'] = $this->normalizedCategoryTypeDetails($event->interest_type, $validated['type_details'] ?? []);
        $validated['distance_km'] = Category::distanceFromTypeDetails($event->interest_type, $validated['type_details'])
            ?? $this->distanceValue($validated);
        $validated['name'] = $this->nameWithDistance($this->categoryTypeName($validated), (float) $validated['distance_km']);
        $this->applyPriceFields($validated);
        unset($validated['category_type'], $validated['custom_category_name'], $validated['distance_option'], $validated['custom_distance_km']);

        $checkpointMapPath = null;

        try {
            if ($request->hasFile('checkpoint_map_image_upload')) {
                $checkpointMapPath = $request->file('checkpoint_map_image_upload')
                    ->store('categories/checkpoint-maps', 'public');
                $validated['checkpoint_map_image'] = $checkpointMapPath;
            }

            unset($validated['checkpoint_map_image_upload']);

            $category = DB::transaction(function () use ($validated) {
                $category = Category::create($validated);
                $category->event?->refreshAutomaticStatus();

                return $category;
            });
        } catch (Throwable $exception) {
            if ($checkpointMapPath) {
                Storage::disk('public')->delete($checkpointMapPath);
            }

            throw $exception;
        }

        return redirect()
            ->route('admin.categories.index', ['event_id' => $validated['event_id']])
            ->with('success', 'Category created successfully. The event status will update automatically when setup is ready.');
    }

    public function edit(Category $category): View
    {
        abort_unless($this->canAccessCategory($category), 403);

        $category->load('event')->loadCount(['registrations', 'raceResults']);
        $categoryInUse = $category->registrations_count > 0 || $category->race_results_count > 0;
        $categoryType = $this->categoryTypeFromName($category);
        $distanceOption = $this->distanceOptionFromValue((float) $category->distance_km);
        $paymentMethods = Category::paymentMethods();

        return view('admin.categories.edit', compact('category', 'categoryInUse', 'categoryType', 'distanceOption', 'paymentMethods'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        abort_unless($this->canAccessCategory($category), 403);

        $category->loadCount(['registrations', 'raceResults']);
        $categoryInUse = $category->registrations_count > 0 || $category->race_results_count > 0;
        $usesSegmentedDistances = $this->usesSegmentedCategoryDistances($category->event?->interest_type);

        if (! $category->started_at) {
            $request->merge([
                'scheduled_start_date' => $request->input(
                    'scheduled_start_date',
                    $category->scheduled_start_date?->format('Y-m-d') ?? $category->event?->event_date?->format('Y-m-d')
                ),
                'scheduled_end_date' => $request->input(
                    'scheduled_end_date',
                    $category->scheduled_end_date?->format('Y-m-d')
                        ?? $category->scheduled_start_date?->format('Y-m-d')
                        ?? $category->event?->event_date?->format('Y-m-d')
                ),
            ]);
        }

        $rules = [
            ...($category->started_at ? [] : [
                'scheduled_start_date' => ['required', 'date'],
                'scheduled_start_time' => ['required', 'date_format:H:i'],
                'scheduled_end_date' => ['required', 'date'],
                'scheduled_end_time' => ['required', 'date_format:H:i'],
            ]),
            'description' => ['nullable', 'string'],
            'qualification_notes' => ['nullable', 'string', 'max:5000'],
            'requires_medical_certificate' => ['sometimes', 'boolean'],
            'checkpoint_map_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_checkpoint_map_image' => ['sometimes', 'boolean'],
            'slot_limit' => ['nullable', 'integer', 'min:1'],
            'price_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'price_currency' => ['required', 'string', 'size:3'],
            'payment_provider' => ['nullable', Rule::in(array_keys(Category::paymentMethods()))],
            'payment_account_name' => ['nullable', 'string', 'max:255'],
            'payment_account_number' => ['nullable', 'string', 'max:255'],
            'payment_instructions' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'in:open,closed,draft'],
        ];

        if (! $categoryInUse) {
            $rules = [
                'category_type' => ['required', Rule::in(array_keys($this->categoryTypes()))],
                'custom_category_name' => ['nullable', 'required_if:category_type,custom', 'string', 'max:255'],
                'distance_option' => [Rule::requiredIf(! $usesSegmentedDistances), 'nullable', Rule::in(array_keys($this->distanceOptions()))],
                'custom_distance_km' => ['nullable', 'required_if:distance_option,custom', 'numeric', 'min:0.01'],
                ...$this->categoryTypeDetailValidationRules($category->event?->interest_type),
                ...$rules,
            ];
        } else {
            $rules = [
                ...$this->categoryTypeDetailValidationRules($category->event?->interest_type, true),
                ...$rules,
            ];
        }

        $validated = $request->validate($rules);

        if (! $category->started_at && ($errors = $this->categoryScheduleErrors(
            $category->event,
            $validated['scheduled_start_date'],
            $validated['scheduled_start_time'],
            $validated['scheduled_end_date'],
            $validated['scheduled_end_time']
        ))) {
            return back()->withErrors($errors)->withInput();
        }

        if (! $categoryInUse) {
            $validated['type_details'] = $this->normalizedCategoryTypeDetails(
                $category->event?->interest_type,
                $validated['type_details'] ?? []
            );
            $validated['distance_km'] = Category::distanceFromTypeDetails($category->event?->interest_type, $validated['type_details'])
                ?? $this->distanceValue($validated);
            $validated['name'] = $this->nameWithDistance($this->categoryTypeName($validated), (float) $validated['distance_km']);
            unset($validated['category_type'], $validated['custom_category_name'], $validated['distance_option'], $validated['custom_distance_km']);
        } elseif (array_key_exists('type_details', $validated)) {
            $mutableKeys = collect(config("conquer.event_category_type_details.{$category->event?->interest_type}", []))
                ->reject(fn (array $definition) => $definition['locked_when_in_use'] ?? false)
                ->keys()
                ->all();
            $submittedDetails = $this->normalizedCategoryTypeDetails(
                $category->event?->interest_type,
                $validated['type_details']
            );
            $validated['type_details'] = [
                ...(is_array($category->type_details) ? $category->type_details : []),
                ...collect($submittedDetails)->only($mutableKeys)->all(),
            ];
        }

        $this->applyPriceFields($validated);

        if ($categoryInUse) {
            $validated['requires_medical_certificate'] = $category->requiresMedicalCertificate();
        }

        $oldCheckpointMapPath = $category->checkpoint_map_image;
        $newCheckpointMapPath = null;

        try {
            if ($request->hasFile('checkpoint_map_image_upload')) {
                $newCheckpointMapPath = $request->file('checkpoint_map_image_upload')
                    ->store('categories/checkpoint-maps', 'public');
                $validated['checkpoint_map_image'] = $newCheckpointMapPath;
            } elseif ($request->boolean('remove_checkpoint_map_image')) {
                $validated['checkpoint_map_image'] = null;
            }

            unset($validated['checkpoint_map_image_upload'], $validated['remove_checkpoint_map_image']);

            DB::transaction(function () use ($category, $validated) {
                $category->update($validated);
                $category->event?->refreshAutomaticStatus();
            });
        } catch (Throwable $exception) {
            if ($newCheckpointMapPath) {
                Storage::disk('public')->delete($newCheckpointMapPath);
            }

            throw $exception;
        }

        if ($oldCheckpointMapPath && $oldCheckpointMapPath !== $category->fresh()->checkpoint_map_image) {
            Storage::disk('public')->delete($oldCheckpointMapPath);
        }

        return redirect()
            ->route('admin.categories.index', ['event_id' => $category->event_id])
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        abort_unless($this->canAccessCategory($category), 403);

        $category->loadCount(['registrations', 'raceResults']);
        $hasRecords = $category->registrations_count > 0 || $category->race_results_count > 0;

        if ($hasRecords && ! $request->boolean('delete_with_records')) {
            return back()->with('error', 'This category has registrations or results. Confirm the warning before deleting it.');
        }

        $event = $category->event;
        $checkpointMapPath = $category->checkpoint_map_image;

        $category->delete();

        if ($checkpointMapPath) {
            Storage::disk('public')->delete($checkpointMapPath);
        }

        $event?->refreshAutomaticStatus();

        return back()->with('success', $hasRecords
            ? 'Category and its related registrations/results were deleted successfully.'
            : 'Category deleted successfully.');
    }

    public function start(Request $request, Category $category): RedirectResponse
    {
        abort_unless($this->canAccessCategory($category), 403);

        $category->load('event');

        if ($category->started_at) {
            return back()->with('error', "{$category->name} already started at {$category->started_at->format('M j, Y g:i:s A')}.");
        }

        if ($category->status === 'draft') {
            return back()->with('error', 'A draft category cannot be started. Open or close registration first.');
        }

        $scheduledStartAt = $category->scheduledStartAt();

        if (! $scheduledStartAt) {
            return back()->with('error', 'Set the event date and category scheduled gun start before starting this category.');
        }

        if (now()->lt($scheduledStartAt)) {
            return back()->with('error', 'This category cannot start before its scheduled time at '.$scheduledStartAt->format('M j, Y g:i A').'.');
        }

        $startedAt = now();
        $started = DB::transaction(fn () => Category::query()
            ->whereKey($category->id)
            ->whereNull('started_at')
            ->update([
                'started_at' => $startedAt,
                'started_by_user_id' => $request->user()->id,
                'updated_at' => $startedAt,
            ]));

        if (! $started) {
            return back()->with('error', "{$category->name} was already started by another administrator.");
        }

        return back()->with('success', "{$category->name} started at {$startedAt->format('g:i:s A')}. Finish times will now use this category start.");
    }

    private function accessibleEventIds(Request $request): array
    {
        $user = $request->user();

        if (! $user->managesAssignedEventsOnly()) {
            return Event::pluck('id')->all();
        }

        return $user->managedEventIds();
    }

    private function canAccessCategory(Category $category): bool
    {
        $event = $category->event;

        return $event && auth()->user()->canManageEvent($event);
    }

    private function categoryTypes(): array
    {
        return [
            'open' => 'Open',
            'male' => 'Male',
            'female' => 'Female',
            'elite' => 'Elite',
            'beginner' => 'Beginner',
            'kids' => 'Kids',
            'senior' => 'Senior',
            'custom' => 'Custom',
        ];
    }

    private function categoryTypeName(array $data): string
    {
        if ($data['category_type'] === 'custom') {
            return trim($data['custom_category_name']);
        }

        return $this->categoryTypes()[$data['category_type']];
    }

    private function distanceOptions(): array
    {
        return [
            '1' => '1K',
            '3' => '3K',
            '5' => '5K',
            '10' => '10K',
            '21' => '21K',
            '42' => '42K',
            'custom' => 'Custom',
        ];
    }

    private function usesSegmentedCategoryDistances(?string $eventType): bool
    {
        return in_array($eventType, ['Triathlon', 'Duathlon'], true);
    }

    private function categoryTypeDetailValidationRules(?string $eventType, bool $onlyMutable = false): array
    {
        $schema = collect(config("conquer.event_category_type_details.{$eventType}", []))
            ->when($onlyMutable, fn ($definitions) => $definitions->reject(
                fn (array $definition) => $definition['locked_when_in_use'] ?? false
            ))
            ->all();

        if ($schema === []) {
            return [];
        }

        $rules = ['type_details' => ['required', 'array']];

        foreach ($schema as $key => $definition) {
            $fieldRules = $definition['rules'];

            if (($definition['type'] ?? null) === 'select' && isset($definition['options'])) {
                $fieldRules[] = Rule::in($definition['options']);
            }

            $rules["type_details.{$key}"] = $fieldRules;
        }

        return $rules;
    }

    private function normalizedCategoryTypeDetails(?string $eventType, mixed $details): array
    {
        if (! is_array($details)) {
            return [];
        }

        return collect(config("conquer.event_category_type_details.{$eventType}", []))
            ->mapWithKeys(fn (array $definition, string $key) => [$key => $details[$key] ?? null])
            ->filter(fn ($value) => filled($value))
            ->all();
    }

    private function distanceValue(array $data): float
    {
        if ($data['distance_option'] === 'custom') {
            return (float) $data['custom_distance_km'];
        }

        return (float) $data['distance_option'];
    }

    private function categoryTypeFromName(Category $category): array
    {
        $typeName = trim(preg_replace('/^'.preg_quote($this->distanceLabel((float) $category->distance_km) ?? '', '/').'\s*/i', '', $category->name));
        $matchedKey = collect($this->categoryTypes())
            ->filter(fn ($label, $key) => $key !== 'custom' && strtolower($label) === strtolower($typeName))
            ->keys()
            ->first();

        return [
            'key' => $matchedKey ?: 'custom',
            'custom' => $matchedKey ? null : $typeName,
        ];
    }

    private function distanceOptionFromValue(float $distanceKm): string
    {
        $distance = rtrim(rtrim(number_format($distanceKm, 2, '.', ''), '0'), '.');

        return array_key_exists($distance, $this->distanceOptions()) ? $distance : 'custom';
    }

    private function nameWithDistance(string $name, float $distanceKm): string
    {
        $name = trim($name);
        $distanceLabel = $this->distanceLabel($distanceKm);

        if ($distanceLabel === null || str_contains(strtolower($name), strtolower($distanceLabel))) {
            return $name;
        }

        return trim($distanceLabel.' '.$name);
    }

    private function distanceLabel(float $distanceKm): ?string
    {
        if ($distanceKm <= 0) {
            return null;
        }

        $distance = rtrim(rtrim(number_format($distanceKm, 2, '.', ''), '0'), '.');

        return $distance.'K';
    }

    private function applyPriceFields(array &$data): void
    {
        $priceAmount = (float) ($data['price_amount'] ?? 0);

        $data['price_cents'] = (int) round($priceAmount * 100);
        $data['price_currency'] = strtoupper($data['price_currency'] ?? 'PHP');

        unset($data['price_amount']);
    }

    private function categoryScheduleErrors(
        ?Event $event,
        string $scheduledStartDate,
        string $scheduledStartTime,
        string $scheduledEndDate,
        string $scheduledEndTime
    ): array
    {
        if (! $event) {
            return ['event_id' => 'Select a valid event for this category.'];
        }

        $timezone = config('app.timezone');
        $startAt = Carbon::parse("{$scheduledStartDate} {$scheduledStartTime}", $timezone);
        $endAt = Carbon::parse("{$scheduledEndDate} {$scheduledEndTime}", $timezone);
        $eventStartsAt = Carbon::parse(
            $event->event_date->format('Y-m-d').' '.($event->start_time?->format('H:i') ?? '00:00'),
            $timezone
        );
        $eventEndsAt = Carbon::parse(
            ($event->event_end_date ?? $event->event_date)->format('Y-m-d').' '.($event->end_time?->format('H:i') ?? '23:59:59'),
            $timezone
        );

        if ($startAt->lt($eventStartsAt)) {
            return ['scheduled_start_time' => 'The scheduled gun start cannot be before the event starts.'];
        }

        if ($startAt->gte($eventEndsAt)) {
            return ['scheduled_start_time' => 'The scheduled gun start must be before the event ends.'];
        }

        if ($endAt->lte($startAt)) {
            return ['scheduled_end_time' => 'The category cutoff/end date and time must be after the scheduled gun start.'];
        }

        if ($endAt->gt($eventEndsAt)) {
            return ['scheduled_end_time' => 'The category cutoff/end cannot be after the event ends.'];
        }

        return [];
    }
}
