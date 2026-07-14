<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryPeriod extends Model
{
    protected $fillable = [
        'employee_type',
        'employee_id',
        'salary',
        'start_date',
        'end_date'
    ];

    public function employee()
    {
        return $this->morphTo();
    }
}
