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
        // 1. Create visitors_and_deliveries table
        Schema::create('visitors_and_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate')->nullable(); // For vehicles
            $table->string('type')->default('visitor'); // visitor, delivery
            $table->string('company_or_name')->nullable(); // Person name or Company name
            $table->text('purpose')->nullable();
            $table->timestamps();
        });

        // 2. Update attendance_logs table
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->string('subject_type')->nullable()->after('id'); // Model class name (e.g., App\Models\Worker)
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->string('proof_image')->nullable()->after('confidence_score'); // Path to snapshot image
            
            // We can drop the old user_id foreign key if it was strict, but it might fail if we don't handle it properly.
            // Assuming it's nullable or just a standard integer, we can keep it for legacy or drop it.
            // For now, let's just make it nullable if it wasn't.
        });
        
        // It's safer to just set old user_id to nullable to avoid dropping foreign keys if they exist, or just drop the column if it's safe.
        // I will drop the column `user_id`
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']); // Assuming there was a foreign key constraint
            $table->dropColumn('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->dropColumn(['subject_type', 'subject_id', 'proof_image']);
        });

        Schema::dropIfExists('visitors_and_deliveries');
    }
};
