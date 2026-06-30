<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create podezes table
        Schema::create('podezes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('buildings')->onDelete('cascade'); // block_id
            $table->string('podez_name');
            $table->integer('podez_number');
            $table->string('podez_type')->default('Staircase'); // Staircase, Elevator, Corridor
            $table->integer('total_floors')->default(1);
            $table->string('status')->default('active');
            $table->timestamps();
        });

        // 2. Add podez_id to floors table
        Schema::table('floors', function (Blueprint $table) {
            $table->foreignId('podez_id')->nullable()->constrained('podezes')->onDelete('cascade');
        });

        // 3. Add podez_id to units table
        Schema::table('units', function (Blueprint $table) {
            $table->foreignId('podez_id')->nullable()->constrained('podezes')->onDelete('cascade');
        });

        // 4. Backfill existing data
        DB::transaction(function () {
            $buildings = DB::table('buildings')->get();

            foreach ($buildings as $building) {
                // If it is object container only (no floors)
                if ($building->total_floors == 0) {
                    continue;
                }

                $entrancesCount = (int) ($building->entrances_count ?? 1);
                if ($entrancesCount < 1) {
                    $entrancesCount = 1;
                }

                // Create podezes for this building
                $podezesMap = [];
                for ($p = 1; $p <= $entrancesCount; $p++) {
                    $podezId = DB::table('podezes')->insertGetId([
                        'building_id' => $building->id,
                        'podez_name' => 'P-' . $p,
                        'podez_number' => $p,
                        'podez_type' => 'Staircase',
                        'total_floors' => $building->total_floors,
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $podezesMap[$p] = $podezId;
                }

                // Fetch floors for this building
                $floors = DB::table('floors')->where('building_id', $building->id)->get();

                foreach ($floors as $floor) {
                    // Find units on this floor
                    $units = DB::table('units')->where('floor_id', $floor->id)->get();

                    // Group units by podyezd
                    $unitsByPodyezd = [];
                    foreach ($units as $unit) {
                        $podyezd = (int) ($unit->podyezd ?? 1);
                        if ($podyezd < 1) $podyezd = 1;
                        if ($podyezd > $entrancesCount) $podyezd = $entrancesCount;
                        $unitsByPodyezd[$podyezd][] = $unit;
                    }

                    // For each podyezd, we need a floor record linked to the corresponding podez
                    $isFirst = true;
                    for ($p = 1; $p <= $entrancesCount; $p++) {
                        $podezId = $podezesMap[$p];
                        $podyezdUnits = $unitsByPodyezd[$p] ?? [];

                        // If it's the first podez, we can reuse the existing floor record
                        if ($isFirst) {
                            DB::table('floors')->where('id', $floor->id)->update([
                                'podez_id' => $podezId,
                            ]);
                            $currentFloorId = $floor->id;
                            $isFirst = false;
                        } else {
                            // Duplicate floor for the subsequent podez
                            $currentFloorId = DB::table('floors')->insertGetId([
                                'building_id' => $building->id,
                                'podez_id' => $podezId,
                                'floor_number' => $floor->floor_number,
                                'blueprint_path' => $floor->blueprint_path,
                                'total_units' => count(array_filter($podyezdUnits, fn($u) => $u->type === 'residential')),
                                'total_commercial_units' => count(array_filter($podyezdUnits, fn($u) => $u->type === 'commercial')),
                                'created_at' => $floor->created_at,
                                'updated_at' => $floor->updated_at,
                            ]);
                        }

                        // Link units to this floor and podez
                        foreach ($podyezdUnits as $unit) {
                            DB::table('units')->where('id', $unit->id)->update([
                                'floor_id' => $currentFloorId,
                                'podez_id' => $podezId,
                            ]);
                        }
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['podez_id']);
            $table->dropColumn('podez_id');
        });

        Schema::table('floors', function (Blueprint $table) {
            $table->dropForeign(['podez_id']);
            $table->dropColumn('podez_id');
        });

        Schema::dropIfExists('podezes');
    }
};
