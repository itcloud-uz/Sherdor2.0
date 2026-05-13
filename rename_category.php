<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$target = \App\Models\TransactionCategory::firstOrCreate(['name' => "Mijoz To'lovi (Shartnoma)"], ['type' => 'income']);

$dups = \App\Models\TransactionCategory::where('name', "Mijoz to'lovi")->get();
foreach($dups as $dup) {
    if ($dup->id !== $target->id) {
        \App\Models\Transaction::where('category_id', $dup->id)->update(['category_id' => $target->id]);
        $dup->delete();
    }
}

echo "Done\n";
