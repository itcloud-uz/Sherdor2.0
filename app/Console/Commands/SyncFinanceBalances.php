<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\CompanyBalance;
use App\Models\Partner;

class SyncFinanceBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinxronizatsiya va Balanslarni qayta hisoblash (tranzaksiyalar asosida)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Hozirgi balansni hisoblash boshlandi...");

        // 1. Reset all balances to 0
        CompanyBalance::truncate();
        $balance = CompanyBalance::create([
            'total_balance' => 0,
            'credit_balance' => 0,
            'total_income' => 0,
            'total_expense' => 0
        ]);

        // Reset partners
        Partner::query()->update(['accountable_balance' => 0]);

        // 2. Iterate over all APPROVED transactions and recalculate
        $transactions = Transaction::where('status', 'approved')->get();

        foreach ($transactions as $t) {
            if ($t->type === 'income') {
                $balance->total_income += $t->amount;
                if ($t->account_type === 'credit') {
                    $balance->credit_balance += $t->amount;
                } else {
                    $balance->total_balance += $t->amount;
                }
                
                if ($t->partner_id && $t->category && $t->category->name !== 'Partner Advance') {
                    $p = Partner::find($t->partner_id);
                    if ($p) {
                        $p->accountable_balance += $t->amount;
                        $p->save();
                    }
                }
            } else {
                $balance->total_expense += $t->amount;
                if ($t->account_type === 'credit') {
                    $balance->credit_balance -= $t->amount;
                } else {
                    $balance->total_balance -= $t->amount;
                }
                
                if ($t->partner_id && $t->category && $t->category->name === 'Partner Advance') {
                    $p = Partner::find($t->partner_id);
                    if ($p) {
                        $p->accountable_balance -= $t->amount;
                        $p->save();
                    }
                }
                
                // Fixed Asset Splits
                if ($t->fixed_asset_id) {
                    $partners = Partner::all();
                    if ($partners->count() > 0) {
                        $splitAmount = $t->amount / $partners->count();
                        foreach ($partners as $p) {
                            $p->accountable_balance -= $splitAmount;
                            $p->save();
                        }
                    }
                }
            }
        }

        $balance->save();

        $this->info("Balans qayta hisoblandi va to'g'irlandi!");
        $this->info("Umumiy balans (Kassa): " . number_format($balance->total_balance, 2) . " so'm");
    }
}
