<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'social_url',
        'source',
        'status',
        'user_id',
        'phone_2',
        'passport_series',
        'gender',
        'birth_date',
        'region',
        'district',
        'address',
        'assigned_employee_id',
        'comment',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getPhoneAttribute($value)
    {
        $value = str_replace(', +998', '', $value ?? '');
        return trim($value, ', ');
    }

    public function getPhone2Attribute($value)
    {
        if (trim($value) === '+998' || trim($value) === '') {
            return null;
        }
        return trim($value);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($lead) {
            if (!empty($lead->social_url)) {
                $url = strtolower($lead->social_url);
                if (str_contains($url, 'facebook.com') || str_contains($url, 'fb.com')) {
                    $lead->source = 'Facebook';
                } elseif (str_contains($url, 'instagram.com') || str_contains($url, 'instagr.am')) {
                    $lead->source = 'Instagram';
                } elseif (str_contains($url, 't.me') || str_contains($url, 'telegram') || str_contains($url, 'tg.me')) {
                    $lead->source = 'Telegram';
                }
            }
        });
    }
}
