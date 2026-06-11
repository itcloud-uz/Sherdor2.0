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
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'region')) {
                $table->string('region')->nullable();
            }
            if (!Schema::hasColumn('clients', 'district')) {
                $table->string('district')->nullable();
            }
            if (!Schema::hasColumn('clients', 'phone_2')) {
                $table->string('phone_2')->nullable();
            }
            if (!Schema::hasColumn('clients', 'birth_date')) {
                $table->date('birth_date')->nullable();
            }
            if (!Schema::hasColumn('clients', 'gender')) {
                $table->string('gender')->nullable();
            }
            if (!Schema::hasColumn('clients', 'passport_photo')) {
                $table->string('passport_photo')->nullable();
            }
            if (!Schema::hasColumn('clients', 'source')) {
                $table->string('source')->nullable();
            }
            if (!Schema::hasColumn('clients', 'contract_language')) {
                $table->string('contract_language')->nullable();
            }
            if (!Schema::hasColumn('clients', 'contract_id')) {
                $table->string('contract_id')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'region',
                'district',
                'phone_2',
                'birth_date',
                'gender',
                'passport_photo',
                'source',
                'contract_language',
                'contract_id'
            ]);
        });
    }
};
