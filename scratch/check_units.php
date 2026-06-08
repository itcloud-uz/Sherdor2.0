<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require_once __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Unit;

$units = Unit::with('floor.building')->get();
echo "Total units: " . $units->count() . "\n";
foreach ($units as $u) {
    if ($u->floor) {
        echo "Unit ID: {$u->id}, Number: {$u->unit_number}, Floor ID: {$u->floor_id}, Floor Number: {$u->floor->floor_number}, Building ID: {$u->floor->building_id}, Building Name: {$u->floor->building->name}\n";
    } else {
        echo "Unit ID: {$u->id}, Number: {$u->unit_number}, Floor ID: {$u->floor_id} (NO FLOOR!)\n";
    }
}
