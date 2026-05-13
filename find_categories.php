<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cats = \App\Models\TransactionCategory::where('name', 'like', '%ijoz%')->get();
foreach($cats as $c) {
    echo $c->id . ' | ' . $c->name . "\n";
}
