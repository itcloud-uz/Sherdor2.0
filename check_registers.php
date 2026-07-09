<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$registers = \App\Models\CashRegister::all();
foreach ($registers as $reg) {
    echo "ID: {$reg->id}, Name: {$reg->name}, Balance: {$reg->balance_uzs}\n";
}
