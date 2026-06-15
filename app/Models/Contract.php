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
        'total_penalty_accumulated',
        'contract_number',
        'partner_id',
        'floor',
        'rooms',
        'payment_type'
    ];

    protected $casts = [
        'ai_verification_logs' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public function getBuyerAttribute()
    {
        if ($this->client_id && $this->client) {
            return $this->client;
        }

        if ($this->partner_id && $this->partner && $this->partner->user) {
            $user = $this->partner->user;
            // Create a pseudo-client object to prevent template errors
            return (object)[
                'first_name' => $user->name,
                'last_name' => '',
                'full_name' => $user->name,
                'address' => 'Ta\'sischi ulushi',
                'pinfl' => $user->phone ?? '_______',
                'passport_issued_date' => null,
                'passport_issued_by' => 'Ta\'sischi',
                'passport_number' => '_______',
                'passport_series' => '_______',
                'phone' => $user->phone ?? '_______',
            ];
        }

        return (object)[
            'first_name' => 'Noma\'lum',
            'last_name' => '',
            'full_name' => 'Noma\'lum Xaridor',
            'address' => '_______',
            'pinfl' => '_______',
            'passport_issued_date' => null,
            'passport_issued_by' => '_______',
            'passport_number' => '_______',
            'passport_series' => '_______',
            'phone' => '_______',
        ];
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function paymentSchedules()
    {
        return $this->hasMany(PaymentSchedule::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
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

    public function generatePaymentSchedules()
    {
        if ($this->paymentSchedules()->count() > 0) {
            return;
        }

        if (($this->payment_type ?? 'installment') === 'installment' && ($this->term_months ?? 1) > 0) {
            $remaining = $this->total_amount - $this->down_payment;
            if ($remaining > 0) {
                $monthly = $remaining / $this->term_months;
                $baseDate = $this->created_at ? \Carbon\Carbon::parse($this->created_at) : now();
                for ($i = 1; $i <= $this->term_months; $i++) {
                    $dueDate = $baseDate->copy()->addMonths($i);
                    $status = $dueDate->isPast() ? 'overdue' : 'pending';
                    
                    \App\Models\PaymentSchedule::create([
                        'contract_id' => $this->id,
                        'expected_amount' => $monthly,
                        'remaining_debt' => $monthly,
                        'due_date' => $dueDate,
                        'status' => $status
                    ]);
                }
            }
        }
    }

    public function markAsActiveSold()
    {
        if ($this->ai_verification_status === 'passed') {
            $this->update(['status' => 'active']);
            
            if ($this->unit) {
                $this->unit->update(['status' => 'sold']);
            }

            // Generate Payment Schedules
            $this->generatePaymentSchedules();

            // Deduct purchase price from client counterparty balance
            if ($this->client_id) {
                $counterparty = \App\Models\Counterparty::where([
                    'customable_type' => 'App\Models\Client',
                    'customable_id' => $this->client_id,
                ])->first();
                if ($counterparty) {
                    $counterparty->decrement('balance', $this->total_amount);
                }
            }

            // Auto-approve any pending transactions associated with this contract
            $pendingTransactions = \App\Models\Transaction::where('contract_id', $this->id)->where('status', 'pending')->get();
            $financeController = app(\App\Http\Controllers\Admin\FinanceController::class);
            foreach ($pendingTransactions as $tx) {
                $financeController->approveTransaction(request(), $tx);
            }
        }
    }

    public function cancelContract()
    {
        $this->update(['status' => 'cancelled']);
        
        if ($this->unit) {
            $this->unit->update(['status' => 'available']);
        }

        if ($this->client_id) {
            $counterparty = \App\Models\Counterparty::where([
                'customable_type' => 'App\Models\Client',
                'customable_id' => $this->client_id,
            ])->first();

            if ($counterparty) {
                // Revert the purchase price debt
                $counterparty->increment('balance', $this->total_amount);
            }
        }
    }
}
