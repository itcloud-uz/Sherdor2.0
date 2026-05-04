<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('mahalla')->nullable();
            $table->string('street')->nullable();
            $table->integer('blocks_count')->default(1);
            $table->integer('entrances_count')->default(1);
            $table->integer('total_apartments')->default(0);
            $table->integer('total_shops')->default(0);
            $table->string('blueprint_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn([
                'city', 'district', 'mahalla', 'street', 
                'blocks_count', 'entrances_count', 
                'total_apartments', 'total_shops', 'blueprint_path'
            ]);
        });
    }
};
