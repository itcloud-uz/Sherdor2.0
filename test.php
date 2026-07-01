<?php
require 'd:/Sherdor 2.0/vendor/autoload.php';
$app = require_once 'd:/Sherdor 2.0/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $req = new \Illuminate\Http\Request();
    $req->merge([
        'name' => 'sea2',
        'is_object_only' => true,
        'blocks_count' => 1,
        'entrances_count' => 1,
        'total_floors' => 0,
        'total_apartments' => 0,
        'total_shops' => 0,
        'shops_per_floor' => 0,
        'apartments_per_floor' => 0,
        'estimated_budget' => 0,
        'basement_floors' => 0
    ]);
    $req->setMethod('POST');
    $ctrl = new \App\Http\Controllers\Admin\FacilityController();
    $res = $ctrl->storeBuilding($req);
    echo 'Success: ' . $res->getStatusCode() . ' -> URL: ' . $res->getTargetUrl();
} catch (\Illuminate\Validation\ValidationException $e) {
    echo 'Validation Error: ';
    print_r($e->errors());
} catch (\Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
