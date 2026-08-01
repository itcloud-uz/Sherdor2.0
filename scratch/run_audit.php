<?php
define('LARAVEL_START', microtime(true));

require '/home/itcloud/Sherdor2.0/vendor/autoload.php';
$app = require_once '/home/itcloud/Sherdor2.0/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'database_tables' => [],
    'modules' => [],
    'errors_found' => [],
    'total_routes' => 0,
    'passed_routes' => 0,
    'failed_routes' => 0,
];

// 1. Audit Database Tables
$tables = DB::select('SHOW TABLES');
$dbName = DB::getDatabaseName();
$tableKey = "Tables_in_" . $dbName;

foreach ($tables as $t) {
    $tableName = $t->$tableKey;
    try {
        $count = DB::table($tableName)->count();
        $results['database_tables'][$tableName] = [
            'status' => 'OK',
            'rows' => $count
        ];
    } catch (\Exception $e) {
        $results['database_tables'][$tableName] = [
            'status' => 'ERROR',
            'error' => $e->getMessage()
        ];
        $results['errors_found'][] = "Database table {$tableName} error: " . $e->getMessage();
    }
}

// 2. Audit Route Controllers & Handlers
$routes = Route::getRoutes()->get('GET');
$user = \App\Models\User::first();

if ($user) {
    auth()->login($user);
}

$moduleMap = [
    'admin.dashboard' => 'Boshqaruv Paneli (Dashboard)',
    'admin.crm' => 'CRM (Lidlar, Mijozlar, Shartnomalar)',
    'admin.finance' => 'Moliya va Kassalar (Finance Core)',
    'admin.facility' => 'Qurilish va Obyektlar (Facility & Buildings)',
    'admin.hr' => 'Kadrlar va Texnikalar (HR & Machinery)',
    'admin.warehouse' => 'Omborxona (Warehouse & Materials)',
    'admin.contract_templates' => 'Shartnoma Shablonlari (Contract Templates)',
    'admin.partner_hub' => 'Tasischilar va Hamkorlar (Partner Hub)',
    'admin.finance.transaction_requests' => 'Tranzaksiya Sorovlari (Transaction Requests)',
    'admin.telephony' => 'Telefoniya (Telephony & Calls)',
    'admin.settings' => 'Sozlamalar (Settings & Users)',
];

foreach ($routes as $route) {
    $uri = $route->uri();
    $name = $route->getName();
    $action = $route->getActionName();
    
    // Only audit admin routes
    if (!str_contains($uri, 'admin') && !str_contains($name ?? '', 'admin')) {
        continue;
    }
    
    $results['total_routes']++;
    $moduleName = 'Boshqa Bolimlar';
    foreach ($moduleMap as $prefix => $mod) {
        if (str_contains($name ?? '', $prefix) || str_contains($uri, str_replace('admin.', '', $prefix))) {
            $moduleName = $mod;
            break;
        }
    }
    
    if (!isset($results['modules'][$moduleName])) {
        $results['modules'][$moduleName] = [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'routes' => []
        ];
    }
    
    $results['modules'][$moduleName]['total']++;
    
    try {
        if (is_string($action) && str_contains($action, '@')) {
            list($controller, $method) = explode('@', $action);
            if (!class_exists($controller)) {
                throw new \Exception("Controller class {$controller} does not exist.");
            }
            if (!method_exists($controller, $method)) {
                throw new \Exception("Method {$method} does not exist in {$controller}.");
            }
        }
        
        $req = \Illuminate\Http\Request::create('/' . ltrim($uri, '/'), 'GET');
        $req->setUserResolver(fn() => $user);
        
        $response = Route::dispatch($req);
        $status = $response->getStatusCode();
        
        if ($status >= 200 && $status < 400) {
            $results['passed_routes']++;
            $results['modules'][$moduleName]['passed']++;
            $results['modules'][$moduleName]['routes'][] = [
                'name' => $name ?: $uri,
                'uri' => $uri,
                'status' => 'OK (' . $status . ')'
            ];
        } else if ($status === 302 || $status === 301) {
            $results['passed_routes']++;
            $results['modules'][$moduleName]['passed']++;
            $results['modules'][$moduleName]['routes'][] = [
                'name' => $name ?: $uri,
                'uri' => $uri,
                'status' => 'OK (Redirect ' . $status . ')'
            ];
        } else {
            $results['failed_routes']++;
            $results['modules'][$moduleName]['failed']++;
            $errMsg = "HTTP {$status} on route {$uri}";
            $results['modules'][$moduleName]['routes'][] = [
                'name' => $name ?: $uri,
                'uri' => $uri,
                'status' => 'FAILED (' . $status . ')'
            ];
            $results['errors_found'][] = "Route error [{$name}]: {$errMsg}";
        }
    } catch (\Throwable $e) {
        $results['failed_routes']++;
        $results['modules'][$moduleName]['failed']++;
        $results['modules'][$moduleName]['routes'][] = [
            'name' => $name ?: $uri,
            'uri' => $uri,
            'status' => 'ERROR: ' . $e->getMessage()
        ];
        $results['errors_found'][] = "Route exception [{$name}]: " . $e->getMessage();
    }
}

$readiness = $results['total_routes'] > 0 ? round(($results['passed_routes'] / $results['total_routes']) * 100, 1) : 0;
$results['overall_readiness_percent'] = $readiness;

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);