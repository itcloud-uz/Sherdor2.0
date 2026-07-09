<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$perms = ['create_transaction', 'edit_transaction', 'delete_transaction'];
foreach ($perms as $p) {
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p]);
}
$sot = \Spatie\Permission\Models\Role::where('name', 'Sotuvchi')->first();
if ($sot) $sot->syncPermissions(['access dashboard', 'access clients', 'access transactions', 'create_transaction']);

$op = \Spatie\Permission\Models\Role::where('name', 'Operator')->first();
if ($op) $op->syncPermissions(['access dashboard', 'access clients', 'access transactions', 'create_transaction']);

$part = \Spatie\Permission\Models\Role::where('name', 'Partner')->first();
if ($part) $part->syncPermissions(['access dashboard', 'access clients', 'access transactions', 'create_transaction', 'edit_transaction', 'delete_transaction', 'access finance']);

$worker = \Spatie\Permission\Models\Role::where('name', 'Worker')->first();
if ($worker) $worker->syncPermissions(['access dashboard', 'access objects']);

echo "Roles and permissions synced.\n";
