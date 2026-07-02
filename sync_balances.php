<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$brigades = App\Models\Brigade::all();
foreach($brigades as $b) {
   $totalWorks = App\Models\BrigadeWork::where('brigade_id', $b->id)->sum('calculated_sum');
   $totalPaid = App\Models\BrigadeTransaction::where('brigade_id', $b->id)->where('type', 'payment')->sum('amount');
   $b->balance = $totalWorks - $totalPaid;
   $b->save();
}
echo "Brigade balances updated!\n";
