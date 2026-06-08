<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$directories = ['photos', 'blueprints', 'models_3d'];
foreach ($directories as $dir) {
    $path = storage_path('app/public/' . $dir);
    if (!file_exists($path)) {
        mkdir($path, 0755, true);
    }
    $file = $path . '/test_write.txt';
    if (@file_put_contents($file, 'test') !== false) {
        echo "SUCCESS: Wrote to " . $dir . "\n";
        unlink($file);
    } else {
        echo "ERROR: Failed to write to " . $dir . "\n";
    }
}
