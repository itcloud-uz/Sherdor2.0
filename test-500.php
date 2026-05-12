<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$controller = $app->make(\App\Http\Controllers\Admin\HrController::class);

$request = Illuminate\Http\Request::create('/hr/worker', 'POST', [
    'name' => 'Ibragimov Haydar',
    'phone' => '998334150454',
    'role' => 'guard',
    'salary_type' => 'fixed_monthly',
    'base_rate' => 1500000,
    'building_id' => '',
    'floor_id' => '',
    'unit_id' => ''
]);

// We must bind the request to the container so validate() works
$app->instance('request', $request);

try {
    $response = $controller->storeWorker($request);
    echo "SUCCESS\n";
} catch (\Illuminate\Validation\ValidationException $e) {
    echo "VALIDATION ERROR:\n";
    print_r($e->errors());
} catch (\Exception $e) {
    echo "EXCEPTION:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
