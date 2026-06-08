<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require_once __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Floor;
use App\Models\Unit;

// Let's create a dummy floor and units for testing
\Illuminate\Support\Facades\DB::transaction(function() {
    $floor = Floor::create([
        'building_id' => 1,
        'floor_number' => 1,
        'total_units' => 4,
        'total_commercial_units' => 3,
    ]);

    // Create 4 residential and 3 commercial units
    for ($i = 1; $i <= 4; $i++) {
        Unit::create([
            'floor_id' => $floor->id,
            'unit_number' => "A10" . $i,
            'type' => 'residential',
            'area_sqm' => 0,
            'price' => 0,
            'status' => 'available',
        ]);
    }
    for ($i = 1; $i <= 3; $i++) {
        Unit::create([
            'floor_id' => $floor->id,
            'unit_number' => "D10" . $i,
            'type' => 'commercial',
            'area_sqm' => 0,
            'price' => 0,
            'status' => 'available',
        ]);
    }

    echo "Initial Floor:\n";
    echo "Floor ID: {$floor->id}, Floor Number: {$floor->floor_number}, Total Res: {$floor->total_units}, Total Comm: {$floor->total_commercial_units}\n";
    echo "Units in DB: " . Unit::where('floor_id', $floor->id)->pluck('unit_number')->join(', ') . "\n\n";

    // Now simulate calling updateFloor controller action
    // We want to edit Floor to: floor_number = 2, total_units = 5, total_commercial_units = 0
    $request = \Illuminate\Http\Request::create(
        "/admin/facility/floors/{$floor->id}",
        "POST",
        [
            '_method' => 'PUT',
            'floor_number' => 2,
            'total_units' => 5,
            'total_commercial_units' => 0,
        ]
    );

    // Bind request to container
    app()->instance('request', $request);

    // Call updateFloor directly
    $controller = app(\App\Http\Controllers\Admin\FacilityController::class);
    $response = $controller->updateFloor($request, $floor->id);

    // Reload floor and units from database
    $floor->refresh();
    echo "After updateFloor call:\n";
    echo "Floor ID: {$floor->id}, Floor Number: {$floor->floor_number}, Total Res: {$floor->total_units}, Total Comm: {$floor->total_commercial_units}\n";
    
    $units = Unit::where('floor_id', $floor->id)->get();
    echo "Units in DB (" . $units->count() . "): " . $units->pluck('unit_number')->join(', ') . "\n";
    foreach ($units as $u) {
        echo "  Unit: {$u->unit_number} (Type: {$u->type})\n";
    }

    throw new \Exception("Rollback intentionally");
});
