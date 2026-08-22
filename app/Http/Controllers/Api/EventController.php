<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\EventResource;
use App\Http\Resources\Api\RegistrationResource;
use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\CategoryRegistrationEligibility;
use App\Services\MobileRecommendationContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $recommendations = app(MobileRecommendationContext::class);
        $user = $recommendations->userFromBearerToken($request);
        $publicStatuses = ['upcoming', 'ongoing', 'completed'];
        $recommendedInterests = $request->boolean('recommended')
            ? $recommendations->userInterests($user)
            : [];

        $events = Event::with([
            'paymentMethods',
            'categories' => fn ($query) => $query
                ->where('status', 'open')
                ->withCount(['registrations' => fn ($registrationQuery) => $registrationQuery->where('status', '!=', 'rejected')]),
            'categories.event.paymentMethods',
        ])
            ->withCount(['registrations' => fn ($query) => $query->where('status', '!=', 'rejected')])
            ->withAggregate(
                ['registrations as participants_count' => fn ($query) => $query->where('status', '!=', 'rejected')],
                DB::raw('distinct user_id'),
                'count'
            )
            ->when($user, function ($query) use ($user) {
                $query->with([
                    'currentUserRegistrations' => fn ($registrationQuery) => $registrationQuery
                        ->where('user_id', $user->id)
                        ->with(['category.event.paymentMethods', 'latestPayment', 'raceResult', 'issuedEBadges.badge'])
                        ->latest('registered_at'),
                ]);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('venue', 'like', "%{$search}%")
                        ->orWhere('organized_by', 'like', "%{$search}%")
                        ->orWhere('interest_type', 'like', "%{$search}%")
                        ->orWhereHas('categories', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('interest'), function ($query) use ($recommendations, $request) {
                $interests = $recommendations->normalizeInterests($request->input('interest'));

                $query->where(function ($inner) use ($interests, $request) {
                    if ($interests !== []) {
                        $inner->whereIn('interest_type', $interests);
                    } else {
                        $interest = $request->string('interest');

                        $inner->where('interest_type', 'like', "%{$interest}%")
                            ->orWhereHas('categories', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$interest}%"));
                    }
                });
            })
            ->latest()
            ->get();

        $events = $events
            ->filter(fn (Event $event) => in_array($event->effective_status, $publicStatuses, true))
            ->when(
                $request->filled('status') && in_array($request->string('status')->value(), $publicStatuses, true),
                fn ($collection) => $collection->filter(fn (Event $event) => $event->effective_status === $request->string('status')->value())
            )
            ->when($recommendedInterests !== [] && ! $request->filled('interest'), function ($collection) use ($recommendedInterests) {
                return $collection
                    ->sortBy(fn (Event $event) => in_array($event->interest_type, $recommendedInterests, true) ? 0 : 1)
                    ->values();
            })
            ->values();

        return response()->json([
            'data' => EventResource::collection($events),
            'meta' => [
                'recommended' => $request->boolean('recommended') && $recommendedInterests !== [],
                'matched_interests' => $recommendedInterests,
            ],
        ]);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $user = app(MobileRecommendationContext::class)->userFromBearerToken($request);

        $event->load([
            'paymentMethods',
            'categories' => fn ($query) => $query
                ->where('status', 'open')
                ->withCount(['registrations' => fn ($registrationQuery) => $registrationQuery->where('status', '!=', 'rejected')]),
            'categories.event.paymentMethods',
            'announcements' => fn ($query) => $query->active()->latest(),
        ])->loadCount(['registrations' => fn ($query) => $query->where('status', '!=', 'rejected')]);

        $event->loadAggregate(
            ['registrations as participants_count' => fn ($query) => $query->where('status', '!=', 'rejected')],
            DB::raw('distinct user_id'),
            'count'
        );

        if (! in_array($event->effective_status, ['upcoming', 'ongoing', 'completed'], true)) {
            return response()->json([
                'message' => 'Event is not available in the mobile app yet.',
            ], 404);
        }

        if ($user) {
            $event->load([
                'currentUserRegistrations' => fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->with(['category.event.paymentMethods', 'latestPayment', 'raceResult', 'issuedEBadges.badge'])
                    ->latest('registered_at'),
            ]);
        }

        return response()->json([
            'data' => new EventResource($event),
        ]);
    }

    public function register(Request $request, Event $event, Category $category): JsonResponse
    {
        if ($category->event_id !== $event->id) {
            return response()->json([
                'message' => 'Invalid category for this event.',
            ], 422);
        }

        if ($event->effective_status !== 'upcoming') {
            return response()->json([
                'message' => 'Registration is only available for upcoming events.',
            ], 422);
        }

        if ($event->registration_deadline && $event->registration_deadline->isBefore(today())) {
            return response()->json([
                'message' => 'Registration deadline has passed.',
            ], 422);
        }

        if ($category->status !== 'open') {
            return response()->json([
                'message' => 'This category is not open for registration.',
            ], 422);
        }

        if ($category->slot_limit !== null
            && $category->registrations()->where('status', '!=', 'rejected')->count() >= $category->slot_limit) {
            return response()->json([
                'message' => 'This category is already full.',
            ], 422);
        }

        $eventRegistrations = $request->user()
            ->registrations()
            ->where('event_id', $event->id)
            ->with('category.event')
            ->get();

        $existingRegistration = $eventRegistrations
            ->firstWhere('category_id', $category->id);

        if ($existingRegistration && $existingRegistration->status !== 'rejected') {
            return response()->json([
                'message' => 'You are already registered for this category.',
            ], 422);
        }

        $eligibility = app(CategoryRegistrationEligibility::class)->evaluate($category, $eventRegistrations);

        if (! $eligibility['allowed']) {
            $conflictingRegistration = $eligibility['conflicting_registration'];

            return response()->json([
                'message' => $eligibility['reason'],
                'conflict_category_id' => $conflictingRegistration?->category_id,
                'conflict_category_name' => $conflictingRegistration?->category?->name,
                'safety_buffer_minutes' => CategoryRegistrationEligibility::SAFETY_BUFFER_MINUTES,
            ], 422);
        }

        $validated = $request->validate([
            'shirt_size' => ['nullable', 'string', Rule::in(['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', 'Small', 'Medium', 'Large', 'Extra Large'])],
            'medical_conditions' => ['nullable', 'string', 'max:2000'],
            'medical_certificate' => [
                $category->requiresMedicalCertificate() ? 'required' : 'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:5120',
            ],
            'first_aid_kit_confirmed' => ['accepted'],
            'waiver_accepted' => ['accepted'],
            'waiver_name' => ['nullable', 'string', 'max:255'],
        ]);

        $registration = $existingRegistration ?? new Registration([
            'user_id' => $request->user()->id,
            'event_id' => $event->id,
        ]);

        $medicalCertificatePath = $registration->medical_certificate_path;
        $medicalCertificateSubmittedAt = $registration->medical_certificate_submitted_at;
        $newMedicalCertificatePath = null;

        if ($request->hasFile('medical_certificate')) {
            $newMedicalCertificatePath = $request->file('medical_certificate')
                ->store('medical-certificates', 'public');
            $medicalCertificatePath = $newMedicalCertificatePath;
            $medicalCertificateSubmittedAt = now();
        }

        $waiverAcceptedAt = $registration->waiver_accepted ? $registration->waiver_accepted_at : now();

        try {
            $registration->fill([
                'category_id' => $category->id,
                'bib_number' => null,
                'shirt_size' => $validated['shirt_size'] ?? 'M',
                'medical_conditions' => $validated['medical_conditions'] ?? null,
                'medical_certificate_path' => $medicalCertificatePath,
                'medical_certificate_submitted_at' => $medicalCertificateSubmittedAt,
                'first_aid_kit_confirmed' => true,
                'waiver_accepted' => true,
                'waiver_accepted_at' => $waiverAcceptedAt,
                'waiver_name' => $validated['waiver_name'] ?? $request->user()->name,
                'waiver_ip' => $request->ip(),
                'waiver_user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
                'kit_waiver_signed_at' => null,
                'kit_released_at' => null,
                'status' => 'pending',
                'rejection_reason' => null,
                'payment_required' => (int) ($category->price_cents ?? 0) > 0,
                'payment_status' => (int) ($category->price_cents ?? 0) > 0 ? 'unpaid' : 'waived',
                'payment_amount_cents' => (int) ($category->price_cents ?? 0),
                'payment_currency' => $category->price_currency ?? 'PHP',
                'paid_at' => null,
                'registered_at' => now(),
            ])->save();
        } catch (QueryException $exception) {
            $duplicateExists = $request->user()->registrations()
                ->where('event_id', $event->id)
                ->where('category_id', $category->id)
                ->exists();

            if ($existingRegistration || ! $duplicateExists) {
                throw $exception;
            }

            if ($newMedicalCertificatePath) {
                Storage::disk('public')->delete($newMedicalCertificatePath);
            }

            return response()->json([
                'message' => 'You are already registered for this category.',
            ], 422);
        }

        return response()->json([
            'message' => $existingRegistration ? 'Registration submitted again for review.' : 'Successfully registered.',
            'data' => new RegistrationResource($registration->load([
                'event',
                'category.event',
                'latestPayment',
                'raceResult',
                'issuedEBadges.badge',
            ])),
        ], $existingRegistration ? 200 : 201);
    }

}
