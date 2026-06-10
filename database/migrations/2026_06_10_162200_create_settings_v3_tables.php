<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Lead sources table
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // 2. Warehouses table
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // 3. Scheduled SMS table
        Schema::create('scheduled_sms', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number');
            $table->text('message');
            $table->dateTime('scheduled_at');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('status')->default('pending'); // 'pending', 'sent', 'failed'
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });

        // 4. Alter users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique();
            $table->string('last_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->unsignedBigInteger('building_id')->nullable();
            $table->unsignedBigInteger('warehouse_id')->nullable();

            $table->foreign('building_id')->references('id')->on('buildings')->onDelete('set null');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
        });

        // 5. Seed default values
        $sources = ['Facebook', 'Instagram', 'Telegram', 'Tavsiya', 'Tashqi reklama'];
        foreach ($sources as $source) {
            DB::table('lead_sources')->insertOrIgnore([
                'name' => $source,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $warehouses = ['Asosiy Omborxona', 'Sher-Dor TJM Ombori'];
        foreach ($warehouses as $wh) {
            DB::table('warehouses')->insertOrIgnore([
                'name' => $wh,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 6. Create standard permissions and assign to Super_Admin & Admin
        $permissions = [
            'access dashboard',
            'access reports',
            'access debtors',
            'access transactions',
            'access objects',
            'access clients',
            'access finance',
            'access hr',
            'access settings',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
        }

        // Assign to Super_Admin and Admin roles
        $superAdmin = Role::where('name', 'Super_Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }

        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            $admin->givePermissionTo($permissions);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['building_id']);
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['username', 'last_name', 'middle_name', 'building_id', 'warehouse_id']);
        });

        Schema::dropIfExists('scheduled_sms');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('lead_sources');

        $permissions = [
            'access dashboard',
            'access reports',
            'access debtors',
            'access transactions',
            'access objects',
            'access clients',
            'access finance',
            'access hr',
            'access settings',
        ];
        Permission::whereIn('name', $permissions)->delete();
    }
};
