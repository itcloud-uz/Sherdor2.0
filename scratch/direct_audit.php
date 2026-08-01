<?php
define('LARAVEL_START', microtime(true));

require '/home/itcloud/Sherdor2.0/vendor/autoload.php';
$app = require_once '/home/itcloud/Sherdor2.0/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Bind session
$session = $app->make('session')->driver();
$app->make('session')->setDefaultDriver('array');

$user = \App\Models\User::first();
if ($user) {
    auth()->login($user);
}

$controllers = [
    'AdminController' => [
        'dashboard' => fn() => app(\App\Http\Controllers\Admin\AdminController::class)->dashboard(request()),
    ],
    'CrmController' => [
        'index' => fn() => app(\App\Http\Controllers\Admin\CrmController::class)->index(request()),
    ],
    'FinanceController' => [
        'index' => fn() => app(\App\Http\Controllers\Admin\FinanceController::class)->index(request()),
    ],
    'FacilityController' => [
        'index' => fn() => app(\App\Http\Controllers\Admin\FacilityController::class)->index(request()),
    ],
    'HrController' => [
        'index' => fn() => app(\App\Http\Controllers\Admin\HrController::class)->index(request()),
        'texnikalar' => fn() => app(\App\Http\Controllers\Admin\HrController::class)->texnikalar(request()),
    ],
    'WarehouseController' => [
        'index' => fn() => app(\App\Http\Controllers\Admin\WarehouseController::class)->index(request()),
    ],
    'ContractTemplateController' => [
        'index' => fn() => app(\App\Http\Controllers\Admin\ContractTemplateController::class)->index(request()),
    ],
    'PartnerHubController' => [
        'index' => fn() => app(\App\Http\Controllers\Admin\PartnerHubController::class)->index(request()),
    ],
    'TelephonyController' => [
        'index' => fn() => app(\App\Http\Controllers\Admin\TelephonyController::class)->index(request()),
    ],
];

$auditReport = [
    'modules' => [],
    'summary' => [
        'passed' => 0,
        'failed' => 0,
        'total' => 0,
    ]
];

foreach ($controllers as $ctrlName => $methods) {
    $auditReport['modules'][$ctrlName] = [];
    foreach ($methods as $methodName => $callback) {
        $auditReport['summary']['total']++;
        try {
            $response = $callback();
            $auditReport['modules'][$ctrlName][$methodName] = [
                'status' => 'OK',
                'message' => 'Renders successfully'
            ];
            $auditReport['summary']['passed']++;
        } catch (\Throwable $e) {
            $auditReport['modules'][$ctrlName][$methodName] = [
                'status' => 'ERROR',
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine()
            ];
            $auditReport['summary']['failed']++;
        }
    }
}

$readiness = round(($auditReport['summary']['passed'] / $auditReport['summary']['total']) * 100, 1);
$auditReport['summary']['readiness_percent'] = $readiness;

echo json_encode($auditReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);