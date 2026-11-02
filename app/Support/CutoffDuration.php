<?php

namespace App\Support;

class CutoffDuration
{
    public const CUSTOM = '__custom_duration__';

    public static function parse(mixed $value): ?array
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^(\d+)\s+hours?\s+(\d+)\s+minutes?$/iD', $value, $matches)) {
            $hours = (float) $matches[1];
            $minutes = (float) $matches[2];

            return $hours <= 1000000 && $minutes <= 59 && $hours + $minutes > 0
                ? ['hours' => (int) $hours, 'minutes' => (int) $minutes]
                : null;
        }

        if (! preg_match('/^(\d+(?:\.\d{1,2})?)\s+(minutes?|hours?)$/iD', $value, $matches)
            || (float) $matches[1] <= 0 || (float) $matches[1] > 1000000) {
            return null;
        }

        $totalMinutes = (float) $matches[1] * (str_starts_with(strtolower($matches[2]), 'hour') ? 60 : 1);
        // Do not silently round historical sub-minute durations into new values.
        if (abs($totalMinutes - round($totalMinutes)) > 0.000001) {
            return null;
        }

        $totalMinutes = (int) round($totalMinutes);

        return ['hours' => intdiv($totalMinutes, 60), 'minutes' => $totalMinutes % 60];
    }

    public static function format(int $hours, int $minutes): string
    {
        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours.' '.($hours === 1 ? 'hour' : 'hours');
        }
        if ($minutes > 0) {
            $parts[] = $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
        }

        return implode(' ', $parts);
    }
}
