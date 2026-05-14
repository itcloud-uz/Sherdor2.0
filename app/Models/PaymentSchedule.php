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
        'penalty_for_this_month',
        'last_sms_sent_at',
        'payment_method',
        'actual_payment_date',
        'original_payment_amount'
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'payment_schedule_id');
    }
}
