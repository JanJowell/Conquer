<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\RaceResult;
use App\Models\Registration;
use App\Models\User;
use App\Services\CertificateIssuer;

function leaderboardAuthenticatedRunner(): array
{
    $plainToken = 'leaderboard-token-'.uniqid();

    $runner = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', $plainToken),
        'api_token_expires_at' => now()->addDay(),
    ]);

    return [$runner, $plainToken];
}

function addLeaderboardResult(User $runner): RaceResult
{
    $event = Event::create([
        'title' => 'Leaderboard Test Race',
        'slug' => 'leaderboard-test-race-'.uniqid(),
        'venue' => 'Bacoor City',
        'event_date' => now()->subDay()->toDateString(),
        'status' => 'completed',
    ]);

    $category = Category::create([
        'event_id' => $event->id,
        'name' => '5K Open',
        'distance_km' => 5,
        'status' => 'closed',
    ]);

    $registration = Registration::create([
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'bib_number' => '501',
        'status' => 'completed',
        'registered_at' => now()->subDays(2),
    ]);

    return RaceResult::create([
        'registration_id' => $registration->id,
        'user_id' => $runner->id,
        'event_id' => $event->id,
        'category_id' => $category->id,
        'finish_time' => '00:24:30',
        'rank_overall' => 1,
        'rank_category' => 1,
    ]);
}

test('mobile leaderboard shows only verified runner accounts', function () {
    [, $plainToken] = leaderboardAuthenticatedRunner();

    $verifiedRunner = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'name' => 'Verified Runner',
    ]);

    $verifiedLegacyRunner = User::factory()->create([
        'role' => 'user',
        'name' => 'Verified Legacy Runner',
    ]);

    $unverifiedRunner = User::factory()->unverified()->create([
        'role' => User::ROLE_RUNNER,
        'name' => 'Unverified Runner',
    ]);

    $administrator = User::factory()->create([
        'role' => User::ROLE_SUPER_ADMIN,
        'name' => 'Administrator',
    ]);

    $response = $this
        ->withToken($plainToken)
        ->getJson('/api/leaderboard')
        ->assertOk()
        ->assertJsonFragment(['id' => $verifiedRunner->id])
        ->assertJsonFragment(['id' => $verifiedLegacyRunner->id])
        ->assertJsonMissing(['id' => $unverifiedRunner->id])
        ->assertJsonMissing(['id' => $administrator->id]);

    expect(collect($response->json('data'))->pluck('id'))
        ->not->toContain($unverifiedRunner->id, $administrator->id);
});

test('verifying a runner makes their existing result appear without changing it', function () {
    [, $plainToken] = leaderboardAuthenticatedRunner();

    $runner = User::factory()->unverified()->create([
        'role' => User::ROLE_RUNNER,
        'name' => 'Pending Result Runner',
    ]);
    $result = addLeaderboardResult($runner);

    $this
        ->withToken($plainToken)
        ->getJson('/api/leaderboard')
        ->assertOk()
        ->assertJsonMissing(['id' => $runner->id]);

    $runner->forceFill(['email_verified_at' => now()])->save();

    $this
        ->withToken($plainToken)
        ->getJson('/api/leaderboard')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $runner->id,
            'results_count' => 1,
        ]);

    $this->assertDatabaseHas('race_results', [
        'id' => $result->id,
        'user_id' => $runner->id,
    ]);
});

test('leaderboard counts only valid certificates and keeps the legacy count alias', function () {
    [, $plainToken] = leaderboardAuthenticatedRunner();
    $runner = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'name' => 'Certificate Leader',
    ]);
    $participantWithoutCertificate = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'name' => 'No Certificate Runner',
    ]);
    $competitor = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'name' => 'Certificate Competitor',
    ]);
    $issuer = app(CertificateIssuer::class);
    $certificate = $issuer->syncForRegistration(addLeaderboardResult($runner)->registration, notify: false);
    $secondCertificate = $issuer->syncForRegistration(addLeaderboardResult($runner)->registration, notify: false);
    $issuer->syncForRegistration(addLeaderboardResult($competitor)->registration, notify: false);

    $response = $this->withToken($plainToken)->getJson('/api/leaderboard')->assertOk();
    $initialLeaderboard = collect($response->json('data'));
    $leader = $initialLeaderboard->firstWhere('id', $runner->id);
    $withoutCertificate = $initialLeaderboard->firstWhere('id', $participantWithoutCertificate->id);

    expect($leader['certificates_count'])->toBe(2)
        ->and($leader['badges_count'])->toBe(2)
        ->and($withoutCertificate['certificates_count'])->toBe(0)
        ->and($withoutCertificate['badges_count'])->toBe(0)
        ->and($leader['rank'])->toBeLessThan($initialLeaderboard->firstWhere('id', $competitor->id)['rank']);

    foreach ([$certificate, $secondCertificate] as $certificateToRevoke) {
        $certificateToRevoke->update([
            'revoked_at' => now(),
            'revocation_reason' => 'Test revocation',
        ]);
    }

    $revokedLeaderboard = collect(
        $this->withToken($plainToken)->getJson('/api/leaderboard')->assertOk()->json('data')
    );
    $revokedPayload = $revokedLeaderboard->firstWhere('id', $runner->id);

    expect($revokedPayload['certificates_count'])->toBe(0)
        ->and($revokedPayload['badges_count'])->toBe(0)
        ->and($revokedPayload['rank'])->toBeGreaterThan($revokedLeaderboard->firstWhere('id', $competitor->id)['rank']);

    $certificate->update([
        'revoked_at' => null,
        'revocation_reason' => null,
    ]);

    $restoredLeaderboard = collect(
        $this->withToken($plainToken)->getJson('/api/leaderboard')->assertOk()->json('data')
    );
    $restoredPayload = $restoredLeaderboard->firstWhere('id', $runner->id);

    expect($restoredPayload['certificates_count'])->toBe(1)
        ->and($restoredPayload['badges_count'])->toBe(1)
        ->and($restoredPayload['rank'])->toBeLessThan($restoredLeaderboard->firstWhere('id', $competitor->id)['rank']);
});
