<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Counterparty extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'phone',
        'email',
        'customable_type',
        'customable_id',
        'balance',
        'account_number',
        'bank_name',
        'inn',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function customable()
    {
        return $this->morphTo();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'counterparty_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'counterparty_id');
    }
}
