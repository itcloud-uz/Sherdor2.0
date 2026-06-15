<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Inertia\Inertia;

class PublicReceiptController extends Controller
{
    public function verify($hash)
    {
        $transaction = Transaction::with([
            'creator', 
            'approver', 
            'paymentSchedule.contract.client', 
            'paymentSchedule.contract.unit', 
            'contract.client', 
            'contract.unit',
            'client'
        ])->where('receipt_hash', $hash)->firstOrFail();

        $client = $transaction->client ?? $transaction->contract?->client ?? $transaction->paymentSchedule?->contract?->client;

        // Check if user has authenticated for this specific receipt
        $authKey = 'receipt_auth_' . $transaction->id;
        
        if (!session($authKey)) {
            if (!$client) {
                return Inertia::render('Public/ReceiptAuth', [
                    'hash' => $hash,
                    'is_internal' => true
                ]);
            } else {
                $firstName = $client->first_name ?? '';
                $lastName = $client->last_name ?? '';
                $maskedFirst = $firstName !== '' ? mb_substr($firstName, 0, 1, 'UTF-8') . '***' : '***';
                $maskedLast = $lastName !== '' ? mb_substr($lastName, 0, 1, 'UTF-8') . '***' : '***';
                
                return Inertia::render('Public/ReceiptAuth', [
                    'hash' => $hash,
                    'is_internal' => false,
                    'client_name_masked' => $maskedFirst . ' ' . $maskedLast
                ]);
            }
        }

        // Calculate contract totals if applicable
        $contractDetails = null;
        $contract = $transaction->contract ?? $transaction->paymentSchedule?->contract;
        
        if ($contract) {
            $totalContractPrice = $contract->total_price ?? 0;
            $totalPaid = \App\Models\PaymentSchedule::where('contract_id', $contract->id)->where('status', 'paid')->sum('paid_amount');
            
            $contractDetails = [
                'total_price' => $totalContractPrice,
                'total_paid' => $totalPaid,
                'remaining' => max(0, $totalContractPrice - $totalPaid),
                'unit' => $contract->unit ? $contract->unit->number : 'N/A'
            ];
        }

        return Inertia::render('Public/ReceiptVerification', [
            'transaction' => $transaction,
            'contractDetails' => $contractDetails
        ]);
    }

    public function authenticate(\Illuminate\Http\Request $request, $hash)
    {
        $transaction = Transaction::with(['paymentSchedule.contract.client', 'contract.client', 'client'])->where('receipt_hash', $hash)->firstOrFail();
        $client = $transaction->client ?? $transaction->contract?->client ?? $transaction->paymentSchedule?->contract?->client;

        if (!$client) {
            // Internal Employee Authentication
            $request->validate([
                'email' => 'required|string',
                'password' => 'required|string',
            ]);
            
            // Check credentials against the users table
            $credentials = $request->only('email', 'password');
            if (auth()->attempt($credentials)) {
                session(['receipt_auth_' . $transaction->id => true]);
                return redirect()->route('public.receipt.verify', $hash);
            }
            
            return back()->withErrors(['message' => 'Login (Elektron pochta) yoki parol xato.']);
        }

        // Client Authentication
        $request->validate([
            'phone' => 'required|string',
            'passport' => 'required|string',
        ]);

        $inputPhone = preg_replace('/[^0-9]/', '', $request->phone);
        $clientPhone = preg_replace('/[^0-9]/', '', $client->phone);
        
        $inputPassport = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $request->passport));
        $clientPassport = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $client->passport_number));

        if (strlen($inputPhone) >= 7 && strlen($inputPassport) >= 5 && str_ends_with($clientPhone, $inputPhone) && $inputPassport === $clientPassport) {
            session(['receipt_auth_' . $transaction->id => true]);
            return redirect()->route('public.receipt.verify', $hash);
        }

        return back()->withErrors(['message' => 'Telefon raqami yoki pasport ma\'lumotlari xato.']);
    }
}
