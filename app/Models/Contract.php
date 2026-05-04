<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'client_id',
        'unit_id',
        'contract_type',
        'price_per_sqm',
        'total_amount',
        'down_payment',
        'term_months',
        'status',
        'pdf_path',
        'signed_pdf_path',
        'ai_verification_status',
        'ai_verification_logs',
        'daily_penalty_rate',
        'total_penalty_accumulated'
    ];

    protected $casts = [
        'ai_verification_logs' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function paymentSchedules()
    {
        return $this->hasMany(PaymentSchedule::class);
    }

    // --- Strict Financial Pipeline (State Machine) ---

    public function generateDraft()
    {
        $this->update(['status' => 'draft']);
        
        if ($this->unit) {
            $this->unit->update(['status' => 'reserved']);
        }
    }

    public function markAwaitingPayment()
    {
        if ($this->status === 'draft') {
            $this->update(['status' => 'awaiting_payment']);
        }
    }

    public function markAwaitingSignature()
    {
        if ($this->status === 'awaiting_payment') {
            $this->update(['status' => 'awaiting_signature']);
        }
    }

    public function markAsActiveSold()
    {
        if ($this->status === 'awaiting_signature' && $this->ai_verification_status === 'passed') {
            $this->update(['status' => 'active']);
            
            if ($this->unit) {
                $this->unit->update(['status' => 'sold']);
            }
        }
    }
}
