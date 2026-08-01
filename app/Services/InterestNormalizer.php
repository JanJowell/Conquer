<?php

namespace App\Services;

use Illuminate\Support\Str;

class InterestNormalizer
{
    public function normalize(mixed $raw): array
    {
        $values = is_array($raw) ? $raw : [$raw];
        $canonical = $this->canonicalMap();
        $seen = [];
        $normalized = [];

        foreach ($values as $value) {
            $interest = trim((string) $value);

            if ($interest === '') {
                continue;
            }

            $key = $this->key($interest);
            $interest = $canonical[$key] ?? $interest;
            $dedupeKey = $this->key($interest);

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $normalized[] = $interest;
        }

        return $normalized;
    }

    private function canonicalMap(): array
    {
        return collect([
            ...config('conquer.event_interest_types', []),
            ...config('conquer.interests', []),
        ])
            ->filter()
            ->unique(fn ($interest) => $this->key((string) $interest))
            ->mapWithKeys(fn ($interest) => [$this->key((string) $interest) => (string) $interest])
            ->all();
    }

    private function key(string $interest): string
    {
        return Str::of($interest)
            ->squish()
            ->lower()
            ->value();
    }
}
