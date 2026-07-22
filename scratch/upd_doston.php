<?php
use App\Models\CashRegister;

$reg = CashRegister::where('name', 'like', '%UZOQOV DOSTON%')->first();
if ($reg) {
    $reg->initial_balance_uzs = 28299600.00;
    $reg->save();
    
    echo "SUCCESS!\n";
    echo "Name: {$reg->name}\n";
    echo "Initial Balance UZS: {$reg->initial_balance_uzs}\n";
    echo "Total Income UZS: {$reg->total_income_uzs}\n";
    echo "Total Expense UZS: {$reg->total_expense_uzs}\n";
    echo "Calculated Balance UZS: {$reg->balance_uzs}\n";
} else {
    echo "Register not found!\n";
}