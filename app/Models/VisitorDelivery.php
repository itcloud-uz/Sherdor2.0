<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitorDelivery extends Model
{
    use HasFactory;

    protected $table = 'visitors_and_deliveries';

    protected $fillable = [
        'license_plate',
        'type',
        'company_or_name',
        'purpose'
    ];

    public function attendanceLogs()
    {
        return $this->morphMany(AttendanceLog::class, 'subject');
    }
}
