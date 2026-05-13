<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$req = new \Illuminate\Http\Request();
$req->merge(['transaction_date' => '2026-05-10T10:00']);
$validator = \Illuminate\Support\Facades\Validator::make($req->all(), ['transaction_date' => 'nullable|date']);
dump($validator->passes());
dump($validator->validated());
