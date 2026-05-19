<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $validated = [
        'type' => 'worker',
        'worker_id' => 1,
        'machinery_id' => null,
        'building_id' => 1,
        'floor_id' => null,
        'unit_id' => null,
        'hours_worked' => 0,
        'volume_done' => 0,
        'measurement_unit' => 'kun',
        'work_category' => null,
        'date' => '2026-05-19',
        'rate' => 250000,
        'calculated_cost' => 0,
        'deductions' => 0.0,
        'status' => 'unpaid'
    ];
    \App\Models\WorkLog::create($validated);
    echo "Success\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
