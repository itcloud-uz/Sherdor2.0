<?php
require '/home/itcloud/Sherdor2.0/vendor/autoload.php';
$app = require_once '/home/itcloud/Sherdor2.0/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\Illuminate\Support\Facades\DB::enableQueryLog();

$user = \App\Models\User::first();
auth()->login($user);
$controller = app(\App\Http\Controllers\Admin\FinanceController::class);

foreach ([0, 1, 2, 8] as $t) {
    \Illuminate\Support\Facades\DB::flushQueryLog();
    $start = microtime(true);

    $request = \Illuminate\Http\Request::create('/admin/finance?tab=' . $t, 'GET');
    app()->instance('request', $request);

    $response = $controller->index($request);

    $elapsed = round((microtime(true) - $start) * 1000, 2);
    $queryCount = count(\Illuminate\Support\Facades\DB::getQueryLog());

    echo "Tab {$t} -> Execution time: {$elapsed} ms, Total Queries: {$queryCount}\n";
}