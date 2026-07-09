<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$role = Spatie\Permission\Models\Role::findByName('Sotuvchi');
$role->givePermissionTo('access transactions');
echo "Permission 'access transactions' added to Sotuvchi.";
