<?php

use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\SavedPaymentOption;
use App\Services\EventPaymentSelection;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array');
putenv('CACHE_STORE=array');
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$origin = $argv[1] ?? 'http://127.0.0.1:9999';
if (! preg_match('~\Ahttp://127\.0\.0\.1:[0-9]+\z~', $origin)) {
    throw new RuntimeException('Fixture origin must be local.');
}
app('url')->forceRootUrl($origin);
config(['app.asset_url' => null]);
$mode = $argv[2] ?? 'saved';
// Unsaved synthetic models only: this fixture never opens a database connection.
$details = ['provider' => 'GCash', 'account_name' => 'Synthetic JJ', 'account_number' => '09170000000',
    'instructions' => 'Synthetic transfer instructions', 'is_enabled' => true];
$copy = new EventPaymentMethod([...$details, 'sort_order' => 0]);
$copy->id = 1;
if ($mode === 'disabled') {
    $copy->is_enabled = false;
}
$event = new Event(['payment_setup_needs_review' => false]);
$event->id = 1;
$event->setRelation('paymentMethods', collect([$copy]));
$savedPaymentOptions = collect([
    [...$details, 'id' => 1, 'label' => 'Synthetic identical option'],
    [...$details, 'id' => 2, 'account_name' => 'Synthetic alternate holder', 'account_number' => '09220000000', 'label' => 'Alternate GCash'],
    [...$details, 'id' => 3, 'provider' => 'Maya', 'label' => 'Synthetic Maya'],
])->map(fn ($attributes) => (new SavedPaymentOption)->forceFill([...$attributes, 'version' => 1]));
$errors = new ViewErrorBag;
if ($mode === 'conflict') {
    session()->flashInput(['payment_selection_submitted' => '1', 'payment_choices' => [EventPaymentSelection::eventToken($copy), 'option:1:1']]);
    $errors->put('default', new MessageBag(['payment_choices' => 'Choose only one payment account per provider for this event.']));
} elseif ($mode === 'empty') {
    session()->flashInput(['payment_selection_submitted' => '1', 'payment_choices' => []]);
} elseif ($mode === 'library') {
    session()->flashInput(['payment_selection_submitted' => '1', 'payment_choices' => ['option:1:1']]);
}
view()->share('errors', $errors);
app('request')->setLaravelSession(session()->driver());
echo '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><form id="event-form">';
echo view('admin.events.partials.payment-selection', compact('event', 'savedPaymentOptions'))->render();
echo '<button type="reset">Reset</button></form></body></html>';
