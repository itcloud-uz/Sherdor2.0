<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierContract extends Model
{
    protected $guarded = [];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
