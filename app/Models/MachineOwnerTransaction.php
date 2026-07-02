<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MachineOwnerTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'machine_owner_id',
        'type',
        'amount',
        'description',
        'date',
        'linked_work_type',
        'linked_work_id',
    ];

    public function machineOwner()
    {
        return $this->belongsTo(MachineOwner::class);
    }
}
