<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brigade extends Model
{
    protected $fillable = ['name', 'foreman_name', 'phone', 'balance'];

    public function workers()
    {
        return $this->hasMany(Worker::class);
    }

    public function transactions()
    {
        return $this->hasMany(BrigadeTransaction::class);
    }
}
