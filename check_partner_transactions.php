<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

function getPartnerRegisterIds($partner) {
    $partnerName = mb_strtolower(str_replace(' ', '', $partner->user->name ?? ''));
    if (empty($partnerName)) return [];

    $pWords = array_filter(explode(' ', mb_strtolower($partner->user->name ?? '')));
    
    $registerIds = \App\Models\CashRegister::all()->filter(function($reg) use ($partnerName, $pWords) {
        $regName = mb_strtolower(str_replace(' ', '', $reg->name));
        if (strpos($regName, $partnerName) !== false || strpos($partnerName, $regName) !== false) {
            return true;
        }
        
        $matches = 0;
        foreach ($pWords as $word) {
            if (strlen($word) < 3) continue;
            if (strpos($regName, $word) !== false) {
                $matches++;
            }
        }
        return $matches >= 2;
    })->pluck('id')->toArray();

    return $registerIds;
}

$partners = \App\Models\Partner::with('user')->get();
foreach ($partners as $partner) {
    echo "Partner: " . $partner->user->name . " (ID: " . $partner->id . ")\n";
    $registerIds = getPartnerRegisterIds($partner);
    echo "  Register IDs: " . implode(', ', $registerIds) . "\n";
    
    $txs = \App\Models\Transaction::where(function($q) use ($partner, $registerIds) {
        $q->where('partner_id', $partner->id);
        if (!empty($registerIds)) {
            $q->orWhereIn('account_id', $registerIds);
        }
    })->get();
    
    echo "  Transactions count: " . $txs->count() . "\n";
    foreach ($txs as $tx) {
        echo "    Tx ID: {$tx->id}, Type: {$tx->type}, Category: {$tx->category}, Amount: {$tx->amount}, Status: {$tx->status}, Notes: {$tx->notes}\n";
    }
}
