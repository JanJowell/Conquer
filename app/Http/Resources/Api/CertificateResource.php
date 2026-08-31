<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registration_id' => $this->registration_id,
            'certificate_number' => $this->certificate_number,
            'status' => $this->revoked_at ? 'revoked' : 'valid',
            'issued_at' => optional($this->issued_at)?->toISOString(),
            'revoked_at' => optional($this->revoked_at)?->toISOString(),
            'verification_url' => route('certificates.verify', $this->verification_token),
            'download_url' => $this->revoked_at
                ? null
                : route('certificates.download', $this->verification_token),
            'event' => $this->whenLoaded('event', fn () => [
                'id' => $this->event?->id,
                'title' => $this->event?->title,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),
        ];
    }
}
