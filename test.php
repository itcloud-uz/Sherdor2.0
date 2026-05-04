<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    \App\Models\Client::create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '12345',
        'passport' => 'AA123'
    ]);
    echo "Client Success\n";
} catch (\Exception $e) {
    echo "Client Error: " . $e->getMessage() . "\n";
}

try {
    \App\Models\Transaction::create([
        'type' => 'income',
        'amount' => 1000,
        'category' => 'client_payment',
        'description' => 'Contract Payment',
        'payment_method' => 'cash',
        'reference_type' => 'contract',
        'reference_id' => 1,
        'status' => 'completed',
        'transaction_date' => now(),
    ]);
    echo "Transaction Success\n";
} catch (\Exception $e) {
    echo "Transaction Error: " . $e->getMessage() . "\n";
}

try {
    \App\Models\Worker::create([
        'name' => 'Worker1',
        'phone' => '123',
        'specialty' => 'Builder',
        'salary_type' => 'fixed',
        'salary_rate' => 1000,
        'measurement_unit' => 'month',
        'status' => 'active'
    ]);
    echo "Worker Success\n";
} catch (\Exception $e) {
    echo "Worker Error: " . $e->getMessage() . "\n";
}
