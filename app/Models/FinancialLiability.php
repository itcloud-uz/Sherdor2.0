<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialLiability extends Model
{
    protected $fillable = [
        'type', 'creditor_name', 'total_amount', 'interest_rate', 'term_months',
        'start_date', 'end_date', 'monthly_payment', 'status', 'description'
    ];

    public function payments()
    {
        return $this->hasMany(LiabilityPayment::class);
    }
}
