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

    protected static function booted()
    {
        static::saving(function ($schedule) {
            $schedule->remaining_debt = max(0, (float)$schedule->expected_amount - (float)$schedule->paid_amount);
            
            // Auto update status to overdue if unpaid and due date is in the past
            if ($schedule->status !== 'paid' && new \DateTime($schedule->due_date) < new \DateTime()) {
                $schedule->status = 'overdue';
            }
        });
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'payment_schedule_id');
    }

    public function getRemainingDebtAttribute()
    {
        return max(0, (float)$this->expected_amount - (float)$this->paid_amount);
    }

    public function getStatusAttribute($value)
    {
        if ($value !== 'paid' && $this->due_date && new \DateTime($this->due_date) < new \DateTime()) {
            return 'overdue';
        }
        return $value;
    }
}
