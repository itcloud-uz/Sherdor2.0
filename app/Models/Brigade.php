<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brigade extends Model
{
    protected $fillable = ['name', 'foreman_name', 'phone', 'balance'];

    protected static function booted()
    {
        static::saved(function ($brigade) {
            \App\Models\Counterparty::updateOrCreate(
                [
                    'customable_type' => get_class($brigade),
                    'customable_id' => $brigade->id,
                ],
                [
                    'name' => $brigade->name,
                    'phone' => $brigade->phone ?? 'N/A',
                    'type' => 'supplier', // Using supplier for brigade
                    'balance' => $brigade->balance ?? 0,
                    'is_active' => true,
                ]
            );
        });
    }

    public function workers()
    {
        return $this->hasMany(Worker::class);
    }

    public function transactions()
    {
        return $this->hasMany(BrigadeTransaction::class);
    }
}
