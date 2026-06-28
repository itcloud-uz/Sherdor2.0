<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClauseVariant extends Model
{
    protected $fillable = [
        'clause_variant_group_id',
        'key',
        'label',
        'content',
        'placeholders'
    ];

    protected $casts = [
        'placeholders' => 'array'
    ];

    public function group()
    {
        return $this->belongsTo(ClauseVariantGroup::class, 'clause_variant_group_id');
    }
}
