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

    protected static function booted()
    {
        static::saved(function ($supplier) {
            \App\Models\Counterparty::updateOrCreate(
                [
                    'customable_type' => get_class($supplier),
                    'customable_id' => $supplier->id,
                ],
                [
                    'name' => $supplier->name ?: "Ta'minotchi #{$supplier->id}",
                    'type' => 'supplier',
                    'phone' => $supplier->phone,
                    'email' => $supplier->email,
                ]
            );
        });

        static::deleted(function ($supplier) {
            \App\Models\Counterparty::where([
                'customable_type' => get_class($supplier),
                'customable_id' => $supplier->id,
            ])->delete();
        });
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
