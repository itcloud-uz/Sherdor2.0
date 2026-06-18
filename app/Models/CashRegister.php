<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    protected $fillable = [
        'name',
        'type',
        'initial_balance_uzs',
        'initial_balance_usd',
        'initial_balance_eur',
        'currency',
        'balance',
        'building_id',
        'bank_name',
        'account_number',
        'mfo_code',
        'is_active',
        'parent_id',
        'creditor_name',
        'total_amount',
        'interest_rate',
        'term_months',
        'start_date',
        'end_date',
        'monthly_payment',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'initial_balance_uzs' => 'decimal:2',
        'initial_balance_usd' => 'decimal:2',
        'initial_balance_eur' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    protected $appends = ['balance_uzs', 'balance_usd', 'balance_eur', 'total_income_uzs', 'total_expense_uzs'];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function parent()
    {
        return $this->belongsTo(CashRegister::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(CashRegister::class, 'parent_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }

    public function getBalanceUzsAttribute()
    {
        $inflow = $this->transactions()->where('status', 'approved')->where('type', 'income')->where('currency', 'UZS')->sum('amount');
        $outflow = $this->transactions()->where('status', 'approved')->where('type', 'expense')->where('currency', 'UZS')->sum('amount');
        $own = (float)($this->initial_balance_uzs + $inflow - $outflow);
        $childrenSum = (float)$this->children()->where('is_active', true)->get()->sum('balance_uzs');
        return $own + $childrenSum;
    }

    public function getBalanceUsdAttribute()
    {
        $inflow = $this->transactions()->where('status', 'approved')->where('type', 'income')->where('currency', 'USD')->sum('amount');
        $outflow = $this->transactions()->where('status', 'approved')->where('type', 'expense')->where('currency', 'USD')->sum('amount');
        $own = (float)($this->initial_balance_usd + $inflow - $outflow);
        $childrenSum = (float)$this->children()->where('is_active', true)->get()->sum('balance_usd');
        return $own + $childrenSum;
    }

    public function getBalanceEurAttribute()
    {
        $inflow = $this->transactions()->where('status', 'approved')->where('type', 'income')->where('currency', 'EUR')->sum('amount');
        $outflow = $this->transactions()->where('status', 'approved')->where('type', 'expense')->where('currency', 'EUR')->sum('amount');
        $own = (float)($this->initial_balance_eur + $inflow - $outflow);
        $childrenSum = (float)$this->children()->where('is_active', true)->get()->sum('balance_eur');
        return $own + $childrenSum;
    }

    public function getTotalIncomeUzsAttribute()
    {
        $own = $this->transactions()->where('status', 'approved')->where('type', 'income')->where('currency', 'UZS')->sum('amount');
        $childrenSum = (float)$this->children()->where('is_active', true)->get()->sum('total_income_uzs');
        return $own + $childrenSum;
    }

    public function getTotalExpenseUzsAttribute()
    {
        $own = $this->transactions()->where('status', 'approved')->where('type', 'expense')->where('currency', 'UZS')->sum('amount');
        $childrenSum = (float)$this->children()->where('is_active', true)->get()->sum('total_expense_uzs');
        return $own + $childrenSum;
    }
}
