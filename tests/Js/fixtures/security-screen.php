<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

// Isolated rendering fixture: no real account, authentication, or database writes.
putenv('APP_ENV=testing');
putenv('SESSION_DRIVER=array');
putenv('CACHE_STORE=array');
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$request = Request::create($argv[1].(($argv[2] ?? '') === 'recovery' ? '?recovery=1' : ''));
$app->instance('request', $request);
$request->setLaravelSession(app('session')->driver());
app('url')->forceRootUrl($argv[1]);
view()->share('errors', new ViewErrorBag);
echo view(($argv[2] ?? '') === 'password' ? 'pages.auth.confirm-password' : 'pages.auth.two-factor-challenge')->render();
