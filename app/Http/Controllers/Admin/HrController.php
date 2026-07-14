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
        $tab = request('tab', '0');

        $users = [];
        $attendanceLogs = [];
        $workers = [];
        $brigades = [];
        $machineries = [];
        $machineOwners = [];
        $buildings = [];
        $unpaidLogs = [];
        $unpaidTotal = 0;
        $payrolls = [];
        $kpiSettings = null;

        if ($tab === '0' || $tab === '1' || $tab === '2' || $tab === 0 || $tab === 1 || $tab === 2) {
            $users = \App\Models\User::with(['roles', 'transactions', 'salaryPeriods'])->withCount(['leads' => function($q) {
                $q->where('status', 'qualified');
            }])->get();
        }

        if ($tab === '0' || $tab === '1' || $tab === 0 || $tab === 1) {
            $attendanceLogs = \App\Models\AttendanceLog::with('subject')->latest()->take(100)->get();
            $workers = Worker::with(['brigade', 'salaryHistories', 'transactions', 'salaryPeriods'])->get();
            $brigades = \App\Models\Brigade::with('workers')->get();
            $machineries = Machinery::all();
            $machineOwners = \App\Models\MachineOwner::with('machineries')->get();
            $buildings = Building::with('floors.units')->get();
            $unpaidLogs = WorkLog::with(['worker', 'machinery', 'building', 'floor', 'unit'])->where('status', 'unpaid')->latest()->limit(500)->get();
            $unpaidTotal = WorkLog::where('status', 'unpaid')->sum('calculated_cost');
            $payrolls = Payroll::with('transaction')->latest()->limit(500)->get();
        }

        if ($tab === '2' || $tab === 2) {
            $kpiSettings = \App\Models\CrmSetting::where('key', 'kpi_settings')->first();
        }

        if ($tab === 'sales_agents') {
            $users = \App\Models\User::role('Sotuvchi')->with(['leads', 'contracts.unit.floor.building', 'roles'])->get();
            $buildings = Building::with('floors.units')->get();
        }

        return Inertia::render('Admin/HR/Index', [
            'users' => $users,
            'attendanceLogs' => $attendanceLogs,
            'workers' => $workers,
            'brigades' => $brigades,
            'machineries' => $machineries,
            'machineOwners' => $machineOwners,
            'buildings' => $buildings,
            'unpaidLogs' => $unpaidLogs,
            'unpaidTotal' => $unpaidTotal,
            'payrolls' => $payrolls,
            'kpiSettings' => $kpiSettings
        ]);
    }

    public function qurilishIshHaqi()
    {
        $buildings = Building::select('id', 'name')->with([
            'floors' => function($q) {
                $q->select('id', 'building_id', 'floor_number');
            },
            'floors.units' => function($q) {
                $q->select('id', 'floor_id', 'unit_number');
            }
        ])->get();
        
        $brigades = \App\Models\Brigade::select('id', 'name', 'foreman_name', 'phone', 'balance')->with([
            'workers' => function($q) {
                $q->select('id', 'brigade_id', 'name', 'phone');
            }, 
            'transactions' => function($q) {
                $q->select('id', 'brigade_id', 'type', 'amount', 'date', 'created_at', 'description');
            },
            'works' => function($q) {
                $q->select('id', 'brigade_id', 'calculated_sum');
            }
        ])->get();
        
        return Inertia::render('Admin/HR/QurilishIshHaqi', [
            'buildings' => $buildings,
            'brigades' => $brigades
        ]);
    }

    public function texnikalar()
    {
        $machineries = \App\Models\Machinery::select('id', 'name', 'machinery_type', 'machine_owner_id', 'driver_name', 'created_at', 'updated_at')->get();
        $owners = \App\Models\MachineOwner::select('id', 'name', 'phone', 'balance', 'created_at', 'updated_at')->with([
            'machineries' => function($q) {
                $q->select('id', 'machine_owner_id', 'name', 'driver_name');
            }, 
            'works' => function($q) {
                $q->with('transactions')->orderBy('date', 'asc');
            },
            'transactions' => function($q) {
                $q->select('id', 'machine_owner_id', 'type', 'amount', 'date', 'created_at', 'description');
            }
        ])->get();
        return Inertia::render('Admin/HR/Texnikalar', [
            'machineries' => $machineries,
            'owners' => $owners
        ]);
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|string|exists:roles,name',
            'phone' => 'nullable|string',
            'pinfl' => 'nullable|string',
            'passport' => 'nullable|string',
            'salary_rate' => 'nullable|numeric',
            'gender' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'employment_date' => 'nullable|date',
            'status' => 'nullable|string',
            'specialty_id' => 'nullable|string',
            'contract_type' => 'nullable|string',
            'characteristics' => 'nullable',
            'passport_front' => 'nullable|string',
            'passport_back' => 'nullable|string',
            'additional_documents' => 'nullable|array',
        ]);

        if (is_array($request->input('characteristics'))) {
            $validated['characteristics'] = json_encode($request->input('characteristics'), JSON_UNESCAPED_UNICODE);
        }

        $passportFrontPath = $this->saveBase64File($request->input('passport_front'), 'passport_photos');
        $passportBackPath = $this->saveBase64File($request->input('passport_back'), 'passport_photos');

        $additionalDocs = [];
        if ($request->has('additional_documents') && is_array($request->input('additional_documents'))) {
            foreach ($request->input('additional_documents') as $doc) {
                if (is_array($doc) && isset($doc['data'])) {
                    $path = $this->saveBase64File($doc['data'], 'hr_documents');
                    if ($path) {
                        $additionalDocs[] = [
                            'name' => $doc['name'] ?? basename($path),
                            'path' => $path
                        ];
                    }
                } elseif (is_string($doc)) {
                    if (str_starts_with($doc, '/storage/') || str_starts_with($doc, 'storage/')) {
                        $additionalDocs[] = [
                            'name' => basename($doc),
                            'path' => $doc
                        ];
                    } else {
                        $path = $this->saveBase64File($doc, 'hr_documents');
                        if ($path) {
                            $additionalDocs[] = [
                                'name' => basename($path),
                                'path' => $path
                            ];
                        }
                    }
                }
            }
        }

        $user = \App\Models\User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'pinfl' => $validated['pinfl'] ?? null,
            'passport' => $validated['passport'] ?? null,
            'salary_rate' => $validated['salary_rate'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'employment_date' => $validated['employment_date'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'specialty_id' => $validated['specialty_id'] ?? null,
            'contract_type' => $validated['contract_type'] ?? null,
            'characteristics' => $validated['characteristics'] ?? null,
            'passport_front' => $passportFrontPath,
            'passport_back' => $passportBackPath,
            'additional_documents' => empty($additionalDocs) ? null : json_encode($additionalDocs, JSON_UNESCAPED_UNICODE),
        ]);

        $user->assignRole($validated['role']);

        $this->syncSalaryPeriods('user', $user->id, $request->input('salary_periods', []));

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
            'brigade_id' => 'nullable|exists:brigades,id',
            'passport' => 'nullable|string',
            'pinfl' => 'nullable|string',
            'gender' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'employment_date' => 'nullable|date',
            'status' => 'nullable|string',
            'specialty_id' => 'nullable|string',
            'contract_type' => 'nullable|string',
            'characteristics' => 'nullable',
            'passport_front' => 'nullable|string',
            'passport_back' => 'nullable|string',
            'additional_documents' => 'nullable|array',
        ]);

        if (is_array($request->input('characteristics'))) {
            $validated['characteristics'] = json_encode($request->input('characteristics'), JSON_UNESCAPED_UNICODE);
        }

        $passportFrontPath = $this->saveBase64File($request->input('passport_front'), 'passport_photos');
        $passportBackPath = $this->saveBase64File($request->input('passport_back'), 'passport_photos');

        $additionalDocs = [];
        if ($request->has('additional_documents') && is_array($request->input('additional_documents'))) {
            foreach ($request->input('additional_documents') as $doc) {
                if (is_array($doc) && isset($doc['data'])) {
                    $path = $this->saveBase64File($doc['data'], 'hr_documents');
                    if ($path) {
                        $additionalDocs[] = [
                            'name' => $doc['name'] ?? basename($path),
                            'path' => $path
                        ];
                    }
                } elseif (is_string($doc)) {
                    if (str_starts_with($doc, '/storage/') || str_starts_with($doc, 'storage/')) {
                        $additionalDocs[] = [
                            'name' => basename($doc),
                            'path' => $doc
                        ];
                    } else {
                        $path = $this->saveBase64File($doc, 'hr_documents');
                        if ($path) {
                            $additionalDocs[] = [
                                'name' => basename($path),
                                'path' => $path
                            ];
                        }
                    }
                }
            }
        }

        $validated['type'] = $validated['salary_type'] === 'fixed_monthly' ? 'fixed' : 'piece_rate';
        $validated['rate'] = $validated['base_rate'];

        $validated['building_id'] = empty($validated['building_id']) ? null : $validated['building_id'];
        $validated['floor_id'] = empty($validated['floor_id']) ? null : $validated['floor_id'];
        $validated['unit_id'] = empty($validated['unit_id']) ? null : $validated['unit_id'];
        $validated['brigade_id'] = empty($validated['brigade_id']) ? null : $validated['brigade_id'];
        
        $validated['passport_front'] = $passportFrontPath;
        $validated['passport_back'] = $passportBackPath;
        $validated['additional_documents'] = empty($additionalDocs) ? null : json_encode($additionalDocs, JSON_UNESCAPED_UNICODE);

        \Illuminate\Support\Facades\DB::transaction(function() use ($validated, $request) {
            $worker = Worker::create($validated);

            $this->syncSalaryPeriods('worker', $worker->id, $request->input('salary_periods', []));
            
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

    public function exportOwnerLedger($ownerId)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\OwnerLedgerExport($ownerId), 'Texnika_Egasi_Hisoboti.xlsx');
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

        $ownerTransactions = \App\Models\MachineOwnerTransaction::where('machine_owner_id', $ownerId)
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'owner' => $owner,
            'balance' => $owner->balance,
            'logs' => $logs,
            'transactions' => $transactions,
            'owner_transactions' => $ownerTransactions
        ]);
    }

    public function storeOwnerTransaction(Request $request, \App\Models\MachineOwner $owner)
    {
        $validated = $request->validate([
            'type' => 'required|in:salary_accrual,advance_payment,penalty,payment',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'date' => 'nullable|date',
            'discount' => 'nullable|numeric|min:0',
            'account_type' => 'nullable|string|in:main,bank,credit,partner',
            'payment_method' => 'nullable|string|in:cash,card,bank_transfer',
            'linked_work_type' => 'nullable|string',
            'linked_work_id' => 'nullable|integer',
        ]);

        if (empty($validated['date'])) {
            $validated['date'] = now()->toDateString();
        }

        $amount = (float) $validated['amount'];
        $discount = (float) ($validated['discount'] ?? 0);
        $totalPayment = $amount + $discount;

        DB::transaction(function () use ($owner, $validated, $amount, $discount, $totalPayment) {
            if ($validated['type'] === 'payment') {
                // Owner balansi umumiy summa + chegirma qiymatiga kamayadi
                $owner->decrement('balance', $totalPayment);

                // 1. Asosiy to'lov (Summa)
                $ledgerTx = \App\Models\MachineOwnerTransaction::create([
                    'machine_owner_id' => $owner->id,
                    'type' => 'payment',
                    'amount' => $amount,
                    'description' => $validated['description'] ?? 'To\'lov',
                    'date' => $validated['date'],
                    'linked_work_type' => $validated['linked_work_type'] ?? null,
                    'linked_work_id' => $validated['linked_work_id'] ?? null,
                ]);
                $ledgerTxId = $ledgerTx->id;
                $ledgerTableName = 'machine_owner_transactions';

                \App\Models\CompanyBalance::addExpense($amount, $validated['account_type'] ?? 'main');

                // Moliya markazi uchun asosiy to'lov tranzaksiyasini yaratish
                $cat = \App\Models\TransactionCategory::firstOrCreate(['name' => 'Texnika To\'lovi'], ['type' => 'expense']);
                $financeTx = \App\Models\Transaction::create([
                    'type' => 'expense',
                    'amount' => $amount,
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'account_type' => $validated['account_type'] ?? 'main',
                    'category_id' => $cat->id,
                    'category_name' => $cat->name,
                    'status' => 'approved',
                    'notes' => $validated['description'] ?? 'Texnika egasiga to\'lov',
                    'transaction_date' => $validated['date'],
                    'created_by' => auth()->id(),
                    'target_type' => 'machine_owner',
                    'target_id' => $owner->id,
                    'linked_work_type' => $validated['linked_work_type'] ?? null,
                    'linked_work_id' => $validated['linked_work_id'] ?? null,
                ]);
                if (isset($ledgerTxId) && isset($ledgerTableName)) { \Illuminate\Support\Facades\DB::table($ledgerTableName)->where('id', $ledgerTxId)->update(['finance_transaction_id' => $financeTx->id]); unset($ledgerTxId); unset($ledgerTableName); }

                // 2. Chegirma (Texnika xarajati)
                if ($discount > 0) {
                    $ledgerTx = \App\Models\MachineOwnerTransaction::create([
                        'machine_owner_id' => $owner->id,
                        'type' => 'payment',
                        'amount' => $discount,
                        'description' => 'Texnika xarajati (Chegirma)',
                        'date' => $validated['date']
                    ]);
                    $ledgerTxId = $ledgerTx->id;
                    $ledgerTableName = 'machine_owner_transactions';

                    \App\Models\CompanyBalance::addExpense($discount, $validated['account_type'] ?? 'main');

                    $discountCat = \App\Models\TransactionCategory::firstOrCreate(['name' => 'Texnika xarajati (chegirma)'], ['type' => 'expense']);
                    $financeTx = \App\Models\Transaction::create([
                        'type' => 'expense',
                        'amount' => $discount,
                        'payment_method' => $validated['payment_method'] ?? 'cash',
                        'account_type' => $validated['account_type'] ?? 'main',
                        'category_id' => $discountCat->id,
                        'category_name' => $discountCat->name,
                        'status' => 'approved',
                        'notes' => 'Texnika xarajati (chegirma)',
                        'transaction_date' => $validated['date'],
                        'created_by' => auth()->id(),
                        'target_type' => 'machine_owner',
                        'target_id' => $owner->id,
                        'linked_work_type' => $validated['linked_work_type'] ?? null,
                        'linked_work_id' => $validated['linked_work_id'] ?? null,
                    ]);
                if (isset($ledgerTxId) && isset($ledgerTableName)) { \Illuminate\Support\Facades\DB::table($ledgerTableName)->where('id', $ledgerTxId)->update(['finance_transaction_id' => $financeTx->id]); unset($ledgerTxId); unset($ledgerTableName); }
                }
            } else {
                // Qarz yoki jarima qo'shilganda
                \App\Models\MachineOwnerTransaction::create([
                    'machine_owner_id' => $owner->id,
                    'type' => $validated['type'],
                    'amount' => $amount,
                    'description' => $validated['description'],
                    'date' => $validated['date']
                ]);

                if ($validated['type'] === 'salary_accrual') {
                    $owner->increment('balance', $amount);
                } else {
                    $owner->decrement('balance', $amount);
                }
            }
        });

        return response()->json(['success' => true, 'message' => 'Tranzaksiya muvaffaqiyatli saqlandi.']);
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
            'phone' => 'nullable|string',
            'pinfl' => 'nullable|string',
            'passport' => 'nullable|string',
            'salary_rate' => 'nullable|numeric',
            'gender' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'employment_date' => 'nullable|date',
            'status' => 'nullable|string',
            'specialty_id' => 'nullable|string',
            'contract_type' => 'nullable|string',
            'characteristics' => 'nullable',
            'passport_front' => 'nullable|string',
            'passport_back' => 'nullable|string',
            'additional_documents' => 'nullable|array',
        ]);

        if (is_array($request->input('characteristics'))) {
            $validated['characteristics'] = json_encode($request->input('characteristics'), JSON_UNESCAPED_UNICODE);
        }

        $passportFrontPath = $user->passport_front;
        if ($request->has('passport_front')) {
            $passportFrontPath = $this->saveBase64File($request->input('passport_front'), 'passport_photos');
        }

        $passportBackPath = $user->passport_back;
        if ($request->has('passport_back')) {
            $passportBackPath = $this->saveBase64File($request->input('passport_back'), 'passport_photos');
        }

        $additionalDocs = [];
        if ($request->has('additional_documents') && is_array($request->input('additional_documents'))) {
            foreach ($request->input('additional_documents') as $doc) {
                if (is_array($doc) && isset($doc['data'])) {
                    $path = $this->saveBase64File($doc['data'], 'hr_documents');
                    if ($path) {
                        $additionalDocs[] = [
                            'name' => $doc['name'] ?? basename($path),
                            'path' => $path
                        ];
                    }
                } elseif (is_string($doc)) {
                    if (str_starts_with($doc, '/storage/') || str_starts_with($doc, 'storage/')) {
                        $additionalDocs[] = [
                            'name' => basename($doc),
                            'path' => $doc
                        ];
                    } else {
                        $path = $this->saveBase64File($doc, 'hr_documents');
                        if ($path) {
                            $additionalDocs[] = [
                                'name' => basename($path),
                                'path' => $path
                            ];
                        }
                    }
                }
            }
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'pinfl' => $validated['pinfl'],
            'passport' => $validated['passport'],
            'salary_rate' => $validated['salary_rate'],
            'gender' => $validated['gender'] ?? $user->gender,
            'birth_date' => $validated['birth_date'] ?? $user->birth_date,
            'employment_date' => $validated['employment_date'] ?? $user->employment_date,
            'status' => $validated['status'] ?? $user->status,
            'specialty_id' => $validated['specialty_id'] ?? $user->specialty_id,
            'contract_type' => $validated['contract_type'] ?? $user->contract_type,
            'characteristics' => $validated['characteristics'] ?? null,
            'passport_front' => $passportFrontPath,
            'passport_back' => $passportBackPath,
            'additional_documents' => empty($additionalDocs) ? null : json_encode($additionalDocs, JSON_UNESCAPED_UNICODE),
        ]);

        if (!empty($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        $this->syncSalaryPeriods('user', $user->id, $request->input('salary_periods', []));

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
            'brigade_id' => 'nullable|exists:brigades,id',
            'passport' => 'nullable|string',
            'pinfl' => 'nullable|string',
            'gender' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'employment_date' => 'nullable|date',
            'status' => 'nullable|string',
            'specialty_id' => 'nullable|string',
            'contract_type' => 'nullable|string',
            'characteristics' => 'nullable',
            'passport_front' => 'nullable|string',
            'passport_back' => 'nullable|string',
            'additional_documents' => 'nullable|array',
        ]);

        if (is_array($request->input('characteristics'))) {
            $validated['characteristics'] = json_encode($request->input('characteristics'), JSON_UNESCAPED_UNICODE);
        }

        $passportFrontPath = $worker->passport_front;
        if ($request->has('passport_front')) {
            $passportFrontPath = $this->saveBase64File($request->input('passport_front'), 'passport_photos');
        }

        $passportBackPath = $worker->passport_back;
        if ($request->has('passport_back')) {
            $passportBackPath = $this->saveBase64File($request->input('passport_back'), 'passport_photos');
        }

        $additionalDocs = [];
        if ($request->has('additional_documents') && is_array($request->input('additional_documents'))) {
            foreach ($request->input('additional_documents') as $doc) {
                if (is_array($doc) && isset($doc['data'])) {
                    $path = $this->saveBase64File($doc['data'], 'hr_documents');
                    if ($path) {
                        $additionalDocs[] = [
                            'name' => $doc['name'] ?? basename($path),
                            'path' => $path
                        ];
                    }
                } elseif (is_string($doc)) {
                    if (str_starts_with($doc, '/storage/') || str_starts_with($doc, 'storage/')) {
                        $additionalDocs[] = [
                            'name' => basename($doc),
                            'path' => $doc
                        ];
                    } else {
                        $path = $this->saveBase64File($doc, 'hr_documents');
                        if ($path) {
                            $additionalDocs[] = [
                                'name' => basename($path),
                                'path' => $path
                            ];
                        }
                    }
                }
            }
        }

        $validated['type'] = $validated['salary_type'] === 'fixed_monthly' ? 'fixed' : 'piece_rate';
        $validated['rate'] = $validated['base_rate'];
        $validated['brigade_id'] = empty($validated['brigade_id']) ? null : $validated['brigade_id'];
        
        $validated['passport_front'] = $passportFrontPath;
        $validated['passport_back'] = $passportBackPath;
        $validated['additional_documents'] = empty($additionalDocs) ? null : json_encode($additionalDocs, JSON_UNESCAPED_UNICODE);

        \Illuminate\Support\Facades\DB::transaction(function() use ($worker, $validated, $request) {
            $oldRate = $worker->base_rate;
            $worker->update($validated);

            $this->syncSalaryPeriods('worker', $worker->id, $request->input('salary_periods', []));
            
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

            $financeTxId = $tx->finance_transaction_id ?? null;

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
            
            if ($financeTxId) {
                \App\Models\Transaction::where('id', $financeTxId)->delete();
            }
        });

        return redirect()->back()->with('message', 'Tranzaksiya o\'chirildi.');
    }

    // --- BRIGADE LEDGER LOGIC ---
    public function getBrigadeLedger(\App\Models\Brigade $brigade)
    {
        try {
            $workerIds = $brigade->workers()->pluck('id')->toArray();
            $logs = \App\Models\WorkLog::with(['building', 'machinery'])
                        ->whereIn('worker_id', $workerIds)
                        ->orderBy('date', 'desc')
                        ->get();
                        
            $transactions = \App\Models\BrigadeTransaction::where('brigade_id', $brigade->id)
                                ->orderBy('date', 'desc')
                                ->get();
                                
            return response()->json([
                'balance' => $brigade->balance,
                'logs' => $logs,
                'transactions' => $transactions
            ]);
        } catch (\Throwable $e) {
            return response("Xatolik xabari: " . $e->getMessage() . " | Qatorda: " . $e->getLine(), 500);
        }
    }

    public function storeBrigadeTransaction(\Illuminate\Http\Request $request, \App\Models\Brigade $brigade)
    {
        $validated = $request->validate([
            'type' => 'required|in:salary_accrual,advance_payment,penalty,payment',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'discount' => 'nullable|numeric|min:0',
            'account_type' => 'nullable|string|in:main,bank,credit,partner',
            'payment_method' => 'nullable|string|in:cash,card,bank_transfer',
            'linked_work_type' => 'nullable|string',
            'linked_work_id' => 'nullable|integer',
        ]);

        $amount = (float) $validated['amount'];
        $discount = (float) ($validated['discount'] ?? 0);
        $totalPayment = $amount + $discount;

        \Illuminate\Support\Facades\DB::transaction(function () use ($brigade, $validated, $amount, $discount, $totalPayment) {
            if ($validated['type'] === 'payment') {
                $brigade->decrement('balance', $totalPayment);

                // 1. Asosiy to'lov
                $ledgerTx = \App\Models\BrigadeTransaction::create([
                    'brigade_id' => $brigade->id,
                    'type' => 'payment',
                    'amount' => $amount,
                    'description' => $validated['description'] ?? 'To\'lov',
                    'date' => $validated['date'],
                    'linked_work_type' => $validated['linked_work_type'] ?? null,
                    'linked_work_id' => $validated['linked_work_id'] ?? null
                ]);
                $ledgerTxId = $ledgerTx->id;
                $ledgerTableName = 'brigade_transactions';

                \App\Models\CompanyBalance::addExpense($amount, $validated['account_type'] ?? 'main');

                // Moliya markazi uchun asosiy to'lov tranzaksiyasi
                $cat = \App\Models\TransactionCategory::firstOrCreate(['name' => 'Brigada To\'lovi'], ['type' => 'expense']);
                $financeTx = \App\Models\Transaction::create([
                    'type' => 'expense',
                    'amount' => $amount,
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'account_type' => $validated['account_type'] ?? 'main',
                    'category_id' => $cat->id,
                    'category_name' => $cat->name,
                    'status' => 'approved',
                    'notes' => $validated['description'] ?? 'Brigada to\'lovi',
                    'transaction_date' => $validated['date'],
                    'created_by' => auth()->id(),
                    'target_type' => 'brigade',
                    'target_id' => $brigade->id,
                    'linked_work_type' => $validated['linked_work_type'] ?? null,
                    'linked_work_id' => $validated['linked_work_id'] ?? null,
                ]);
                if (isset($ledgerTxId) && isset($ledgerTableName)) { \Illuminate\Support\Facades\DB::table($ledgerTableName)->where('id', $ledgerTxId)->update(['finance_transaction_id' => $financeTx->id]); unset($ledgerTxId); unset($ledgerTableName); }

                // 2. Chegirma
                if ($discount > 0) {
                    $ledgerTx = \App\Models\BrigadeTransaction::create([
                        'brigade_id' => $brigade->id,
                        'type' => 'payment',
                        'amount' => $discount,
                        'description' => 'Brigada xarajati (Chegirma)',
                        'date' => $validated['date']
                    ]);
                    $ledgerTxId = $ledgerTx->id;
                    $ledgerTableName = 'brigade_transactions';

                    \App\Models\CompanyBalance::addExpense($discount, $validated['account_type'] ?? 'main');

                    $discountCat = \App\Models\TransactionCategory::firstOrCreate(['name' => 'Brigada xarajati (chegirma)'], ['type' => 'expense']);
                    $financeTx = \App\Models\Transaction::create([
                        'type' => 'expense',
                        'amount' => $discount,
                        'payment_method' => $validated['payment_method'] ?? 'cash',
                        'account_type' => $validated['account_type'] ?? 'main',
                        'category_id' => $discountCat->id,
                        'category_name' => $discountCat->name,
                        'status' => 'approved',
                        'notes' => 'Brigada xarajati (chegirma)',
                        'transaction_date' => $validated['date'],
                        'created_by' => auth()->id(),
                        'target_type' => 'brigade',
                        'target_id' => $brigade->id,
                    ]);
                if (isset($ledgerTxId) && isset($ledgerTableName)) { \Illuminate\Support\Facades\DB::table($ledgerTableName)->where('id', $ledgerTxId)->update(['finance_transaction_id' => $financeTx->id]); unset($ledgerTxId); unset($ledgerTableName); }
                }
            } else {
                \App\Models\BrigadeTransaction::create([
                    'brigade_id' => $brigade->id,
                    'type' => $validated['type'],
                    'amount' => $amount,
                    'description' => $validated['description'],
                    'date' => $validated['date']
                ]);

                if ($validated['type'] === 'salary_accrual') {
                    $brigade->increment('balance', $amount);
                } else {
                    $brigade->decrement('balance', $amount);
                }
            }
        });

        return response()->json(['success' => true, 'message' => 'Tranzaksiya muvaffaqiyatli saqlandi.']);
    }

    public function exportBrigadeLedger(\App\Models\Brigade $brigade)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\BrigadeLedgerExport($brigade), 'brigada_jurnali_' . $brigade->name . '_' . date('Y_m_d') . '.xlsx');
    }

    // --- GUARD LEDGER LOGIC ---
    public function getGuardLedger(\App\Models\Worker $guard)
    {
        try {
            $transactions = \Illuminate\Support\Facades\DB::table('worker_transactions')
                                ->where('worker_id', $guard->id)
                                ->orderBy('date', 'desc')
                                ->get();
                                
            return response()->json([
                'balance' => $guard->balance,
                'transactions' => $transactions,
                'guard' => $guard
            ]);
        } catch (\Throwable $e) {
            return response("Xatolik xabari: " . $e->getMessage() . " | Qatorda: " . $e->getLine(), 500);
        }
    }

    public function storeGuardTransaction(\Illuminate\Http\Request $request, \App\Models\Worker $guard)
    {
        $validated = $request->validate([
            'type' => 'required|in:salary_accrual,advance_payment,penalty,payment',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'discount' => 'nullable|numeric|min:0',
            'account_type' => 'nullable|string|in:main,bank,credit,partner',
            'payment_method' => 'nullable|string|in:cash,card,bank_transfer',
        ]);

        $amount = (float) $validated['amount'];
        $discount = (float) ($validated['discount'] ?? 0);
        $totalPayment = $amount + $discount;

        \Illuminate\Support\Facades\DB::transaction(function () use ($guard, $validated, $amount, $discount, $totalPayment) {
            if ($validated['type'] === 'payment') {
                $guard->decrement('balance', $totalPayment);

                // 1. Asosiy to'lov
                $ledgerTxId = \Illuminate\Support\Facades\DB::table('worker_transactions')->insertGetId([
                    'worker_id' => $guard->id,
                    'type' => 'payment',
                    'amount' => $amount,
                    'description' => $validated['description'] ?? 'To\'lov',
                    'date' => $validated['date'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $ledgerTableName = 'worker_transactions';

                \App\Models\CompanyBalance::addExpense($amount, $validated['account_type'] ?? 'main');

                // Moliya markazi uchun asosiy to'lov tranzaksiyasi
                $cat = \App\Models\TransactionCategory::firstOrCreate(['name' => 'Qorovul To\'lovi'], ['type' => 'expense']);
                $financeTx = \App\Models\Transaction::create([
                    'type' => 'expense',
                    'amount' => $amount,
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'account_type' => $validated['account_type'] ?? 'main',
                    'category_id' => $cat->id,
                    'category_name' => $cat->name,
                    'status' => 'approved',
                    'notes' => ($validated['description'] ?? 'Qorovul to\'lovi') . "\nQorovul: " . $guard->name,
                    'transaction_date' => $validated['date'],
                    'created_by' => auth()->id(),
                    'target_type' => 'worker',
                    'target_id' => $guard->id,
                ]);
                if (isset($ledgerTxId) && isset($ledgerTableName)) { \Illuminate\Support\Facades\DB::table($ledgerTableName)->where('id', $ledgerTxId)->update(['finance_transaction_id' => $financeTx->id]); unset($ledgerTxId); unset($ledgerTableName); }

                // 2. Chegirma
                if ($discount > 0) {
                    $ledgerTxId = \Illuminate\Support\Facades\DB::table('worker_transactions')->insertGetId([
                        'worker_id' => $guard->id,
                        'type' => 'payment',
                        'amount' => $discount,
                        'description' => 'Chegirma',
                        'date' => $validated['date'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $ledgerTableName = 'worker_transactions';

                    \App\Models\CompanyBalance::addExpense($discount, $validated['account_type'] ?? 'main');

                    $discountCat = \App\Models\TransactionCategory::firstOrCreate(['name' => 'Qorovul xarajati (chegirma)'], ['type' => 'expense']);
                    $financeTx = \App\Models\Transaction::create([
                        'type' => 'expense',
                        'amount' => $discount,
                        'payment_method' => $validated['payment_method'] ?? 'cash',
                        'account_type' => $validated['account_type'] ?? 'main',
                        'category_id' => $discountCat->id,
                        'category_name' => $discountCat->name,
                        'status' => 'approved',
                        'notes' => 'Qorovul xarajati (chegirma)',
                        'transaction_date' => $validated['date'],
                        'created_by' => auth()->id(),
                        'target_type' => 'worker',
                        'target_id' => $guard->id,
                    ]);
                if (isset($ledgerTxId) && isset($ledgerTableName)) { \Illuminate\Support\Facades\DB::table($ledgerTableName)->where('id', $ledgerTxId)->update(['finance_transaction_id' => $financeTx->id]); unset($ledgerTxId); unset($ledgerTableName); }
                }
            } else {
                \Illuminate\Support\Facades\DB::table('worker_transactions')->insert([
                    'worker_id' => $guard->id,
                    'type' => $validated['type'],
                    'amount' => $amount,
                    'description' => $validated['description'],
                    'date' => $validated['date'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($validated['type'] === 'salary_accrual') {
                    $guard->increment('balance', $amount);
                    \App\Models\WorkerSalaryHistory::create([
                        'worker_id' => $guard->id,
                        'salary' => $amount,
                        'effective_date' => $validated['date']
                    ]);
                } else {
                    $guard->decrement('balance', $amount);
                }
            }
        });

        return response()->json(['success' => true, 'message' => 'Tranzaksiya muvaffaqiyatli saqlandi.']);
    }

    public function storeKpiSettings(Request $request)
    {
        $validated = $request->validate([
            'comparison_type' => 'required|string',
            'start_val' => 'required|numeric',
            'end_val' => 'required|numeric',
            'bonus_amount' => 'required|numeric',
            'fixed_salary' => 'required|numeric'
        ]);

        \App\Models\CrmSetting::updateOrCreate(
            ['key' => 'kpi_settings'],
            ['value' => json_encode($validated)]
        );

        return redirect()->back()->with('message', 'KPI sozlamalari muvaffaqiyatli saqlandi.');
    }

    private function saveBase64File($base64String, $subDir = 'hr')
    {
        if (empty($base64String)) {
            return null;
        }

        // If it's already a saved path, return it as-is
        if (str_starts_with($base64String, '/storage/') || str_starts_with($base64String, 'storage/')) {
            return $base64String;
        }

        if (preg_match('/^data:([^;]+);base64,(.*)$/', $base64String, $matches)) {
            $mimeType = $matches[1];
            $data = $matches[2];
            
            $extensions = [
                'image/jpeg' => 'jpg',
                'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'application/pdf' => 'pdf',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            ];
            
            $ext = $extensions[$mimeType] ?? 'bin';
            $decodedData = base64_decode($data);
        } else {
            $decodedData = base64_decode($base64String, true);
            if ($decodedData === false) {
                return null;
            }
            $ext = 'jpg';
        }

        if (!$decodedData) {
            return null;
        }

        $fileName = $subDir . '/' . uniqid() . '_' . time() . '.' . $ext;
        \Illuminate\Support\Facades\Storage::disk('public')->put($fileName, $decodedData);
        
        return '/storage/' . $fileName;
    }

    public function getBrigadeWorks(\App\Models\Brigade $brigade)
    {
        $works = \App\Models\BrigadeWork::where('brigade_id', $brigade->id)
            ->with('transactions')
            ->orderBy('work_date', 'desc')
            ->get();
            
        // Add paid amount logic for legacy fallback if needed, but Vue uses the relation
        $works = $works->map(function($w) {
            $paid = collect($w->transactions)->sum('amount');
            $w->paid_amount = (float)$paid;
            $w->balance = (float)($w->calculated_sum - $paid);
            return $w;
        });
        return response()->json($works);
    }

    public function storeBrigadeWork(\Illuminate\Http\Request $request, \App\Models\Brigade $brigade)
    {
        $validated = $request->validate([
            'work_name' => 'required|string',
            'building' => 'nullable|string',
            'block' => 'nullable|string',
            'floor' => 'nullable|string',
            'unit' => 'nullable|string',
            'measure1' => 'nullable|numeric',
            'measure2' => 'nullable|numeric',
            'total_measure' => 'nullable|numeric',
            'quantity' => 'nullable|numeric',
            'total' => 'nullable|numeric',
            'price' => 'nullable|numeric',
            'calculated_sum' => 'required|numeric',
            'work_date' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        $validated['brigade_id'] = $brigade->id;
        $work = \App\Models\BrigadeWork::create($validated);

        // Update brigade balance
        $brigade->increment('balance', $work->calculated_sum);

        return response()->json($work);
    }

    public function updateBrigadeWork(\Illuminate\Http\Request $request, \App\Models\BrigadeWork $work)
    {
        $validated = $request->validate([
            'work_name' => 'required|string',
            'building' => 'nullable|string',
            'block' => 'nullable|string',
            'floor' => 'nullable|string',
            'unit' => 'nullable|string',
            'measure1' => 'nullable|numeric',
            'measure2' => 'nullable|numeric',
            'total_measure' => 'nullable|numeric',
            'quantity' => 'nullable|numeric',
            'total' => 'nullable|numeric',
            'price' => 'nullable|numeric',
            'calculated_sum' => 'required|numeric',
            'work_date' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        $oldSum = $work->calculated_sum;
        $work->update($validated);

        $diff = $work->calculated_sum - $oldSum;
        if ($diff != 0) {
            $work->brigade->increment('balance', $diff);
        }

        return response()->json($work);
    }

    public function deleteBrigadeWork(\App\Models\BrigadeWork $work)
    {
        $work->brigade->decrement('balance', $work->calculated_sum);
        $work->delete();
        return response()->json(['success' => true]);
    }

    public function getUnpaidWorks($type, $id)
    {
        try {
            $works = collect();

            if ($type === 'machine_owner') {
                $works = \App\Models\MachineWork::where('machine_owner_id', $id)->get();
                $works = $works->map(function($w) {
                    $paid = \App\Models\MachineOwnerTransaction::where('linked_work_type', \App\Models\MachineWork::class)
                                ->where('linked_work_id', $w->id)
                                ->sum('amount');
                    $w->paid_amount = (float)$paid;
                    $w->balance = (float)($w->total_price - $paid);
                    return $w;
                })->filter(fn($w) => $w->balance > 0)->values();
            } elseif ($type === 'brigade') {
                $works = \App\Models\BrigadeWork::where('brigade_id', $id)->get();
                $works = $works->map(function($w) {
                    $paid = \App\Models\BrigadeTransaction::where('linked_work_type', \App\Models\BrigadeWork::class)
                                ->where('linked_work_id', $w->id)
                                ->sum('amount');
                    $w->paid_amount = (float)$paid;
                    $w->balance = (float)($w->calculated_sum - $paid);
                    return $w;
                })->filter(fn($w) => $w->balance > 0)->values();
            } elseif ($type === 'worker') {
                $works = \App\Models\WorkLog::where('worker_id', $id)->get();
                $works = $works->map(function($w) {
                    $paid = \App\Models\WorkerTransaction::where('linked_work_type', \App\Models\WorkLog::class)
                                ->where('linked_work_id', $w->id)
                                ->sum('amount');
                    $w->paid_amount = (float)$paid;
                    $w->balance = (float)($w->calculated_cost - $paid);
                    return $w;
                })->filter(fn($w) => $w->balance > 0)->values();
            }

            return response()->json($works);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('getUnpaidWorks error: ' . $e->getMessage());
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    private function syncSalaryPeriods($employeeType, $employeeId, array $periods)
    {
        \App\Models\SalaryPeriod::where('employee_type', $employeeType)
            ->where('employee_id', $employeeId)
            ->delete();

        foreach ($periods as $period) {
            if (empty($period['start_month']) || empty($period['end_month']) || empty($period['salary'])) {
                continue;
            }

            $startDate = \Carbon\Carbon::createFromFormat('Y-m', $period['start_month'])->startOfMonth()->toDateString();
            $endDate = \Carbon\Carbon::createFromFormat('Y-m', $period['end_month'])->endOfMonth()->toDateString();

            \App\Models\SalaryPeriod::create([
                'employee_type' => $employeeType,
                'employee_id' => $employeeId,
                'salary' => $period['salary'],
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
        }
    }
}
