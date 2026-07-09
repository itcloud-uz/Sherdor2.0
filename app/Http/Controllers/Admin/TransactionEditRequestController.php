<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionEditRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Admin\FinanceController;

class TransactionEditRequestController extends Controller
{
    public function index()
    {
        $requests = TransactionEditRequest::with(['transaction.counterparty', 'transaction.account', 'requester', 'resolver'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Admin/Finance/TransactionRequests', [
            'requests' => $requests
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:transactions,id',
            'action' => 'required|in:edit,delete',
            'reason' => 'required|string',
            'requested_changes' => 'nullable|array'
        ]);

        $transaction = Transaction::findOrFail($validated['transaction_id']);

        if ($transaction->status !== 'approved' && $transaction->status !== 'pending') {
             return redirect()->back()->withErrors(['error' => 'Bu tranzaksiya allaqachon bekor qilingan yoki rad etilgan.']);
        }

        TransactionEditRequest::create([
            'transaction_id' => $transaction->id,
            'requested_by' => auth()->id(),
            'action' => $validated['action'],
            'reason' => $validated['reason'],
            'requested_changes' => $validated['requested_changes'] ?? null,
            'status' => 'pending'
        ]);

        return redirect()->back()->with('message', 'So\'rov adminga muvaffaqiyatli yuborildi.');
    }

    public function approve(TransactionEditRequest $transactionRequest)
    {
        if ($transactionRequest->status !== 'pending') {
            return redirect()->back()->withErrors(['error' => 'So\'rov allaqachon ko\'rib chiqilgan.']);
        }

        DB::beginTransaction();
        try {
            $transaction = $transactionRequest->transaction;
            
            if ($transactionRequest->action === 'delete') {
                $financeController = app(FinanceController::class);
                $financeController->destroy($transaction);
            } elseif ($transactionRequest->action === 'edit' && $transactionRequest->requested_changes) {
                // Not fully implemented automatic edit yet, because edit might require changing categories or accounts.
                // For now, we will mark it as approved, and the admin has to manually edit the transaction.
                // We'll update the request status only.
            }

            $transactionRequest->update([
                'status' => 'approved',
                'resolved_by' => auth()->id(),
                'resolved_at' => now()
            ]);

            DB::commit();
            return redirect()->back()->with('message', 'So\'rov tasdiqlandi va amalga oshirildi.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Xatolik yuz berdi: ' . $e->getMessage()]);
        }
    }

    public function reject(TransactionEditRequest $transactionRequest)
    {
        if ($transactionRequest->status !== 'pending') {
            return redirect()->back()->withErrors(['error' => 'So\'rov allaqachon ko\'rib chiqilgan.']);
        }

        $transactionRequest->update([
            'status' => 'rejected',
            'resolved_by' => auth()->id(),
            'resolved_at' => now()
        ]);

        return redirect()->back()->with('message', 'So\'rov rad etildi.');
    }
}
