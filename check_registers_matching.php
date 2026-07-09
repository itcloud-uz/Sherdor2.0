<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$partners = \App\Models\Partner::with('user')->get();
$registers = \App\Models\CashRegister::all();

function matchesPartner($registerName, $partner) {
    $regName = mb_strtoupper(str_replace(' ', '', $registerName));
    $pName = mb_strtoupper(str_replace(' ', '', $partner->user->name ?? ''));
    if (empty($pName)) return false;
    
    if (strpos($pName, $regName) !== false || strpos($regName, $pName) !== false) {
        return true;
    }
    
    $pParts = array_filter(explode(' ', mb_strtoupper($partner->user->name ?? '')));
    $rParts = array_filter(explode(' ', mb_strtoupper($registerName)));
    
    $matches = 0;
    foreach ($pParts as $pPart) {
        if (strlen($pPart) < 3) continue;
        foreach ($rParts as $rPart) {
            if (strlen($rPart) < 3) continue;
            if (strpos($pPart, $rPart) !== false || strpos($rPart, $pPart) !== false) {
                $matches++;
            }
        }
    }
    return $matches >= 2;
}

foreach ($partners as $partner) {
    echo "Partner: ID={$partner->id}, Name={$partner->user->name}\n";
    foreach ($registers as $reg) {
        if (matchesPartner($reg->name, $partner)) {
            echo "  -> Matches Register: ID={$reg->id}, Name={$reg->name}\n";
        }
    }
}
