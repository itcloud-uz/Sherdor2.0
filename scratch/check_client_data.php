<?php
require __DIR__ . "/../vendor/autoload.php";
$app = require_once __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$clients = \App\Models\Client::with('contracts.unit')->latest()->take(5)->get();
foreach ($clients as $client) {
    echo "Client: " . $client->first_name . " " . $client->last_name . "\n";
    echo "  Passport Issued: " . $client->passport_issued_date . "\n";
    echo "  Created At: " . $client->created_at . "\n";
    foreach ($client->contracts as $c) {
        echo "    Contract: " . $c->contract_number . " Date: " . $c->created_at . " / " . $c->contract_date . "\n";
    }
}
