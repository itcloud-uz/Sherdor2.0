<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiabilityPayment extends Model
{
    protected $fillable = [
        'financial_liability_id', 'amount', 'payment_date', 'status', 'transaction_id', 'notes'
    ];

    public function liability()
    {
        return $this->belongsTo(FinancialLiability::class, 'financial_liability_id');
    }
}
