<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['subject_type', 'subject_id', 'camera_id', 'type', 'timestamp', 'confidence_score', 'proof_image'])]
class AttendanceLog extends Model
{
    /**
     * Get the parent subject model (User, Worker, Machinery, or VisitorDelivery).
     */
    public function subject()
    {
        return $this->morphTo();
    }
}
