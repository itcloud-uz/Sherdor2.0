<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'passport_number',
        'pinfl',
        'phone',
        'address',
        'passport_issued_date',
        'passport_issued_by',
        'is_blacklisted',
        'region',
        'district',
        'phone_2',
        'birth_date',
        'gender',
        'passport_photo',
        'source',
        'contract_language',
        'contract_id',
    ];

    public function getFullNameAttribute()
    {
        return trim("{$this->last_name} {$this->first_name} {$this->middle_name}");
    }

    public function getPassportSeriesAttribute()
    {
        return $this->passport_number;
    }

    protected static function booted()
    {
        static::saved(function ($client) {
            \App\Models\Counterparty::updateOrCreate(
                [
                    'customable_type' => get_class($client),
                    'customable_id' => $client->id,
                ],
                [
                    'name' => trim("{$client->last_name} {$client->first_name} {$client->middle_name}") ?: "Mijoz #{$client->id}",
                    'type' => 'client',
                    'phone' => $client->phone,
                ]
            );
        });

        static::deleted(function ($client) {
            \App\Models\Counterparty::where([
                'customable_type' => get_class($client),
                'customable_id' => $client->id,
            ])->delete();
        });
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }
}
