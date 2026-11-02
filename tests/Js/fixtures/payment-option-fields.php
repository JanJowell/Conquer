<?php

use App\Models\SavedPaymentOption;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ViewErrorBag;

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array');
putenv('CACHE_STORE=array');
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
view()->share('errors', new ViewErrorBag);
// Synthetic unsaved model only; no real account data or database operations.
$option = new SavedPaymentOption(['provider' => 'PayMongo']);
echo '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><form>';
echo view('admin.payments.options.fields', compact('option'))->render();
echo '</form><script src="/js/payment-option-fields.js"></script></body></html>';
