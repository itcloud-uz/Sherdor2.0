<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = \App\Models\User::first();
auth()->login($user);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::create('/admin/hr/guard/1/transactions', 'GET')
);

echo $response->getContent();
