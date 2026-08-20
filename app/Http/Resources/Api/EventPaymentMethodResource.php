<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventPaymentMethodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'label' => \App\Models\EventPaymentMethod::providers()[$this->provider] ?? $this->provider,
            'account_name' => $this->account_name,
            'account_number' => $this->account_number,
            'instructions' => $this->instructions,
            'is_online' => $this->isOnlineCheckout(),
            'is_enabled' => (bool) $this->is_enabled,
        ];
    }
}
