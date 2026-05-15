<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $guarded = [];

    public function contracts()
    {
        return $this->hasMany(SupplierContract::class);
    }

    public function orders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
