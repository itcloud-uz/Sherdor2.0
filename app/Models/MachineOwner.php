<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineOwner extends Model
{
    protected $fillable = ['name', 'phone', 'balance'];

    protected static function booted()
    {
        static::saved(function ($owner) {
            \App\Models\Counterparty::updateOrCreate(
                [
                    'customable_type' => get_class($owner),
                    'customable_id' => $owner->id,
                ],
                [
                    'name' => $owner->name,
                    'phone' => $owner->phone ?? 'N/A',
                    'type' => 'supplier', // Assuming they provide machinery service
                    'balance' => $owner->balance ?? 0,
                    'is_active' => true,
                ]
            );
        });
    }

    public function machineries()
    {
        return $this->hasMany(Machinery::class);
    }

    public function works()
    {
        return $this->hasMany(MachineWork::class, 'machine_owner_id');
    }

    public function transactions()
    {
        return $this->hasMany(MachineOwnerTransaction::class);
    }
}
