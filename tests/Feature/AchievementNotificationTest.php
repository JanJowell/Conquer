<?php

use App\Models\Certificate;
use App\Models\EBadge;
use App\Models\Event as RaceEvent;
use App\Models\IssuedEBadge;
use App\Models\PushNotification;
use App\Models\User;
use App\Services\CertificateNotificationService;
use App\Services\EBadgeNotificationService;
use App\Services\FirebaseCloudMessaging;
use Illuminate\Support\Facades\Event;

function certificateNotificationFixture(User $user): Certificate
{
    $raceEvent = new RaceEvent(['title' => 'Notification Test Event']);
    $raceEvent->id = 501;

    $certificate = new Certificate([
        'user_id' => $user->id,
        'event_id' => $raceEvent->id,
        'registration_id' => 601,
        'verification_token' => 'certificate-notification-test-token',
    ]);
    $certificate->id = 701;
    $certificate->setRelation('user', $user);
    $certificate->setRelation('event', $raceEvent);

    return $certificate;
}

function badgeNotificationFixture(User $user): IssuedEBadge
{
    $raceEvent = new RaceEvent(['title' => 'Notification Test Event']);
    $raceEvent->id = 502;

    $badge = new EBadge(['title' => 'Finisher']);
    $badge->id = 702;

    $issuedBadge = new IssuedEBadge([
        'user_id' => $user->id,
        'event_id' => $raceEvent->id,
        'registration_id' => 602,
        'e_badge_id' => $badge->id,
    ]);
    $issuedBadge->id = 703;
    $issuedBadge->setRelation('user', $user);
    $issuedBadge->setRelation('event', $raceEvent);
    $issuedBadge->setRelation('badge', $badge);

    return $issuedBadge;
}

test('certificate and badge issuers persist achievement notifications', function () {
    $user = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $messaging = Mockery::mock(FirebaseCloudMessaging::class);
    $messaging->shouldReceive('sendNotification')
        ->twice()
        ->andReturn(['sent' => 1, 'processed' => true]);

    (new CertificateNotificationService($messaging))->notifyIssued(certificateNotificationFixture($user));
    (new EBadgeNotificationService($messaging))->notifyIssued(badgeNotificationFixture($user));

    expect(PushNotification::query()->where('type', 'achievement')->count())->toBe(2);
});

test('notification persistence failures never interrupt result side effects', function () {
    $user = User::factory()->create(['role' => User::ROLE_RUNNER]);
    $messaging = Mockery::mock(FirebaseCloudMessaging::class);
    $messaging->shouldNotReceive('sendNotification');

    $eventName = 'eloquent.creating: '.PushNotification::class;
    Event::listen($eventName, static function (): never {
        throw new RuntimeException('Simulated notification database failure.');
    });

    try {
        (new CertificateNotificationService($messaging))->notifyIssued(certificateNotificationFixture($user));
        (new EBadgeNotificationService($messaging))->notifyIssued(badgeNotificationFixture($user));
    } finally {
        Event::forget($eventName);
    }

    expect(PushNotification::query()->count())->toBe(0);
});
