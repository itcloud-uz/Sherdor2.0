<?php

namespace App\Observers;

use App\Models\Contract;
use App\Models\PaymentSchedule;
use Carbon\Carbon;

class ContractObserver
{
    /**
     * Handle the Contract "created" event.
     */
    public function created(Contract $contract): void
    {
        if ($contract->payment_type === 'full') {
            PaymentSchedule::create([
                'contract_id' => $contract->id,
                'expected_amount' => $contract->total_amount,
                'remaining_debt' => $contract->total_amount,
                'paid_amount' => 0,
                'due_date' => $contract->created_at ? Carbon::parse($contract->created_at)->format('Y-m-d') : Carbon::now()->format('Y-m-d'),
                'status' => 'pending',
                'original_payment_amount' => $contract->total_amount
            ]);
            return;
        }

        // 1. Calculate the remaining amount after down payment
        $remainingAmount = $contract->total_amount - $contract->down_payment;
        
        // 2. Ensure term_months is valid
        if ($contract->term_months <= 0) {
            return;
        }

        // 3. Calculate the monthly expected amount
        $monthlyExpectedAmount = $remainingAmount / $contract->term_months;

        // 4. Generate the schedules
        $schedules = [];
        $startDate = $contract->created_at ? Carbon::parse($contract->created_at) : Carbon::now();

        for ($i = 1; $i <= $contract->term_months; $i++) {
            $dueDate = $startDate->copy()->addMonths($i);
            $status = $dueDate->isPast() ? 'overdue' : 'pending';
            
            $schedules[] = [
                'contract_id' => $contract->id,
                'expected_amount' => $monthlyExpectedAmount,
                'remaining_debt' => $monthlyExpectedAmount,
                'paid_amount' => 0,
                'due_date' => $dueDate->format('Y-m-d'),
                'status' => $status,
                'created_at' => $startDate->toDateTimeString(),
                'updated_at' => $startDate->toDateTimeString(),
            ];
        }

        // 5. Bulk insert for efficiency
        PaymentSchedule::insert($schedules);
    }
}
