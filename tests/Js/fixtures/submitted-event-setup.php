<?php

use App\Services\EventRevisionComparison;
use Illuminate\Contracts\Console\Kernel;

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array');
putenv('CACHE_STORE=array');
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
// Synthetic arrays only. No manager/venue lookups or other database operations.
$snapshot = ['event' => ['title' => 'Synthetic pending event', 'venue' => 'Municipal Hall — Kapitangan, Paombong, Bulacan',
    'description' => str_repeat('LongUnbrokenSyntheticDescription', 25), 'interest_type' => 'Marathon',
    'event_date' => '2026-11-04', 'start_time' => '11:47:00', 'type_details' => '{"cutoff_time":"2 hours"}'],
    'categories' => [29 => ['name' => '1K Open', 'distance_km' => '1.00', 'distance_unit' => 'km',
        'requires_medical_certificate' => true, 'price_cents' => 95000, 'price_currency' => 'PHP',
        'course_map' => json_encode(['version' => 1, 'path' => [['lat' => 14, 'lng' => 121], ['lat' => 14.1, 'lng' => 121.1]],
            'checkpoints' => [['lat' => 14, 'lng' => 121, 'label' => 'Start']]])]],
    'paymentMethods' => [8 => ['provider' => 'GCash', 'account_name' => 'Synthetic holder', 'account_number' => '09170000000',
        'instructions' => "Use the reference.\nKeep the receipt.", 'is_enabled' => true]],
    'checkpoints' => [4 => ['name' => 'Water station', 'location' => 'Park gate', 'type' => 'hydration', 'latitude' => 14, 'longitude' => 121]]];
$submittedSetup = app(EventRevisionComparison::class)->submittedSetup($snapshot);
$manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
$css = '/build/'.$manifest['resources/css/app.css']['file'];
echo '<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="'.e($css).'"></head><body><main class="mx-auto max-w-5xl p-4"><section class="rounded-xl border border-slate-200 p-4"><h1>Pending Review</h1><details data-complete-setup><summary>Complete submitted setup, routes and payment options</summary>';
echo view('admin.events.partials.submitted-setup', compact('submittedSetup'))->render();
echo '</details></section></main></body></html>';
