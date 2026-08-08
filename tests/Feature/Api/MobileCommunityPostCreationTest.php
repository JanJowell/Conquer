<?php

use App\Models\CommunityPost;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function creationMobileUser(): array
{
    $plainToken = 'creation-'.uniqid();
    $user = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', $plainToken),
        'api_token_expires_at' => now()->addDay(),
    ]);

    return [$user, $plainToken];
}

test('a community post accepts a required title with text content', function () {
    [$user, $token] = creationMobileUser();

    $this->withToken($token)
        ->postJson('/api/community-posts', [
            'title' => 'Morning Trail Run',
            'content' => 'Completed ten kilometers today.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Morning Trail Run')
        ->assertJsonPath('data.content', 'Completed ten kilometers today.');

    $this->assertDatabaseHas('community_posts', [
        'user_id' => $user->id,
        'title' => 'Morning Trail Run',
        'content' => 'Completed ten kilometers today.',
    ]);
});

test('a community post accepts a required title with an image and no content', function () {
    Storage::fake('public');
    [, $token] = creationMobileUser();

    $response = $this->withToken($token)
        ->post('/api/community-posts', [
            'title' => 'Race Day Photo',
            'image' => UploadedFile::fake()->image('race-day.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Race Day Photo')
        ->assertJsonPath('data.content', null);

    $post = CommunityPost::findOrFail($response->json('data.id'));
    expect($post->content)->toBeNull()
        ->and($post->image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($post->image_path);
});

test('a community post accepts a required title with a video and no content', function () {
    Storage::fake('public');
    [, $token] = creationMobileUser();

    $response = $this->withToken($token)
        ->post('/api/community-posts', [
            'title' => 'Finish Line Video',
            'video' => UploadedFile::fake()->create('finish.mp4', 512, 'video/mp4'),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Finish Line Video')
        ->assertJsonPath('data.content', null);

    $post = CommunityPost::findOrFail($response->json('data.id'));
    expect($post->content)->toBeNull()
        ->and($post->video_path)->not->toBeNull();
    Storage::disk('public')->assertExists($post->video_path);
});

test('a title-only community post is rejected', function () {
    [, $token] = creationMobileUser();

    $this->withToken($token)
        ->postJson('/api/community-posts', ['title' => 'Title Without a Body'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content', 'image', 'video']);

    $this->assertDatabaseCount('community_posts', 0);
});

test('a community post without a title is rejected', function () {
    [, $token] = creationMobileUser();

    $this->withToken($token)
        ->postJson('/api/community-posts', ['content' => 'Content without a title.'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);

    $this->assertDatabaseCount('community_posts', 0);
});

test('an empty community post is rejected', function () {
    [, $token] = creationMobileUser();

    $this->withToken($token)
        ->postJson('/api/community-posts', [
            'title' => '',
            'content' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'content', 'image', 'video']);

    $this->assertDatabaseCount('community_posts', 0);
});

test('a media-only post can have its title edited without adding content', function () {
    Storage::fake('public');
    [$user, $token] = creationMobileUser();
    $post = CommunityPost::create([
        'user_id' => $user->id,
        'title' => 'Original Media Title',
        'content' => null,
        'image_path' => 'community/images/existing.jpg',
    ]);

    $this->withToken($token)
        ->patchJson("/api/community-posts/{$post->id}", [
            'title' => 'Updated Media Title',
            'content' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated Media Title')
        ->assertJsonPath('data.content', null);

    $this->assertDatabaseHas('community_posts', [
        'id' => $post->id,
        'title' => 'Updated Media Title',
        'content' => null,
        'image_path' => 'community/images/existing.jpg',
    ]);
});

test('editing cannot leave a community post with only a title', function () {
    [$user, $token] = creationMobileUser();
    $post = CommunityPost::create([
        'user_id' => $user->id,
        'title' => 'Original Text Post',
        'content' => 'Original content.',
    ]);

    $this->withToken($token)
        ->patchJson("/api/community-posts/{$post->id}", [
            'title' => 'Updated Title',
            'content' => null,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);

    $this->assertDatabaseHas('community_posts', [
        'id' => $post->id,
        'title' => 'Original Text Post',
        'content' => 'Original content.',
    ]);
});
