<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Building;
use App\Models\Podez;
use App\Models\Floor;
use App\Models\Unit;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function () {
            // Find or get Baraka Residence buildings
            $bA1 = Building::where('name', 'BARAKA RESIDENCE')->where('block_name', 'Blok A-1')->first();
            if ($bA1) {
                $bA1->update([
                    'corpus_name' => 'A1 Tipi',
                    'total_floors' => 16,
                    'basement_floors' => 1,
                    'blocks_count' => 1,
                    'total_apartments' => 105,
                    'total_shops' => 14,
                    'total_parking' => 0,
                    'construction_area' => 5773.50,
                ]);

                $podA1 = Podez::firstOrCreate(
                    ['building_id' => $bA1->id, 'podez_number' => 1],
                    [
                        'podez_name' => "A-1 Yo'lak",
                        'podez_number' => 1,
                        'total_apartments' => 105,
                        'total_commercial' => 14,
                        'total_floors' => 16,
                    ]
                );
                $podA1->update(['total_floors' => 16]);

                $floorsA1 = [];
                for ($f = -1; $f <= 16; $f++) {
                    if ($f === 0) continue;
                    $floorsA1[$f] = Floor::firstOrCreate([
                        'building_id' => $bA1->id,
                        'podez_id' => $podA1->id,
                        'floor_number' => $f,
                    ]);
                }

                // A-1 Basement Shops (-1 floor): 7 shops
                $a1BasementShops = [119.5, 16.9, 27.7, 39.3, 40.6, 40.6, 40.6];
                foreach ($a1BasementShops as $idx => $area) {
                    $shopNo = $idx + 1;
                    Unit::updateOrCreate(
                        ['floor_id' => $floorsA1[-1]->id, 'unit_number' => "A-1-D{$shopNo}"],
                        [
                            'podez_id' => $podA1->id,
                            'type' => 'commercial',
                            'area_sqm' => $area,
                            'price' => $area * 9000000,
                            'status' => 'available',
                            'rooms_count' => 1,
                            'podyezd' => 1,
                        ]
                    );
                }

                // A-1 1st floor Shops: 7 shops
                $a1FirstFloorShops = [54.4, 34.6, 34.6, 57.1, 58.4, 39.5, 39.5];
                foreach ($a1FirstFloorShops as $idx => $area) {
                    $shopNo = $idx + 8;
                    Unit::updateOrCreate(
                        ['floor_id' => $floorsA1[1]->id, 'unit_number' => "A-1-D{$shopNo}"],
                        [
                            'podez_id' => $podA1->id,
                            'type' => 'commercial',
                            'area_sqm' => $area,
                            'price' => $area * 11000000,
                            'status' => 'available',
                            'rooms_count' => 1,
                            'podyezd' => 1,
                        ]
                    );
                }

                // A-1 Apartments: 105 units across floors 2..16 (7 units per floor)
                $aptCounter = 1;
                for ($f = 2; $f <= 16; $f++) {
                    $floorUnits = [
                        ['rooms' => 1, 'area' => 40.59],
                        ['rooms' => 2, 'area' => 58.40],
                        ['rooms' => 2, 'area' => 61.20],
                        ['rooms' => 2, 'area' => 64.50],
                        ['rooms' => 2, 'area' => 65.00],
                        ['rooms' => 2, 'area' => 67.30],
                        ['rooms' => 4, 'area' => 98.20],
                    ];

                    foreach ($floorUnits as $uSpec) {
                        $uName = "A-1-{$aptCounter}";
                        Unit::updateOrCreate(
                            ['floor_id' => $floorsA1[$f]->id, 'unit_number' => $uName],
                            [
                                'podez_id' => $podA1->id,
                                'type' => 'residential',
                                'area_sqm' => $uSpec['area'],
                                'price' => $uSpec['area'] * 7000000,
                                'status' => 'available',
                                'rooms_count' => $uSpec['rooms'],
                                'podyezd' => 1,
                            ]
                        );
                        $aptCounter++;
                    }
                }
            }

            // Set construction area for other Baraka blocks if found
            Building::where('name', 'BARAKA RESIDENCE')->where('block_name', 'Blok A-2')->update(['construction_area' => 6169.50]);
            Building::where('name', 'BARAKA RESIDENCE')->where('block_name', 'Blok B')->update(['construction_area' => 5016.00]);
            Building::where('name', 'BARAKA RESIDENCE')->where('block_name', 'like', '%Yer osti%')->update(['construction_area' => 536.80]);
            Building::where('name', 'BARAKA RESIDENCE')->where('block_name', 'like', '%Yer usti%')->update(['construction_area' => 648.00]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
