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
        Schema::table('transaction_categories', function (Blueprint $table) {
            $table->string('target_type')->nullable()->after('type');
            $table->boolean('is_active')->default(true)->after('target_type');
        });

        // Seed existing categories with proper target_types based on previous logic
        $categories = DB::table('transaction_categories')->get();
        foreach ($categories as $cat) {
            $name = strtolower($cat->name);
            $targetType = null;

            if (str_contains($name, 'brigada') || str_contains($name, 'yollanma')) {
                $targetType = 'brigade';
            } elseif (str_contains($name, 'texnika') || str_contains($name, 'arendator')) {
                $targetType = 'machine_owner';
            } elseif (str_contains($name, 'oylik') || str_contains($name, 'avans') || str_contains($name, 'ishchi')) {
                $targetType = 'worker';
            } elseif (str_contains($name, 'ta\'minotchi') || str_contains($name, 'qurilish material') || str_contains($name, 'material')) {
                $targetType = 'supplier';
            } elseif (str_contains($name, 'mijoz') || str_contains($name, 'client') || str_contains($name, "boshlang'ich to'lov") || str_contains($name, "boshlang'ich to’lov")) {
                $targetType = 'client';
            } elseif (str_contains($name, 'ta\'sischi') || str_contains($name, 'hamkor') || str_contains($name, 'partner') || str_contains($name, 'sarmoya') || str_contains($name, 'investitsiya')) {
                $targetType = 'partner';
            } elseif (str_contains($name, 'asosiy vosita')) {
                $targetType = 'fixed_asset';
            }

            if ($targetType) {
                DB::table('transaction_categories')->where('id', $cat->id)->update(['target_type' => $targetType]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_categories', function (Blueprint $table) {
            $table->dropColumn('target_type');
            $table->dropColumn('is_active');
        });
    }
};
