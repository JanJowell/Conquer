<?php

use App\Exceptions\CheckpointScanException;
use App\Services\CheckpointRecordingPilot;
use Illuminate\Config\Repository;

function checkpointPilotFailure(Repository $config, int $event = 17, int $category = 29): CheckpointScanException
{
    try {
        (new CheckpointRecordingPilot($config))->assertAllowed($event, $category);
    } catch (CheckpointScanException $exception) {
        return $exception;
    }

    throw new RuntimeException('A pilot denial was expected.');
}

test('pilot accepts only the exact configured positive integer pair', function (mixed $event, mixed $category) {
    $config = new Repository(['checkpoint_scanning' => ['enabled' => true,
        'pilot_event_id' => $event, 'pilot_category_id' => $category]]);
    (new CheckpointRecordingPilot($config))->assertAllowed(17, 29);
    expect(true)->toBeTrue();
})->with([[17, 29], ['17', '29'], [17, '29'], ['17', 29]]);

test('every incomplete or malformed pilot ID fails closed', function (string $field, mixed $invalid) {
    $config = new Repository(['checkpoint_scanning' => ['enabled' => true, 'pilot_event_id' => 17, 'pilot_category_id' => 29]]);
    $config->set('checkpoint_scanning.'.$field, $invalid);
    $failure = checkpointPilotFailure($config);
    expect($failure->status)->toBe(503)->and($failure->errorCode)->toBe('checkpoint_recording_unavailable');
})->with(['pilot_event_id', 'pilot_category_id'])->with([
    'null' => [null], 'empty' => [''], 'zero integer' => [0], 'negative integer' => [-1],
    'zero string' => ['0'], 'negative string' => ['-1'], 'boolean true' => [true], 'boolean false' => [false],
    'event float' => [17.0], 'category float' => [29.0], 'empty array' => [[]], 'array' => [[17]],
    'multiple IDs' => ['17,29'], 'wildcard' => ['*'], 'all events' => ['all'], 'decimal' => ['17.0'],
    'scientific notation' => ['1.7e1'], 'positive sign' => ['+17'], 'leading zero' => ['017'],
    'leading whitespace' => [' 17'], 'trailing whitespace' => ['29 '], 'newline' => ["17\n"],
    'null byte' => ["29\0"], 'partial numeric' => ['17suffix'], 'overflow' => [str_repeat('9', 30)],
]);

test('pilot denies mismatches rather than allowing either half of the pair', function (int $event, int $category) {
    $config = new Repository(['checkpoint_scanning' => ['enabled' => true, 'pilot_event_id' => 17, 'pilot_category_id' => 29]]);
    $failure = checkpointPilotFailure($config, $event, $category);
    expect($failure->status)->toBe(403)->and($failure->errorCode)->toBe('checkpoint_pilot_forbidden');
})->with([[18, 29], [17, 30], [18, 30], [0, 29], [17, 0], [-17, -29]]);

test('pilot requires the enabled flag to be explicitly true in configuration', function (mixed $enabled) {
    $config = new Repository(['checkpoint_scanning' => ['enabled' => $enabled, 'pilot_event_id' => 17, 'pilot_category_id' => 29]]);
    $failure = checkpointPilotFailure($config);
    expect($failure->status)->toBe(503)->and($failure->errorCode)->toBe('checkpoint_recording_disabled');
})->with(['missing' => [null], 'off' => [false], 'zero' => [0], 'one' => [1], 'empty' => [''],
    'false string' => ['false'], 'true string' => ['true'], 'zero string' => ['0'], 'one string' => ['1'],
    'empty array' => [[]], 'array' => [[true]]]);

test('no pilot keys or invented unrestricted settings can open recording', function () {
    $config = new Repository(['checkpoint_scanning' => ['enabled' => true, 'mode' => 'all', 'pilot_required' => false]]);
    expect(checkpointPilotFailure($config)->errorCode)->toBe('checkpoint_recording_unavailable');
});

test('one resolved pilot policy rechecks mutable configuration rather than caching an allowance', function () {
    $config = new Repository(['checkpoint_scanning' => ['enabled' => true, 'pilot_event_id' => 17, 'pilot_category_id' => 29]]);
    $policy = new CheckpointRecordingPilot($config);
    $policy->assertAllowed(17, 29);
    $config->set('checkpoint_scanning.pilot_category_id', 30);
    expect(fn () => $policy->assertAllowed(17, 29))->toThrow(CheckpointScanException::class);
    $policy->assertAllowed(17, 30);
    $config->set('checkpoint_scanning.enabled', false);
    expect(fn () => $policy->assertAllowed(17, 30))->toThrow(CheckpointScanException::class);
});

test('largest representable positive IDs are compared without float or overflow coercion', function () {
    $config = new Repository(['checkpoint_scanning' => ['enabled' => true,
        'pilot_event_id' => (string) PHP_INT_MAX, 'pilot_category_id' => PHP_INT_MAX]]);
    (new CheckpointRecordingPilot($config))->assertAllowed(PHP_INT_MAX, PHP_INT_MAX);
    $config->set('checkpoint_scanning.pilot_event_id', (string) PHP_INT_MAX.'0');
    expect(checkpointPilotFailure($config, PHP_INT_MAX, PHP_INT_MAX)->errorCode)->toBe('checkpoint_recording_unavailable');
});
