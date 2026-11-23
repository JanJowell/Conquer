<?php

use App\Models\Event;
use App\Services\EventCleanup;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

$origin = $argv[1] ?? '';
$cleanupAction = $argv[2] ?? '';
$work = realpath($argv[3] ?? '');
$private = realpath(dirname(__DIR__, 3).'/storage/app/private');
if (! preg_match('~\Ahttp://127\.0\.0\.1:[0-9]+\z~', $origin)
    || ! in_array($cleanupAction, ['delete', 'archive', 'restore', 'cancel'], true)
    || ! $work || ! $private || ! str_starts_with($work, $private.DIRECTORY_SEPARATOR)
    || ! preg_match('~[/\\\\](?:event-cleanup-202611(?:15|18)|cancellation-ui-20261120)-[a-f0-9]{32}[/\\\\]application\z~', $work)
    || (is_file($work.'/.env') && hash_file('sha256', $work.'/.env') !== '5ca88707fb17a5bf51ad089fc71d8b2270c22c43ad8b7738dcf9fe748d85ea3d')) {
    throw new RuntimeException('An isolated cleanup application and loopback origin are required.');
}
foreach (['APP_ENV' => 'testing', 'APP_BASE_PATH' => $work, 'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'DATABASE_URL' => '',
    'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'VIEW_COMPILED_PATH' => $work.'/storage/framework/views',
    'APP_CONFIG_CACHE' => $work.'/bootstrap/cache/unused-browser-config.php', 'LOG_CHANNEL' => 'null'] as $key => $value) {
    putenv($key.'='.$value);
}
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
// Current source, but a synthetic base/config/cache and loopback-only routes.
// No copying dependencies or booting the real application's environment.
$app = Application::configure(basePath: $work)->withRouting(using: function () {
    foreach (['delete' => 'cleanup.destroy', 'archive' => 'archive', 'restore' => 'restore', 'cancel' => 'cancel'] as $action => $name) {
        Route::post('/admin/events/{event}/'.($action === 'delete' ? 'cleanup' : $action), fn () => abort(404))->name('admin.events.'.$name);
    }
})->create();
$app->make(Kernel::class)->bootstrap();
Http::preventStrayRequests();
app('url')->forceRootUrl($origin);
config(['app.asset_url' => null, 'session.driver' => 'array', 'view.paths' => [$root.'/resources/views']]);
$event = (new Event)->forceFill(['id' => 7, 'title' => 'Synthetic <event> & title', 'slug' => 'synthetic-cleanup',
    'review_status' => 'draft', 'status' => 'draft', 'review_version' => 0]);
$event->syncOriginal(); // Unsaved synthetic model: rendering opens no database connection.
$cleanup = app(EventCleanup::class);
echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Local cleanup confirmation test</title>';
echo '<style>body{font:16px system-ui}dialog{max-width:32rem;max-height:90vh;overflow:auto}button,input{margin:8px}</style>';
echo '<button id="opener" data-event-cleanup-open="'.($cleanupAction === 'cancel' ? 'event-cancel-7' : 'event-cleanup-7').'">Open confirmation</button>';
echo view($cleanupAction === 'cancel' ? 'admin.events.partials.cancellation-confirmation' : 'admin.events.partials.cleanup-confirmation', compact('event', 'cleanup', 'cleanupAction'))->render();
echo '<script src="/js/event-cleanup.js" defer></script></html>';
