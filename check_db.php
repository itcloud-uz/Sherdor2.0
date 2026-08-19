<?php
require __DIR__.'/vendor/autoload.php';
\ = require_once __DIR__.'/bootstrap/app.php';
\->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Brigade Transactions:\n";
\ = DB::table('brigade_transactions')->get();
foreach(\ as \) {
    echo json_encode(\) . "\n";
}

echo "\nWorker Transactions:\n";
\ = DB::table('worker_transactions')->get();
foreach(\ as \) {
    echo json_encode(\) . "\n";
}

echo "\nBrigades:\n";
\ = DB::table('brigades')->get();
foreach(\ as \) {
    echo json_encode(\) . "\n";
}
