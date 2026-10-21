<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

// Render the actual shared Blade editor without a server, account, or database.
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['services.google_maps.browser_key' => 'browser-test-key-not-a-real-credential']);
app('url')->forceRootUrl($argv[1]);
view()->share('errors', new ViewErrorBag);

echo Blade::render(<<<'BLADE'
<!doctype html><html><head><meta charset="utf-8"><title>Course editor test</title></head><body>
<form id="event-form">
    @include('admin.categories.partials.course-map-editor', ['name' => 'categories[0][course_map]', 'value' => null])
    <template id="category-template">
        @include('admin.categories.partials.course-map-editor', ['name' => 'categories[__INDEX__][course_map]', 'value' => null])
    </template>
</form>
@stack('scripts')
</body></html>
BLADE);
