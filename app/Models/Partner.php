<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $fillable = ['user_id', 'investment_share_percentage', 'total_invested', 'accountable_balance'];

    protected $casts = [
        'accountable_balance' => 'decimal:2',
        'total_invested' => 'decimal:2',
        'investment_share_percentage' => 'decimal:2'
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function consensusVotes()
    {
        return $this->hasMany(ConsensusVote::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'partner_id');
    }
}
