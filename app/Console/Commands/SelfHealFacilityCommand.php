<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\Floor;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SelfHealFacilityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'facility:self-heal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Self-heal facility data: clamp podyezd values and generate missing floors/units';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting facility self-heal...');

        // 1. Clamp/cap unit podyezd to the building's entrances_count (bulk update per building)
        $allBuildings = Building::with('floors')->get();
        $clampedCount = 0;

        foreach ($allBuildings as $b) {
            $ec = (int) ($b->entrances_count ?? 1);
            if ($ec < 1) $ec = 1;

            $floorIds = $b->floors->pluck('id')->toArray();
            if (!empty($floorIds)) {
                $affected = DB::table('units')
                    ->whereIn('floor_id', $floorIds)
                    ->where(function ($q) use ($ec) {
                        $q->where('podyezd', '>', $ec)
                          ->orWhere('podyezd', '<', 1)
                          ->orWhereNull('podyezd');
                    })
                    ->update(['podyezd' => $ec]);

                $clampedCount += $affected;
            }
        }

        $this->info("Clamped podyezd on {$clampedCount} units across {$allBuildings->count()} buildings.");

        // 2. Auto-generate floors and units for buildings that have total_floors > 0 but 0 floors in DB
        $emptyBuildings = Building::where('total_floors', '>', 0)
            ->whereDoesntHave('floors')
            ->get();

        if ($emptyBuildings->isNotEmpty()) {
            $this->info("Found {$emptyBuildings->count()} building(s) with missing floors. Generating...");

            foreach ($emptyBuildings as $building) {
                $this->generateFloorsAndUnits($building);
                $this->line("  - Generated floors/units for building: {$building->name} (ID: {$building->id})");
            }
        } else {
            $this->info('No buildings with missing floors found.');
        }

        $this->info('Facility self-heal complete.');
        return Command::SUCCESS;
    }

    /**
     * Generate floors and units for a building (simplified version for self-heal).
     */
    private function generateFloorsAndUnits(Building $building): void
    {
        DB::transaction(function () use ($building) {
            $totalFloors = (int) $building->total_floors;
            $basementFloors = (int) ($building->basement_floors ?? 0);
            $shopsPerFloor = (int) ($building->shops_per_floor ?? 0);
            $apartmentsPerFloor = (int) ($building->apartments_per_floor ?? 0);
            $entrancesCount = (int) ($building->entrances_count ?? 1);
            $totalShops = (int) ($building->total_shops ?? 0);
            $totalApartments = (int) ($building->total_apartments ?? 0);

            if ($entrancesCount < 1) $entrancesCount = 1;

            // Build desired floor numbers
            $floorNumbers = [];
            for ($b = -$basementFloors; $b <= -1; $b++) {
                if ($b < 0) $floorNumbers[] = $b;
            }
            $aboveGroundCount = $totalFloors - $basementFloors;
            for ($a = 1; $a <= $aboveGroundCount; $a++) {
                $floorNumbers[] = $a;
            }

            // Count commercial floors (basement floors + floor 1)
            $commercialFloorCount = $basementFloors + 1;
            if ($aboveGroundCount < 1) {
                $commercialFloorCount = $basementFloors;
            }

            // Count residential floors (floor 2 and above)
            $residentialFloorCount = max(0, $aboveGroundCount - 1);

            // Backward compatibility: calculate per-floor values from totals if not set
            if ($shopsPerFloor == 0 && $totalShops > 0 && $commercialFloorCount > 0) {
                $shopsPerFloor = (int) ceil($totalShops / $commercialFloorCount);
            }
            if ($apartmentsPerFloor == 0 && $totalApartments > 0 && $residentialFloorCount > 0) {
                $apartmentsPerFloor = (int) ceil($totalApartments / $residentialFloorCount);
            }

            // Create each desired floor with its units
            foreach ($floorNumbers as $f) {
                $isCommercialFloor = ($f < 0 || $f === 1);
                $isResidentialFloor = ($f >= 2);

                $targetCommercial = $isCommercialFloor ? $shopsPerFloor : 0;
                $targetResidential = $isResidentialFloor ? $apartmentsPerFloor : 0;

                $floor = Floor::create([
                    'building_id' => $building->id,
                    'floor_number' => $f,
                    'total_units' => $targetResidential,
                    'total_commercial_units' => $targetCommercial,
                ]);

                // Create residential units
                $this->createUnitsForFloor($floor, 'residential', $targetResidential, $entrancesCount);

                // Create commercial units
                $this->createUnitsForFloor($floor, 'commercial', $targetCommercial, $entrancesCount);
            }
        });
    }

    /**
     * Create units for a newly created floor.
     */
    private function createUnitsForFloor(Floor $floor, string $type, int $count, int $entrancesCount): void
    {
        if ($count <= 0) return;

        $f = $floor->floor_number;
        $unitsPerEntrance = (int) ceil($count / $entrancesCount);
        if ($unitsPerEntrance < 1) $unitsPerEntrance = 1;

        for ($i = 1; $i <= $count; $i++) {
            $unitNumber = $this->generateUnitNumber($f, $i, $type);
            $podyezd = (int) ceil($i / $unitsPerEntrance);
            if ($podyezd > $entrancesCount) $podyezd = $entrancesCount;

            Unit::create([
                'floor_id' => $floor->id,
                'unit_number' => $unitNumber,
                'type' => $type,
                'area_sqm' => 0,
                'price' => 0,
                'status' => 'available',
                'podyezd' => $podyezd,
            ]);
        }
    }

    /**
     * Generate a unit number string.
     */
    private function generateUnitNumber(int $floorNumber, int $sequence, string $type): string
    {
        $seqStr = str_pad($sequence, 2, '0', STR_PAD_LEFT);

        if ($type === 'commercial') {
            if ($floorNumber < 0) {
                return 'D-' . abs($floorNumber) . $seqStr;
            }
            return 'D' . $floorNumber . $seqStr;
        }

        if ($floorNumber < 0) {
            return 'B' . abs($floorNumber) . $seqStr;
        }
        return 'A' . $floorNumber . $seqStr;
    }
}
