<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $brigade = \App\Models\Brigade::first();
    if (!$brigade) {
        echo "No brigades found!\n";
        exit;
    }
    
    $controller = app()->make(\App\Http\Controllers\Admin\HrController::class);
    $response = $controller->getBrigadeLedger($brigade);
    echo $response->getContent();
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
