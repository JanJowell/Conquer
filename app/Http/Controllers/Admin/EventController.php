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
        $request->merge([
            'categories' => $this->filledCategoryRows($request->input('categories', [])),
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'venue' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
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
            'categories.*.distance_option' => ['required', Rule::in(array_keys($this->distanceOptions()))],
            'categories.*.custom_distance_km' => ['nullable', 'required_if:categories.*.distance_option,custom', 'numeric', 'min:0.01'],
            'categories.*.scheduled_start_time' => ['required', 'date_format:H:i'],
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
            ...$this->typeDetailValidationRules($request),
        ]);

        if ($errors = $this->categorySetupErrors($validated['categories'] ?? [], $validated['start_time'] ?? null, $validated['end_time'] ?? null)) {
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
        $request->merge([
            'categories' => $this->filledCategoryRows($request->input('categories', [])),
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'venue' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
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
            'categories.*.distance_option' => ['required', Rule::in(array_keys($this->distanceOptions()))],
            'categories.*.custom_distance_km' => ['nullable', 'required_if:categories.*.distance_option,custom', 'numeric', 'min:0.01'],
            'categories.*.scheduled_start_time' => ['required', 'date_format:H:i'],
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
            ...$this->typeDetailValidationRules($request),
        ]);

        if ($errors = $this->categorySetupErrors($validated['categories'] ?? [], $validated['start_time'] ?? null, $validated['end_time'] ?? null)) {
            return back()->withErrors($errors)->withInput();
        }

        if ($errors = $this->existingCategoryScheduleErrors($event, $validated['start_time'] ?? null, $validated['end_time'] ?? null)) {
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
        foreach (['category_type', 'custom_category_name', 'distance_option', 'custom_distance_km', 'description', 'slot_limit', 'payment_provider', 'payment_account_name', 'payment_account_number', 'payment_instructions'] as $field) {
            if (filled($row[$field] ?? null)) {
                return true;
            }
        }

        return (float) ($row['price_amount'] ?? 0) > 0;
    }

    private function createCategoriesForEvent(Event $event, array $rows): void
    {
        foreach ($rows as $row) {
            $distanceKm = $this->distanceValue($row);

            $event->categories()->create([
                'name' => $this->nameWithDistance($this->categoryTypeName($row), $distanceKm),
                'distance_km' => $distanceKm,
                'description' => $row['description'] ?? null,
                'slot_limit' => $row['slot_limit'] ?? null,
                'price_cents' => (int) round((float) ($row['price_amount'] ?? 0) * 100),
                'price_currency' => strtoupper($row['price_currency'] ?? 'PHP'),
                'payment_provider' => $row['payment_provider'] ?? null,
                'payment_account_name' => $row['payment_account_name'] ?? null,
                'payment_account_number' => $row['payment_account_number'] ?? null,
                'payment_instructions' => $row['payment_instructions'] ?? null,
                'status' => $row['status'] ?? 'open',
                'scheduled_start_time' => $row['scheduled_start_time'],
                'scheduled_end_time' => $row['scheduled_end_time'],
            ]);
        }
    }

    private function categorySetupErrors(array $rows, ?string $eventStartTime = null, ?string $eventEndTime = null): array
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            $number = $index + 1;

            if (($row['category_type'] ?? null) === 'custom' && blank($row['custom_category_name'] ?? null)) {
                $errors["categories.{$index}.custom_category_name"] = "Category {$number}: enter a custom category type.";
            }

            if (($row['distance_option'] ?? null) === 'custom' && blank($row['custom_distance_km'] ?? null)) {
                $errors["categories.{$index}.custom_distance_km"] = "Category {$number}: enter a custom distance.";
            }

            $scheduledStartTime = $row['scheduled_start_time'] ?? null;
            $scheduledEndTime = $row['scheduled_end_time'] ?? null;

            if ($scheduledStartTime && $eventStartTime && $scheduledStartTime < $eventStartTime) {
                $errors["categories.{$index}.scheduled_start_time"] = "Category {$number}: scheduled gun start cannot be before the event start time.";
            }

            if ($scheduledStartTime && $eventEndTime && $scheduledStartTime >= $eventEndTime) {
                $errors["categories.{$index}.scheduled_start_time"] = "Category {$number}: scheduled gun start must be before the event end time.";
            }

            if ($scheduledStartTime && $scheduledEndTime && $scheduledEndTime <= $scheduledStartTime) {
                $errors["categories.{$index}.scheduled_end_time"] = "Category {$number}: cutoff/end time must be after the scheduled gun start.";
            }

            if ($scheduledEndTime && $eventEndTime && $scheduledEndTime > $eventEndTime) {
                $errors["categories.{$index}.scheduled_end_time"] = "Category {$number}: cutoff/end time cannot be after the event end time.";
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

    private function existingCategoryScheduleErrors(Event $event, ?string $eventStartTime, ?string $eventEndTime): array
    {
        $event->loadMissing('categories');

        foreach ($event->categories as $category) {
            $scheduledStartTime = $category->scheduled_start_time?->format('H:i');
            $scheduledEndTime = $category->scheduled_end_time?->format('H:i');

            if (! $scheduledStartTime && ! $scheduledEndTime) {
                continue;
            }

            if ($eventStartTime && $scheduledStartTime && $scheduledStartTime < $eventStartTime) {
                return ['start_time' => "The event cannot start after the scheduled gun start of {$category->name} ({$category->scheduled_start_time->format('g:i A')})."];
            }

            if ($eventEndTime && $scheduledEndTime && $scheduledEndTime > $eventEndTime) {
                return ['end_time' => "The event cannot end before the cutoff/end time of {$category->name} ({$category->scheduled_end_time->format('g:i A')})."];
            }
        }

        return [];
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
            ->whereDate('event_date', '<', today())
            ->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);
    }
}
