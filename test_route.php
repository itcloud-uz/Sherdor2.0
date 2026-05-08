<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::first();
Auth::login($user);

$response = app()->make(Illuminate\Contracts\Http\Kernel::class)->handle(Illuminate\Http\Request::create('/admin/ai-agents', 'GET'));
echo "STATUS: " . $response->getStatusCode() . "\n";

if ($response->exception) {
    echo $response->exception->getMessage() . "\n";
    echo $response->exception->getTraceAsString();
}
