<?php

use App\Models\Category;
use App\Models\Event;
use App\Models\SavedPaymentOption;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('DB_URL=');
putenv('SESSION_DRIVER=array');
putenv('CACHE_STORE=array');
putenv('MAIL_MAILER=array');
putenv('QUEUE_CONNECTION=sync');
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$request = Request::create($argv[1]);
$app->instance('request', $request);
$request->setLaravelSession(app('session')->driver());
app('url')->forceRootUrl($argv[1]);
config(['services.google_maps' => ['browser_key' => '', 'static_enabled' => false]]);
// Synthetic validation errors exercise the actual shared error summary.
$errors = new ViewErrorBag;
if (($argv[2] ?? '') === 'errors') {
    $errors->put('default', new MessageBag([
        'title' => 'Enter a title <script>window.syntheticUnsafe=true</script>',
        'categories.0.scheduled_start_time' => 'Enter the category start time.',
        'categories.0.course_map' => 'Upload an image or apply a reviewed course.',
        'payment_choices.0' => 'Choose an available payment option.',
        'review_version' => 'Reload this changed draft before submitting.',
    ]));
}
view()->share('errors', $errors);
auth()->setUser(new User(['role' => User::ROLE_EVENT_MANAGER]));
$request->session()->flashInput([
    'interest_type' => 'Marathon', 'type_details' => ['Marathon' => ['cutoff_time' => '9.5 hours']],
    'venue_name' => 'Synthetic gate', 'venue_area_code' => '0402100000',
    'venue_city_code' => '0402103000', 'venue_barangay_code' => '0402103001',
]);
$event = null;
$categoryRows = null;
if (in_array($argv[2] ?? '', ['saved-banner', 'saved-image', 'saved-course', 'invalid-course', 'saved-empty', 'confirmation-revision'], true)) {
    // The real edit form checks readiness through model queries. Create only
    // an ephemeral in-memory schema; never connect this fixture to a real DB.
    if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
        throw new RuntimeException('The browser fixture requires in-memory SQLite.');
    }
    if ($app->make(Kernel::class)->call('migrate', ['--force' => true]) !== 0) {
        throw new RuntimeException('The in-memory browser fixture schema could not be prepared.');
    }
    $event = new Event(['title' => 'Synthetic saved draft', 'venue' => 'Synthetic venue',
        'banner_image' => 'events/banners/synthetic-existing.png']);
    $event->id = 900001;
    if (($argv[2] ?? '') === 'confirmation-revision') {
        $event->revision_of_id = 900000;
    }
    $event->setRelation('categories', collect())->setRelation('paymentMethods', collect());
    if (($argv[2] ?? '') !== 'saved-banner') {
        $course = ['version' => 1, 'path' => [['lat' => 14.46, 'lng' => 120.96], ['lat' => 14.47, 'lng' => 120.97]], 'checkpoints' => []];
        $category = new Category(['name' => 'Synthetic saved category', 'status' => 'open', 'slot_limit' => 50]);
        $category->id = 900002;
        $category->checkpoint_map_image = ($argv[2] ?? '') === 'saved-image' ? 'categories/maps/synthetic-existing.png' : null;
        $category->course_map = match ($argv[2] ?? '') {
            'saved-course' => $course,
            'invalid-course' => [...$course, 'path' => [$course['path'][0]]],
            default => null,
        };
        $event->setRelation('categories', collect([$category]));
        $categoryRows = [['id' => $category->id, 'category_type' => 'open', 'distance_option' => '5',
            'slot_limit' => 50, 'price_amount' => '0.00', 'price_currency' => 'PHP', 'status' => 'open',
            'course_map' => $category->course_map]];
    }
}
$options = collect();
if (($argv[2] ?? '') === 'payment') {
    $option = new SavedPaymentOption(['label' => 'Synthetic shared account', 'provider' => 'GCash',
        'account_name' => 'Synthetic holder', 'account_number' => '09170000000',
        'is_shared' => true, 'is_enabled' => true, 'version' => 1]);
    $option->id = 900001;
    $options->push($option);
}

// Render the real form and its event-type script without accounts or database writes.
$html = view('admin.events.partials.form', [
    'event' => $event,
    'categoryRows' => $categoryRows,
    'interestTypes' => config('conquer.event_interest_types'),
    'categoryTypes' => ['open' => 'Open', 'group' => 'Group', 'custom' => 'Custom'],
    'distanceOptions' => ['5' => '5 km', 'custom' => 'Custom'],
    'paymentMethods' => [],
    'managers' => collect(),
    'managerSchedules' => [],
    'savedPaymentOptions' => $options,
    'venueLocationsAvailable' => true,
])->render();
echo '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1">';
echo app(Vite::class)(['resources/css/app.css']);
echo '</head><body><form id="test-form" data-event-setup-form>'.$html.'</form></body></html>';
