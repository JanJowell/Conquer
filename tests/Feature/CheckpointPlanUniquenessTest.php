<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('fresh installations enforce one checkpoint plan per category in the database', function () {
    $manager = User::factory()->create(['role' => User::ROLE_EVENT_MANAGER]);
    $event = Event::create(['title' => 'Synthetic uniqueness fixture', 'slug' => 'synthetic-plan-uniqueness',
        'venue' => 'Synthetic venue', 'event_date' => '2026-12-01', 'start_time' => '06:00', 'manager_id' => $manager->id]);
    $first = $event->categories()->create(['name' => 'Synthetic first', 'distance_km' => 1, 'price_cents' => 0]);
    $second = $event->categories()->create(['name' => 'Synthetic second', 'distance_km' => 2, 'price_cents' => 0]);
    $row = ['event_id' => $event->id, 'category_id' => $first->id, 'fingerprint' => str_repeat('a', 64),
        'category_started_at' => '2026-12-01 06:00:00', 'snapshot' => '{}'];
    DB::table('checkpoint_scan_plans')->insert($row);
    $caught = null;
    try {
        DB::table('checkpoint_scan_plans')->insert($row);
    } catch (QueryException $exception) {
        $caught = $exception;
    }
    expect($caught)->not->toBeNull()
        ->and((string) $caught->getCode())->toBe('23000')
        ->and(DB::table('checkpoint_scan_plans')->where('category_id', $first->id)->count())->toBe(1);
    DB::table('checkpoint_scan_plans')->insert([...$row, 'category_id' => $second->id]);
    expect(DB::table('checkpoint_scan_plans')->count())->toBe(2)
        ->and(Schema::hasIndex('checkpoint_scan_plans', ['category_id'], 'unique'))->toBeTrue()
        ->and(config('checkpoint_scanning.enabled'))->toBeFalse();
});
