<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

try {
    Schema::dropIfExists('money_transfers');
    echo "Dropped money_transfers\n";
    Schema::dropIfExists('invoices');
    echo "Dropped invoices\n";
    Schema::dropIfExists('counterparties');
    echo "Dropped counterparties\n";
    Schema::dropIfExists('cash_registers');
    echo "Dropped cash_registers\n";
    Schema::dropIfExists('currency_rates');
    echo "Dropped currency_rates\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
