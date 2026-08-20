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
            $objectName = 'BARAKA RESIDENCE';

            // 1. BLOK A-1 (A1 Tipi)
            $bA1 = Building::updateOrCreate(
                ['name' => $objectName, 'block_name' => 'Blok A-1'],
                [
                    'corpus_name' => 'A1 Tipi',
                    'total_floors' => 16,
                    'basement_floors' => 1,
                    'blocks_count' => 1,
                    'total_apartments' => 105,
                    'total_shops' => 14,
                    'total_parking' => 0,
                    'city' => 'Samarqand viloyati',
                    'district' => 'Urgut shahri',
                    'mahalla' => 'Do\'stlik MFY',
                    'street' => 'Navoiy ko\'chasi 83-uy',
                ]
            );

            $podA1 = Podez::firstOrCreate(
                ['building_id' => $bA1->id, 'podez_number' => 1],
                [
                    'podez_name' => 'A-1 Yo\'lak',
                    'podez_number' => 1,
                    'total_apartments' => 105,
                    'total_commercial' => 14
                ]
            );

            // Create floors -1 to 16 for A-1
            $floorsA1 = [];
            for ($f = -1; $f <= 16; $f++) {
                if ($f === 0) continue;
                $floorsA1[$f] = Floor::firstOrCreate([
                    'building_id' => $bA1->id,
                    'podez_id' => $podA1->id,
                    'floor_number' => $f,
                ]);
            }

            // A-1 Yerto'la Shops (-1 qavat): 7 shops
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
                        'podyezd' => 1
                    ]
                );
            }

            // A-1 1-qavat Shops: 7 shops
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
                        'podyezd' => 1
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
                            'podyezd' => 1
                        ]
                    );
                    $aptCounter++;
                }
            }


            // 2. BLOK A-2 (A2 Tipi)
            $bA2 = Building::updateOrCreate(
                ['name' => $objectName, 'block_name' => 'Blok A-2'],
                [
                    'corpus_name' => 'A2 Tipi',
                    'total_floors' => 16,
                    'basement_floors' => 1,
                    'blocks_count' => 1,
                    'total_apartments' => 120,
                    'total_shops' => 14,
                    'total_parking' => 0,
                    'city' => 'Samarqand viloyati',
                    'district' => 'Urgut shahri',
                    'mahalla' => 'Do\'stlik MFY',
                    'street' => 'Navoiy ko\'chasi 83-uy',
                ]
            );

            $podA2 = Podez::firstOrCreate(
                ['building_id' => $bA2->id, 'podez_number' => 1],
                [
                    'podez_name' => 'A-2 Yo\'lak',
                    'podez_number' => 1,
                    'total_apartments' => 120,
                    'total_commercial' => 14
                ]
            );

            $floorsA2 = [];
            for ($f = -1; $f <= 16; $f++) {
                if ($f === 0) continue;
                $floorsA2[$f] = Floor::firstOrCreate([
                    'building_id' => $bA2->id,
                    'podez_id' => $podA2->id,
                    'floor_number' => $f,
                ]);
            }

            // A-2 Yerto'la Shops: 7 shops
            $a2BasementShops = [110.0, 25.0, 30.0, 42.0, 45.0, 45.0, 45.0];
            foreach ($a2BasementShops as $idx => $area) {
                $shopNo = $idx + 1;
                Unit::updateOrCreate(
                    ['floor_id' => $floorsA2[-1]->id, 'unit_number' => "A-2-D{$shopNo}"],
                    [
                        'podez_id' => $podA2->id,
                        'type' => 'commercial',
                        'area_sqm' => $area,
                        'price' => $area * 9000000,
                        'status' => 'available',
                        'rooms_count' => 1,
                        'podyezd' => 1
                    ]
                );
            }

            // A-2 1-qavat Shops: 7 shops
            $a2FirstFloorShops = [49.1, 58.4, 54.4, 34.6, 45.0, 45.0, 42.0];
            foreach ($a2FirstFloorShops as $idx => $area) {
                $shopNo = $idx + 8;
                Unit::updateOrCreate(
                    ['floor_id' => $floorsA2[1]->id, 'unit_number' => "A-2-D{$shopNo}"],
                    [
                        'podez_id' => $podA2->id,
                        'type' => 'commercial',
                        'area_sqm' => $area,
                        'price' => $area * 11000000,
                        'status' => 'available',
                        'rooms_count' => 1,
                        'podyezd' => 1
                    ]
                );
            }

            // A-2 Apartments: 120 units (8 units per floor across 15 floors)
            $aptCounter = 1;
            for ($f = 2; $f <= 16; $f++) {
                $floorUnits = [
                    ['rooms' => 1, 'area' => 41.20],
                    ['rooms' => 2, 'area' => 52.10],
                    ['rooms' => 2, 'area' => 54.30],
                    ['rooms' => 2, 'area' => 56.50],
                    ['rooms' => 2, 'area' => 58.00],
                    ['rooms' => 2, 'area' => 60.50],
                    ['rooms' => 2, 'area' => 62.80],
                    ['rooms' => 2, 'area' => 64.90],
                ];

                foreach ($floorUnits as $uSpec) {
                    $uName = "A-2-{$aptCounter}";
                    Unit::updateOrCreate(
                        ['floor_id' => $floorsA2[$f]->id, 'unit_number' => $uName],
                        [
                            'podez_id' => $podA2->id,
                            'type' => 'residential',
                            'area_sqm' => $uSpec['area'],
                            'price' => $uSpec['area'] * 7000000,
                            'status' => 'available',
                            'rooms_count' => $uSpec['rooms'],
                            'podyezd' => 1
                        ]
                    );
                    $aptCounter++;
                }
            }


            // 3. BLOK B (B Tipi)
            $bB = Building::updateOrCreate(
                ['name' => $objectName, 'block_name' => 'Blok B'],
                [
                    'corpus_name' => 'B Tipi',
                    'total_floors' => 16,
                    'basement_floors' => 1,
                    'blocks_count' => 1,
                    'total_apartments' => 90,
                    'total_shops' => 18,
                    'total_parking' => 0,
                    'city' => 'Samarqand viloyati',
                    'district' => 'Urgut shahri',
                    'mahalla' => 'Do\'stlik MFY',
                    'street' => 'Navoiy ko\'chasi 83-uy',
                ]
            );

            $podB = Podez::firstOrCreate(
                ['building_id' => $bB->id, 'podez_number' => 1],
                [
                    'podez_name' => 'B Yo\'lak',
                    'podez_number' => 1,
                    'total_apartments' => 90,
                    'total_commercial' => 18
                ]
            );

            $floorsB = [];
            for ($f = -1; $f <= 16; $f++) {
                if ($f === 0) continue;
                $floorsB[$f] = Floor::firstOrCreate([
                    'building_id' => $bB->id,
                    'podez_id' => $podB->id,
                    'floor_number' => $f,
                ]);
            }

            // B Yerto'la Shops: 7 shops
            $bBasementShops = [26.8, 16.9, 26.8, 26.8, 26.8, 26.8, 16.9];
            foreach ($bBasementShops as $idx => $area) {
                $shopNo = $idx + 1;
                Unit::updateOrCreate(
                    ['floor_id' => $floorsB[-1]->id, 'unit_number' => "B-D{$shopNo}"],
                    [
                        'podez_id' => $podB->id,
                        'type' => 'commercial',
                        'area_sqm' => $area,
                        'price' => $area * 9000000,
                        'status' => 'available',
                        'rooms_count' => 1,
                        'podyezd' => 1
                    ]
                );
            }

            // B 1-qavat Shops: 11 shops
            $bFirstFloorShops = [26.8, 26.8, 26.8, 26.8, 26.8, 26.8, 26.8, 33.1, 67.3, 42.0, 39.9];
            foreach ($bFirstFloorShops as $idx => $area) {
                $shopNo = $idx + 8;
                Unit::updateOrCreate(
                    ['floor_id' => $floorsB[1]->id, 'unit_number' => "B-D{$shopNo}"],
                    [
                        'podez_id' => $podB->id,
                        'type' => 'commercial',
                        'area_sqm' => $area,
                        'price' => $area * 11000000,
                        'status' => 'available',
                        'rooms_count' => 1,
                        'podyezd' => 1
                    ]
                );
            }

            // B Apartments: 90 units (6 units per floor across 15 floors, all 2-room)
            $aptCounter = 1;
            for ($f = 2; $f <= 16; $f++) {
                $floorUnits = [
                    ['rooms' => 2, 'area' => 54.20],
                    ['rooms' => 2, 'area' => 55.40],
                    ['rooms' => 2, 'area' => 55.70],
                    ['rooms' => 2, 'area' => 56.00],
                    ['rooms' => 2, 'area' => 57.50],
                    ['rooms' => 2, 'area' => 58.50],
                ];

                foreach ($floorUnits as $uSpec) {
                    $uName = "B-1-{$aptCounter}";
                    Unit::updateOrCreate(
                        ['floor_id' => $floorsB[$f]->id, 'unit_number' => $uName],
                        [
                            'podez_id' => $podB->id,
                            'type' => 'residential',
                            'area_sqm' => $uSpec['area'],
                            'price' => $uSpec['area'] * 7000000,
                            'status' => 'available',
                            'rooms_count' => $uSpec['rooms'],
                            'podyezd' => 1
                        ]
                    );
                    $aptCounter++;
                }
            }


            // 4. YER OSTI AVTOTURARGOHI (24 Mashina o'rni)
            $bParkUnder = Building::updateOrCreate(
                ['name' => $objectName, 'block_name' => 'Yer osti avtoturargohi'],
                [
                    'corpus_name' => 'Yer osti (-1 qavat)',
                    'total_floors' => 0,
                    'basement_floors' => 1,
                    'blocks_count' => 1,
                    'total_parking' => 24,
                    'city' => 'Samarqand viloyati',
                    'district' => 'Urgut shahri',
                ]
            );
            $podParkUnder = Podez::firstOrCreate(
                ['building_id' => $bParkUnder->id, 'podez_number' => 1],
                ['podez_name' => 'Yer osti parkovka zali', 'podez_number' => 1, 'total_parking' => 24]
            );
            $floorParkUnder = Floor::firstOrCreate(['building_id' => $bParkUnder->id, 'podez_id' => $podParkUnder->id, 'floor_number' => -1]);

            for ($p = 1; $p <= 24; $p++) {
                Unit::updateOrCreate(
                    ['floor_id' => $floorParkUnder->id, 'unit_number' => "P-{$p}"],
                    [
                        'podez_id' => $podParkUnder->id,
                        'type' => 'parking',
                        'area_sqm' => 22.37,
                        'price' => 25000000,
                        'status' => 'available',
                        'podyezd' => 1
                    ]
                );
            }

            // 5. YER USTI AVTOTURARGOHI (36 Mashina o'rni)
            $bParkSurface = Building::updateOrCreate(
                ['name' => $objectName, 'block_name' => 'Yer usti avtoturargohi'],
                [
                    'corpus_name' => 'Hovli va bino atrofi',
                    'total_floors' => 1,
                    'basement_floors' => 0,
                    'blocks_count' => 1,
                    'total_parking' => 36,
                    'city' => 'Samarqand viloyati',
                    'district' => 'Urgut shahri',
                ]
            );
            $podParkSurface = Podez::firstOrCreate(
                ['building_id' => $bParkSurface->id, 'podez_number' => 1],
                ['podez_name' => 'Ochiq yer usti parkovkasi', 'podez_number' => 1, 'total_parking' => 36]
            );
            $floorParkSurface = Floor::firstOrCreate(['building_id' => $bParkSurface->id, 'podez_id' => $podParkSurface->id, 'floor_number' => 1]);

            for ($p = 25; $p <= 60; $p++) {
                Unit::updateOrCreate(
                    ['floor_id' => $floorParkSurface->id, 'unit_number' => "P-{$p}"],
                    [
                        'podez_id' => $podParkSurface->id,
                        'type' => 'parking',
                        'area_sqm' => 18.00,
                        'price' => 20000000,
                        'status' => 'available',
                        'podyezd' => 1
                    ]
                );
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Building::where('name', 'BARAKA RESIDENCE')->delete();
    }
};
