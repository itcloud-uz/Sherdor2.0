<?php
use App\Models\CashRegister;
use App\Models\Transaction;

$reg = CashRegister::where('name', 'like', '%UZOQOV%')->first();
if (!$reg) {
    echo "Register not found\n";
    exit;
}

echo "Register ID: {$reg->id}\n";
echo "Name: {$reg->name}\n";
echo "Initial Balance UZS: {$reg->initial_balance_uzs}\n";
echo "Balance UZS (Attribute): {$reg->balance_uzs}\n";
echo "Total Income UZS: {$reg->total_income_uzs}\n";
echo "Total Expense UZS: {$reg->total_expense_uzs}\n";

$txs = Transaction::where('account_id', $reg->id)->get();
echo "\nTransactions count: " . $txs->count() . "\n";
foreach ($txs as $t) {
    echo "TX #{$t->id} | Type: {$t->type} | Amount: {$t->amount} {$t->currency} | Status: {$t->status} | Date: {$t->transaction_date} | Notes: {$t->notes}\n";
}