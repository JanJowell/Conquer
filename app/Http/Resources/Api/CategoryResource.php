<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $registeredCount = $this->whenCounted('registrations');
        $allEventPaymentMethods = $this->event
            ? ($this->event->relationLoaded('paymentMethods')
                ? $this->event->paymentMethods
                : $this->event->paymentMethods()->get())
            : collect();
        $eventPaymentMethods = $allEventPaymentMethods->where('is_enabled', true)->values();
        $fallbackPaymentMethod = $eventPaymentMethods->first(fn ($method) => ! $method->isOnlineCheckout());
        $legacyPaymentInstructions = $allEventPaymentMethods->isEmpty() && filled($this->payment_provider)
            ? [
                'provider' => $this->payment_provider,
                'account_name' => $this->payment_account_name,
                'account_number' => $this->payment_account_number,
                'instructions' => $this->payment_instructions,
            ]
            : ($fallbackPaymentMethod ? [
                'provider' => $fallbackPaymentMethod->provider,
                'account_name' => $fallbackPaymentMethod->account_name,
                'account_number' => $fallbackPaymentMethod->account_number,
                'instructions' => $fallbackPaymentMethod->instructions,
            ] : null);

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'distance_km' => $this->distance_km !== null ? (float) $this->distance_km : null,
            'type_details' => $this->resolvedTypeDetails(),
            'type_detail_items' => $this->formattedTypeDetails(),
            'requires_medical_certificate' => $this->requiresMedicalCertificate(),
            'description' => $this->description,
            'qualification_notes' => $this->qualification_notes,
            'slot_limit' => $this->slot_limit,
            'price_cents' => $this->price_cents,
            'price_amount' => number_format(($this->price_cents ?? 0) / 100, 2, '.', ''),
            'price_currency' => $this->price_currency ?? 'PHP',
            'is_free' => (int) ($this->price_cents ?? 0) === 0,
            'payment_instructions' => (int) ($this->price_cents ?? 0) > 0 ? $legacyPaymentInstructions : null,
            'payment_options' => (int) ($this->price_cents ?? 0) > 0
                ? EventPaymentMethodResource::collection($eventPaymentMethods)
                : [],
            'registered_count' => $registeredCount,
            'slots_remaining' => is_int($registeredCount) && $this->slot_limit !== null
                ? max($this->slot_limit - $registeredCount, 0)
                : null,
            'status' => $this->status,
            'scheduled_start_date' => optional($this->scheduled_start_date ?? $this->event?->event_date)->format('Y-m-d'),
            'scheduled_start_time' => optional($this->scheduled_start_time)->format('H:i'),
            'scheduled_end_date' => optional($this->scheduled_end_date ?? $this->scheduled_start_date ?? $this->event?->event_date)->format('Y-m-d'),
            'scheduled_end_time' => optional($this->scheduled_end_time)->format('H:i'),
            'scheduled_start_at' => optional($this->scheduledStartAt())->toIso8601String(),
            'scheduled_end_at' => optional($this->scheduledEndAt())->toIso8601String(),
            'started_at' => optional($this->started_at)->toIso8601String(),
            'has_started' => $this->started_at !== null,
        ];
    }
}
