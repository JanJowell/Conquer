<?php

use App\Models\CommunityPost;
use App\Models\CommunityPostHidden;
use App\Models\CommunityPostLike;
use App\Models\CommunityPostReport;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

function archiveMobileUser(string $label): array
{
    $plainToken = 'archive-'.$label.'-'.uniqid();

    $user = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', $plainToken),
        'api_token_expires_at' => now()->addDay(),
    ]);

    return [$user, $plainToken];
}

function archiveCommunityPost(User $owner, array $attributes = []): CommunityPost
{
    return CommunityPost::create([
        'user_id' => $owner->id,
        'title' => 'Archive Test Post',
        'content' => 'A community post used to verify archive behavior.',
        ...$attributes,
    ]);
}

test('deleting a community post archives it and preserves its media', function () {
    Storage::fake('public');
    [$owner, $ownerToken] = archiveMobileUser('owner');
    [$otherUser, $otherToken] = archiveMobileUser('other');

    Storage::disk('public')->put('community/images/archive-test.jpg', 'image');
    Storage::disk('public')->put('community/videos/archive-test.mp4', 'video');

    $post = archiveCommunityPost($owner, [
        'image_path' => 'community/images/archive-test.jpg',
        'video_path' => 'community/videos/archive-test.mp4',
    ]);

    CommunityPostHidden::create([
        'user_id' => $otherUser->id,
        'community_post_id' => $post->id,
    ]);
    CommunityPostReport::create([
        'user_id' => $otherUser->id,
        'community_post_id' => $post->id,
        'reason' => 'Test report',
    ]);

    $this
        ->withToken($ownerToken)
        ->deleteJson("/api/community-posts/{$post->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Post moved to archive. It will be permanently deleted after 30 days.');

    $this->assertSoftDeleted('community_posts', [
        'id' => $post->id,
        'deleted_by_user_id' => $owner->id,
    ]);
    Storage::disk('public')->assertExists($post->image_path);
    Storage::disk('public')->assertExists($post->video_path);

    $this->getJson('/api/community-posts')->assertOk()->assertJsonMissing(['id' => $post->id]);
    $this->withToken($ownerToken)->getJson('/api/community-posts/feed')->assertOk()->assertJsonMissing(['id' => $post->id]);
    $this->withToken($ownerToken)->getJson("/api/community-posts/{$post->id}")->assertNotFound();
    $this->withToken($otherToken)->getJson('/api/community-posts/hidden')->assertOk()->assertJsonMissing(['id' => $post->id]);
    $this->withToken($otherToken)->getJson('/api/community-posts/reported')->assertOk()->assertJsonMissing(['id' => $post->id]);

    $this
        ->withToken($ownerToken)
        ->getJson('/api/community-posts/archived')
        ->assertOk()
        ->assertJsonPath('data.0.id', $post->id)
        ->assertJsonStructure(['data' => [['deleted_at', 'permanently_deleted_at']]]);

    $this
        ->withToken($otherToken)
        ->getJson('/api/community-posts/archived')
        ->assertOk()
        ->assertJsonMissing(['id' => $post->id]);
});

test('only the owner can restore a post they moved to archive', function () {
    Storage::fake('public');
    [$owner, $ownerToken] = archiveMobileUser('restore-owner');
    [, $otherToken] = archiveMobileUser('restore-other');

    $post = archiveCommunityPost($owner);

    $this->withToken($ownerToken)->deleteJson("/api/community-posts/{$post->id}")->assertOk();

    $this
        ->withToken($otherToken)
        ->postJson("/api/community-posts/{$post->id}/restore")
        ->assertForbidden();

    $this
        ->withToken($ownerToken)
        ->postJson("/api/community-posts/{$post->id}/restore")
        ->assertOk()
        ->assertJsonPath('message', 'Post restored successfully.')
        ->assertJsonPath('data.id', $post->id);

    $this->assertDatabaseHas('community_posts', [
        'id' => $post->id,
        'deleted_at' => null,
        'deleted_by_user_id' => null,
    ]);
});

test('owners cannot restore posts deleted by a moderator or after retention expires', function () {
    [$owner, $ownerToken] = archiveMobileUser('moderated-owner');
    $moderator = User::factory()->create(['role' => User::ROLE_CONTENT_MODERATOR]);

    $moderatedPost = archiveCommunityPost($owner);
    $moderatedPost->forceFill(['deleted_by_user_id' => $moderator->id])->save();
    $moderatedPost->delete();

    $this
        ->withToken($ownerToken)
        ->postJson("/api/community-posts/{$moderatedPost->id}/restore")
        ->assertForbidden();

    $expiredPost = archiveCommunityPost($owner);
    $expiredPost->forceFill(['deleted_by_user_id' => $owner->id])->save();
    $expiredPost->delete();
    CommunityPost::withTrashed()
        ->whereKey($expiredPost->id)
        ->update(['deleted_at' => now()->subDays(31)]);

    $this
        ->withToken($ownerToken)
        ->postJson("/api/community-posts/{$expiredPost->id}/restore")
        ->assertStatus(410)
        ->assertJsonPath('message', 'This post can no longer be restored.');
});

test('expired archived posts and their media are permanently deleted', function () {
    Storage::fake('public');
    [$owner] = archiveMobileUser('purge-owner');
    [$otherUser] = archiveMobileUser('purge-other');

    Storage::disk('public')->put('community/images/expired.jpg', 'expired');
    Storage::disk('public')->put('community/images/recent.jpg', 'recent');

    $expiredPost = archiveCommunityPost($owner, ['image_path' => 'community/images/expired.jpg']);
    $expiredPost->forceFill(['deleted_by_user_id' => $owner->id])->save();
    $expiredPost->delete();
    CommunityPost::withTrashed()->whereKey($expiredPost->id)->update(['deleted_at' => now()->subDays(31)]);
    CommunityPostLike::create([
        'community_post_id' => $expiredPost->id,
        'user_id' => $otherUser->id,
    ]);

    $recentPost = archiveCommunityPost($owner, ['image_path' => 'community/images/recent.jpg']);
    $recentPost->forceFill(['deleted_by_user_id' => $owner->id])->save();
    $recentPost->delete();

    $this->artisan('community-posts:purge-archived --dry-run')
        ->expectsOutput('Dry run: 1 archived community post(s) are eligible for permanent deletion.')
        ->assertSuccessful();

    Storage::disk('public')->assertExists($expiredPost->image_path);

    $this->artisan('community-posts:purge-archived')
        ->expectsOutput('Permanently deleted 1 archived community post(s).')
        ->assertSuccessful();

    $this->assertDatabaseMissing('community_posts', ['id' => $expiredPost->id]);
    $this->assertDatabaseMissing('community_post_likes', ['community_post_id' => $expiredPost->id]);
    $this->assertSoftDeleted('community_posts', ['id' => $recentPost->id]);
    Storage::disk('public')->assertMissing($expiredPost->image_path);
    Storage::disk('public')->assertExists($recentPost->image_path);
});

test('archived community post cleanup is scheduled daily', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($scheduledEvent) => str_contains(
            $scheduledEvent->command,
            'community-posts:purge-archived --days=30'
        ));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 3 * * *');
});
