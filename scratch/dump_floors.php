<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require_once __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Building;
use App\Models\Floor;
use App\Models\Unit;

$buildings = Building::with('floors.units')->get();
foreach ($buildings as $b) {
    echo "Building: {$b->name} (ID: {$b->id})\n";
    foreach ($b->floors as $f) {
        echo "  Floor: {$f->floor_number} (ID: {$f->id})\n";
        $units = $f->units;
        echo "    Total Units (in model): {$f->total_units}, Total Commercial (in model): {$f->total_commercial_units}\n";
        echo "    Actual units in DB (" . $units->count() . "): " . $units->pluck('unit_number')->join(', ') . "\n";
    }
}
