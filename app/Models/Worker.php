<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    protected $fillable = ['name', 'type', 'rate', 'phone', 'role', 'salary_type', 'base_rate', 'building_id', 'floor_id', 'unit_id', 'photo_path', 'brigade_id'];

    public function workLogs()
    {
        return $this->hasMany(WorkLog::class);
    }

    public function building() { return $this->belongsTo(Building::class); }
    public function floor() { return $this->belongsTo(Floor::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function brigade() { return $this->belongsTo(Brigade::class); }
}
