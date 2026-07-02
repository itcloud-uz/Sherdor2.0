<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MachineWork extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'paid' => 'decimal:2',
        'fuel' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function owner()
    {
        return $this->belongsTo(MachineOwner::class, 'machine_owner_id');
    }

    public function machinery()
    {
        return $this->belongsTo(Machinery::class);
    }
}
