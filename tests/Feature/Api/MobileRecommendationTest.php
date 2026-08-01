<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\TrainingModule;
use App\Models\User;
use Illuminate\Support\Str;

function recommendationMobileUser(string $token = 'recommendation-token', array $interests = ['Marathon']): User
{
    return User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'interests' => $interests,
        'api_token' => hash('sha256', $token),
        'api_token_expires_at' => now()->addDay(),
    ]);
}

function recommendationEvent(string $title, string $interestType): Event
{
    $event = Event::create([
        'title' => $title,
        'slug' => Str::slug($title).'-'.Str::random(6),
        'description' => 'A complete public event description.',
        'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(),
        'start_time' => '06:00',
        'registration_deadline' => now()->addWeek()->toDateString(),
        'status' => 'upcoming',
        'banner_image' => 'events/banners/sample.jpg',
        'organized_by' => 'Conquer Events Team',
        'interest_type' => $interestType,
    ]);

    Category::create([
        'event_id' => $event->id,
        'name' => '5K',
        'distance_km' => 5,
        'slot_limit' => 25,
        'status' => 'open',
    ]);

    return $event;
}

function recommendationTrainingModule(string $title, ?string $interestType): TrainingModule
{
    return TrainingModule::create([
        'title' => $title,
        'description' => 'A focused training module for mobile recommendations.',
        'content' => 'Training content.',
        'type' => 'program',
        'interest_type' => $interestType,
        'duration' => 30,
        'difficulty_level' => 'beginner',
        'is_published' => true,
    ]);
}

test('recommended events prioritize the mobile users matching interests', function () {
    $token = 'event-recommendation-token';
    recommendationMobileUser($token, ['Marathon']);

    recommendationEvent('Cycling Sprint', 'Cycling');
    recommendationEvent('Marathon Classic', 'Marathon');

    $this
        ->withToken($token)
        ->getJson('/api/events?recommended=1')
        ->assertOk()
        ->assertJsonPath('meta.recommended', true)
        ->assertJsonPath('meta.matched_interests.0', 'Marathon')
        ->assertJsonPath('data.0.title', 'Marathon Classic')
        ->assertJsonPath('data.0.interest_type', 'Marathon');
});

test('recommended training modules prioritize matches while keeping general modules', function () {
    $token = 'training-recommendation-token';
    recommendationMobileUser($token, ['Marathon']);

    recommendationTrainingModule('General Base Training', null);
    recommendationTrainingModule('Marathon Build Plan', 'Marathon');
    recommendationTrainingModule('Cycling Cadence Plan', 'Cycling');

    $response = $this
        ->withToken($token)
        ->getJson('/api/training-modules?recommended=1')
        ->assertOk()
        ->assertJsonPath('meta.recommended', true)
        ->assertJsonPath('meta.matched_interests.0', 'Marathon')
        ->assertJsonPath('data.0.title', 'Marathon Build Plan')
        ->assertJsonPath('data.0.interest_type', 'Marathon');

    expect(collect($response->json('data'))->pluck('title')->all())
        ->toContain('General Base Training')
        ->not->toContain('Cycling Cadence Plan');
});
