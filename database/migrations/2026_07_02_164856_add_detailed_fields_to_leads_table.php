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
        Schema::table('leads', function (Blueprint $table) {
            $table->string('phone_2')->nullable();
            $table->string('passport_series')->nullable();
            $table->string('gender')->nullable(); // 'Erkak', 'Ayol'
            $table->date('birth_date')->nullable();
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->string('address')->nullable();
            $table->foreignId('assigned_employee_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['assigned_employee_id']);
            $table->dropColumn([
                'phone_2',
                'passport_series',
                'gender',
                'birth_date',
                'region',
                'district',
                'address',
                'assigned_employee_id'
            ]);
        });
    }
};
