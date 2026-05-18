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
        Schema::table('machineries', function (Blueprint $table) {
            $table->foreignId('machine_owner_id')->nullable()->constrained('machine_owners')->nullOnDelete();
        });

        // Migrate data
        $machineries = \Illuminate\Support\Facades\DB::table('machineries')->get();
        foreach($machineries as $m) {
            if ($m->owner_name) {
                $owner = \Illuminate\Support\Facades\DB::table('machine_owners')->where('name', $m->owner_name)->first();
                if (!$owner) {
                    $ownerId = \Illuminate\Support\Facades\DB::table('machine_owners')->insertGetId([
                        'name' => $m->owner_name,
                        'balance' => $m->balance ?? 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                } else {
                    $ownerId = $owner->id;
                    \Illuminate\Support\Facades\DB::table('machine_owners')->where('id', $ownerId)->increment('balance', $m->balance ?? 0);
                }

                \Illuminate\Support\Facades\DB::table('machineries')->where('id', $m->id)->update(['machine_owner_id' => $ownerId]);
            }
        }

        Schema::table('machineries', function (Blueprint $table) {
            $table->dropColumn(['owner_name', 'balance']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machineries', function (Blueprint $table) {
            $table->string('owner_name')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->dropForeign(['machine_owner_id']);
            $table->dropColumn('machine_owner_id');
        });
    }
};
