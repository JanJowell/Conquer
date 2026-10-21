<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use JsonException;

class CategoryCourseMap implements ValidationRule
{
    public const MAX_POINTS = 1000;

    public const MAX_CHECKPOINTS = 50;

    public const MAX_LABEL_LENGTH = 120;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $map = self::decode($value);
        } catch (JsonException) {
            $fail('The course map must contain valid route data.');

            return;
        }

        if ($map === null) {
            return;
        }

        $validator = Validator::make(['map' => $map], [
            'map' => ['required', 'array:version,path,checkpoints'],
            'map.version' => ['required', 'integer', 'in:1'],
            'map.path' => ['required', 'array', 'list', 'min:2', 'max:'.self::MAX_POINTS],
            'map.path.*' => ['required', 'array:lat,lng'],
            'map.path.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'map.path.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'map.checkpoints' => ['present', 'array', 'list', 'max:'.self::MAX_CHECKPOINTS],
            'map.checkpoints.*' => ['required', 'array:lat,lng,label'],
            'map.checkpoints.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'map.checkpoints.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'map.checkpoints.*.label' => ['required', 'string', 'max:'.self::MAX_LABEL_LENGTH],
        ]);

        if ($validator->fails()) {
            $fail('The course map is invalid: '.$validator->errors()->first());

            return;
        }

        $normalized = self::normalize($map);
        $distinctPoints = collect($normalized['path'])->unique(fn (array $point) => $point['lat'].','.$point['lng']);

        if ($distinctPoints->count() < 2) {
            $fail('The course map needs at least two different route points.');
        }
    }

    public static function normalize(mixed $value): ?array
    {
        $map = self::decode($value);

        if ($map === null) {
            return null;
        }

        $point = fn (array $coordinate): array => [
            'lat' => round((float) $coordinate['lat'], 6),
            'lng' => round((float) $coordinate['lng'], 6),
        ];

        return [
            'version' => 1,
            'path' => array_map($point, $map['path']),
            'checkpoints' => array_map(fn (array $checkpoint): array => [
                ...$point($checkpoint),
                'label' => trim($checkpoint['label']),
            ], $map['checkpoints']),
        ];
    }

    private static function decode(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            if (strlen($value) > 150000) {
                throw new JsonException('Course map payload is too large.');
            }

            return json_decode($value, true, 12, JSON_THROW_ON_ERROR);
        }

        return $value;
    }
}
