<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Worker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccrueMonthlySalaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:accrue-monthly-salaries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculates and adds the monthly salary to operators and workers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting monthly salary accrual...');

        $today = Carbon::now()->toDateString();
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();
        
        $accruedCount = 0;

        DB::transaction(function () use ($today, $startOfMonth, $endOfMonth, &$accruedCount) {
            
            // 1. Accrue for Workers (like Guards or any fixed_monthly workers)
            $workers = Worker::where('salary_type', 'fixed_monthly')
                             ->where('base_rate', '>', 0)
                             ->get();

            foreach ($workers as $worker) {
                // Find active salary period for today
                $activePeriod = \App\Models\SalaryPeriod::where('employee_type', 'worker')
                    ->where('employee_id', $worker->id)
                    ->whereDate('start_date', '<=', $today)
                    ->orderBy('start_date', 'desc')
                    ->first();

                $salaryAmount = $activePeriod ? $activePeriod->salary : $worker->base_rate;

                // Check if already accrued in this month
                $exists = DB::table('worker_transactions')
                    ->where('worker_id', $worker->id)
                    ->where('type', 'salary_accrual')
                    ->whereBetween('date', [$startOfMonth, $endOfMonth])
                    ->exists();

                if (!$exists) {
                    DB::table('worker_transactions')->insert([
                        'worker_id' => $worker->id,
                        'type' => 'salary_accrual',
                        'amount' => $salaryAmount,
                        'description' => 'Oylik ish haqi (oylik maoshdan)',
                        'date' => $today,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $worker->increment('balance', $salaryAmount);
                    $accruedCount++;
                }
            }

            // 2. Accrue for System Users (Operators/Prorabs) with a fixed salary
            $users = User::where('salary_rate', '>', 0)->get();

            foreach ($users as $user) {
                // Find active salary period for today
                $activePeriod = \App\Models\SalaryPeriod::where('employee_type', 'user')
                    ->where('employee_id', $user->id)
                    ->whereDate('start_date', '<=', $today)
                    ->orderBy('start_date', 'desc')
                    ->first();

                $salaryAmount = $activePeriod ? $activePeriod->salary : $user->salary_rate;

                // Check if already accrued in this month
                $exists = DB::table('user_transactions')
                    ->where('user_id', $user->id)
                    ->where('type', 'salary_accrual')
                    ->whereBetween('date', [$startOfMonth, $endOfMonth])
                    ->exists();

                if (!$exists) {
                    DB::table('user_transactions')->insert([
                        'user_id' => $user->id,
                        'type' => 'salary_accrual',
                        'amount' => $salaryAmount,
                        'description' => 'Oylik ish haqi (oylik maoshdan)',
                        'date' => $today,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $user->increment('balance', $salaryAmount);
                    $accruedCount++;
                }
            }
        });

        $this->info("Successfully accrued monthly salary for {$accruedCount} people.");
    }
}
