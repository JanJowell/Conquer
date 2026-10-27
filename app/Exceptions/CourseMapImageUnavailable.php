<?php

namespace App\Exceptions;

use RuntimeException;

class CourseMapImageUnavailable extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $status = 503)
    {
        parent::__construct('The generated course map is unavailable.');
    }
}
