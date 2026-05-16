<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Delete old categories
        DB::table('transaction_categories')
            ->whereIn('name', ['Oylik maoshlar', 'Ijara'])
            ->delete();

        // Add the new category if it doesn't exist
        $exists = DB::table('transaction_categories')
            ->where('name', 'Asosiy vositalar')
            ->exists();

        if (!$exists) {
            DB::table('transaction_categories')->insert([
                'name' => 'Asosiy vositalar',
                'type' => 'expense',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('transaction_categories')
            ->where('name', 'Asosiy vositalar')
            ->delete();

        DB::table('transaction_categories')->insert([
            ['name' => 'Oylik maoshlar', 'type' => 'expense', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ijara', 'type' => 'expense', 'created_at' => now(), 'updated_at' => now()]
        ]);
    }
};
