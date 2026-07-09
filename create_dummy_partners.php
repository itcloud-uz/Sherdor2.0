<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$dostonUser = \App\Models\User::firstOrCreate(
    ['email' => 'doston@itcloud.uz'],
    ['name' => 'UZOQOV DOSTON', 'password' => bcrypt('password')]
);

$jaxongirUser = \App\Models\User::firstOrCreate(
    ['email' => 'jaxongir@itcloud.uz'],
    ['name' => 'RAXMONOV JAXONGIR', 'password' => bcrypt('password')]
);

$dostonPartner = \App\Models\Partner::firstOrCreate(
    ['user_id' => $dostonUser->id],
    ['investment_share_percentage' => 50, 'total_invested' => 5527000, 'accountable_balance' => 63590000]
);

$jaxongirPartner = \App\Models\Partner::firstOrCreate(
    ['user_id' => $jaxongirUser->id],
    ['investment_share_percentage' => 50, 'total_invested' => 5527000, 'accountable_balance' => 63590000]
);

echo "Dummy partners created!\n";
