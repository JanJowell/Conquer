<?php

use App\Models\Category;
use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

test('an upcoming event in the past is saved as completed', function () {
    Carbon::setTestNow('2026-04-29 12:00:00');

    try {
        $event = Event::create([
            'title' => 'Past Fun Run',
            'slug' => 'past-fun-run',
            'venue' => 'Bacoor City',
            'event_date' => '2026-04-28',
            'status' => 'upcoming',
        ]);

        expect($event->fresh()->status)->toBe('completed');
    } finally {
        Carbon::setTestNow();
    }
});

test('an upcoming event today or later remains upcoming', function () {
    Carbon::setTestNow('2026-04-29 12:00:00');

    try {
        $event = Event::create([
            'title' => 'Future Fun Run',
            'slug' => 'future-fun-run',
            'venue' => 'Bacoor City',
            'event_date' => '2026-04-30',
            'status' => 'upcoming',
        ]);

        expect($event->fresh()->status)->toBe('upcoming');
    } finally {
        Carbon::setTestNow();
    }
});

test('past upcoming events already in storage display as completed', function () {
    Carbon::setTestNow('2026-04-29 12:00:00');

    try {
        DB::table('events')->insert([
            'title' => 'Stored Past Fun Run',
            'slug' => 'stored-past-fun-run',
            'description' => 'A complete public event description.',
            'venue' => 'Bacoor City',
            'event_date' => '2026-04-28',
            'start_time' => '06:00',
            'registration_deadline' => '2026-04-20',
            'status' => 'upcoming',
            'banner_image' => 'events/banners/sample.jpg',
            'organized_by' => 'Conquer Events Team',
            'interest_type' => config('conquer.event_interest_types.0'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event = Event::first();

        Category::create([
            'event_id' => $event->id,
            'name' => '5K',
            'distance_km' => 5,
            'slot_limit' => 100,
            'status' => 'open',
        ]);

        expect($event->fresh()->effective_status)->toBe('completed');
    } finally {
        Carbon::setTestNow();
    }
});

test('a same-day event remains ongoing between its start and end times', function () {
    $previousTimezone = config('app.timezone');
    config()->set('app.timezone', 'Asia/Manila');
    Carbon::setTestNow(Carbon::parse('2026-08-16 00:11:00', 'Asia/Manila'));

    try {
        expect(Event::statusForDate('upcoming', '2026-08-16', '00:00', '17:00'))->toBe('ongoing');
    } finally {
        Carbon::setTestNow();
        config()->set('app.timezone', $previousTimezone);
    }
});

test('same-day event status respects start and end boundaries', function (string $now, string $expectedStatus) {
    $previousTimezone = config('app.timezone');
    config()->set('app.timezone', 'Asia/Manila');
    Carbon::setTestNow(Carbon::parse($now, 'Asia/Manila'));

    try {
        expect(Event::statusForDate('upcoming', '2026-08-16', '05:00', '17:00'))->toBe($expectedStatus);
    } finally {
        Carbon::setTestNow();
        config()->set('app.timezone', $previousTimezone);
    }
})->with([
    'before start' => ['2026-08-16 04:59:59', 'upcoming'],
    'exactly at start' => ['2026-08-16 05:00:00', 'ongoing'],
    'during event' => ['2026-08-16 12:00:00', 'ongoing'],
    'exactly at end' => ['2026-08-16 17:00:00', 'completed'],
    'after end' => ['2026-08-16 17:00:01', 'completed'],
]);

test('calendar dates take priority over clock times', function (string $now, string $eventDate, string $expectedStatus) {
    $previousTimezone = config('app.timezone');
    config()->set('app.timezone', 'Asia/Manila');
    Carbon::setTestNow(Carbon::parse($now, 'Asia/Manila'));

    try {
        expect(Event::statusForDate('upcoming', $eventDate, '05:00', '17:00'))->toBe($expectedStatus);
    } finally {
        Carbon::setTestNow();
        config()->set('app.timezone', $previousTimezone);
    }
})->with([
    'past calendar day' => ['2026-08-16 00:01:00', '2026-08-15', 'completed'],
    'future calendar day' => ['2026-08-16 23:59:00', '2026-08-17', 'upcoming'],
]);

test('a same-day event without an end time stays ongoing after it starts', function () {
    $previousTimezone = config('app.timezone');
    config()->set('app.timezone', 'Asia/Manila');
    Carbon::setTestNow(Carbon::parse('2026-08-16 23:59:59', 'Asia/Manila'));

    try {
        expect(Event::statusForDate('upcoming', '2026-08-16', '05:00'))->toBe('ongoing');
    } finally {
        Carbon::setTestNow();
        config()->set('app.timezone', $previousTimezone);
    }
});
