<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require_once __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Building;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

echo "--- Run Self-Healing ---\n";

$allBuildings = Building::with('floors')->get();
foreach ($allBuildings as $b) {
    $ec = (int)($b->entrances_count ?? 1);
    if ($ec < 1) $ec = 1;
    $floorIds = $b->floors->pluck('id')->toArray();
    if (!empty($floorIds)) {
        $count = DB::table('units')
            ->whereIn('floor_id', $floorIds)
            ->where(function($q) use ($ec) {
                $q->where('podyezd', '>', $ec)
                  ->orWhere('podyezd', '<', 1)
                  ->orWhereNull('podyezd');
            })
            ->count();
        
        echo "Building ID: {$b->id}, Name: {$b->name}, Entrances Count: {$ec}\n";
        echo "  Units to clamp: {$count}\n";
        
        if ($count > 0) {
            DB::table('units')
                ->whereIn('floor_id', $floorIds)
                ->where(function($q) use ($ec) {
                    $q->where('podyezd', '>', $ec)
                      ->orWhere('podyezd', '<', 1)
                      ->orWhereNull('podyezd');
                })
                ->update(['podyezd' => $ec]);
            echo "  Clamped successfully.\n";
        }
    }
}
echo "Done.\n";
