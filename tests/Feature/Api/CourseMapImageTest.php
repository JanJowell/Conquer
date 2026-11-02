<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\GoogleStaticCourseMap;
use App\Support\ParticipantCourseMap;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

function imageTestRoute(): array
{
    return [
        'version' => 1,
        'path' => [['lat' => 38.5, 'lng' => -120.2], ['lat' => 40.7, 'lng' => -120.95], ['lat' => 43.252, 'lng' => -126.453]],
        'checkpoints' => [['lat' => 40.7, 'lng' => -120.95, 'label' => 'Water station']],
    ];
}

function imageTestCategory(): Category
{
    $event = Event::forceCreate(['review_status' => null,
        'title' => 'Map image run', 'slug' => 'map-image-run',
        'description' => 'A complete public event description.', 'venue' => 'Bacoor City',
        'event_date' => now()->addMonth()->toDateString(), 'start_time' => '06:00', 'end_time' => '12:00',
        'registration_deadline' => now()->addWeek()->toDateString(), 'status' => 'upcoming',
        'banner_image' => 'events/banners/sample.jpg', 'organized_by' => 'Conquer Events Team',
        'interest_type' => config('conquer.event_interest_types.0'),
    ]);

    return Category::create([
        'event_id' => $event->id, 'name' => '5K', 'distance_km' => 5, 'slot_limit' => 25,
        'status' => 'open', 'scheduled_start_time' => '06:00', 'scheduled_end_time' => '08:00',
        'course_map' => imageTestRoute(), 'checkpoint_map_image' => 'categories/maps/fallback.jpg',
    ]);
}

function imageTestUrl(Category $category): string
{
    return route('api.categories.course-map-image', [
        'category' => $category->id,
        'revision' => app(GoogleStaticCourseMap::class)->revision(ParticipantCourseMap::make($category->course_map)),
    ]);
}

function imageTestPng(): string
{
    $image = imagecreatetruecolor(1280, 960);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

beforeEach(function () {
    config([
        'services.google_maps.static_enabled' => true,
        'services.google_maps.static_key' => 'test-static-key',
        'services.google_maps.signing_secret' => 'vNIXE0xscrmjlyV-12Nj_BvUPaw=',
        'services.google_maps.static_daily_limit' => 100,
    ]);
    Http::preventStrayRequests();
    $this->mapUser = User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', 'map-image-token'),
        'api_token_expires_at' => now()->addDay(),
    ]);
});

test('category metadata contains only the protected image endpoint and legend without fetching Google', function () {
    $category = imageTestCategory();
    $this->getJson('/api/events/'.$category->event_id)->assertOk()
        ->assertJsonPath('data.categories.0.course_map_image.url', imageTestUrl($category))
        ->assertJsonPath('data.categories.0.course_map_image.requires_authentication', true)
        ->assertJsonPath('data.categories.0.course_map_image.legend.2.number', 1)
        ->assertJsonPath('data.categories.0.checkpoint_map_image_url', asset('storage/categories/maps/fallback.jpg'))
        ->assertDontSee('test-static-key')->assertDontSee('vNIXE0xs')->assertDontSee('maps.googleapis.com');
    Http::assertNothingSent();
});

test('signed static request uses the canonical encoded polyline and a signature over the exact encoded path', function () {
    $url = app(GoogleStaticCourseMap::class)->signedUrl(ParticipantCourseMap::make(imageTestRoute()));
    [$unsigned, $signature] = explode('&signature=', $url);
    $path = substr($unsigned, strlen('https://maps.googleapis.com'));
    $secret = base64_decode(strtr('vNIXE0xscrmjlyV-12Nj_BvUPaw=', '-_', '+/'));
    $expected = strtr(base64_encode(hash_hmac('sha1', $path, $secret, true)), '+/', '-_');

    expect($signature)->toBe($expected)
        ->and(rawurldecode($url))->toContain('enc:_p~iF~ps|U_ulLnnqC_mqNvxq`@')
        ->and(substr_count($url, 'markers='))->toBe(3)
        ->and($url)->toContain('size=640x480&scale=2&format=png&maptype=roadmap')
        ->and($url)->not->toContain('Water');
});

test('image bytes are returned without permanent storage or exposing provider URLs', function () {
    $category = imageTestCategory();
    $png = imageTestPng();
    Http::fake(['maps.googleapis.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png'])]);

    $response = $this->withToken('map-image-token')->get(imageTestUrl($category));
    $response->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->getContent())->toBe($png)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');
    Http::assertSentCount(1);
    expect($category->fresh()->checkpoint_map_image)->toBe('categories/maps/fallback.jpg');
});

test('image requests require a valid mobile token even when the URL is known', function () {
    $category = imageTestCategory();
    $this->getJson(imageTestUrl($category))->assertUnauthorized();
    $this->withToken('wrong-token')->getJson(imageTestUrl($category))->assertUnauthorized();
    Http::assertNothingSent();
});

test('incomplete events and unowned closed categories cannot trigger image requests', function () {
    $category = imageTestCategory();
    $url = imageTestUrl($category);
    $category->event->update(['banner_image' => null]);
    $this->withToken('map-image-token')->getJson($url)->assertNotFound();
    $category->event->update(['banner_image' => 'events/banner.jpg']);
    $category->update(['status' => 'closed']);
    $this->withToken('map-image-token')->getJson($url)->assertNotFound();
    Http::assertNothingSent();
});

test('an existing registration permits a closed category map but another participants registration does not', function () {
    $category = imageTestCategory();
    // Keep a separate open category so event readiness is independent of this category.
    $other = $category->replicate();
    $other->name = '10K';
    $other->save();
    $category->update(['status' => 'closed']);
    $registration = Registration::create([
        'user_id' => User::factory()->create()->id, 'event_id' => $category->event_id,
        'category_id' => $category->id, 'shirt_size' => 'M', 'status' => 'approved',
    ]);
    $url = imageTestUrl($category);
    $this->withToken('map-image-token')->getJson($url)->assertNotFound();
    $registration->update(['user_id' => $this->mapUser->id]);
    Http::fake(['maps.googleapis.com/*' => Http::response(imageTestPng(), 200, ['Content-Type' => 'image/png'])]);
    $this->withToken('map-image-token')->get($url)->assertOk();
    $registration->update(['status' => 'rejected']);
    $this->withToken('map-image-token')->getJson($url)->assertNotFound();
    Http::assertSentCount(1);
});

test('disabled or incomplete configuration preserves image fallback without calling Google', function (string $setting, mixed $value) {
    $category = imageTestCategory();
    config(['services.google_maps.'.$setting => $value]);
    $this->getJson('/api/events/'.$category->event_id)->assertOk()->assertJsonPath('data.categories.0.course_map_image', null);
    $this->withToken('map-image-token')->getJson(imageTestUrl($category))
        ->assertStatus(503)->assertJsonPath('reason', 'not_configured')
        ->assertJsonPath('fallback_image_url', asset('storage/categories/maps/fallback.jpg'));
    Http::assertNothingSent();
})->with([
    'disabled' => ['static_enabled', false],
    'missing key' => ['static_key', null],
    'missing secret' => ['signing_secret', null],
    'invalid secret' => ['signing_secret', '***invalid***'],
]);

test('changed and cleared routes cannot serve an image for an old legend', function () {
    $category = imageTestCategory();
    $url = imageTestUrl($category);
    $route = imageTestRoute();
    $route['checkpoints'][0]['label'] = 'Changed label';
    $category->update(['course_map' => $route]);
    $this->withToken('map-image-token')->getJson($url)->assertStatus(409)->assertJsonPath('reason', 'route_changed');
    $category->update(['course_map' => null]);
    $this->withToken('map-image-token')->getJson($url)->assertNotFound()->assertJsonPath('reason', 'no_route');
    Http::assertNothingSent();
});

test('head probes do not spend map requests', function () {
    $category = imageTestCategory();
    $this->withToken('map-image-token')->head(imageTestUrl($category))->assertStatus(405);
    Http::assertNothingSent();
});

test('provider failures are sanitized and return the independent uploaded fallback', function (string $case) {
    $category = imageTestCategory();
    Log::spy();
    $png = imageTestPng();
    Http::fake(['maps.googleapis.com/*' => match ($case) {
        'forbidden' => Http::response('test-static-key secret provider diagnostic', 403),
        'redirect' => Http::response('', 302, ['Location' => 'https://example.test/leak']),
        'html' => Http::response('<html>provider error</html>', 200, ['Content-Type' => 'text/html']),
        'corrupt' => Http::response('not a PNG', 200, ['Content-Type' => 'image/png']),
        'truncated PNG' => Http::response(substr($png, 0, 40), 200, ['Content-Type' => 'image/png']),
        'warning' => Http::response($png, 200, ['Content-Type' => 'image/png', 'X-Staticmap-API-Warning' => 'Too many markers']),
        'oversize' => Http::response($png, 200, ['Content-Type' => 'image/png', 'Content-Length' => '4000001']),
        'oversize stream' => Http::response(str_repeat('x', 4000001), 200, ['Content-Type' => 'image/png']),
        'timeout' => Http::failedConnection('test-static-key private URL'),
    }]);

    $this->withToken('map-image-token')->getJson(imageTestUrl($category))->assertStatus(502)
        ->assertJsonPath('fallback_image_url', asset('storage/categories/maps/fallback.jpg'))
        ->assertDontSee('test-static-key')->assertDontSee('private URL')->assertDontSee('diagnostic');
    Http::assertSentCount(1);
    Log::shouldNotHaveReceived('error');
})->with(['forbidden', 'redirect', 'html', 'corrupt', 'truncated PNG', 'warning', 'oversize', 'oversize stream', 'timeout']);

test('every checkpoint has a distinct marker token and a loop has one combined endpoint', function () {
    $route = imageTestRoute();
    $route['path'][] = $route['path'][0];
    $route['checkpoints'] = array_fill(0, 50, $route['checkpoints'][0]);
    $map = ParticipantCourseMap::make($route);
    $maps = app(GoogleStaticCourseMap::class);
    $legend = $maps->legend($map);
    expect($legend)->toHaveCount(51)
        ->and($legend[0]['label'])->toBe('Start / Finish')
        ->and($legend[50]['number'])->toBe(50)
        ->and(count(array_unique(array_map(fn ($marker) => $marker['marker_color'].':'.$marker['marker_label'], $legend))))->toBe(51)
        ->and(substr_count($maps->signedUrl($map), 'markers='))->toBe(51);
});

test('the daily shared request budget counts failures and refuses additional provider calls', function () {
    config(['services.google_maps.static_daily_limit' => 1]);
    $category = imageTestCategory();
    Http::fake(['maps.googleapis.com/*' => Http::response('', 503)]);
    $this->withToken('map-image-token')->getJson(imageTestUrl($category))->assertStatus(502);
    User::factory()->create([
        'role' => User::ROLE_RUNNER,
        'api_token' => hash('sha256', 'second-image-token'),
        'api_token_expires_at' => now()->addDay(),
    ]);
    $this->withToken('second-image-token')->getJson(imageTestUrl($category))->assertStatus(429)->assertJsonPath('reason', 'daily_limit');
    Http::assertSentCount(1);
});

test('zero image budget prevents any provider call', function () {
    config(['services.google_maps.static_daily_limit' => 0]);
    $category = imageTestCategory();
    $this->withToken('map-image-token')->getJson(imageTestUrl($category))->assertStatus(429)
        ->assertJsonPath('reason', 'daily_limit');
    Http::assertNothingSent();
});

test('outside testing image configuration requires a shared supported cache driver', function () {
    $originalEnvironment = app()->environment();
    app()->instance('env', 'production');
    try {
        $maps = app(GoogleStaticCourseMap::class);
        expect($maps->configured())->toBeFalse();
        config(['cache.default' => 'database']);
        expect($maps->configured())->toBeTrue();
        config(['cache.default' => 'redis']);
        expect($maps->configured())->toBeTrue();
        config(['cache.default' => 'file']);
        expect($maps->configured())->toBeFalse();
    } finally {
        app()->instance('env', $originalEnvironment);
    }
    Http::assertNothingSent();
});

test('per token throttling prevents additional upstream requests', function () {
    $category = imageTestCategory();
    Http::fake(['maps.googleapis.com/*' => Http::response('', 503)]);
    for ($i = 0; $i < 6; $i++) {
        $this->withToken('map-image-token')->getJson(imageTestUrl($category))->assertStatus(502);
    }
    $this->withToken('map-image-token')->getJson(imageTestUrl($category))->assertStatus(429);
    Http::assertSentCount(6);
});

test('client query parameters cannot change the Google request or provider destination', function () {
    $category = imageTestCategory();
    $expected = app(GoogleStaticCourseMap::class)->signedUrl(ParticipantCourseMap::make($category->course_map));
    Http::fake(['maps.googleapis.com/*' => Http::response(imageTestPng(), 200, ['Content-Type' => 'image/png'])]);
    $this->withToken('map-image-token')->get(imageTestUrl($category).'&url=https://example.test&key=evil&size=9999x9999&path=evil')->assertOk();
    Http::assertSent(fn ($request) => $request->url() === $expected);
    Http::assertSentCount(1);
});

test('oversized static URLs fall back without silently truncating the stored route', function () {
    $category = imageTestCategory();
    $route = imageTestRoute();
    $route['path'] = array_map(fn ($i) => [
        'lat' => $i % 2 ? 89.99999 : -89.99999,
        'lng' => $i % 2 ? 179.99999 : -179.99999,
    ], range(0, 999));
    $route['checkpoints'] = array_fill(0, 50, $route['checkpoints'][0]);
    $category->update(['course_map' => $route]);
    $this->getJson('/api/events/'.$category->event_id)->assertOk()
        ->assertJsonPath('data.categories.0.course_map_image', null)
        ->assertJsonCount(1000, 'data.categories.0.course_map.path');
    $this->withToken('map-image-token')->getJson(imageTestUrl($category))->assertStatus(422)
        ->assertJsonPath('reason', 'route_too_large');
    expect($category->fresh()->course_map)->toBe($route);
    Http::assertNothingSent();
});

test('the shared image budget resets at UTC midnight', function () {
    $this->travelTo(now('UTC')->startOfDay()->addHours(23)->addMinutes(59));
    $this->mapUser->update(['api_token_expires_at' => now()->addDay()]);
    config(['services.google_maps.static_daily_limit' => 1]);
    $category = imageTestCategory();
    Http::fake(['maps.googleapis.com/*' => Http::response('', 503)]);
    $url = imageTestUrl($category);
    $this->withToken('map-image-token')->getJson($url)->assertStatus(502);
    $this->withToken('map-image-token')->getJson($url)->assertStatus(429);
    $this->travel(2)->minutes();
    $this->withToken('map-image-token')->getJson($url)->assertStatus(502);
    Http::assertSentCount(2);
});

test('an unavailable budget store fails closed before Google is contacted', function () {
    $category = imageTestCategory();
    // Ignore request throttles here to isolate the provider budget guard.
    $this->withoutMiddleware(ThrottleRequests::class);
    Cache::shouldReceive('lock')->once()->andThrow(new RuntimeException('private cache diagnostic'));
    $this->withToken('map-image-token')->getJson(imageTestUrl($category))->assertStatus(503)
        ->assertJsonPath('reason', 'budget_unavailable')->assertDontSee('private cache');
    Http::assertNothingSent();
});
