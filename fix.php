<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$t = \App\Models\Transaction::find(14);
if ($t) {
    $t->status = 'pending';
    $t->account_type = 'main';
    $t->save();
    app(\App\Http\Controllers\Admin\FinanceController::class)->approveTransaction($t);
    echo 'Fixed transaction 14';
}
