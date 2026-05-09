<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'type', 'amount', 'currency', 'payment_method', 'status',
        'payment_schedule_id', 'created_by', 'approved_by', 'receipt_hash', 'notes',
        'transaction_hash', 'category', 'category_id', 'transaction_date', 'attachment_path', 'contract_id', 'account_type', 'partner_id', 'source',
        'worker_id', 'machinery_id', 'building_id', 'supplier_id'
    ];

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function paymentSchedule()
    {
        return $this->belongsTo(\App\Models\PaymentSchedule::class);
    }

    public function contract()
    {
        return $this->belongsTo(\App\Models\Contract::class);
    }
}
