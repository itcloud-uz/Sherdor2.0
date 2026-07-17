<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionImport extends Model
{
    protected $fillable = [
        'file_name',
        'account_id',
        'building_id',
        'comment',
        'row_count',
        'created_by'
    ];

    public function account()
    {
        return $this->belongsTo(CashRegister::class, 'account_id');
    }

    public function building()
    {
        return $this->belongsTo(Building::class, 'building_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'transaction_import_id');
    }
}
