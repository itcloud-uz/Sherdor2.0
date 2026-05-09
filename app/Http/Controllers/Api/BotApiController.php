<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class BotApiController extends Controller
{
    /**
     * 1. Verify user by Telegram Chat ID
     */
    public function verifyUser(Request $request)
    {
        $request->validate([
            'chat_id' => 'required|string',
        ]);

        $user = User::where('telegram_chat_id', $request->chat_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found for this chat ID.'], 404);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'roles' => $user->getRoleNames(),
                'balance' => $user->balance,
            ]
        ]);
    }

    /**
     * 2. Create a new transaction from Telegram
     */
    public function createTransaction(Request $request)
    {
        $request->validate([
            'chat_id' => 'required|string',
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string',
            'description' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'account_type' => 'nullable|string',
            'category' => 'nullable|string',
            'source' => 'nullable|string'
        ]);

        $user = User::where('telegram_chat_id', $request->chat_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $transaction = Transaction::create([
            'type' => $request->type,
            'amount' => $request->amount,
            'currency' => $request->currency ?? 'UZS',
            'payment_method' => $request->payment_method ?? 'cash',
            'account_type' => $request->account_type ?? 'main',
            'category' => $request->category ?? 'Telegram Bot',
            'notes' => $request->description,
            'created_by' => $user->id,
            'status' => 'pending',
            'source' => $request->source ?? 'telegram',
            'transaction_date' => now()->toDateString(),
        ]);

        return response()->json([
            'message' => 'Transaction created successfully and is pending approval.',
            'transaction_id' => $transaction->id
        ]);
    }

    /**
     * 3. Get list of pending transactions
     */
    public function getPendingTransactions(Request $request)
    {
        // Add chat_id to verify if this user is allowed to see pending, if needed
        $transactions = Transaction::with('creator:id,name')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'transactions' => $transactions
        ]);
    }

    /**
     * 4. Approve transaction
     */
    public function approveTransaction(Request $request)
    {
        $request->validate([
            'chat_id' => 'required|string',
            'transaction_id' => 'required|integer|exists:transactions,id',
        ]);

        $user = User::where('telegram_chat_id', $request->chat_id)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        // Ideally, check if $user has permission to approve (e.g., hasRole('admin'))
        if (!$user->hasRole('admin') && !$user->hasRole('super-admin')) {
            return response()->json(['error' => 'Permission denied. Only admins can approve.'], 403);
        }

        $transaction = Transaction::findOrFail($request->transaction_id);

        if ($transaction->status !== 'pending') {
            return response()->json(['error' => 'Transaction is not pending.'], 400);
        }

        // We use the auth() system inside FinanceController, so we need to manually login the user temporarily
        // so that FinanceController can use auth()->id() as the approver.
        auth()->login($user);

        $response = app(\App\Http\Controllers\Admin\FinanceController::class)->approveTransaction($transaction);

        auth()->logout();

        if ($response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }

        return response()->json([
            'message' => 'Transaction approved successfully.',
            'transaction_id' => $transaction->id
        ]);
    }
}
