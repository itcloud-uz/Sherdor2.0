<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require_once __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$clients = \App\Models\Client::where('first_name', 'like', '%Malika%')
    ->orWhere('last_name', 'like', '%Malika%')
    ->with('contracts.unit.floor.building')
    ->get();

foreach ($clients as $client) {
    echo "Client: " . json_encode($client->toArray(), JSON_PRETTY_PRINT) . "\n";
}
