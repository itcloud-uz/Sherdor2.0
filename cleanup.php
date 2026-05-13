<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$target = \App\Models\TransactionCategory::firstOrCreate(['name' => "Mijoz to'lovi"], ['type' => 'income']);
$duplicates = \App\Models\TransactionCategory::where('name', "Shartnoma to'lovi")->get();

foreach($duplicates as $dup) {
    \App\Models\Transaction::where('category_id', $dup->id)->update(['category_id' => $target->id]);
    $dup->delete();
}

echo "Done\n";
