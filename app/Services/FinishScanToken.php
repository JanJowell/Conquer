<?php

namespace App\Services;

use App\Models\Registration;

class FinishScanToken
{
    public function issue(Registration $registration): string
    {
        $payload = $this->encode(json_encode([
            'version' => 1,
            'registration_id' => $registration->id,
            'event_id' => $registration->event_id,
            'category_id' => $registration->category_id,
        ], JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->signature($payload);
    }

    public function resolve(string $token): ?Registration
    {
        $parts = explode('.', trim($token));

        if (count($parts) !== 2 || ! hash_equals($this->signature($parts[0]), $parts[1])) {
            return null;
        }

        try {
            $decoded = json_decode($this->decode($parts[0]), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (($decoded['version'] ?? null) !== 1
            || ! is_numeric($decoded['registration_id'] ?? null)
            || ! is_numeric($decoded['event_id'] ?? null)
            || ! is_numeric($decoded['category_id'] ?? null)) {
            return null;
        }

        return Registration::query()
            ->whereKey((int) $decoded['registration_id'])
            ->where('event_id', (int) $decoded['event_id'])
            ->where('category_id', (int) $decoded['category_id'])
            ->first();
    }

    private function signature(string $payload): string
    {
        return $this->encode(hash_hmac('sha256', $payload, (string) config('app.key'), true));
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new \RuntimeException('Invalid finish scan token encoding.');
        }

        return $decoded;
    }
}
