<?php

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

putenv('APP_ENV=testing');
putenv('SESSION_DRIVER=array');
putenv('CACHE_STORE=array');
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$request = Request::create($argv[1]);
$app->instance('request', $request);
$request->setLaravelSession(app('session')->driver());
if (($argv[2] ?? '') === 'restored') {
    $request->session()->flash('_old_input', [
        'venue_mode' => 'new', 'venue_name' => 'Municipal Hall, Gate 2',
        'venue_area_code' => '0401000000', 'venue_city_code' => '0401029000',
        'venue_barangay_code' => '0401029001',
    ]);
}
app('url')->forceRootUrl($argv[1]);
view()->share('errors', new ViewErrorBag);
$event = null;
if (in_array($argv[2] ?? '', ['legacy', 'structured', 'unavailable'], true)) {
    $event = new Event(['venue' => 'Existing assembly point <img src=x onerror="window.venueXss=true">']);
    $event->id = 99;
}
if (($argv[2] ?? '') === 'structured') {
    $event->venue_id = 11;
    $event->setRelation('savedVenue', new Venue([
        'name' => 'Assembly Hall', 'address' => 'Gate 2',
        'area_code' => '0401000000', 'city_code' => '0401029000', 'barangay_code' => '0401029001',
    ]));
}
echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
echo app(Vite::class)(['resources/css/app.css']);
echo '</head><body><form id="test-form" class="mx-auto max-w-5xl p-4">';
echo view('admin.events.partials.venue', ['event' => $event, 'venueLocationsAvailable' => ($argv[2] ?? '') !== 'unavailable'])->render();
echo '<button type="submit">Save test</button></form></body></html>';
