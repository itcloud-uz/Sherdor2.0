<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\MachineOwner;
use App\Models\Brigade;
use App\Models\Counterparty;
use App\Models\Transaction;

echo "Syncing Machine Owners...\n";
$owners = MachineOwner::all();
foreach($owners as $owner) {
    $cp = Counterparty::firstOrCreate([
        'customable_type' => MachineOwner::class,
        'customable_id' => $owner->id,
    ], [
        'name' => $owner->name,
        'phone' => $owner->phone ?? 'N/A',
        'type' => 'supplier',
        'balance' => $owner->balance ?? 0,
        'is_active' => true,
    ]);
    
    // Update transactions
    Transaction::where('target_type', 'machine_owner')
        ->where('target_id', $owner->id)
        ->update(['counterparty_id' => $cp->id]);
}

echo "Syncing Brigades...\n";
$brigades = Brigade::all();
foreach($brigades as $brigade) {
    $cp = Counterparty::firstOrCreate([
        'customable_type' => Brigade::class,
        'customable_id' => $brigade->id,
    ], [
        'name' => $brigade->name,
        'phone' => $brigade->phone ?? 'N/A',
        'type' => 'supplier',
        'balance' => $brigade->balance ?? 0,
        'is_active' => true,
    ]);
    
    // Update transactions
    Transaction::where('target_type', 'brigade')
        ->where('target_id', $brigade->id)
        ->update(['counterparty_id' => $cp->id]);
}

echo "Done!\n";
