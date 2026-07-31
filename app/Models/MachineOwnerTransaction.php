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

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function machineOwner()
    {
        return $this->belongsTo(MachineOwner::class);
    }

    public function linked_work()
    {
        return $this->morphTo(__FUNCTION__, 'linked_work_type', 'linked_work_id');
    }
}
