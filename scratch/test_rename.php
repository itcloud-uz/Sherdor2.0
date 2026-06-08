<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require_once __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Floor;
use App\Models\Unit;

// Let's run inside a transaction and rollback so we don't mess up the DB
\Illuminate\Support\Facades\DB::transaction(function() {
    $floor = Floor::find(15); // Floor 1 of Sherdor TJM
    if (!$floor) {
        echo "Floor 15 not found!\n";
        return;
    }
    echo "Before update:\n";
    echo "Floor Number: {$floor->floor_number}\n";
    echo "Units: " . $floor->units->pluck('unit_number')->join(', ') . "\n";

    $oldFloorNumber = $floor->floor_number;
    $floor->floor_number = 2; // change to 2
    $floor->save();

    echo "After floor number change saved (Floor Number in object: {$floor->floor_number}):\n";
    // Check if floor number changed, rename existing units
    if ($oldFloorNumber !== $floor->floor_number) {
        $oldResPrefix = $oldFloorNumber < 0 ? 'B' . abs($oldFloorNumber) : 'A' . $oldFloorNumber;
        $newResPrefix = $floor->floor_number < 0 ? 'B' . abs($floor->floor_number) : 'A' . $floor->floor_number;
        
        $oldCommPrefix = 'D' . $oldFloorNumber;
        $newCommPrefix = 'D' . $floor->floor_number;
        
        echo "Prefixes: Res: $oldResPrefix -> $newResPrefix, Comm: $oldCommPrefix -> $newCommPrefix\n";

        foreach ($floor->units as $unit) {
            echo "Checking unit: {$unit->unit_number} (type: {$unit->type})\n";
            if ($unit->type === 'residential') {
                if (str_starts_with($unit->unit_number, $oldResPrefix)) {
                    $oldNum = $unit->unit_number;
                    $unit->unit_number = $newResPrefix . substr($unit->unit_number, strlen($oldResPrefix));
                    $unit->save();
                    echo "  Renamed residential: $oldNum -> {$unit->unit_number}\n";
                } else {
                    echo "  Does not start with $oldResPrefix\n";
                }
            } else if ($unit->type === 'commercial') {
                if (str_starts_with($unit->unit_number, $oldCommPrefix)) {
                    $oldNum = $unit->unit_number;
                    $unit->unit_number = $newCommPrefix . substr($unit->unit_number, strlen($oldCommPrefix));
                    $unit->save();
                    echo "  Renamed commercial: $oldNum -> {$unit->unit_number}\n";
                } else {
                    echo "  Does not start with $oldCommPrefix\n";
                }
            }
        }
    }

    // Refresh and check again
    $floor->refresh();
    echo "After refresh:\n";
    echo "Floor Number: {$floor->floor_number}\n";
    echo "Units: " . $floor->units->pluck('unit_number')->join(', ') . "\n";

    throw new \Exception("Rollback transaction intentionally");
});
