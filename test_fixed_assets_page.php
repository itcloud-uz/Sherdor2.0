<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $request = Illuminate\Http\Request::create('/admin/fixed-assets', 'GET');
    $response = Route::dispatch($request);
    
    if ($response->getStatusCode() === 200 || $response->isRedirection()) {
        echo "SUCCESS\n";
    } else {
        echo "FAILED: HTTP status " . $response->getStatusCode() . "\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
