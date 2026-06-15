<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceAction extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'transaction_id',
        'transaction_type',
        'amount',
        'currency',
        'notes',
        'details'
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transaction()
    {
        // Must use withTrashed because transaction might be soft-deleted
        return $this->belongsTo(Transaction::class)->withTrashed();
    }
}
