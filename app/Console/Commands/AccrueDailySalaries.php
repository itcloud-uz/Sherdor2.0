<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Worker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccrueDailySalaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:accrue-daily-salaries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculates and adds the daily portion of a fixed monthly salary to the balances of operators and guards';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting daily salary accrual...');

        $daysInMonth = Carbon::now()->daysInMonth;
        $today = Carbon::now()->toDateString();
        
        $accruedCount = 0;

        DB::transaction(function () use ($daysInMonth, $today, &$accruedCount) {
            
            // 1. Accrue for Workers (like Guards or any fixed_monthly workers)
            $workers = Worker::where('salary_type', 'fixed_monthly')
                             ->where('base_rate', '>', 0)
                             ->get();

            foreach ($workers as $worker) {
                // Calculate today's portion of the monthly salary
                $dailyRate = $worker->base_rate / $daysInMonth;

                // Check if already accrued today to prevent double counting
                $exists = DB::table('worker_transactions')
                    ->where('worker_id', $worker->id)
                    ->where('type', 'salary_accrual')
                    ->where('date', $today)
                    ->where('description', 'Kunlik ish haqi (oylik maoshdan)')
                    ->exists();

                if (!$exists) {
                    DB::table('worker_transactions')->insert([
                        'worker_id' => $worker->id,
                        'type' => 'salary_accrual',
                        'amount' => $dailyRate,
                        'description' => 'Kunlik ish haqi (oylik maoshdan)',
                        'date' => $today,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $worker->increment('balance', $dailyRate);
                    $accruedCount++;
                }
            }

            // 2. Accrue for System Users (Operators/Prorabs) with a fixed salary
            $users = User::where('salary_rate', '>', 0)->get();

            foreach ($users as $user) {
                $dailyRate = $user->salary_rate / $daysInMonth;

                $exists = DB::table('user_transactions')
                    ->where('user_id', $user->id)
                    ->where('type', 'salary_accrual')
                    ->where('date', $today)
                    ->where('description', 'Kunlik ish haqi (oylik maoshdan)')
                    ->exists();

                if (!$exists) {
                    DB::table('user_transactions')->insert([
                        'user_id' => $user->id,
                        'type' => 'salary_accrual',
                        'amount' => $dailyRate,
                        'description' => 'Kunlik ish haqi (oylik maoshdan)',
                        'date' => $today,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $user->increment('balance', $dailyRate);
                    $accruedCount++;
                }
            }
        });

        $this->info("Successfully accrued daily salary for {$accruedCount} people.");
    }
}
