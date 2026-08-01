<?php
require '/home/itcloud/Sherdor2.0/vendor/autoload.php';
$app = require_once '/home/itcloud/Sherdor2.0/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cols = \Illuminate\Support\Facades\Schema::getColumnListing('floors');
echo "FLOORS COLUMNS: " . implode(', ', $cols) . "\n";