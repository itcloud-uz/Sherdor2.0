<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerTransaction extends Model
{
    protected $table = 'worker_transactions';

    protected $fillable = [
        'worker_id',
        'type',
        'amount',
        'description',
        'work_category',
        'date'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function worker()
    {
        return $this->belongsTo(Worker::class);
    }
}
