<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyBalance extends Model
{
    protected $fillable = ['total_balance', 'credit_balance', 'total_income', 'total_expense'];

    public static function addIncome($amount, $accountType = 'main')
    {
        $balance = self::firstOrCreate([]);
        $balance->total_income += $amount; // keep overall income stat

        if ($accountType === 'credit') {
            $balance->credit_balance += $amount;
        } else {
            $balance->total_balance += $amount;
        }
        
        $balance->save();
    }

    public static function addExpense($amount, $accountType = 'main')
    {
        $balance = self::firstOrCreate([]);
        $balance->total_expense += $amount; // keep overall expense stat
        
        if ($accountType === 'credit') {
            $balance->credit_balance -= $amount;
        } else {
            $balance->total_balance -= $amount;
        }
        
        $balance->save();
    }
}
