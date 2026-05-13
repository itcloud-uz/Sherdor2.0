<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cats = \App\Models\TransactionCategory::all();
foreach($cats as $c) {
    if (str_contains(strtolower($c->name), 'mijoz') && $c->id != 16) {
        \App\Models\Transaction::where('category_id', $c->id)->update(['category_id' => 16]);
        $c->delete();
        echo 'Deleted ' . $c->name . "\n";
    }
}
echo "Done\n";
