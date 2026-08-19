<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$works = DB::table("works")->where("brigade_id", 2)->get();
echo json_encode($works) . "\n";

