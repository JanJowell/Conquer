<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Checkpoint;
use App\Models\CommunityPost;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $interestTypes = $this->eventInterestTypes();

        $this->completePastUpcomingEvents($user);

        $events = Event::query()
            ->with(['manager'])
            ->withCount(['categories', 'registrations', 'raceResults'])
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->when($request->filled('status') && $request->string('status')->value() === 'draft', function ($query) {
                $query->where('status', 'draft');
            })
            ->when($request->filled('status') && $request->string('status')->value() !== 'draft', function ($query) {
                $query->where('status', '!=', 'draft');
            })
            ->when($request->filled('interest_type'), function ($query) use ($request) {
                $query->where('interest_type', $request->string('interest_type'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('venue', 'like', "%{$search}%")
                        ->orWhere('organized_by', 'like', "%{$search}%")
                        ->orWhere('interest_type', 'like', "%{$search}%");
                });
            })
            ->orderBy('event_date')
            ->paginate(10)
            ->withQueryString();

        if ($request->filled('status') && $request->string('status')->value() !== 'draft') {
            $events->setCollection($events->getCollection()
                ->filter(fn (Event $event) => $event->effective_status === $request->string('status')->value())
                ->values());
        }

        return view('admin.events.index', compact('events', 'interestTypes'));
    }

    public function create(): View
    {
        $user = auth()->user();
        $interestTypes = $this->eventInterestTypes();
        $categoryTypes = $this->categoryTypes();
        $distanceOptions = $this->distanceOptions();
        $paymentMethods = Category::paymentMethods();

        $managers = User::query()
            ->whereIn('role', [User::ROLE_EVENT_MANAGER, User::ROLE_LEGACY_ADMIN])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.events.create', compact('interestTypes', 'managers', 'categoryTypes', 'distanceOptions', 'paymentMethods'));
    }

    public function show(Event $event): View
    {
        abort_unless($this->canAccessEvent($event), 403);

        $event->load([
            'manager',
            'categories' => fn ($query) => $query->withCount('registrations')->orderBy('distance_km'),
            'checkpoints' => fn ($query) => $query->orderBy('order')->orderBy('name'),
            'announcements' => fn ($query) => $query->latest()->take(5),
        ])->loadCount(['categories', 'registrations', 'raceResults']);

        $participantStatusCounts = $event->registrations()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentRegistrations = $event->registrations()
            ->with(['user', 'category'])
            ->latest('registered_at')
            ->latest('id')
            ->take(8)
            ->get();
        $readinessErrors = $event->publicReadinessErrors();

        return view('admin.events.show', compact('event', 'participantStatusCounts', 'recentRegistrations', 'readinessErrors'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $usesSegmentedDistances = $this->usesSegmentedCategoryDistances($request->input('interest_type'));
        $eventStartDate = $request->input('event_date');
        $eventEndDate = $request->input('event_end_date', $eventStartDate);
        $request->merge([
            'event_end_date' => $eventEndDate,
            'categories' => $this->categoryRowsWithScheduleDates(
                $this->filledCategoryRows($request->input('categories', [])),
                $eventStartDate
            ),
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'venue' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'event_end_date' => ['required', 'date', 'after_or_equal:event_date'],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:event_date'],
            'banner_image' => ['nullable', 'string', 'max:255'],
            'banner_image_upload' => ['nullable', 'image', 'max:4096'],
            'organized_by' => ['nullable', 'string', 'max:255'],
            'interest_type' => ['required', Rule::in($this->eventInterestTypes())],
            'manager_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', [User::ROLE_EVENT_MANAGER, User::ROLE_LEGACY_ADMIN])),
            ],
            'categories' => ['nullable', 'array'],
            'categories.*.category_type' => ['required', Rule::in(array_keys($this->categoryTypes()))],
            'categories.*.custom_category_name' => ['nullable', 'required_if:categories.*.category_type,custom', 'string', 'max:255'],
            'categories.*.distance_option' => [Rule::requiredIf(! $usesSegmentedDistances), 'nullable', Rule::in(array_keys($this->distanceOptions()))],
            'categories.*.custom_distance_km' => ['nullable', 'required_if:categories.*.distance_option,custom', 'numeric', 'min:0.01'],
            'categories.*.scheduled_start_date' => ['required', 'date'],
            'categories.*.scheduled_start_time' => ['required', 'date_format:H:i'],
            'categories.*.scheduled_end_date' => ['required', 'date'],
            'categories.*.scheduled_end_time' => ['required', 'date_format:H:i'],
            'categories.*.description' => ['nullable', 'string'],
            'categories.*.slot_limit' => ['nullable', 'integer', 'min:1'],
            'categories.*.price_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'categories.*.price_currency' => ['required', 'string', 'size:3'],
            'categories.*.payment_provider' => ['nullable', Rule::in(array_keys(Category::paymentMethods()))],
            'categories.*.payment_account_name' => ['nullable', 'string', 'max:255'],
            'categories.*.payment_account_number' => ['nullable', 'string', 'max:255'],
            'categories.*.payment_instructions' => ['nullable', 'string', 'max:5000'],
            'categories.*.status' => ['required', 'in:open,closed,draft'],
            ...$this->categoryTypeDetailValidationRules($request->input('interest_type')),
            ...$this->typeDetailValidationRules($request),
        ]);

        if ($errors = $this->eventScheduleErrors($validated)) {
            return back()->withErrors($errors)->withInput();
        }

        if ($errors = $this->categorySetupErrors($validated['categories'] ?? [], $validated)) {
            return back()->withErrors($errors)->withInput();
        }

        $categoryRows = $validated['categories'] ?? [];
        unset($validated['categories']);
        $this->normalizeTypeDetails($validated, $request);

        $validated['status'] = 'draft';

        if ($user->managesAssignedEventsOnly()) {
            $validated['manager_id'] = $user->id;
        }

        if ($request->hasFile('banner_image_upload')) {
            $validated['banner_image'] = $request->file('banner_image_upload')->store('events/banners', 'public');
        }

        unset($validated['banner_image_upload']);

        $event = DB::transaction(function () use ($validated, $categoryRows) {
            $event = Event::create([
                ...$validated,
                'slug' => Str::slug($validated['title'].'-'.time()),
            ]);

            $this->createCategoriesForEvent($event, $categoryRows);
            $event->refreshAutomaticStatus();

            return $event;
        });

        return redirect()
            ->route('admin.events.show', $event)
            ->with('success', 'Event setup saved. The status updates automatically when event details, categories, and payment setup are ready.');
    }

    public function edit(Event $event): View
    {
        abort_unless($this->canAccessEvent($event), 403);

        $event->load([
            'categories' => fn ($query) => $query->withCount(['registrations', 'raceResults'])->orderBy('distance_km')->orderBy('name'),
        ])->loadCount(['categories', 'registrations', 'raceResults']);
        $interestTypes = $this->eventInterestTypes();
        $categoryTypes = $this->categoryTypes();
        $distanceOptions = $this->distanceOptions();
        $paymentMethods = Category::paymentMethods();

        $managers = User::query()
            ->whereIn('role', [User::ROLE_EVENT_MANAGER, User::ROLE_LEGACY_ADMIN])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.events.edit', compact('event', 'interestTypes', 'managers', 'categoryTypes', 'distanceOptions', 'paymentMethods'));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        abort_unless($this->canAccessEvent($event), 403);

        $user = $request->user();
        $usesSegmentedDistances = $this->usesSegmentedCategoryDistances($request->input('interest_type'));
        $eventStartDate = $request->input('event_date');
        $eventEndDate = $request->input('event_end_date', $eventStartDate);
        $request->merge([
            'event_end_date' => $eventEndDate,
            'categories' => $this->categoryRowsWithScheduleDates(
                $this->filledCategoryRows($request->input('categories', [])),
                $eventStartDate
            ),
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'venue' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'event_end_date' => ['required', 'date', 'after_or_equal:event_date'],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:event_date'],
            'banner_image' => ['nullable', 'string', 'max:255'],
            'banner_image_upload' => ['nullable', 'image', 'max:4096'],
            'organized_by' => ['nullable', 'string', 'max:255'],
            'interest_type' => ['required', Rule::in($this->eventInterestTypes())],
            'manager_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', [User::ROLE_EVENT_MANAGER, User::ROLE_LEGACY_ADMIN])),
            ],
            'categories' => ['nullable', 'array'],
            'categories.*.category_type' => ['required', Rule::in(array_keys($this->categoryTypes()))],
            'categories.*.custom_category_name' => ['nullable', 'required_if:categories.*.category_type,custom', 'string', 'max:255'],
            'categories.*.distance_option' => [Rule::requiredIf(! $usesSegmentedDistances), 'nullable', Rule::in(array_keys($this->distanceOptions()))],
            'categories.*.custom_distance_km' => ['nullable', 'required_if:categories.*.distance_option,custom', 'numeric', 'min:0.01'],
            'categories.*.scheduled_start_date' => ['required', 'date'],
            'categories.*.scheduled_start_time' => ['required', 'date_format:H:i'],
            'categories.*.scheduled_end_date' => ['required', 'date'],
            'categories.*.scheduled_end_time' => ['required', 'date_format:H:i'],
            'categories.*.description' => ['nullable', 'string'],
            'categories.*.slot_limit' => ['nullable', 'integer', 'min:1'],
            'categories.*.price_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'categories.*.price_currency' => ['required', 'string', 'size:3'],
            'categories.*.payment_provider' => ['nullable', Rule::in(array_keys(Category::paymentMethods()))],
            'categories.*.payment_account_name' => ['nullable', 'string', 'max:255'],
            'categories.*.payment_account_number' => ['nullable', 'string', 'max:255'],
            'categories.*.payment_instructions' => ['nullable', 'string', 'max:5000'],
            'categories.*.status' => ['required', 'in:open,closed,draft'],
            ...$this->categoryTypeDetailValidationRules($request->input('interest_type')),
            ...$this->typeDetailValidationRules($request),
        ]);

        if ($errors = $this->eventScheduleErrors($validated)) {
            return back()->withErrors($errors)->withInput();
        }

        if ($errors = $this->categorySetupErrors($validated['categories'] ?? [], $validated)) {
            return back()->withErrors($errors)->withInput();
        }

        if ($errors = $this->existingCategoryScheduleErrors($event, $validated)) {
            return back()->withErrors($errors)->withInput();
        }

        $categoryRows = $validated['categories'] ?? [];
        unset($validated['categories']);
        $this->normalizeTypeDetails($validated, $request, $event);

        if ($user->managesAssignedEventsOnly()) {
            $validated['manager_id'] = $user->id;
        }

        if ($request->hasFile('banner_image_upload')) {
            $validated['banner_image'] = $request->file('banner_image_upload')->store('events/banners', 'public');
        }

        unset($validated['banner_image_upload']);

        DB::transaction(function () use ($event, $validated, $categoryRows) {
            $event->update([
                ...$validated,
                'slug' => Str::slug($validated['title'].'-'.$event->id),
            ]);

            $this->createCategoriesForEvent($event, $categoryRows);
            $event->refreshAutomaticStatus();
        });

        return redirect()->route('admin.events.show', $event)->with('success', 'Event setup updated. Status was detected automatically.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        abort_unless($this->canAccessEvent($event), 403);

        $event->loadCount(['categories', 'registrations', 'raceResults']);
        $checkpointsCount = Checkpoint::where('event_id', $event->id)->count();
        $communityPostsCount = CommunityPost::where('event_id', $event->id)->count();
        $dependentCount = $event->categories_count
            + $event->registrations_count
            + $event->race_results_count
            + $checkpointsCount
            + $communityPostsCount;

        if ($dependentCount > 0) {
            return back()->with('error', 'This event has related setup, registrations, results, checkpoints, or community posts. Mark it completed instead of deleting it.');
        }

        $event->delete();

        return back()->with('success', 'Event deleted successfully.');
    }

    private function canAccessEvent(Event $event): bool
    {
        return auth()->user()->canManageEvent($event);
    }

    private function eventInterestTypes(): array
    {
        return config('conquer.event_interest_types', []);
    }

    private function typeDetailValidationRules(Request $request): array
    {
        if (! $request->has('type_details')) {
            return [];
        }

        $interestType = $request->input('interest_type');
        $schema = Event::typeDetailSchema(is_string($interestType) ? $interestType : null);
        $rules = [
            'type_details' => ['required', 'array'],
            "type_details.{$interestType}" => ['required', 'array'],
        ];

        foreach ($schema as $key => $definition) {
            if (($definition['category_owned'] ?? false)
                && ! $request->has("type_details.{$interestType}.{$key}")) {
                continue;
            }

            $fieldRules = $definition['rules'] ?? ['nullable'];

            if (($definition['type'] ?? null) === 'select' && isset($definition['options'])) {
                $fieldRules[] = Rule::in($definition['options']);
            }

            $rules["type_details.{$interestType}.{$key}"] = $fieldRules;
        }

        return $rules;
    }

    private function normalizeTypeDetails(array &$validated, Request $request, ?Event $event = null): void
    {
        if (! $request->has('type_details')) {
            if ($event && $event->interest_type !== ($validated['interest_type'] ?? null)) {
                $validated['type_details'] = collect(Event::typeDetailSchema($validated['interest_type'] ?? null))
                    ->mapWithKeys(fn (array $definition, string $key) => [
                        $key => ($definition['type'] ?? null) === 'boolean' ? false : null,
                    ])
                    ->all();
            } else {
                unset($validated['type_details']);
            }

            return;
        }

        $interestType = $validated['interest_type'];
        $schema = Event::typeDetailSchema($interestType);
        $submitted = data_get($validated, "type_details.{$interestType}", []);
        $details = [];

        foreach ($schema as $key => $definition) {
            if (($definition['category_owned'] ?? false)
                && ! array_key_exists($key, $submitted)) {
                if ($event
                    && $event->interest_type === $interestType
                    && array_key_exists($key, $event->type_details ?? [])) {
                    $details[$key] = $event->type_details[$key];
                }

                continue;
            }

            $value = $submitted[$key] ?? null;
            $details[$key] = ($definition['type'] ?? null) === 'boolean'
                ? filter_var($value, FILTER_VALIDATE_BOOLEAN)
                : $value;
        }

        $validated['type_details'] = $details;
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

    private function categoryTypeDetailValidationRules(?string $eventType): array
    {
        $schema = config("conquer.event_category_type_details.{$eventType}", []);

        if ($schema === []) {
            return [];
        }

        $rules = ['categories.*.type_details' => ['required', 'array']];

        foreach ($schema as $key => $definition) {
            $rules["categories.*.type_details.{$key}"] = $definition['rules'];
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

    private function filledCategoryRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)
            ->filter(fn ($row) => is_array($row) && $this->categoryRowHasIntent($row))
            ->values()
            ->all();
    }

    private function categoryRowHasIntent(array $row): bool
    {
        if (collect($row['type_details'] ?? [])->contains(fn ($value) => filled($value))) {
            return true;
        }

        foreach (['category_type', 'custom_category_name', 'distance_option', 'custom_distance_km', 'description', 'slot_limit', 'payment_provider', 'payment_account_name', 'payment_account_number', 'payment_instructions'] as $field) {
            if (filled($row[$field] ?? null)) {
                return true;
            }
        }

        return (float) ($row['price_amount'] ?? 0) > 0;
    }

    private function categoryRowsWithScheduleDates(array $rows, ?string $eventStartDate): array
    {
        return collect($rows)
            ->map(function (array $row) use ($eventStartDate) {
                $row['scheduled_start_date'] ??= $eventStartDate;
                $row['scheduled_end_date'] ??= $row['scheduled_start_date'] ?? $eventStartDate;

                return $row;
            })
            ->all();
    }

    private function createCategoriesForEvent(Event $event, array $rows): void
    {
        foreach ($rows as $row) {
            $typeDetails = $this->normalizedCategoryTypeDetails($event->interest_type, $row['type_details'] ?? []);
            $distanceKm = Category::distanceFromTypeDetails($event->interest_type, $typeDetails)
                ?? $this->distanceValue($row);

            $event->categories()->create([
                'name' => $this->nameWithDistance($this->categoryTypeName($row), $distanceKm),
                'distance_km' => $distanceKm,
                'type_details' => $typeDetails ?: null,
                'description' => $row['description'] ?? null,
                'slot_limit' => $row['slot_limit'] ?? null,
                'price_cents' => (int) round((float) ($row['price_amount'] ?? 0) * 100),
                'price_currency' => strtoupper($row['price_currency'] ?? 'PHP'),
                'payment_provider' => $row['payment_provider'] ?? null,
                'payment_account_name' => $row['payment_account_name'] ?? null,
                'payment_account_number' => $row['payment_account_number'] ?? null,
                'payment_instructions' => $row['payment_instructions'] ?? null,
                'status' => $row['status'] ?? 'open',
                'scheduled_start_date' => $row['scheduled_start_date'],
                'scheduled_start_time' => $row['scheduled_start_time'],
                'scheduled_end_date' => $row['scheduled_end_date'],
                'scheduled_end_time' => $row['scheduled_end_time'],
            ]);
        }
    }

    private function categorySetupErrors(array $rows, array $eventSchedule): array
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            $number = $index + 1;

            if (($row['category_type'] ?? null) === 'custom' && blank($row['custom_category_name'] ?? null)) {
                $errors["categories.{$index}.custom_category_name"] = "Category {$number}: enter a custom category type.";
            }

            if (! $this->usesSegmentedCategoryDistances($eventSchedule['interest_type'] ?? null)
                && ($row['distance_option'] ?? null) === 'custom'
                && blank($row['custom_distance_km'] ?? null)) {
                $errors["categories.{$index}.custom_distance_km"] = "Category {$number}: enter a custom distance.";
            }

            $scheduleError = $this->scheduleWindowError(
                $row['scheduled_start_date'] ?? null,
                $row['scheduled_start_time'] ?? null,
                $row['scheduled_end_date'] ?? null,
                $row['scheduled_end_time'] ?? null,
                $eventSchedule['event_date'] ?? null,
                $eventSchedule['start_time'] ?? null,
                $eventSchedule['event_end_date'] ?? null,
                $eventSchedule['end_time'] ?? null
            );

            if ($scheduleError) {
                $errors["categories.{$index}.{$scheduleError['field']}"] = "Category {$number}: {$scheduleError['message']}";
            }

            if ((float) ($row['price_amount'] ?? 0) <= 0) {
                continue;
            }

            if (blank($row['payment_provider'] ?? null)) {
                $errors["categories.{$index}.payment_provider"] = "Category {$number}: paid categories require a payment method.";
            }

            if (blank($row['payment_account_name'] ?? null)) {
                $errors["categories.{$index}.payment_account_name"] = "Category {$number}: paid categories require a payment account name.";
            }

            if (blank($row['payment_account_number'] ?? null) && blank($row['payment_instructions'] ?? null)) {
                $errors["categories.{$index}.payment_account_number"] = "Category {$number}: paid categories require an account number or clear payment instructions.";
            }
        }

        return $errors;
    }

    private function existingCategoryScheduleErrors(Event $event, array $eventSchedule): array
    {
        $event->loadMissing('categories');

        foreach ($event->categories as $category) {
            if (! $category->scheduledStartAt() && ! $category->scheduledEndAt()) {
                continue;
            }

            $scheduleError = $this->scheduleWindowError(
                $category->scheduled_start_date?->format('Y-m-d') ?? $event->event_date?->format('Y-m-d'),
                $category->scheduled_start_time?->format('H:i') ?? $event->start_time?->format('H:i'),
                $category->scheduled_end_date?->format('Y-m-d') ?? $category->scheduled_start_date?->format('Y-m-d') ?? $event->event_date?->format('Y-m-d'),
                $category->scheduled_end_time?->format('H:i') ?? $event->end_time?->format('H:i'),
                $eventSchedule['event_date'] ?? null,
                $eventSchedule['start_time'] ?? null,
                $eventSchedule['event_end_date'] ?? null,
                $eventSchedule['end_time'] ?? null
            );

            if ($scheduleError) {
                $eventField = $scheduleError['field'] === 'scheduled_start_time'
                    ? 'start_time'
                    : 'end_time';

                return [$eventField => "The updated event schedule would place {$category->name} outside the event date range."];
            }
        }

        return [];
    }

    private function eventScheduleErrors(array $data): array
    {
        if (empty($data['start_time']) || empty($data['end_time'])) {
            return [];
        }

        $startAt = Carbon::parse($data['event_date'].' '.$data['start_time'], config('app.timezone'));
        $endAt = Carbon::parse($data['event_end_date'].' '.$data['end_time'], config('app.timezone'));

        return $endAt->lte($startAt)
            ? ['end_time' => 'The event end date and time must be after the event start date and time.']
            : [];
    }

    /** @return array{field: string, message: string}|null */
    private function scheduleWindowError(
        ?string $startDate,
        ?string $startTime,
        ?string $endDate,
        ?string $endTime,
        ?string $eventStartDate,
        ?string $eventStartTime,
        ?string $eventEndDate,
        ?string $eventEndTime
    ): ?array {
        if (! $startDate || ! $startTime || ! $endDate || ! $endTime || ! $eventStartDate || ! $eventEndDate) {
            return null;
        }

        $timezone = config('app.timezone');
        $startAt = Carbon::parse("{$startDate} {$startTime}", $timezone);
        $endAt = Carbon::parse("{$endDate} {$endTime}", $timezone);
        $eventStartsAt = Carbon::parse($eventStartDate.' '.($eventStartTime ?: '00:00'), $timezone);
        $eventEndsAt = Carbon::parse($eventEndDate.' '.($eventEndTime ?: '23:59:59'), $timezone);

        if ($startAt->lt($eventStartsAt)) {
            return ['field' => 'scheduled_start_time', 'message' => 'scheduled gun start cannot be before the event starts.'];
        }

        if ($startAt->gte($eventEndsAt)) {
            return ['field' => 'scheduled_start_time', 'message' => 'scheduled gun start must be before the event ends.'];
        }

        if ($endAt->lte($startAt)) {
            return ['field' => 'scheduled_end_time', 'message' => 'cutoff/end date and time must be after the scheduled gun start.'];
        }

        if ($endAt->gt($eventEndsAt)) {
            return ['field' => 'scheduled_end_time', 'message' => 'cutoff/end cannot be after the event ends.'];
        }

        return null;
    }

    private function categoryTypeName(array $data): string
    {
        if ($data['category_type'] === 'custom') {
            return trim($data['custom_category_name']);
        }

        return $this->categoryTypes()[$data['category_type']];
    }

    private function distanceValue(array $data): float
    {
        if ($data['distance_option'] === 'custom') {
            return (float) $data['custom_distance_km'];
        }

        return (float) $data['distance_option'];
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

    private function completePastUpcomingEvents(User $user): void
    {
        Event::query()
            ->when($user->managesAssignedEventsOnly(), function ($query) use ($user) {
                $query->where('manager_id', $user->id);
            })
            ->where('status', 'upcoming')
            ->where(function ($query) {
                $query->whereDate('event_end_date', '<', today())
                    ->orWhere(function ($legacyQuery) {
                        $legacyQuery->whereNull('event_end_date')
                            ->whereDate('event_date', '<', today());
                    });
            })
            ->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);
    }
}
