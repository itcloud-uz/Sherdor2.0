<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\CompanyBalance;
use App\Models\Transaction;
use App\Models\LiabilityPayment;
use App\Models\FinancialLiability;

#[Signature('finance:recalculate')]
#[Description('Recalculate the global company balance by completely resetting it and summing up all active transactions')]
class RecalculateFinanceBalance extends Command
{
    public function handle()
    {
        $this->info('Recalculating company balance...');

        // 1. Reset everything to 0
        $balance = CompanyBalance::firstOrCreate([]);
        $balance->total_balance = 0;
        $balance->credit_balance = 0;
        $balance->total_income = 0;
        $balance->total_expense = 0;
        $balance->save();

        // 2. Sum up regular transactions (approved only)
        $transactions = Transaction::where('status', 'approved')->get();
        foreach ($transactions as $t) {
            if ($t->type === 'income') {
                CompanyBalance::addIncome($t->amount, $t->account_type);
            } else {
                CompanyBalance::addExpense($t->amount, $t->account_type);
            }
        }

        // 3. Sum up liabilities (Loans received)
        $liabilities = FinancialLiability::where('status', 'active')->get();
        foreach ($liabilities as $l) {
            if ($l->fund_account) {
                CompanyBalance::addIncome($l->total_amount, $l->fund_account);
            }
        }

        // 4. Sum up liability payments (Loans paid back)
        $payments = LiabilityPayment::where('status', 'paid')->with('financialLiability')->get();
        foreach ($payments as $p) {
            $accType = $p->financialLiability->fund_account ?? 'main';
            CompanyBalance::addExpense($p->amount, $accType);
        }

        $balance->refresh();

        $this->info("Recalculation complete!");
        $this->info("Total Balance: " . number_format($balance->total_balance) . " UZS");
        $this->info("Total Income: " . number_format($balance->total_income) . " UZS");
        $this->info("Total Expense: " . number_format($balance->total_expense) . " UZS");
    }
}
