import re

path = r'd:\Sherdor 2.0\app\Http\Controllers\Admin\HrController.php'
with open(path, 'r', encoding='utf8') as f:
    text = f.read()

php_old = '''                $backdated = $request->input('backdated_salaries', []);
                if (!empty($backdated)) {
                    foreach ($backdated as $entry) {
                        if (empty($entry['month']) || empty($entry['salary'])) continue;
                        $month = $entry['month'];
                        $amount = $entry['salary'];
                        \Illuminate\Support\Facades\DB::table('worker_transactions')->insert([
                            'worker_id' => $worker->id,
                            'type' => 'salary_accrual',
                            'amount' => $amount,
                            'description' => "Maosh ($month) - Qorovul",
                            'date' => $month . '-01',
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $worker->increment('balance', $amount);
                    }
                }'''

php_new = '''                $salaryPeriods = $request->input('salary_periods', []);
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
                }'''

text = text.replace(php_old, php_new)

with open(path, 'w', encoding='utf8') as f:
    f.write(text)
