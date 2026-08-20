<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $registeredCount = $this->whenCounted('registrations');

        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'distance_km' => $this->distance_km !== null ? (float) $this->distance_km : null,
            'requires_medical_certificate' => $this->requiresMedicalCertificate(),
            'description' => $this->description,
            'slot_limit' => $this->slot_limit,
            'price_cents' => $this->price_cents,
            'price_amount' => number_format(($this->price_cents ?? 0) / 100, 2, '.', ''),
            'price_currency' => $this->price_currency ?? 'PHP',
            'is_free' => (int) ($this->price_cents ?? 0) === 0,
            'payment_instructions' => (int) ($this->price_cents ?? 0) > 0 ? [
                'provider' => $this->payment_provider,
                'account_name' => $this->payment_account_name,
                'account_number' => $this->payment_account_number,
                'instructions' => $this->payment_instructions,
            ] : null,
            'registered_count' => $registeredCount,
            'slots_remaining' => is_int($registeredCount) && $this->slot_limit !== null
                ? max($this->slot_limit - $registeredCount, 0)
                : null,
            'status' => $this->status,
            'scheduled_start_time' => optional($this->scheduled_start_time)->format('H:i'),
            'started_at' => optional($this->started_at)->toIso8601String(),
            'has_started' => $this->started_at !== null,
        ];
    }
}
