<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require_once __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Floor;
use App\Models\Unit;

\Illuminate\Support\Facades\DB::transaction(function() {
    $floor = Floor::find(15); // Floor 1 of Sherdor TJM
    echo "Before deletion:\n";
    echo "Commercial count: " . $floor->units()->where('type', 'commercial')->count() . "\n";
    echo "Commercial units: " . $floor->units()->where('type', 'commercial')->pluck('unit_number')->join(', ') . "\n";

    // Simulating changing total commercial units from 5 to 2
    $totalCommercial = 2;
    $currentCommercialCount = $floor->units()->where('type', 'commercial')->count();

    if ($totalCommercial < $currentCommercialCount) {
        $excessCount = $currentCommercialCount - $totalCommercial;
        $excessIds = $floor->units()
            ->where('type', 'commercial')
            ->orderBy('id', 'desc')
            ->limit($excessCount)
            ->pluck('id');
        
        echo "Excess count: $excessCount\n";
        echo "Excess IDs to delete: " . $excessIds->join(', ') . "\n";
        
        \App\Models\Unit::whereIn('id', $excessIds)->delete();
    }

    $floor->refresh();
    echo "After deletion:\n";
    echo "Commercial count: " . $floor->units()->where('type', 'commercial')->count() . "\n";
    echo "Commercial units: " . $floor->units()->where('type', 'commercial')->pluck('unit_number')->join(', ') . "\n";

    throw new \Exception("Rollback intentionally");
});
