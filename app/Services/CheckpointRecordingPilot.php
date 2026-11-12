<?php

namespace App\Services;

use App\Exceptions\CheckpointScanException;
use Illuminate\Contracts\Config\Repository;

class CheckpointRecordingPilot
{
    public function __construct(private readonly Repository $config) {}

    public function assertAllowed(int $eventId, int $categoryId): void
    {
        if ($this->config->get('checkpoint_scanning.enabled', false) !== true) {
            throw new CheckpointScanException('Checkpoint recording is disabled.', 'checkpoint_recording_disabled', 503);
        }

        $pilotEvent = $this->positiveId($this->config->get('checkpoint_scanning.pilot_event_id'));
        $pilotCategory = $this->positiveId($this->config->get('checkpoint_scanning.pilot_category_id'));
        if ($pilotEvent === null || $pilotCategory === null) {
            throw new CheckpointScanException('Checkpoint recording requires a configured pilot event and category.',
                'checkpoint_recording_unavailable', 503);
        }

        if ($eventId !== $pilotEvent || $categoryId !== $pilotCategory) {
            throw new CheckpointScanException('Checkpoint recording is not available for this event and category.',
                'checkpoint_pilot_forbidden', 403);
        }
    }

    private function positiveId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value) || preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
            return null;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($parsed) ? $parsed : null;
    }
}
