<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Client;
use App\Models\Unit;
use App\Models\Contract;
use App\Models\PaymentSchedule;

// Create dummy Client
$client = Client::create([
    'first_name' => 'John',
    'last_name' => 'Doe',
    'phone' => '+998901234567',
]);

// Create dummy Unit (we need to temporarily disable foreign key checks, or just create a unit, wait, unit belongs to building/floor, so let's just create a mock unit)
\Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

$unit = Unit::create([
    'unit_number' => 'A-101',
    'area_sqm' => 50,
    'price' => 50000,
    'status' => 'available'
]);

// Create Contract
$contract = Contract::create([
    'client_id' => $client->id,
    'unit_id' => $unit->id,
    'total_amount' => 50000,
    'down_payment' => 10000,
    'term_months' => 20,
    'status' => 'active'
]);

$schedules = PaymentSchedule::where('contract_id', $contract->id)->get();

echo "Contract Created! Total Amount: $contract->total_amount, Down Payment: $contract->down_payment, Terms: $contract->term_months\n";
echo "Schedules Generated: " . $schedules->count() . "\n";
foreach($schedules as $index => $schedule) {
    if ($index < 3 || $index == $schedules->count() - 1) {
        echo "Month " . ($index + 1) . " -> Due: $schedule->due_date | Amount: $schedule->expected_amount | Status: $schedule->status\n";
    }
}

\Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

// Clean up mock data
PaymentSchedule::truncate();
Contract::truncate();
Client::truncate();
Unit::truncate();
