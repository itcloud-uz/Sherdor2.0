<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineOwner extends Model
{
    protected $fillable = ['name', 'phone', 'balance'];

    public function machineries()
    {
        return $this->hasMany(Machinery::class);
    }
}
