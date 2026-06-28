<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contract;
use App\Models\ContractTemplate;

// Get latest templates
$resTemplate = ContractTemplate::where('type', 'residential')->latest()->first();
$commTemplate = ContractTemplate::where('type', 'commercial')->latest()->first();

$count = 0;

foreach (Contract::all() as $contract) {
    if (empty($contract->contract_template_id)) {
        $type = $contract->contract_type ?? 'residential';
        $template = ($type === 'commercial') ? $commTemplate : $resTemplate;
        
        if ($template) {
            $contract->contract_template_id = $template->id;
            
            // Build default variants
            $variants = [];
            foreach ($template->variantGroups as $group) {
                $defaultVariant = $group->variants()->first();
                if ($defaultVariant) {
                    $variants[$group->anchor] = [
                        'key' => $defaultVariant->key,
                        'placeholders' => []
                    ];
                    foreach ($defaultVariant->placeholders as $p) {
                        $val = '';
                        if ($p === 'BOSHLANGICH_FOIZ') {
                            $val = $defaultVariant->key === 'NAQD' ? '100' : '20';
                        } elseif ($p === 'TOLOV_MUDDAT_KUN') {
                            $val = '6';
                        } elseif ($p === 'MUDDAT_OY') {
                            $val = (string)($contract->term_months ?: 36);
                        }
                        $variants[$group->anchor]['placeholders'][$p] = $val;
                    }
                }
            }
            $contract->selected_variants = $variants;
            
            // Reset generated PDF path to trigger recompilation next time it's accessed
            $contract->pdf_path = null;
            $contract->save();
            $count++;
        }
    }
}

echo "Successfully updated $count old contracts to the latest templates.\n";
