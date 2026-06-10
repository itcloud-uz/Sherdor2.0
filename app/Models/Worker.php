<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    protected $fillable = ['name', 'type', 'rate', 'phone', 'role', 'salary_type', 'base_rate', 'building_id', 'floor_id', 'unit_id', 'photo_path', 'brigade_id', 'passport', 'pinfl', 'gender', 'birth_date', 'employment_date', 'status', 'specialty_id', 'contract_type', 'characteristics', 'passport_front', 'passport_back', 'additional_documents'];

    public function workLogs()
    {
        return $this->hasMany(WorkLog::class);
    }

    public function building() { return $this->belongsTo(Building::class); }
    public function floor() { return $this->belongsTo(Floor::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function brigade() { return $this->belongsTo(Brigade::class); }

    public function salaryHistories()
    {
        return $this->hasMany(WorkerSalaryHistory::class)->orderBy('effective_date', 'desc');
    }

    protected static function booted()
    {
        static::saved(function ($worker) {
            \App\Models\Counterparty::updateOrCreate(
                [
                    'customable_type' => get_class($worker),
                    'customable_id' => $worker->id,
                ],
                [
                    'name' => $worker->name ?: "Xodim #{$worker->id}",
                    'type' => 'employee',
                    'phone' => $worker->phone,
                ]
            );
        });

        static::deleted(function ($worker) {
            \App\Models\Counterparty::where([
                'customable_type' => get_class($worker),
                'customable_id' => $worker->id,
            ])->delete();
        });
    }

    public function transactions()
    {
        return $this->hasMany(WorkerTransaction::class);
    }
}
