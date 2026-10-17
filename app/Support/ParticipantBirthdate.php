<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class ParticipantBirthdate
{
    public const MAX_AGE_YEARS = 120;

    /**
     * @return array<int, string>
     */
    public static function rules(bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'date',
            'before:today',
            'after_or_equal:'.self::oldestAllowedDate(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'birthdate.required' => 'Birthdate is required for participant accounts.',
            'birthdate.date' => 'Birthdate must be a valid date.',
            'birthdate.before' => 'Birthdate must be before today.',
            'birthdate.after_or_equal' => 'Birthdate must be within the last '.self::MAX_AGE_YEARS.' years.',
        ];
    }

    public static function oldestAllowedDate(): string
    {
        return today()->subYears(self::MAX_AGE_YEARS)->toDateString();
    }

    public static function latestAllowedDate(): string
    {
        return today()->subDay()->toDateString();
    }

    public static function isPlausible(?CarbonInterface $birthdate): bool
    {
        if ($birthdate === null) {
            return false;
        }

        $date = $birthdate->copy()->startOfDay();

        return $date->lt(today())
            && $date->gte(today()->subYears(self::MAX_AGE_YEARS));
    }

    public static function registrationBlockReason(?CarbonInterface $birthdate): ?string
    {
        return self::isPlausible($birthdate)
            ? null
            : 'Add a valid birthdate to your profile before registering for an event.';
    }
}
