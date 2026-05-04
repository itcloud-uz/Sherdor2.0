<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsensusRequest extends Model
{
    protected $fillable = ['transaction_id', 'status'];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function votes()
    {
        return $this->hasMany(ConsensusVote::class);
    }
}
