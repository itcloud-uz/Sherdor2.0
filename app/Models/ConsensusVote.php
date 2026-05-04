<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsensusVote extends Model
{
    protected $fillable = ['consensus_request_id', 'partner_id', 'vote'];

    public function request()
    {
        return $this->belongsTo(ConsensusRequest::class, 'consensus_request_id');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
