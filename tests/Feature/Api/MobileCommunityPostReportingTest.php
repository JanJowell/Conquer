<?php

use App\Models\CommunityPost;
use App\Models\CommunityPostReport;
use App\Models\User;

function reportingMobileUser(string $label, bool $verified = true): array
{
    $plainToken = 'reporting-'.$label.'-'.uniqid();

    $user = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'email_verified_at' => $verified ? now() : null,
        'api_token' => hash('sha256', $plainToken),
        'api_token_expires_at' => now()->addDay(),
    ]);

    return [$user, $plainToken];
}

function reportingCommunityPost(User $owner): CommunityPost
{
    return CommunityPost::create([
        'user_id' => $owner->id,
        'title' => 'Reporting Test Post',
        'content' => 'Reporting Test Post content used to verify the community threshold.',
    ]);
}

test('only verified users can report community posts', function () {
    [$owner] = reportingMobileUser('unverified-owner');
    [, $unverifiedToken] = reportingMobileUser('unverified-reporter', false);
    $post = reportingCommunityPost($owner);

    $this->withToken($unverifiedToken)
        ->postJson("/api/community-posts/{$post->id}/report", ['reason' => 'Needs review'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only verified users can report community posts.');

    $this->assertDatabaseCount('community_post_reports', 0);
    expect($post->fresh()->is_flagged)->toBeFalse();
});

test('the first two distinct verified reports keep a post visible and send it to review', function () {
    [$owner] = reportingMobileUser('visible-owner');
    [, $firstToken] = reportingMobileUser('visible-first');
    [, $secondToken] = reportingMobileUser('visible-second');
    $post = reportingCommunityPost($owner);

    $this->withToken($firstToken)
        ->postJson("/api/community-posts/{$post->id}/report", ['reason' => 'First concern'])
        ->assertOk()
        ->assertJsonPath('data.report_count', 1)
        ->assertJsonPath('data.temporarily_hidden', false)
        ->assertJsonPath('data.hide_threshold', 3);

    $this->getJson('/api/community-posts')->assertJsonFragment(['id' => $post->id]);

    $this->withToken($secondToken)
        ->postJson("/api/community-posts/{$post->id}/report", ['reason' => 'Second concern'])
        ->assertOk()
        ->assertJsonPath('data.report_count', 2)
        ->assertJsonPath('data.temporarily_hidden', false);

    expect($post->fresh()->is_flagged)->toBeFalse();
    $this->assertDatabaseCount('community_post_reports', 2);

    $moderator = User::factory()->create(['role' => User::ROLE_CONTENT_MODERATOR]);
    $this->actingAs($moderator)
        ->get(route('admin.content.pending-review'))
        ->assertOk()
        ->assertSee('Reporting Test Post')
        ->assertSee('2 verified reports')
        ->assertSee('Still visible');
});

test('repeat reports from the same user do not advance the threshold', function () {
    [$owner] = reportingMobileUser('repeat-owner');
    [, $reporterToken] = reportingMobileUser('repeat-reporter');
    $post = reportingCommunityPost($owner);

    $this->withToken($reporterToken)
        ->postJson("/api/community-posts/{$post->id}/report", ['reason' => 'Original reason'])
        ->assertJsonPath('data.report_count', 1);

    $this->withToken($reporterToken)
        ->postJson("/api/community-posts/{$post->id}/report", ['reason' => 'Updated reason'])
        ->assertOk()
        ->assertJsonPath('data.report_count', 1)
        ->assertJsonPath('data.temporarily_hidden', false);

    $this->assertDatabaseCount('community_post_reports', 1);
    $this->assertDatabaseHas('community_post_reports', [
        'community_post_id' => $post->id,
        'reason' => 'Updated reason',
    ]);
    expect($post->fresh()->is_flagged)->toBeFalse();
});

test('the third distinct verified report temporarily hides the post', function () {
    [$owner] = reportingMobileUser('threshold-owner');
    $post = reportingCommunityPost($owner);

    foreach (range(1, 3) as $reportNumber) {
        [, $token] = reportingMobileUser('threshold-'.$reportNumber);

        $response = $this->withToken($token)
            ->postJson("/api/community-posts/{$post->id}/report", [
                'reason' => "Concern {$reportNumber}",
            ])
            ->assertOk()
            ->assertJsonPath('data.report_count', $reportNumber);

        $response->assertJsonPath('data.temporarily_hidden', $reportNumber === 3);
    }

    expect($post->fresh()->is_flagged)->toBeTrue();
    $this->getJson('/api/community-posts')->assertJsonMissing(['id' => $post->id]);
});

test('a moderator decision resolves pending reports while preserving their history', function () {
    [$owner] = reportingMobileUser('decision-owner');
    [, $reporterToken] = reportingMobileUser('decision-reporter');
    $post = reportingCommunityPost($owner);
    $moderator = User::factory()->create(['role' => User::ROLE_CONTENT_MODERATOR]);

    $this->withToken($reporterToken)
        ->postJson("/api/community-posts/{$post->id}/report", ['reason' => 'Please review'])
        ->assertOk();

    $this->actingAs($moderator)
        ->post(route('admin.content.community-posts.unflag', $post), [
            'moderation_note' => 'Reviewed and allowed to remain.',
        ])
        ->assertRedirect();

    $report = CommunityPostReport::where('community_post_id', $post->id)->sole();
    expect($report->reviewed_at)->not->toBeNull()
        ->and($report->reviewed_by)->toBe($moderator->id)
        ->and($post->fresh()->is_flagged)->toBeFalse();

    $this->actingAs($moderator)
        ->get(route('admin.content.pending-review'))
        ->assertOk()
        ->assertDontSee('Reporting Test Post');

    $this->actingAs($moderator)
        ->get(route('admin.content.community-posts.show', $post))
        ->assertOk()
        ->assertSee('Reviewed and allowed to remain.')
        ->assertSee('Reviewed');
});
