<?php

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Event;
use Illuminate\Support\Carbon;

function automaticAnnouncementReadyEventPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'City Night Run',
        'slug' => 'city-night-run-'.uniqid(),
        'description' => 'A complete event for runners.',
        'venue' => 'Bacoor City',
        'event_date' => '2026-07-18',
        'start_time' => '18:00',
        'end_time' => '20:00',
        'registration_deadline' => '2026-07-10',
        'status' => 'draft',
        'banner_image' => 'events/banners/sample.jpg',
        'organized_by' => 'Conquer Events Team',
        'interest_type' => config('conquer.event_interest_types.0'),
    ], $overrides);
}

test('event keeps one synced automatic announcement', function () {
    Carbon::setTestNow('2026-06-05 10:00:00');

    try {
        $event = Event::create(automaticAnnouncementReadyEventPayload());

        $announcement = Announcement::where('event_id', $event->id)
            ->where('is_auto_generated', true)
            ->first();

        expect($announcement)->not->toBeNull()
            ->and($announcement->is_published)->toBeFalse()
            ->and($announcement->title)->toBe('Registration now open: City Night Run')
            ->and($announcement->expires_at->format('Y-m-d H:i:s'))->toBe('2026-07-11 00:59:59');

        Category::create([
            'event_id' => $event->id,
            'name' => '5K Open',
            'distance_km' => 5,
            'slot_limit' => 100,
            'status' => 'open',
        ]);

        $event->refreshAutomaticStatus();
        $announcement->refresh();

        expect($announcement->is_published)->toBeTrue()
            ->and($announcement->published_at?->format('Y-m-d H:i:s'))->toBe('2026-06-05 10:00:00')
            ->and($announcement->content)->toContain('Registration deadline: July 10, 2026')
            ->and($announcement->content)->toContain('Event date: July 18, 2026')
            ->and($announcement->content)->toContain('Event time: 6:00 PM - 8:00 PM');

        $event->update(['title' => 'City Night Run 2026']);
        $event->refreshAutomaticStatus();

        expect(Announcement::where('event_id', $event->id)->where('is_auto_generated', true)->count())->toBe(1)
            ->and($announcement->fresh()->title)->toBe('Registration now open: City Night Run 2026');
    } finally {
        Carbon::setTestNow();
    }
});

test('event announcements expose a mobile event action', function () {
    Carbon::setTestNow('2026-06-05 10:00:00');

    try {
        $event = Event::create(automaticAnnouncementReadyEventPayload());

        Category::create([
            'event_id' => $event->id,
            'name' => '5K Open',
            'distance_km' => 5,
            'slot_limit' => 100,
            'status' => 'open',
        ]);

        $event->refreshAutomaticStatus();
        $announcement = Announcement::where('event_id', $event->id)
            ->where('is_auto_generated', true)
            ->firstOrFail();

        $this
            ->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonPath('data.0.id', $announcement->id)
            ->assertJsonPath('data.0.action.type', 'event_detail')
            ->assertJsonPath('data.0.action.label', 'View Event')
            ->assertJsonPath('data.0.action.event_id', $event->id)
            ->assertJsonMissingPath('data.0.action.web_url');
    } finally {
        Carbon::setTestNow();
    }
});
