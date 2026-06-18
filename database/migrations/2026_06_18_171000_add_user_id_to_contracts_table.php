<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('contracts', 'user_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->after('client_id');
            });
        }

        // Seed the 'Sotuvchi' role
        Role::firstOrCreate(['name' => 'Sotuvchi']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('contracts', 'user_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
    }
};
