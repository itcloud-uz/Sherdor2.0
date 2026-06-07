<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->integer('podyezd')->nullable();
        });

        // Auto-populate the podyezd field for existing units based on sequential distribution
        $buildings = \App\Models\Building::with(['floors.units'])->get();
        foreach ($buildings as $building) {
            $entrancesCount = (int) ($building->entrances_count ?? 1);
            if ($entrancesCount < 1) {
                $entrancesCount = 1;
            }

            foreach ($building->floors as $floor) {
                // Distribute residential units
                $residentialUnits = $floor->units->where('type', 'residential')->values();
                $resCount = $residentialUnits->count();
                if ($resCount > 0) {
                    $unitsPerEntrance = (int) ceil($resCount / $entrancesCount);
                    if ($unitsPerEntrance < 1) $unitsPerEntrance = 1;
                    foreach ($residentialUnits as $index => $unit) {
                        $podyezd = (int) ceil(($index + 1) / $unitsPerEntrance);
                        if ($podyezd > $entrancesCount) {
                            $podyezd = $entrancesCount;
                        }
                        $unit->update(['podyezd' => $podyezd]);
                    }
                }

                // Distribute commercial units
                $commercialUnits = $floor->units->where('type', 'commercial')->values();
                $commCount = $commercialUnits->count();
                if ($commCount > 0) {
                    $unitsPerEntrance = (int) ceil($commCount / $entrancesCount);
                    if ($unitsPerEntrance < 1) $unitsPerEntrance = 1;
                    foreach ($commercialUnits as $index => $unit) {
                        $podyezd = (int) ceil(($index + 1) / $unitsPerEntrance);
                        if ($podyezd > $entrancesCount) {
                            $podyezd = $entrancesCount;
                        }
                        $unit->update(['podyezd' => $podyezd]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('podyezd');
        });
    }
};
