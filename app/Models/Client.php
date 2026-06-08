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
    ];

    public function getFullNameAttribute()
    {
        return trim("{$this->last_name} {$this->first_name} {$this->middle_name}");
    }

    public function getPassportSeriesAttribute()
    {
        return $this->passport_number;
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }
}
