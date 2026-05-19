import re

path = r'd:\Sherdor 2.0\app\Http\Controllers\Admin\HrController.php'
with open(path, 'r', encoding='utf8') as f:
    text = f.read()

php_old = '''        \Illuminate\Support\Facades\DB::transaction(function() use ($worker, $validated, $request) {
            $oldRate = $worker->base_rate;
            $worker->update($validated);
            
            if ($worker->role === 'guard' && $worker->salary_type === 'fixed_monthly') {
                if ((float)$oldRate !== (float)$worker->base_rate) {
                    \App\Models\WorkerSalaryHistory::create([
                        'worker_id' => $worker->id,
                        'salary' => $worker->base_rate,
                        'effective_date' => $request->input('effective_date', now()->toDateString())
                    ]);
                }
            }
        });'''

php_new = '''        \Illuminate\Support\Facades\DB::transaction(function() use ($worker, $validated, $request) {
            $oldRate = $worker->base_rate;
            $worker->update($validated);
            
            if ($worker->role === 'guard' && $worker->salary_type === 'fixed_monthly') {
                if ((float)$oldRate !== (float)$worker->base_rate) {
                    \App\Models\WorkerSalaryHistory::create([
                        'worker_id' => $worker->id,
                        'salary' => $worker->base_rate,
                        'effective_date' => $request->input('effective_date', now()->toDateString())
                    ]);
                }
                
                $salaryPeriods = $request->input('salary_periods', []);
                if (!empty($salaryPeriods)) {
                    foreach ($salaryPeriods as $period) {
                        if (empty($period['start_month']) || empty($period['end_month']) || empty($period['salary'])) continue;
                        
                        $start = \Carbon\Carbon::createFromFormat('Y-m', $period['start_month'])->startOfMonth();
                        $end = \Carbon\Carbon::createFromFormat('Y-m', $period['end_month'])->startOfMonth();
                        $amount = $period['salary'];
                        
                        // Log history for the start of the period
                        \App\Models\WorkerSalaryHistory::create([
                            'worker_id' => $worker->id,
                            'salary' => $amount,
                            'effective_date' => $start->toDateString()
                        ]);

                        $current = $start->copy();
                        while ($current->lte($end)) {
                            $monthStr = $current->format('Y-m');
                            \Illuminate\Support\Facades\DB::table('worker_transactions')->insert([
                                'worker_id' => $worker->id,
                                'type' => 'salary_accrual',
                                'amount' => $amount,
                                'description' => "Maosh ($monthStr) - Qorovul (Davr bo'yicha)",
                                'date' => $current->toDateString(),
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                            $worker->increment('balance', $amount);
                            $current->addMonth();
                        }
                    }
                }
            }
        });'''

if php_old in text:
    text = text.replace(php_old, php_new)
    with open(path, 'w', encoding='utf8') as f:
        f.write(text)
    print('Updated HrController.php successfully.')
else:
    print('Could not find the target string in HrController.php')
