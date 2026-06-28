<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClauseVariantGroup extends Model
{
    protected $fillable = [
        'contract_template_id',
        'anchor',
        'label'
    ];

    public function template()
    {
        return $this->belongsTo(ContractTemplate::class, 'contract_template_id');
    }

    public function variants()
    {
        return $this->hasMany(ClauseVariant::class, 'clause_variant_group_id');
    }
}
