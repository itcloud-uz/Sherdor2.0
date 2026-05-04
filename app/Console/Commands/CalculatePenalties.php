<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use App\Models\Contract;
use App\Models\PaymentSchedule;
use Illuminate\Support\Carbon;

#[Signature('erp:calculate-penalties')]
#[Description('Calculates rolling daily penalties for overdue contracts')]
class CalculatePenalties extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting penalty calculations...');
        $today = Carbon::today();

        // Get all unpaid schedules where due_date is in the past
        $overdueSchedules = PaymentSchedule::with('contract')
            ->where('status', '!=', 'paid')
            ->whereDate('due_date', '<', $today)
            ->get();

        $processedCount = 0;

        foreach ($overdueSchedules as $schedule) {
            $contract = $schedule->contract;
            if (!$contract) continue;

            $remainingDebt = max(0, $schedule->expected_amount - $schedule->paid_amount);
            $schedule->remaining_debt = $remainingDebt;

            if ($remainingDebt > 0) {
                // Calculate 1 day of penalty based on remaining debt and contract's daily penalty rate
                $dailyRate = $contract->daily_penalty_rate / 100;
                $dailyCharge = $remainingDebt * $dailyRate;

                // Update schedule penalty
                $schedule->penalty_for_this_month += $dailyCharge;
                
                // Update contract total accumulated penalty
                $contract->total_penalty_accumulated += $dailyCharge;
                $contract->save();
            }

            $schedule->save();
            $processedCount++;
        }

        $this->info("Completed. Processed {$processedCount} overdue schedules.");
    }
}
