<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

DB::transaction(function() {
    $brigade = DB::table("brigades")->where("id", 2)->first();
    DB::table("brigades")->where("id", 2)->update(["balance" => 8000000 - 10000000]);
});
echo "Fixed!\n";

