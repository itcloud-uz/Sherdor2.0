<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\PaymentSchedule;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Retrieve all payment schedules and update their remaining_debt and status in database
        $schedules = PaymentSchedule::all();
        foreach ($schedules as $schedule) {
            $schedule->remaining_debt = max(0, (float)$schedule->expected_amount - (float)$schedule->paid_amount);
            if ($schedule->status !== 'paid' && new \DateTime($schedule->due_date) < new \DateTime()) {
                $schedule->status = 'overdue';
            }
            $schedule->saveQuietly();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
