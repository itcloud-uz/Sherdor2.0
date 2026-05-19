<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Worker;
use App\Models\Machinery;
use App\Models\WorkLog;
use App\Models\Payroll;
use App\Models\Building;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class HrController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/HR/Index', [
            'users' => \App\Models\User::with('roles')->get(),
            'attendanceLogs' => \App\Models\AttendanceLog::with('subject')->latest()->take(100)->get(),
            'workers' => Worker::with(['brigade', 'salaryHistories'])->get(),
            'brigades' => \App\Models\Brigade::with('workers')->get(),
            'machineries' => Machinery::all(),
            'machineOwners' => \App\Models\MachineOwner::with('machineries')->get(),
            'buildings' => Building::with('floors.units')->get(),
            'unpaidLogs' => WorkLog::with(['worker', 'machinery', 'building', 'floor', 'unit'])->where('status', 'unpaid')->latest()->limit(500)->get(),
            'unpaidTotal' => WorkLog::where('status', 'unpaid')->sum('calculated_cost'),
            'payrolls' => Payroll::with('transaction')->latest()->limit(500)->get()
        ]);
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|string|exists:roles,name',
            'pinfl' => 'nullable|string',
            'passport' => 'nullable|string',
            'salary_rate' => 'nullable|numeric'
        ]);

        $user = \App\Models\User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'pinfl' => $validated['pinfl'],
            'passport' => $validated['passport'],
            'salary_rate' => $validated['salary_rate'],
        ]);

        $user->assignRole($validated['role']);

        return redirect()->back()->with('message', 'User created and role assigned.');
    }

    public function storeWorker(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'phone' => 'nullable|string',
            'role' => 'required|in:office,builder,driver,guard',
            'salary_type' => 'required|in:fixed_monthly,piece_rate,hourly',
            'base_rate' => 'required|numeric|min:0',
            'building_id' => 'nullable|exists:buildings,id',
            'floor_id' => 'nullable|exists:floors,id',
            'unit_id' => 'nullable|exists:units,id',
            'brigade_id' => 'nullable|exists:brigades,id'
        ]);

        $validated['type'] = $validated['salary_type'] === 'fixed_monthly' ? 'fixed' : 'piece_rate';
        $validated['rate'] = $validated['base_rate'];

        $validated['building_id'] = empty($validated['building_id']) ? null : $validated['building_id'];
        $validated['floor_id'] = empty($validated['floor_id']) ? null : $validated['floor_id'];
        $validated['unit_id'] = empty($validated['unit_id']) ? null : $validated['unit_id'];
        $validated['brigade_id'] = empty($validated['brigade_id']) ? null : $validated['brigade_id'];

        \Illuminate\Support\Facades\DB::transaction(function() use ($validated, $request) {
            $worker = Worker::create($validated);
            
            if ($worker->role === 'guard' && $worker->salary_type === 'fixed_monthly') {
                $startDate = $request->input('start_date', now()->toDateString());
                \App\Models\WorkerSalaryHistory::create([
                    'worker_id' => $worker->id,
                    'salary' => $worker->base_rate,
                    'effective_date' => $startDate
                ]);

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
        });

        return redirect()->back()->with('message', 'Worker added.');
    }

    public function storeMachineOwner(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
        ]);
        \App\Models\MachineOwner::create($validated);
        return redirect()->back()->with('message', 'Texnika egasi qo\'shildi.');
    }

    public function updateMachineOwner(Request $request, \App\Models\MachineOwner $machineOwner)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
        ]);
        $machineOwner->update($validated);
        return redirect()->back()->with('message', 'Texnika egasi yangilandi.');
    }

    public function deleteMachineOwner(\App\Models\MachineOwner $machineOwner)
    {
        if ($machineOwner->balance != 0) {
            return redirect()->back()->with('error', 'Balansda pul qoldig\'i mavjud bo\'lgan egasini o\'chirib bo\'lmaydi.');
        }
        $machineOwner->delete();
        return redirect()->back()->with('message', 'Texnika egasi o\'chirildi.');
    }

    public function storeBrigade(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'foreman_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
        ]);
        \App\Models\Brigade::create($validated);
        return redirect()->back()->with('message', 'Brigada qo\'shildi.');
    }

    public function updateBrigade(Request $request, \App\Models\Brigade $brigade)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'foreman_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
        ]);
        $brigade->update($validated);
        return redirect()->back()->with('message', 'Brigada yangilandi.');
    }

    public function deleteBrigade(\App\Models\Brigade $brigade)
    {
        if ($brigade->balance != 0) {
            return redirect()->back()->with('error', 'Balansda pul qoldig\'i mavjud bo\'lgan brigadani o\'chirib bo\'lmaydi.');
        }
        $brigade->delete();
        return redirect()->back()->with('message', 'Brigada o\'chirildi.');
    }

    public function storeMachinery(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
                        'machine_owner_id' => 'required|exists:machine_owners,id',
            'driver_name' => 'nullable|string',
            'machinery_type' => 'nullable|string',
            'payment_type' => 'required|in:hourly,volume',
            'license_plate' => 'nullable|string',
            'cost_per_hour' => 'required|numeric|min:0'
        ]);

        Machinery::create($validated);
        return redirect()->back()->with('message', 'Machinery added.');
    }

    public function updateMachinery(Request $request, Machinery $machinery)
    {
        $validated = $request->validate([
            'name' => 'required|string',
                        'machine_owner_id' => 'required|exists:machine_owners,id',
            'driver_name' => 'nullable|string',
            'machinery_type' => 'nullable|string',
            'payment_type' => 'required|in:hourly,volume',
            'license_plate' => 'nullable|string',
            'cost_per_hour' => 'required|numeric|min:0'
        ]);

        $machinery->update($validated);
        return redirect()->back()->with('message', 'Texnika yangilandi.');
    }

    public function deleteMachinery(Machinery $machinery)
    {
        $machinery->delete();
        return redirect()->back()->with('message', 'Texnika o\'chirildi.');
    }

    public function getOwnerLedger($ownerId)
    {
        $owner = \App\Models\MachineOwner::with('machineries')->findOrFail($ownerId);
        $machineryIds = $owner->machineries->pluck('id');

        $logs = WorkLog::with(['building', 'machinery'])
            ->whereIn('machinery_id', $machineryIds)
            ->orderBy('date', 'desc')
            ->get();

        $transactions = \App\Models\MachineryTransaction::with('machinery')
            ->whereIn('machinery_id', $machineryIds)
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'owner' => $owner,
            'balance' => $owner->balance,
            'logs' => $logs,
            'transactions' => $transactions
        ]);
    }

    public function getMachineryLedger(Machinery $machinery)
    {
        $logs = WorkLog::with(['building'])->where('machinery_id', $machinery->id)->orderBy('date', 'desc')->get();
        $transactions = \App\Models\MachineryTransaction::where('machinery_id', $machinery->id)->orderBy('date', 'desc')->get();
        
        $totalEarned = $logs->sum('calculated_cost');
        
        // Return both logs and transactions, unpaid_balance is machinery->balance
        return response()->json([
            'machinery' => $machinery,
            'logs' => $logs,
            'transactions' => $transactions,
            'total_earned' => $totalEarned,
            'unpaid_balance' => $machinery->balance
        ]);
    }

    public function storeMachineryTransaction(Request $request, Machinery $machinery)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'description' => 'nullable|string'
        ]);

        DB::transaction(function () use ($machinery, $validated) {
            \App\Models\MachineryTransaction::create([
                'machinery_id' => $machinery->id,
                'type' => 'payment',
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'date' => now()
            ]);

            $machinery->decrement('balance', $validated['amount']);
            if ($machinery->machine_owner_id) {
                \App\Models\MachineOwner::where('id', $machinery->machine_owner_id)->decrement('balance', $validated['amount']);
            }

            // Deduct from company balance
            \App\Models\CompanyBalance::addExpense($validated['amount']);
        });

        return redirect()->back()->with('message', 'To\'lov muvaffaqiyatli amalga oshirildi.');
    }

    public function storeBiometric(Request $request)
    {
        $validated = $request->validate([
            'person_type' => 'required|in:user,worker',
            'person_id' => 'required|integer',
            'image' => 'required|string'
        ]);

        // Base64 image decode
        $image = $validated['image'];
        $image = str_replace('data:image/jpeg;base64,', '', $image);
        $image = str_replace('data:image/png;base64,', '', $image);
        $image = str_replace(' ', '+', $image);
        $imageName = $validated['person_type'] . '_' . $validated['person_id'] . '_' . time() . '.jpg';
        
        \Illuminate\Support\Facades\Storage::disk('public')->put('biometrics/' . $imageName, base64_decode($image));
        $photoPath = 'biometrics/' . $imageName;

        if ($validated['person_type'] === 'user') {
            $user = \App\Models\User::findOrFail($validated['person_id']);
            $user->update(['photo_path' => $photoPath]);
        } else {
            $worker = \App\Models\Worker::findOrFail($validated['person_id']);
            $worker->update(['photo_path' => $photoPath]);
        }

        return redirect()->back()->with('message', 'Biometric rasm muvaffaqiyatli saqlandi.');
    }

    public function storeLog(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:worker,machinery',
            'worker_id' => 'nullable|required_if:type,worker|exists:workers,id',
            'machinery_id' => 'nullable|required_if:type,machinery|exists:machineries,id',
            'building_id' => 'required|exists:buildings,id',
            'floor_id' => 'nullable|exists:floors,id',
            'unit_id' => 'nullable|exists:units,id',
            'hours_worked' => 'nullable|numeric|min:0',
            'volume_done' => 'nullable|numeric|min:0',
            'measurement_unit' => 'nullable|string|max:50',
                        'work_category' => 'nullable|string|max:255',
            'date' => 'required|date',
            'custom_rate' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'description' => 'nullable|string'
        ]);

        $deductions = (float) $request->input('deductions', 0);
        $calculatedCost = 0;
        $rate = 0; // Default to prevent 500 errors

        if ($validated['type'] === 'worker') {
            $worker = Worker::find($validated['worker_id']);
            if ($worker->salary_type === 'hourly') {
                $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $worker->base_rate;
                $calculatedCost = (($validated['hours_worked'] ?? 0) * $rate) - $deductions;
            } elseif ($worker->salary_type === 'piece_rate') {
                $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $worker->base_rate;
                $calculatedCost = (($validated['volume_done'] ?? 0) * $rate) - $deductions;
            } else {
                $calculatedCost = 0 - $deductions; 
                $rate = 0;
            }
            $validated['machinery_id'] = null;
        } else {
            $machinery = Machinery::find($validated['machinery_id']);
            $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $machinery->cost_per_hour;
            $calculatedCost = (($validated['hours_worked'] ?? 0) * $rate) - $deductions;
            $validated['worker_id'] = null;
        }

        $validated['rate'] = $rate;
        $validated['calculated_cost'] = $calculatedCost;
        $validated['deductions'] = $deductions;
        $validated['status'] = 'unpaid';
        $validated['description'] = $request->input('description');
        unset($validated['custom_rate']);

        DB::transaction(function () use ($validated, $calculatedCost) {
            $log = WorkLog::create($validated);
            
            // Accrue salary to worker's ledger or Brigade's ledger
            if ($validated['type'] === 'worker' && $calculatedCost != 0) {
                $worker = Worker::find($validated['worker_id']);
                
                // If worker belongs to a Brigade, credit the Brigade. Otherwise, credit the Worker.
                if ($worker->brigade_id) {
                    $brigade = \App\Models\Brigade::find($worker->brigade_id);
                    $brigade->increment('balance', $calculatedCost);
                    
                    // Optional: You could log this into a brigade_transactions table if needed.
                    // For now, we will just update the balance as requested.
                } else {
                    DB::table('worker_transactions')->insert([
                        'worker_id' => $worker->id,
                        'type' => 'salary_accrual',
                        'amount' => $calculatedCost,
                        'description' => 'Ish xajmi kiritildi: Building #' . $validated['building_id'],
                        'date' => $validated['date'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $worker->increment('balance', $calculatedCost);
                }
            }

            // Accrue salary to machinery owner's ledger
            if ($validated['type'] === 'machinery' && $calculatedCost != 0) {
                $machinery = Machinery::find($validated['machinery_id']);
                if ($machinery->machine_owner_id) {
                    $owner = \App\Models\MachineOwner::find($machinery->machine_owner_id);
                    $owner->increment('balance', $calculatedCost);
                    // Could also log to machine_owner_transactions here.
                }
            }

            // Automate construction progress tracking based on expenses and volume
            $building = Building::find($validated['building_id']);
            if ($building) {
                $grossCost = $calculatedCost + $deductions;
                $building->increment('actual_expenses', $grossCost);

                // Add volume to the active construction stage
                if (isset($validated['volume_done']) && $validated['volume_done'] > 0) {
                    $activeStage = \App\Models\ConstructionStage::where('building_id', $building->id)
                        ->whereColumn('current_volume', '<', 'target_volume')
                        ->orderBy('id')
                        ->first();
                    
                    if ($activeStage) {
                        $activeStage->current_volume += $validated['volume_done'];
                        if ($activeStage->current_volume > $activeStage->target_volume) {
                            $activeStage->current_volume = $activeStage->target_volume;
                        }
                        
                        if ($activeStage->target_volume > 0) {
                            $activeStage->progress_percentage = (int) (($activeStage->current_volume / $activeStage->target_volume) * 100);
                        } else {
                            $activeStage->progress_percentage = 100;
                        }
                        
                        $activeStage->save();
                    }
                }
            }
        });

        return redirect()->back()->with('message', 'Log saved. Cost allocated to Building and Worker Ledger.');
    }

    public function generatePayroll()
    {
        DB::transaction(function () {
            $unpaidLogs = WorkLog::where('status', 'unpaid')->get();
            
            if ($unpaidLogs->isEmpty()) {
                return redirect()->back()->with('error', 'No unpaid logs to generate payroll.');
            }

            $totalAmount = $unpaidLogs->sum('calculated_cost');

            if ($totalAmount <= 0) {
                return redirect()->back()->with('error', 'Total amount is 0.');
            }

            $payroll = Payroll::create([
                'total_amount' => $totalAmount,
                'status' => 'pending' // You can change this to 'generated' or something else later if needed
            ]);

            WorkLog::where('status', 'unpaid')->update([
                'status' => 'paid',
                'payroll_id' => $payroll->id
            ]);
        });

        return redirect()->back()->with('message', 'Oylik hisoboti muvaffaqiyatli shakllantirildi.');
    }

    // --- NEW: User Management ---
    public function updateUser(Request $request, \App\Models\User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'nullable|string|exists:roles,name',
            'pinfl' => 'nullable|string',
            'passport' => 'nullable|string',
            'salary_rate' => 'nullable|numeric'
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'pinfl' => $validated['pinfl'],
            'passport' => $validated['passport'],
            'salary_rate' => $validated['salary_rate'],
        ]);

        if (!empty($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return redirect()->back()->with('message', 'Foydalanuvchi yangilandi.');
    }

    public function deleteUser(\App\Models\User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'O\'zingizni o\'chira olmaysiz.');
        }
        $user->delete();
        return redirect()->back()->with('message', 'Foydalanuvchi o\'chirildi.');
    }

    public function getUserLedger(\App\Models\User $user)
    {
        $transactions = DB::table('user_transactions')->where('user_id', $user->id)->orderByDesc('date')->get();
        return response()->json([
            'balance' => $user->balance,
            'transactions' => $transactions
        ]);
    }

    public function storeUserTransaction(Request $request, \App\Models\User $user)
    {
        $validated = $request->validate([
            'type' => 'required|in:salary_accrual,advance_payment,penalty,payment',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'date' => 'required|date',
        ]);

        DB::transaction(function () use ($user, $validated) {
            DB::table('user_transactions')->insert([
                'user_id' => $user->id,
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'date' => $validated['date'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($validated['type'] === 'salary_accrual') {
                $user->increment('balance', $validated['amount']);
            } else {
                $user->decrement('balance', $validated['amount']);
            }
        });

        return redirect()->back()->with('message', 'Tranzaksiya muvaffaqiyatli saqlandi.');
    }

    // --- NEW: Worker Management ---
    public function updateWorker(Request $request, Worker $worker)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'phone' => 'nullable|string',
            'role' => 'required|in:office,builder,driver,guard',
            'salary_type' => 'required|in:fixed_monthly,piece_rate,hourly',
            'metric_type' => 'nullable|string',
            'base_rate' => 'required|numeric|min:0',
            'brigade_id' => 'nullable|exists:brigades,id'
        ]);

        $validated['type'] = $validated['salary_type'] === 'fixed_monthly' ? 'fixed' : 'piece_rate';
        $validated['rate'] = $validated['base_rate'];
        $validated['brigade_id'] = empty($validated['brigade_id']) ? null : $validated['brigade_id'];

        \Illuminate\Support\Facades\DB::transaction(function() use ($worker, $validated, $request) {
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
        });

        return redirect()->back()->with('message', 'Xodim ma\'lumotlari yangilandi.');
    }

    public function deleteWorker(Worker $worker)
    {
        $worker->delete();
        return redirect()->back()->with('message', 'Xodim o\'chirildi.');
    }

    public function storeManualLog(Request $request)
    {
        $validated = $request->validate([
            'subject_type' => 'required|in:User,Worker,Machinery,VisitorDelivery',
            'subject_id' => 'nullable|integer',
            'type' => 'required|in:entry,exit,start_work,stop_work',
            'image' => 'nullable|string'
        ]);

        $subjectClass = '\\App\\Models\\' . $validated['subject_type'];
        
        $photoPath = null;
        if (!empty($validated['image'])) {
            $image = str_replace('data:image/jpeg;base64,', '', $validated['image']);
            $image = str_replace('data:image/png;base64,', '', $image);
            $image = str_replace(' ', '+', $image);
            $imageName = 'events/' . time() . '_manual_' . uniqid() . '.jpg';
            \Illuminate\Support\Facades\Storage::disk('public')->put($imageName, base64_decode($image));
            $photoPath = '/storage/' . $imageName;
        }

        \App\Models\AttendanceLog::create([
            'subject_type' => $subjectClass,
            'subject_id' => $validated['subject_id'],
            'camera_id' => 'Manual Web',
            'type' => $validated['type'],
            'timestamp' => now(),
            'confidence_score' => 100, // Manual is 100%
            'proof_image' => $photoPath,
        ]);

        return redirect()->back()->with('message', 'Manual davomat yozildi!');
    }

    public function getWorkerLedger(Worker $worker)
    {
        $transactions = DB::table('worker_transactions')->where('worker_id', $worker->id)->where('type', '!=', 'salary_accrual')->orderByDesc('date')->get();
        $logs = \App\Models\WorkLog::with('building')->where('type', 'worker')->where('worker_id', $worker->id)->orderByDesc('date')->get();
        
        $categoryBalances = [];

        // Sum earnings from logs
        foreach ($logs as $log) {
            $cat = $log->work_category ?: 'Umumiy';
            if (!isset($categoryBalances[$cat])) {
                $categoryBalances[$cat] = 0;
            }
            $categoryBalances[$cat] += $log->calculated_cost;
        }

        // Subtract payments from transactions
        foreach ($transactions as $t) {
            $cat = $t->work_category ?: 'Umumiy';
            if (!isset($categoryBalances[$cat])) {
                $categoryBalances[$cat] = 0;
            }
            // Assuming transactions are deductions (payments out to worker)
            $categoryBalances[$cat] -= $t->amount;
        }

        return response()->json([
            'balance' => $worker->balance,
            'category_balances' => $categoryBalances,
            'transactions' => $transactions,
            'logs' => $logs
        ]);
    }

    public function storeWorkerTransaction(Request $request, Worker $worker)
    {
        $validated = $request->validate([
            'type' => 'required|in:salary_accrual,advance_payment,penalty,payment',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'work_category' => 'nullable|string|max:255',
            'date' => 'required|date',
        ]);

        DB::transaction(function () use ($worker, $validated) {
            DB::table('worker_transactions')->insert([
                'worker_id' => $worker->id,
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'work_category' => $validated['work_category'] ?? null,
                'date' => $validated['date'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // If it's Kirim (salary_accrual), balance increases (company owes worker)
            // If it's Chiqim (payment, advance_payment, penalty), balance decreases
            if ($validated['type'] === 'salary_accrual') {
                $worker->increment('balance', $validated['amount']);
            } else {
                $worker->decrement('balance', $validated['amount']);
            }
        });

        return redirect()->back()->with('message', 'Tranzaksiya muvaffaqiyatli saqlandi.');
    }


    public function printPayroll($id)
    {
        $payroll = \App\Models\Payroll::with(['workLogs.worker', 'workLogs.machinery', 'workLogs.building'])->findOrFail($id);
        
        return view('admin.hr.print_payroll', compact('payroll'));
    }

    public function updateLog(Request $request, WorkLog $log)
    {
        $validated = $request->validate([
            'hours_worked' => 'nullable|numeric|min:0',
            'volume_done' => 'nullable|numeric|min:0',
            'date' => 'required|date',
            'custom_rate' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'description' => 'nullable|string'
        ]);

        $oldCost = $log->calculated_cost;
        $deductions = (float) $request->input('deductions', 0);
        $newCost = 0;
        $rate = 0;

        if ($log->type === 'worker') {
            $worker = Worker::find($log->worker_id);
            if ($worker->salary_type === 'hourly') {
                $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $worker->base_rate;
                $newCost = (($validated['hours_worked'] ?? 0) * $rate) - $deductions;
            } elseif ($worker->salary_type === 'piece_rate') {
                $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $worker->base_rate;
                $newCost = (($validated['volume_done'] ?? 0) * $rate) - $deductions;
            }
        } else {
            $machinery = Machinery::find($log->machinery_id);
            $rate = $request->filled('custom_rate') ? $request->input('custom_rate') : $machinery->cost_per_hour;
            $newCost = (($validated['hours_worked'] ?? 0) * $rate) - $deductions;
        }

        $delta = $newCost - $oldCost;

        DB::transaction(function () use ($log, $validated, $newCost, $oldCost, $delta, $rate, $deductions) {
            $log->update([
                'hours_worked' => $validated['hours_worked'] ?? null,
                'volume_done' => $validated['volume_done'] ?? null,
                'rate' => $rate ?? 0,
                'calculated_cost' => $newCost,
                'deductions' => $deductions,
                'date' => $validated['date'],
                'description' => $validated['description'] ?? null
            ]);

            if ($delta != 0) {
                if ($log->type === 'worker') {
                    $worker = Worker::find($log->worker_id);
                    if ($worker->brigade_id) {
                        \App\Models\Brigade::where('id', $worker->brigade_id)->increment('balance', $delta);
                    } else {
                        $worker->increment('balance', $delta);
                        $tx = DB::table('worker_transactions')->where('worker_id', $worker->id)->where('amount', $oldCost)->where('date', $log->getOriginal('date'))->where('type', 'salary_accrual')->first();
                        if ($tx) {
                            DB::table('worker_transactions')->where('id', $tx->id)->update(['amount' => $newCost, 'date' => $validated['date']]);
                        }
                    }
                } else {
                    $machinery = Machinery::find($log->machinery_id);
                    if ($machinery->machine_owner_id) {
                        \App\Models\MachineOwner::where('id', $machinery->machine_owner_id)->increment('balance', $delta);
                    }
                }
                
                $building = Building::find($log->building_id);
                if ($building) {
                    $oldGross = $oldCost + $log->getOriginal('deductions');
                    $newGross = $newCost + $deductions;
                    $grossDelta = $newGross - $oldGross;
                    $building->increment('actual_expenses', $grossDelta);
                }
            }
        });

        return redirect()->back()->with('message', 'Ish hajmi tahrirlandi.');
    }

    public function deleteLog(WorkLog $log)
    {
        DB::transaction(function () use ($log) {
            $cost = $log->calculated_cost;
            
            if ($log->type === 'worker') {
                $worker = Worker::find($log->worker_id);
                if ($worker->brigade_id) {
                    \App\Models\Brigade::where('id', $worker->brigade_id)->decrement('balance', $cost);
                } else {
                    $worker->decrement('balance', $cost);
                    $tx = DB::table('worker_transactions')->where('worker_id', $worker->id)->where('amount', $cost)->where('date', $log->date)->where('type', 'salary_accrual')->first();
                    if ($tx) {
                        DB::table('worker_transactions')->where('id', $tx->id)->delete();
                    }
                }
            } else {
                $machinery = Machinery::find($log->machinery_id);
                if ($machinery->machine_owner_id) {
                    \App\Models\MachineOwner::where('id', $machinery->machine_owner_id)->decrement('balance', $cost);
                }
            }
            
            $building = Building::find($log->building_id);
            if ($building) {
                $grossCost = $cost + $log->deductions;
                $building->decrement('actual_expenses', $grossCost);
            }

            $log->delete();
        });

        return redirect()->back()->with('message', 'Ish hajmi o\'chirildi.');
    }

    public function updateTransaction(Request $request, $type, $id)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'date' => 'required|date'
        ]);

        DB::transaction(function () use ($type, $id, $validated) {
            $table = $type . '_transactions';
            $tx = DB::table($table)->where('id', $id)->first();
            if (!$tx) abort(404);

            $oldAmount = $tx->amount;
            $newAmount = $validated['amount'];
            $delta = $newAmount - $oldAmount;

            DB::table($table)->where('id', $id)->update([
                'amount' => $newAmount,
                'description' => $validated['description'],
                'date' => $validated['date'],
                'updated_at' => now()
            ]);

            if ($delta != 0) {
                if ($type === 'worker') {
                    $worker = Worker::find($tx->worker_id);
                    if ($tx->type === 'salary_accrual') {
                        $worker->increment('balance', $delta);
                    } else {
                        $worker->decrement('balance', $delta);
                        if ($tx->type === 'payment') \App\Models\CompanyBalance::addExpense($delta);
                    }
                } elseif ($type === 'machinery') {
                    $machinery = Machinery::find($tx->machinery_id);
                    if ($tx->type === 'salary_accrual') {
                        $machinery->increment('balance', $delta);
                        if ($machinery->machine_owner_id) {
                            \App\Models\MachineOwner::where('id', $machinery->machine_owner_id)->increment('balance', $delta);
                        }
                    } else {
                        $machinery->decrement('balance', $delta);
                        if ($machinery->machine_owner_id) {
                            \App\Models\MachineOwner::where('id', $machinery->machine_owner_id)->decrement('balance', $delta);
                        }
                        if ($tx->type === 'payment') \App\Models\CompanyBalance::addExpense($delta);
                    }
                } elseif ($type === 'user') {
                    $user = \App\Models\User::find($tx->user_id);
                    if ($tx->type === 'salary_accrual') {
                        $user->increment('balance', $delta);
                    } else {
                        $user->decrement('balance', $delta);
                        if ($tx->type === 'payment') \App\Models\CompanyBalance::addExpense($delta);
                    }
                }
            }
        });

        return redirect()->back()->with('message', 'Tranzaksiya tahrirlandi.');
    }

    public function deleteTransaction($type, $id)
    {
        DB::transaction(function () use ($type, $id) {
            $table = $type . '_transactions';
            $tx = DB::table($table)->where('id', $id)->first();
            if (!$tx) abort(404);

            if ($type === 'worker') {
                $worker = Worker::find($tx->worker_id);
                if ($tx->type === 'salary_accrual') {
                    $worker->decrement('balance', $tx->amount);
                } else {
                    $worker->increment('balance', $tx->amount);
                    if ($tx->type === 'payment') \App\Models\CompanyBalance::addIncome($tx->amount); // revert expense
                }
            } elseif ($type === 'machinery') {
                $machinery = Machinery::find($tx->machinery_id);
                if ($tx->type === 'salary_accrual') {
                    $machinery->decrement('balance', $tx->amount);
                    if ($machinery->machine_owner_id) {
                        \App\Models\MachineOwner::where('id', $machinery->machine_owner_id)->decrement('balance', $tx->amount);
                    }
                } else {
                    $machinery->increment('balance', $tx->amount);
                    if ($machinery->machine_owner_id) {
                        \App\Models\MachineOwner::where('id', $machinery->machine_owner_id)->increment('balance', $tx->amount);
                    }
                    if ($tx->type === 'payment') \App\Models\CompanyBalance::addIncome($tx->amount);
                }
            } elseif ($type === 'user') {
                $user = \App\Models\User::find($tx->user_id);
                if ($tx->type === 'salary_accrual') {
                    $user->decrement('balance', $tx->amount);
                } else {
                    $user->increment('balance', $tx->amount);
                    if ($tx->type === 'payment') \App\Models\CompanyBalance::addIncome($tx->amount);
                }
            }

            DB::table($table)->where('id', $id)->delete();
        });

        return redirect()->back()->with('message', 'Tranzaksiya o\'chirildi.');
    }
}
