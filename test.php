<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Brigade Transactions:\n";
echo json_encode(DB::table("brigade_transactions")->get()) . "\n";
echo "Brigades:\n";
echo json_encode(DB::table("brigades")->get()) . "\n";

