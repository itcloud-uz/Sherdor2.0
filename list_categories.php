<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$categories = \App\Models\TransactionCategory::all();
foreach($categories as $c) {
    echo $c->id . ' | ' . $c->name . " | " . $c->type . "\n";
}
