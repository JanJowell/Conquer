<?php

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('approval migration preserves old events and defaults later inserts to private drafts', function () {
    $previous = config('database.default');
    config(['database.connections.review_upgrade' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true], 'database.default' => 'review_upgrade']);
    try {
        Schema::create('users', fn ($table) => $table->id());
        Schema::create('events', function ($table) {
            $table->id();
            $table->string('title');
            $table->string('status');
        });
        DB::table('events')->insert(['id' => 1, 'title' => 'Existing event', 'status' => 'upcoming']);
        $migration = require database_path('migrations/2026_10_30_000002_add_event_approval_workflow.php');
        $migration->up();
        $old = DB::table('events')->find(1);
        expect($old->title)->toBe('Existing event')->and($old->status)->toBe('upcoming')
            ->and($old->review_status)->toBeNull()->and($old->approved_by)->toBeNull()
            ->and($old->approved_at)->toBeNull()->and($old->review_version)->toBe(0)
            ->and(DB::table('event_reviews')->count())->toBe(0)->and(DB::table('event_revisions')->count())->toBe(0);
        DB::table('events')->insert(['id' => 2, 'title' => 'Later SQL insert', 'status' => 'upcoming']);
        expect(DB::table('events')->find(2)->review_status)->toBe('draft')
            ->and((new Event)->review_status)->toBe('draft');
        expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    } finally {
        config(['database.default' => $previous]);
        DB::purge('review_upgrade');
    }
});

test('submission snapshot migration preserves older review records without reconstructing their contents', function () {
    $previous = config('database.default');
    config(['database.connections.snapshot_upgrade' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'database.default' => 'snapshot_upgrade']);
    try {
        Schema::create('event_reviews', function ($table) {
            $table->id();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->string('setup_hash');
        });
        DB::table('event_reviews')->insert(['id' => 1, 'action' => 'return', 'reason' => 'Saved reason', 'setup_hash' => 'saved-hash']);
        $migration = require database_path('migrations/2026_10_30_000003_add_submission_snapshots_to_event_reviews.php');
        $migration->up();
        $old = DB::table('event_reviews')->find(1);
        expect($old->action)->toBe('return')->and($old->reason)->toBe('Saved reason')
            ->and($old->setup_hash)->toBe('saved-hash')->and($old->submission_snapshot)->toBeNull()
            ->and(DB::table('event_reviews')->count())->toBe(1);
        expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    } finally {
        config(['database.default' => $previous]);
        DB::purge('snapshot_upgrade');
    }
});
