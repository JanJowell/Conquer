<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bib_number' => $this->bib_number,
            'shirt_size' => $this->shirt_size,
            'medical_conditions' => $this->medical_conditions,
            'requires_medical_certificate' => $this->category?->requiresMedicalCertificate() ?? false,
            'medical_certificate_url' => $this->medical_certificate_path ? asset('storage/'.$this->medical_certificate_path) : null,
            'medical_certificate_submitted_at' => optional($this->medical_certificate_submitted_at)?->toISOString(),
            'first_aid_kit_confirmed' => (bool) $this->first_aid_kit_confirmed,
            'waiver_accepted' => (bool) $this->waiver_accepted,
            'waiver_accepted_at' => optional($this->waiver_accepted_at)?->toISOString(),
            'waiver_name' => $this->waiver_name,
            'kit_waiver_signed_at' => optional($this->kit_waiver_signed_at)?->toISOString(),
            'kit_released_at' => optional($this->kit_released_at)?->toISOString(),
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'payment_required' => (bool) $this->payment_required,
            'payment_status' => $this->payment_status,
            'payment_amount_cents' => $this->payment_amount_cents,
            'payment_amount' => number_format(($this->payment_amount_cents ?? 0) / 100, 2, '.', ''),
            'payment_currency' => $this->payment_currency ?? 'PHP',
            'paid_at' => optional($this->paid_at)?->toISOString(),
            'latest_payment' => $this->whenLoaded('latestPayment', fn () => $this->latestPayment ? [
                'id' => $this->latestPayment->id,
                'provider' => $this->latestPayment->provider,
                'provider_reference' => $this->latestPayment->provider_reference,
                'status' => $this->latestPayment->status,
                'amount_cents' => $this->latestPayment->amount_cents,
                'amount' => number_format(($this->latestPayment->amount_cents ?? 0) / 100, 2, '.', ''),
                'currency' => $this->latestPayment->currency,
                'checkout_url' => $this->latestPayment->checkout_url,
                'proof_url' => $this->latestPayment->proof_path ? asset('storage/'.$this->latestPayment->proof_path) : null,
                'submitted_at' => optional($this->latestPayment->submitted_at)?->toISOString(),
                'paid_at' => optional($this->latestPayment->paid_at)?->toISOString(),
            ] : null),
            'registered_at' => optional($this->registered_at)?->toISOString(),
            'event' => new EventResource($this->whenLoaded('event')),
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
