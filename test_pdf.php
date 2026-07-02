<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/admin/crm/contracts/2/export-doc/pdf', 'GET');
$user = App\Models\User::first();
$app['auth']->guard()->login($user);
$response = $kernel->handle($request);
echo $response->getStatusCode() . "\n";
if ($response->getStatusCode() == 302) {
    echo $response->headers->get('Location') . "\n";
    $errors = session('errors');
    if ($errors) print_r($errors->all());
} else if ($response->getStatusCode() == 200) {
    echo get_class($response) . "\n";
} else {
    echo $response->getContent();
}
