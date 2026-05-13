<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$txns = \App\Models\Transaction::latest()->take(5)->get();
foreach($txns as $t) {
    echo $t->id . ' | ' . $t->amount . ' | txn_date: ' . $t->transaction_date . ' | updated_at: ' . $t->updated_at . "\n";
}
