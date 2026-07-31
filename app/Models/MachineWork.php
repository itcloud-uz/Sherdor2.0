<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MachineWork extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function owner()
    {
        return $this->belongsTo(MachineOwner::class, 'machine_owner_id');
    }

    public function machinery()
    {
        return $this->belongsTo(Machinery::class);
    }

    public function transactions()
    {
        return $this->morphMany(MachineOwnerTransaction::class, 'linked_work');
    }
}
