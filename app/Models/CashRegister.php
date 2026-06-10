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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'initial_balance_uzs' => 'decimal:2',
        'initial_balance_usd' => 'decimal:2',
        'initial_balance_eur' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    protected $appends = ['balance_uzs', 'balance_usd', 'balance_eur'];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }

    public function getBalanceUzsAttribute()
    {
        $inflow = $this->transactions()->where('status', 'approved')->where('type', 'income')->where('currency', 'UZS')->sum('amount');
        $outflow = $this->transactions()->where('status', 'approved')->where('type', 'expense')->where('currency', 'UZS')->sum('amount');
        return (float)($this->initial_balance_uzs + $inflow - $outflow);
    }

    public function getBalanceUsdAttribute()
    {
        $inflow = $this->transactions()->where('status', 'approved')->where('type', 'income')->where('currency', 'USD')->sum('amount');
        $outflow = $this->transactions()->where('status', 'approved')->where('type', 'expense')->where('currency', 'USD')->sum('amount');
        return (float)($this->initial_balance_usd + $inflow - $outflow);
    }

    public function getBalanceEurAttribute()
    {
        $inflow = $this->transactions()->where('status', 'approved')->where('type', 'income')->where('currency', 'EUR')->sum('amount');
        $outflow = $this->transactions()->where('status', 'approved')->where('type', 'expense')->where('currency', 'EUR')->sum('amount');
        return (float)($this->initial_balance_eur + $inflow - $outflow);
    }
}
