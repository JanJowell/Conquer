<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;

final class ParticipantHealthProfile
{
    public const BMI_NOTICE = 'BMI is a screening indicator, not a medical diagnosis or a measure of fitness to participate.';

    public static function validationRules(): array
    {
        return [
            'height_cm' => ['nullable', 'required_with:weight_kg', 'numeric', 'between:50,250'],
            'weight_kg' => ['nullable', 'required_with:height_cm', 'numeric', 'between:10,500'],
            'bmi_measured_at' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public static function normalize(
        array $validated,
        ?User $existingUser = null,
        ?string $capturedOn = null
    ): array
    {
        $measurementsWereSubmitted = array_key_exists('height_cm', $validated)
            || array_key_exists('weight_kg', $validated);

        if (! $measurementsWereSubmitted) {
            // The date is system managed. Ignore date-only submissions from
            // older clients instead of allowing them to rewrite it.
            unset($validated['bmi_measured_at']);

            return $validated;
        }

        if (blank($validated['height_cm'] ?? null) && blank($validated['weight_kg'] ?? null)) {
            $validated['height_cm'] = null;
            $validated['weight_kg'] = null;
            $validated['bmi_measured_at'] = null;

            return $validated;
        }

        $measurementsAreUnchanged = $existingUser
            && (float) $existingUser->height_cm === (float) $validated['height_cm']
            && (float) $existingUser->weight_kg === (float) $validated['weight_kg'];

        $validated['bmi_measured_at'] = $measurementsAreUnchanged && $existingUser->bmi_measured_at
            ? $existingUser->bmi_measured_at->toDateString()
            : ($capturedOn ?: now()->toDateString());

        return $validated;
    }

    public static function calculate(mixed $heightCm, mixed $weightKg): ?float
    {
        $heightCm = (float) $heightCm;
        $weightKg = (float) $weightKg;

        if ($heightCm <= 0 || $weightKg <= 0) {
            return null;
        }

        return round($weightKg / (($heightCm / 100) ** 2), 1);
    }

    public static function interpretation(?CarbonInterface $birthdate, ?CarbonInterface $measuredAt, ?float $bmi): string
    {
        if ($bmi === null) {
            return 'unavailable';
        }

        if ($birthdate === null) {
            return 'age_unknown';
        }

        $referenceDate = $measuredAt ?? now();

        return $birthdate->diffInYears($referenceDate) >= 20
            ? 'adult'
            : 'age_specific_required';
    }

    public static function category(?float $bmi, string $interpretation): ?string
    {
        if ($bmi === null || $interpretation !== 'adult') {
            return null;
        }

        return match (true) {
            $bmi < 18.5 => 'Underweight',
            $bmi < 25 => 'Healthy weight',
            $bmi < 30 => 'Overweight',
            default => 'Obesity',
        };
    }
}
