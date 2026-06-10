<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoneyTransfer extends Model
{
    protected $fillable = [
        'from_account_id',
        'to_account_id',
        'sent_amount',
        'sent_currency',
        'exchange_rate',
        'received_amount',
        'received_currency',
        'description',
    ];

    protected $casts = [
        'sent_amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
    ];

    public function fromAccount()
    {
        return $this->belongsTo(CashRegister::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(CashRegister::class, 'to_account_id');
    }
}
