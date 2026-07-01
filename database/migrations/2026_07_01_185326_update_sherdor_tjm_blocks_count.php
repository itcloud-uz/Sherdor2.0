<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update blocks_count to 4 for SHER-DOR TJM building (case-insensitive)
        \DB::table('buildings')
            ->where('name', 'like', '%Sher-dor%')
            ->orWhere('name', 'like', '%Sherdor%')
            ->update(['blocks_count' => 4]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \DB::table('buildings')
            ->where('name', 'like', '%Sher-dor%')
            ->orWhere('name', 'like', '%Sherdor%')
            ->update(['blocks_count' => 1]);
    }
};
