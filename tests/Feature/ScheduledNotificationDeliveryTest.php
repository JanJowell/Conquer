<?php

use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Api\NotificationController as ApiNotificationController;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserDeviceToken;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    expect(app()->environment())->toBe('testing');
    expect(config('database.default'))->toBe('sqlite');
    expect(config('database.connections.sqlite.database'))->toBe(':memory:');

    $this->travelTo(now()->startOfMinute());
    Mail::fake();
    Http::preventStrayRequests();
    config([
        'services.firebase.project_id' => 'scheduler-test-project',
        'services.firebase.credentials' => null,
        'services.firebase.credentials_json' => null,
        'services.firebase.credentials_base64' => null,
        'services.firebase.client_email' => 'scheduler@example.invalid',
        'services.firebase.private_key' => 'not-a-real-private-key',
    ]);
    Cache::put('firebase_fcm_access_token', 'fake-scheduler-token', now()->addHour());
});

afterEach(function () {
    $this->travelBack();
});

function scheduledDeliveryNotice(array $overrides = []): PushNotification
{
    return PushNotification::create(array_replace([
        'title' => 'Isolated scheduler test',
        'message' => 'Fake delivery only',
        'type' => 'announcement',
        'target_audience' => 'runners',
        'scheduled_at' => now()->subMinute(),
        'sent_at' => null,
        'is_active' => true,
    ], $overrides));
}

function scheduledDeliveryDevice(string $token = 'fake-runner-device'): UserDeviceToken
{
    $user = User::factory()->create(['role' => User::ROLE_RUNNER]);

    return UserDeviceToken::create([
        'user_id' => $user->id,
        'token' => $token,
        'platform' => 'android',
        'last_used_at' => now(),
    ]);
}

test('due notification sends once and a subsequent scheduler pass does not resend it', function () {
    Http::fake(['https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/scheduler-test-project/messages/test'], 200)]);
    $device = scheduledDeliveryDevice();
    $notice = scheduledDeliveryNotice(['scheduled_at' => now()]);

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    expect($notice->fresh()->sent_at)->not->toBeNull();
    Http::assertSent(fn (Request $request) => $request['message']['token'] === $device->token
        && $request['message']['data']['notification_id'] === (string) $notice->id
        && $request->hasHeader('Authorization', 'Bearer fake-scheduler-token'));

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    Http::assertSentCount(1);
});

test('ineligible scheduled notifications do not send', function (string $state) {
    scheduledDeliveryDevice();
    $overrides = match ($state) {
        'future' => ['scheduled_at' => now()->addMinute()],
        'inactive' => ['is_active' => false],
        'already sent' => ['sent_at' => now()->subMinutes(2)],
        'unscheduled' => ['scheduled_at' => null],
    };
    $notice = scheduledDeliveryNotice($overrides);
    $originalSentAt = $notice->sent_at?->toISOString();

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);

    Http::assertNothingSent();
    expect($notice->fresh()->sent_at?->toISOString())->toBe($originalSentAt);
})->with(['future', 'inactive', 'already sent', 'unscheduled']);

test('a future notification becomes eligible when its scheduled time arrives', function () {
    Http::fake(['https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/scheduler-test-project/messages/test'], 200)]);
    scheduledDeliveryDevice();
    $notice = scheduledDeliveryNotice(['scheduled_at' => now()->addMinutes(5)]);

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    Http::assertNothingSent();
    $this->travel(5)->minutes();
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);

    Http::assertSentCount(1);
    expect($notice->fresh()->sent_at)->not->toBeNull();
});

test('runner audience excludes administrator devices', function () {
    Http::fake(['https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/scheduler-test-project/messages/test'], 200)]);
    $runnerDevice = scheduledDeliveryDevice();
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    UserDeviceToken::create(['user_id' => $admin->id, 'token' => 'fake-admin-device', 'platform' => 'android']);
    scheduledDeliveryNotice();

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request['message']['token'] === $runnerDevice->token);
});

test('temporary Firebase failure remains pending then succeeds on a later scheduler pass', function () {
    scheduledDeliveryDevice();
    $notice = scheduledDeliveryNotice();
    Http::fake([
        'https://fcm.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['status' => 'UNAVAILABLE']], 503)
            ->push(['name' => 'projects/scheduler-test-project/messages/recovered'], 200),
    ]);

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    expect($notice->fresh()->sent_at)->toBeNull()
        ->and($notice->fresh()->is_active)->toBeTrue();
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    expect($notice->fresh()->sent_at)->not->toBeNull();
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    Http::assertSentCount(2);
});

test('no registered devices produces no Firebase request', function () {
    User::factory()->create(['role' => User::ROLE_RUNNER]);
    $notice = scheduledDeliveryNotice();

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);

    Http::assertNothingSent();
    // Current design uses sent_at for terminal processing, including zero devices.
    expect($notice->fresh()->sent_at)->not->toBeNull();
});

test('missing Firebase configuration pauses a scheduled notification without marking it sent', function () {
    scheduledDeliveryDevice();
    $notice = scheduledDeliveryNotice();
    config(['services.firebase.private_key' => null]);

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);

    Http::assertNothingSent();
    expect($notice->fresh()->sent_at)->toBeNull()
        ->and($notice->fresh()->is_active)->toBeFalse()
        ->and($notice->fresh()->deliveryError())->toContain('configuration is missing or invalid');
});

test('Firebase authentication rejection must not mark a scheduled notification as sent', function () {
    $device = scheduledDeliveryDevice();
    $notice = scheduledDeliveryNotice();
    Http::fake(['https://fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAUTHENTICATED']], 401)]);

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);

    Http::assertSentCount(1);
    expect(UserDeviceToken::find($device->id))->not->toBeNull();
    expect($notice->fresh()->sent_at)->toBeNull();
    expect($notice->fresh()->is_active)->toBeFalse()
        ->and($notice->fresh()->deliveryError())->toContain('Delivery is paused');
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    Http::assertSentCount(1);
});

test('notification command is registered with Laravel scheduler', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'notifications:send-scheduled'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');
});

function scheduledDeliverySigningKey(): void
{
    // Ephemeral test-only RSA key. Never load real .env credentials.
    static $pem;
    if ($pem === null) {
        $options = ['config' => base_path('tests/Fixtures/openssl-test.cnf'), 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $key = openssl_pkey_new($options);
        if ($key === false || ! openssl_pkey_export($key, $pem, null, $options)) {
            throw new RuntimeException('Could not create the ephemeral test key.');
        }
    }
    config(['services.firebase.private_key' => $pem]);
}

test('a rejected cached OAuth token is refreshed once and delivery succeeds', function () {
    scheduledDeliverySigningKey();
    scheduledDeliveryDevice();
    $notice = scheduledDeliveryNotice();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-fake-token'], 200),
        'https://fcm.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['status' => 'UNAUTHENTICATED']], 401)
            ->push(['name' => 'projects/test/messages/accepted'], 200),
    ]);

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'fcm.googleapis.com')
        && $request->hasHeader('Authorization', 'Bearer fresh-fake-token'));
    expect($notice->fresh()->sent_at)->not->toBeNull()
        ->and($notice->fresh()->deliveryError())->toBeNull();
});

test('persistent OAuth rejection pauses the batch after one refresh without deleting devices', function () {
    scheduledDeliverySigningKey();
    scheduledDeliveryDevice('device-one');
    scheduledDeliveryDevice('device-two');
    $notice = scheduledDeliveryNotice();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-fake-token'], 200),
        'https://fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAUTHENTICATED']], 401),
    ]);

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    Http::assertSentCount(3);
    expect($notice->fresh()->sent_at)->toBeNull()
        ->and($notice->fresh()->is_active)->toBeFalse()
        ->and(UserDeviceToken::count())->toBe(2)
        ->and(Cache::has('firebase_fcm_access_token'))->toBeFalse();
});

test('invalid service account credentials pause without retrying every scheduler pass', function () {
    scheduledDeliverySigningKey();
    Cache::forget('firebase_fcm_access_token');
    scheduledDeliveryDevice();
    $notice = scheduledDeliveryNotice();
    Http::fake(['https://oauth2.googleapis.com/token' => Http::response([
        'error' => 'invalid_grant', 'error_description' => 'provider-detail-that-must-not-be-stored',
    ], 400)]);

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    Http::assertSentCount(1);
    expect($notice->fresh()->sent_at)->toBeNull()
        ->and($notice->fresh()->is_active)->toBeFalse()
        ->and(json_encode($notice->fresh()->data))->not->toContain('provider-detail-that-must-not-be-stored');
});

test('permission and platform credential errors pause without refreshing OAuth or deleting devices', function (int $status, ?string $detailCode) {
    $device = scheduledDeliveryDevice();
    $notice = scheduledDeliveryNotice();
    $error = ['status' => $status === 401 ? 'UNAUTHENTICATED' : 'PERMISSION_DENIED'];
    if ($detailCode !== null) {
        $error['details'] = [
            ['@type' => 'type.googleapis.com/google.rpc.BadRequest'],
            ['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => $detailCode],
        ];
    }
    Http::fake(['https://fcm.googleapis.com/*' => Http::response(['error' => $error], $status)]);

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    Http::assertSentCount(1);
    expect($notice->fresh()->sent_at)->toBeNull()
        ->and($notice->fresh()->is_active)->toBeFalse()
        ->and(UserDeviceToken::find($device->id))->not->toBeNull()
        ->and(Cache::get('firebase_fcm_access_token'))->toBe('fake-scheduler-token');
})->with([
    'permissions' => [403, null],
    'sender mismatch' => [403, 'SENDER_ID_MISMATCH'],
    'APNs or web push credential' => [401, 'THIRD_PARTY_AUTH_ERROR'],
]);

test('temporary token endpoint failure stays pending and recovers later', function () {
    scheduledDeliverySigningKey();
    Cache::forget('firebase_fcm_access_token');
    scheduledDeliveryDevice();
    $notice = scheduledDeliveryNotice();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::sequence()
            ->push(['error' => 'temporarily_unavailable'], 503)
            ->push(['access_token' => 'fresh-fake-token'], 200),
        'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/test/messages/accepted'], 200),
    ]);
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    expect($notice->fresh()->sent_at)->toBeNull()->and($notice->fresh()->is_active)->toBeTrue();
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    expect($notice->fresh()->sent_at)->not->toBeNull();
    Http::assertSentCount(3);
});

test('recovering a partially delivered notification does not resend to accepted devices', function (int $failureStatus, string $failureCode) {
    scheduledDeliveryDevice('accepted-device');
    scheduledDeliveryDevice('failed-device');
    $notice = scheduledDeliveryNotice(['data' => ['event_id' => 123]]);
    $fail = true;
    Http::fake(function (Request $request) use (&$fail, $failureStatus, $failureCode) {
        expect($request['message']['data'])->not->toHaveKey('_firebase_delivery');
        if ($request['message']['token'] === 'failed-device' && $fail) {
            return Http::response(['error' => ['status' => $failureCode]], $failureStatus);
        }

        return Http::response(['name' => 'projects/test/messages/accepted'], 200);
    });
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    expect($notice->fresh()->sent_at)->toBeNull()
        ->and($notice->fresh()->data['_firebase_delivery']['accepted_token_hashes'])->toBe([hash('sha256', 'accepted-device')]);
    $fail = false;
    $notice->refresh()->update(['is_active' => true]);
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    Http::assertSentCount(3);
    expect(Http::recorded(fn (Request $request) => $request['message']['token'] === 'accepted-device'))->toHaveCount(1)
        ->and($notice->fresh()->sent_at)->not->toBeNull()
        ->and($notice->fresh()->deliveryError())->toBeNull()
        ->and($notice->fresh()->data['event_id'])->toBe(123);
})->with(['permission pause' => [403, 'PERMISSION_DENIED'], 'temporary outage' => [503, 'UNAVAILABLE']]);

test('delivery error is visible in admin but internal delivery bookkeeping stays out of participant responses', function () {
    $device = scheduledDeliveryDevice();
    $notice = scheduledDeliveryNotice(['data' => ['event_id' => 123]]);
    Http::fake(['https://fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED']], 403)]);
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    $notice->refresh();
    $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]));
    view()->share('errors', new ViewErrorBag);
    $html = view('admin.notifications.index', ['notifications' => PushNotification::paginate()])->render();
    expect($html)->toContain('Delivery needs attention', 'Paused', 'Firebase authentication or permissions failed');
    expect(view('admin.notifications.show', ['notification' => $notice])->render())->toContain('Delivery needs attention');

    $notice->update(['is_active' => true, 'sent_at' => now()]);
    $request = Illuminate\Http\Request::create('/api/notifications');
    $request->setUserResolver(fn () => $device->user);
    $response = (new ApiNotificationController)->index($request);
    expect($response->getData(true)['data'][0]['data'])->toBe(['event_id' => 123])
        ->and($response->getContent())->not->toContain('_firebase_delivery');
});

test('malformed credential JSON pauses safely without any external requests', function (string $json) {
    scheduledDeliveryDevice();
    $notice = scheduledDeliveryNotice();
    config(['services.firebase.credentials_json' => $json]);

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);

    Http::assertNothingSent();
    expect($notice->fresh()->sent_at)->toBeNull()
        ->and($notice->fresh()->is_active)->toBeFalse()
        ->and($notice->fresh()->deliveryError())->toContain('configuration is missing or invalid');
})->with(['invalid JSON' => ['{invalid'], 'scalar JSON' => ['"not-credentials"'], 'invalid field types' => ['{"client_email":[],"private_key":123}']]);

test('rate limiting keeps delivery pending without removing the device', function () {
    $device = scheduledDeliveryDevice();
    $notice = scheduledDeliveryNotice();
    Http::fake(['https://fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429)]);

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);

    Http::assertSentCount(1);
    expect($notice->fresh()->sent_at)->toBeNull()
        ->and($notice->fresh()->is_active)->toBeTrue()
        ->and(UserDeviceToken::find($device->id))->not->toBeNull();
});

test('only an explicitly unregistered device is removed on a permanent rejection', function (string $code, int $status, bool $removed) {
    $device = scheduledDeliveryDevice();
    scheduledDeliveryNotice();
    Http::fake(['https://fcm.googleapis.com/*' => Http::response(['error' => ['details' => [
        ['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => $code],
    ]]], $status)]);

    $this->artisan('notifications:send-scheduled')->assertExitCode(0);

    Http::assertSentCount(1);
    expect(UserDeviceToken::whereKey($device->id)->exists())->toBe(! $removed);
})->with(['expired device' => ['UNREGISTERED', 404, true], 'invalid payload' => ['INVALID_ARGUMENT', 400, false]]);

test('administrator can reactivate a paused notification and send it after credentials are corrected', function () {
    scheduledDeliveryDevice();
    $notice = scheduledDeliveryNotice();
    Http::fake(['https://fcm.googleapis.com/*' => Http::sequence()
        ->push(['error' => ['status' => 'PERMISSION_DENIED']], 403)
        ->push(['name' => 'projects/test/messages/recovered'], 200)]);
    $this->artisan('notifications:send-scheduled')->assertExitCode(0);
    $notice->refresh();
    $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->actingAs($admin);
    $controller = app(NotificationController::class);
    $controller->sendNow($notice);
    Http::assertSentCount(1);

    $request = Illuminate\Http\Request::create('/admin/notifications/'.$notice->id, 'PUT', [
        'title' => $notice->title,
        'message' => $notice->message,
        'type' => $notice->type,
        'target_audience' => $notice->target_audience,
        'scheduled_at' => null,
        'is_active' => true,
    ]);
    $request->setUserResolver(fn () => $admin);
    $controller->update($request, $notice);
    $controller->sendNow($notice->fresh());

    Http::assertSentCount(2);
    expect($notice->fresh()->sent_at)->not->toBeNull()
        ->and($notice->fresh()->is_active)->toBeTrue()
        ->and($notice->fresh()->deliveryError())->toBeNull();
});
