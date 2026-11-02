<?php

use App\Models\User;
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
app('url')->forceRootUrl($argv[1]);
config(['services.google_maps' => ['browser_key' => '', 'static_enabled' => false]]);
view()->share('errors', new ViewErrorBag);
auth()->setUser(new User(['role' => User::ROLE_SUPER_ADMIN]));
$request->session()->flashInput(['interest_type' => 'Marathon', 'type_details' => ['Marathon' => ['cutoff_time' => '9.5 hours']]]);

// Render the real form and its event-type script without accounts or database writes.
$html = view('admin.events.partials.form', [
    'event' => null,
    'interestTypes' => config('conquer.event_interest_types'),
    'categoryTypes' => ['open' => 'Open'],
    'distanceOptions' => ['5' => '5 km'],
    'paymentMethods' => [],
    'managers' => collect(),
    'managerSchedules' => [],
])->render();
echo '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">';
echo app(Vite::class)(['resources/css/app.css']);
echo '</head><body><form id="test-form">'.$html.'</form></body></html>';
