<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    protected $fillable = ['total_amount', 'transaction_id', 'status'];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function workLogs()
    {
        return $this->hasMany(WorkLog::class);
    }
}
