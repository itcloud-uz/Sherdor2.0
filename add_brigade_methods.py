with open(r'd:\Sherdor 2.0\app\Http\Controllers\Admin\HrController.php', 'r', encoding='utf-8') as f:
    text = f.read()

last_brace = text.rfind('}')

methods = """
    // --- BRIGADE LEDGER LOGIC ---
    public function getBrigadeLedger(\\App\\Models\\Brigade $brigade)
    {
        $workerIds = $brigade->workers()->pluck('id')->toArray();
        $logs = \\App\\Models\\WorkLog::with(['building', 'machinery'])
                    ->where('type', 'worker')
                    ->whereIn('worker_id', $workerIds)
                    ->orderBy('date', 'desc')
                    ->get();
                    
        $transactions = \\App\\Models\\BrigadeTransaction::where('brigade_id', $brigade->id)
                            ->orderBy('date', 'desc')
                            ->get();
                            
        return response()->json([
            'balance' => $brigade->balance,
            'logs' => $logs,
            'transactions' => $transactions
        ]);
    }

    public function storeBrigadeTransaction(\\Illuminate\\Http\\Request $request, \\App\\Models\\Brigade $brigade)
    {
        $validated = $request->validate([
            'type' => 'required|in:salary_accrual,advance_payment,penalty,payment',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
            'date' => 'required|date'
        ]);

        \\Illuminate\\Support\\Facades\\DB::transaction(function () use ($brigade, $validated) {
            \\App\\Models\\BrigadeTransaction::create([
                'brigade_id' => $brigade->id,
                'type' => $validated['type'],
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'date' => $validated['date']
            ]);

            if ($validated['type'] === 'salary_accrual') {
                $brigade->increment('balance', $validated['amount']);
            } else {
                $brigade->decrement('balance', $validated['amount']);
                if ($validated['type'] === 'payment') {
                    \\App\\Models\\CompanyBalance::addExpense($validated['amount']);
                }
            }
        });

        return redirect()->back()->with('message', 'Tranzaksiya muvaffaqiyatli saqlandi.');
    }

    public function exportBrigadeLedger(\\App\\Models\\Brigade $brigade)
    {
        return \\Maatwebsite\\Excel\\Facades\\Excel::download(new \\App\\Exports\\BrigadeLedgerExport($brigade), 'brigada_jurnali_' . $brigade->name . '_' . date('Y_m_d') . '.xlsx');
    }
"""

new_text = text[:last_brace] + methods + "\n}\n"

with open(r'd:\Sherdor 2.0\app\Http\Controllers\Admin\HrController.php', 'w', encoding='utf-8') as f:
    f.write(new_text)

print('Added Brigade Ledger methods')
