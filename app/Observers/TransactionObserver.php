<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Models\FinanceAction;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        FinanceAction::create([
            'user_id' => auth()->id(),
            'action' => 'create',
            'transaction_id' => $transaction->id,
            'transaction_type' => $transaction->type,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency ?? 'UZS',
            'notes' => "Tranzaksiya qo'shildi: #" . $transaction->id . ($transaction->notes ? " - " . $transaction->notes : ""),
            'details' => $transaction->toArray(),
        ]);
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        // If it's a soft-delete or restore, it's handled by deleted/restored events
        if ($transaction->isDirty('deleted_at')) {
            return;
        }

        FinanceAction::create([
            'user_id' => auth()->id(),
            'action' => 'update',
            'transaction_id' => $transaction->id,
            'transaction_type' => $transaction->type,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency ?? 'UZS',
            'notes' => "Tranzaksiya tahrirlandi: #" . $transaction->id,
            'details' => [
                'old' => array_intersect_key($transaction->getOriginal(), $transaction->getDirty()),
                'new' => $transaction->getDirty(),
            ],
        ]);
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {
        FinanceAction::create([
            'user_id' => auth()->id(),
            'action' => 'delete',
            'transaction_id' => $transaction->id,
            'transaction_type' => $transaction->type,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency ?? 'UZS',
            'notes' => "Tranzaksiya o'chirildi (Savatchaga yuborildi): #" . $transaction->id,
            'details' => $transaction->toArray(),
        ]);
    }

    /**
     * Handle the Transaction "restored" event.
     */
    public function restored(Transaction $transaction): void
    {
        FinanceAction::create([
            'user_id' => auth()->id(),
            'action' => 'restore',
            'transaction_id' => $transaction->id,
            'transaction_type' => $transaction->type,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency ?? 'UZS',
            'notes' => "Tranzaksiya qayta tiklandi: #" . $transaction->id,
            'details' => $transaction->toArray(),
        ]);
    }
}
