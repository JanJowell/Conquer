<?php

namespace App\Support;

use App\Rules\CategoryCourseMap;

class ParticipantCourseMap
{
    public static function make(mixed $value): ?array
    {
        // Reuse the write contract, but keep malformed legacy data from breaking
        // the surrounding event/registration response.
        $invalid = false;
        (new CategoryCourseMap)->validate('course_map', $value, function () use (&$invalid): void {
            $invalid = true;
        });

        if ($invalid) {
            return null;
        }

        $map = CategoryCourseMap::normalize($value);

        if ($map === null) {
            return null;
        }

        $start = $map['path'][0];
        $finish = $map['path'][count($map['path']) - 1];

        return [
            'version' => $map['version'],
            'path' => $map['path'],
            'start' => $start,
            'finish' => $finish,
            'is_loop' => $start === $finish,
            'checkpoints' => array_map(fn (array $checkpoint, int $index): array => [
                ...$checkpoint,
                'number' => $index + 1,
            ], $map['checkpoints'], array_keys($map['checkpoints'])),
        ];
    }
}
