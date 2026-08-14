<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\MoneyTransfer;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $transferCategory = TransactionCategory::where('name', 'Ichki O\'tkazma')->first();
        if (!$transferCategory) return;

        $transfers = MoneyTransfer::all();
        foreach ($transfers as $transfer) {
            // Find orphaned expense transaction
            $expense = Transaction::where('category_id', $transferCategory->id)
                ->where('account_id', $transfer->from_account_id)
                ->where('type', 'expense')
                ->where('amount', $transfer->sent_amount)
                ->whereNull('money_transfer_id')
                ->whereBetween('created_at', [
                    $transfer->created_at->copy()->subMinutes(5), 
                    $transfer->created_at->copy()->addMinutes(5)
                ])
                ->first();

            if ($expense) {
                $expense->update(['money_transfer_id' => $transfer->id]);
                // If there are duplicates created by updateOrCreate due to the bug, we can optionally delete them, but it's safer to just link one and let user delete duplicates if they exist.
            }

            // Find orphaned income transaction
            $income = Transaction::where('category_id', $transferCategory->id)
                ->where('account_id', $transfer->to_account_id)
                ->where('type', 'income')
                ->where('amount', $transfer->received_amount)
                ->whereNull('money_transfer_id')
                ->whereBetween('created_at', [
                    $transfer->created_at->copy()->subMinutes(5), 
                    $transfer->created_at->copy()->addMinutes(5)
                ])
                ->first();

            if ($income) {
                $income->update(['money_transfer_id' => $transfer->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed, this is a data fix
    }
};
