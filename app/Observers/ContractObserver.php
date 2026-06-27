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
                'original_payment_amount' => $contract->total_amount,
                'payment_type' => 'full'
            ]);
            return;
        }

        $startDate = $contract->created_at ? Carbon::parse($contract->created_at) : Carbon::now();
        $schedules = [];

        // 1. Generate down payment schedules if down_payment > 0
        if ($contract->down_payment > 0) {
            $downPaymentTerm = $contract->is_down_payment_split ? (int)($contract->down_payment_split_months ?? 1) : 1;
            $downPaymentMonthly = $contract->down_payment / $downPaymentTerm;
            for ($i = 1; $i <= $downPaymentTerm; $i++) {
                $dueDate = $contract->is_down_payment_split ? $startDate->copy()->addMonths($i) : $startDate->copy();
                $status = $dueDate->isPast() ? 'overdue' : 'pending';
                $schedules[] = [
                    'contract_id' => $contract->id,
                    'expected_amount' => $downPaymentMonthly,
                    'remaining_debt' => $downPaymentMonthly,
                    'paid_amount' => 0,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'status' => $status,
                    'created_at' => $startDate->toDateTimeString(),
                    'updated_at' => $startDate->toDateTimeString(),
                    'payment_type' => 'down_payment'
                ];
            }
        }

        // 2. Generate regular installments
        if ($contract->term_months > 0) {
            $remainingAmount = $contract->total_amount - $contract->down_payment;
            if ($remainingAmount > 0) {
                $monthlyExpectedAmount = $remainingAmount / $contract->term_months;
                
                // If down payment is split, regular installments start after the down payment period
                $downPaymentTermOffset = $contract->is_down_payment_split ? (int)($contract->down_payment_split_months ?? 1) : 0;
                
                for ($i = 1; $i <= $contract->term_months; $i++) {
                    $dueDate = $startDate->copy()->addMonths($downPaymentTermOffset + $i);
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
                        'payment_type' => 'installment'
                    ];
                }
            }
        }

        // 5. Bulk insert for efficiency
        if (count($schedules) > 0) {
            PaymentSchedule::insert($schedules);
        }
    }
}
