<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MachineryTransaction extends Model
{
    protected $fillable = ['machinery_id', 'type', 'amount', 'description', 'date'];

    public function machinery()
    {
        return $this->belongsTo(Machinery::class);
    }
}
