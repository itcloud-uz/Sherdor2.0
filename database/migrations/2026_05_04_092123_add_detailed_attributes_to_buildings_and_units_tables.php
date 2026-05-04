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
        Schema::table('buildings', function (Blueprint $table) {
            $table->string('block_name')->nullable()->after('total_floors');
            $table->string('corpus_name')->nullable()->after('block_name');
            $table->decimal('length_m', 10, 2)->nullable()->after('blueprint_path');
            $table->decimal('width_m', 10, 2)->nullable()->after('length_m');
            $table->decimal('height_m', 10, 2)->nullable()->after('width_m');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->integer('rooms_count')->default(1)->after('type');
            $table->integer('kitchens_count')->default(1)->after('rooms_count');
            $table->integer('bathrooms_count')->default(1)->after('kitchens_count');
            $table->integer('toilets_count')->default(1)->after('bathrooms_count');
            $table->string('orientation')->nullable()->after('toilets_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['rooms_count', 'kitchens_count', 'bathrooms_count', 'toilets_count', 'orientation']);
        });

        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn(['block_name', 'corpus_name', 'length_m', 'width_m', 'height_m']);
        });
    }
};
