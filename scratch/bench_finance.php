<?php
$start = microtime(true);

$request = \Illuminate\Http\Request::create('/admin/finance?tab=2', 'GET');
app()->instance('request', $request);

$controller = app(\App\Http\Controllers\Admin\FinanceController::class);
$response = $controller->index($request);

$elapsed = round((microtime(true) - $start) * 1000, 2);
echo "Tab 2 execution time: {$elapsed} ms\n";

$start = microtime(true);
$request2 = \Illuminate\Http\Request::create('/admin/finance?tab=8', 'GET');
app()->instance('request', $request2);

$response2 = $controller->index($request2);
$elapsed2 = round((microtime(true) - $start) * 1000, 2);
echo "Tab 8 execution time: {$elapsed2} ms\n";