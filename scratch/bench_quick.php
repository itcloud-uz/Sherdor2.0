<?php
$start = microtime(true);

$request = \Illuminate\Http\Request::create('/admin/finance?tab=2', 'GET');
app()->instance('request', $request);

$controller = app(\App\Http\Controllers\Admin\FinanceController::class);
$response = $controller->index($request);

$elapsed = round((microtime(true) - $start) * 1000, 2);
echo "Tab 2 execution time: {$elapsed} ms\n";