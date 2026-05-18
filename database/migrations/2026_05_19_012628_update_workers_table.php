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
        Schema::table('workers', function (Blueprint $table) {
            $table->foreignId('brigade_id')->nullable()->constrained('brigades')->nullOnDelete();
        });

        // Migrate data: For piece_rate workers, we can optionally create a brigade
        $workers = \Illuminate\Support\Facades\DB::table('workers')->where('salary_type', '!=', 'fixed_monthly')->get();
        foreach($workers as $w) {
            if ($w->balance != 0) {
                $brigadeId = \Illuminate\Support\Facades\DB::table('brigades')->insertGetId([
                    'name' => $w->name . ' Brigadasi',
                    'foreman_name' => $w->name,
                    'balance' => $w->balance,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                \Illuminate\Support\Facades\DB::table('workers')->where('id', $w->id)->update([
                    'brigade_id' => $brigadeId,
                    'balance' => 0 // Transfer balance to brigade
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workers', function (Blueprint $table) {
            $table->dropForeign(['brigade_id']);
            $table->dropColumn('brigade_id');
        });
    }
};
