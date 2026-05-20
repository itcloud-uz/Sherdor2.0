<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
foreach($tables as $table) {
    $tableName = $table->name;
    try {
        $cols = Schema::getColumnListing($tableName);
        if(in_array('name', $cols)) {
            $count = DB::table($tableName)->where('name', 'like', '%AXMEDOV%')->count();
            if($count > 0) echo "Found in table: " . $tableName . "\n";
        }
    } catch(\Exception $e) {}
}
