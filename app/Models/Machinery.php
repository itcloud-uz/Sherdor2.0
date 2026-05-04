<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Machinery extends Model
{
    protected $fillable = [
        'name', 'license_plate', 'cost_per_hour',
        'owner_name', 'machinery_type', 'payment_type', 'balance'
    ];

    public function workLogs()
    {
        return $this->hasMany(WorkLog::class);
    }

    public function transactions()
    {
        return $this->hasMany(MachineryTransaction::class);
    }
}
