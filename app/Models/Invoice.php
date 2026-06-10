<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_number',
        'counterparty_id',
        'building_id',
        'amount',
        'paid_amount',
        'currency',
        'due_date',
        'status',
        'type',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function counterparty()
    {
        return $this->belongsTo(Counterparty::class);
    }

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'invoice_id');
    }
}
