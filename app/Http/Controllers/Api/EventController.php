<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\EventResource;
use App\Http\Resources\Api\RegistrationResource;
use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $events = Event::with(['categories' => fn ($query) => $query->withCount('registrations')])
            ->withCount('registrations')
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
            ->when($request->filled('interest'), function ($query) use ($request) {
                $interest = $request->string('interest');

                $query->where(function ($inner) use ($interest) {
                    $inner->where('interest_type', 'like', "%{$interest}%")
                        ->orWhereHas('categories', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$interest}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->get();

        return response()->json([
            'data' => EventResource::collection($events),
        ]);
    }

    public function show(Event $event): JsonResponse
    {
        $event->load([
            'categories' => fn ($query) => $query->withCount('registrations'),
            'announcements' => fn ($query) => $query->where('is_published', true)->latest(),
        ])->loadCount('registrations');

        return response()->json([
            'data' => new EventResource($event),
        ]);
    }

    public function register(Request $request, Event $event, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'shirt_size' => ['nullable', 'string', Rule::in(['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', 'Small', 'Medium', 'Large', 'Extra Large'])],
            'medical_conditions' => ['nullable', 'string', 'max:2000'],
        ]);

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

        if ($category->slot_limit !== null && $category->registrations()->count() >= $category->slot_limit) {
            return response()->json([
                'message' => 'This category is already full.',
            ], 422);
        }

        if ($request->user()->registrations()->where('event_id', $event->id)->where('category_id', $category->id)->exists()) {
            return response()->json([
                'message' => 'You are already registered for this category.',
            ], 422);
        }

        $registration = Registration::create([
            'user_id' => $request->user()->id,
            'event_id' => $event->id,
            'category_id' => $category->id,
            'bib_number' => 'BIB-'.strtoupper(uniqid()),
            'shirt_size' => $validated['shirt_size'] ?? 'M',
            'medical_conditions' => $validated['medical_conditions'] ?? null,
            'status' => 'registered',
            'registered_at' => now(),
        ]);

        return response()->json([
            'message' => 'Successfully registered.',
            'data' => new RegistrationResource($registration->load(['event', 'category'])),
        ], 201);
    }
}
