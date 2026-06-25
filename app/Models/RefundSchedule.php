<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefundSchedule extends Model
{
    protected $fillable = [
        'contract_id',
        'client_id',
        'amount',
        'paid_amount',
        'due_date',
        'status',
        'notes'
    ];

    protected static function booted()
    {
        static::saving(function ($schedule) {
            // Auto update status
            if ($schedule->paid_amount >= $schedule->amount) {
                $schedule->status = 'paid';
            } else {
                if (new \DateTime($schedule->due_date) < new \DateTime() && $schedule->paid_amount <= 0) {
                    $schedule->status = 'overdue';
                } elseif ($schedule->paid_amount > 0) {
                    $schedule->status = 'partial';
                } else {
                    $schedule->status = 'pending';
                }
            }
        });
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'refund_schedule_id');
    }

    public function getRemainingDebtAttribute()
    {
        return max(0, (float)$this->amount - (float)$this->paid_amount);
    }
}
