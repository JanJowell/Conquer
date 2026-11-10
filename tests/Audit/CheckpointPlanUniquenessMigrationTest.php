<?php

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/../Support/CheckpointPlanUniquenessContract.php';

uses(TestCase::class);

test('checkpoint plan corrective migration contract', function (string $case) {
    $previous = config('database.default');
    config(['database.connections.checkpoint_uniqueness_audit' => ['driver' => 'sqlite', 'database' => ':memory:',
        'prefix' => '', 'foreign_key_constraints' => true], 'database.default' => 'checkpoint_uniqueness_audit']);
    try {
        runCheckpointPlanUniquenessCase($case, fn (bool $condition, string $message) => $this->assertTrue($condition, $message));
    } finally {
        config(['database.default' => $previous, 'checkpoint_scanning.enabled' => false]);
        DB::purge('checkpoint_uniqueness_audit');
    }
})->with(checkpointPlanUniquenessCases());
