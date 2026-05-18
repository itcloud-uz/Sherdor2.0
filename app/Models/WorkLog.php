<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkLog extends Model
{
    protected $fillable = [
        'type', 'worker_id', 'machinery_id', 'building_id', 'floor_id', 'unit_id', 'hours_worked', 
        'volume_done', 'measurement_unit', 'work_category', 'rate', 'calculated_cost', 'deductions', 'date', 'payroll_id', 'status'
    ];

    public function worker()
    {
        return $this->belongsTo(Worker::class);
    }

    public function machinery()
    {
        return $this->belongsTo(Machinery::class);
    }

    public function building()
    {
        return $this->belongsTo(\App\Models\Building::class);
    }

    public function floor()
    {
        return $this->belongsTo(\App\Models\Floor::class);
    }

    public function unit()
    {
        return $this->belongsTo(\App\Models\Unit::class);
    }

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
