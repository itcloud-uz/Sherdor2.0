<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $appends = ['category_display'];

    public function getCategoryDisplayAttribute()
    {
        if ($this->relationLoaded('category') && $this->getRelation('category')) {
            return $this->getRelation('category')->name;
        }

        if (!empty($this->attributes['category_name'])) {
            return $this->attributes['category_name'];
        }

        if (!empty($this->attributes['category'])) {
            return $this->attributes['category'];
        }

        return null;
    }

    protected $fillable = [
        'type', 'amount', 'currency', 'payment_method', 'status',
        'payment_schedule_id', 'created_by', 'approved_by', 'receipt_hash', 'notes', 'description',
        'transaction_hash', 'category', 'category_id', 'transaction_date', 'attachment_path', 'contract_id', 'client_id', 'account_type', 'partner_id', 'source',
        'worker_id', 'machinery_id', 'building_id', 'supplier_id', 'latitude', 'longitude', 'work_category',
        'target_type', 'target_id', 'account_id', 'counterparty_id', 'invoice_id', 'refund_schedule_id',
        'linked_work_type', 'linked_work_id', 'transaction_import_id'
    ];

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function client()
    {
        return $this->belongsTo(\App\Models\Client::class);
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

    public function category()
    {
        return $this->belongsTo(\App\Models\TransactionCategory::class, 'category_id');
    }

    public function cashRegister()
    {
        return $this->belongsTo(\App\Models\CashRegister::class, 'account_id');
    }

    public function counterparty()
    {
        return $this->belongsTo(\App\Models\Counterparty::class, 'counterparty_id');
    }

    public function invoice()
    {
        return $this->belongsTo(\App\Models\Invoice::class, 'invoice_id');
    }

    public function building()
    {
        return $this->belongsTo(\App\Models\Building::class);
    }

    public function refundSchedule()
    {
        return $this->belongsTo(\App\Models\RefundSchedule::class, 'refund_schedule_id');
    }

    public function partner()
    {
        return $this->belongsTo(\App\Models\Partner::class);
    }

    public function worker()
    {
        return $this->belongsTo(\App\Models\Worker::class);
    }

    public function supplier()
    {
        return $this->belongsTo(\App\Models\Supplier::class);
    }

    public function brigade()
    {
        return $this->belongsTo(\App\Models\Brigade::class);
    }

    public function linkedWork()
    {
        return $this->morphTo(__FUNCTION__, 'linked_work_type', 'linked_work_id');
    }

    public function import()
    {
        return $this->belongsTo(TransactionImport::class, 'transaction_import_id');
    }
}
