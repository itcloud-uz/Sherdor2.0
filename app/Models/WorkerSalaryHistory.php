<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerSalaryHistory extends Model
{
    protected $fillable = ['worker_id', 'salary', 'effective_date'];

    public function worker()
    {
        return $this->belongsTo(Worker::class);
    }
}
