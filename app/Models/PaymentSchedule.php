<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSchedule extends Model
{
    protected $fillable = [
        'contract_id',
        'expected_amount',
        'paid_amount',
        'due_date',
        'status',
        'remaining_debt',
        'penalty_for_this_month'
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
}
