<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Partner;
use App\Models\Transaction;

// Reset CompanyBalance total_balance to 0 as requested by user
\App\Models\CompanyBalance::query()->update(['total_balance' => 0]);

// Clean up duplicate Uzoqov partners
$dostonPartners = Partner::whereHas('user', function($q) {
    $q->where('name', 'like', '%UZOQOV%');
})->orderBy('id', 'asc')->get();

if ($dostonPartners->count() > 1) {
    $dostonPartner = $dostonPartners->first();
    // Delete others
    foreach ($dostonPartners as $i => $p) {
        if ($i > 0) {
            Transaction::where('partner_id', $p->id)->delete();
            $p->delete();
        }
    }
} else {
    $dostonPartner = $dostonPartners->first();
}

if (!$dostonPartner) {
    $dostonUser = User::firstOrCreate(
        ['email' => 'doston@itcloud.uz'],
        ['name' => 'UZOQOV DOSTON', 'password' => bcrypt('password')]
    );
    $dostonPartner = Partner::updateOrCreate(
        ['user_id' => $dostonUser->id],
        ['investment_share_percentage' => 50, 'total_invested' => 5527000, 'accountable_balance' => -63590000]
    );
} else {
    $dostonPartner->update(['investment_share_percentage' => 50]);
}

// Clean up duplicate Raxmonov partners
$jaxongirPartners = Partner::whereHas('user', function($q) {
    $q->where('name', 'like', '%RAXMONOV%');
})->orderBy('id', 'asc')->get();

if ($jaxongirPartners->count() > 1) {
    $jaxongirPartner = $jaxongirPartners->first();
    foreach ($jaxongirPartners as $i => $p) {
        if ($i > 0) {
            Transaction::where('partner_id', $p->id)->delete();
            $p->delete();
        }
    }
} else {
    $jaxongirPartner = $jaxongirPartners->first();
}

if (!$jaxongirPartner) {
    $jaxongirUser = User::firstOrCreate(
        ['email' => 'jaxongir@itcloud.uz'],
        ['name' => 'RAXMONOV JAXONGIR', 'password' => bcrypt('password')]
    );
    $jaxongirPartner = Partner::updateOrCreate(
        ['user_id' => $jaxongirUser->id],
        ['investment_share_percentage' => 50, 'total_invested' => 5527000, 'accountable_balance' => -63590000]
    );
} else {
    $jaxongirPartner->update(['investment_share_percentage' => 50]);
}

// Clear all fake transactions associated with these partners (deleting seeded ones)
Transaction::whereIn('partner_id', [$dostonPartner->id, $jaxongirPartner->id])->forceDelete();

echo "Database transactions seeded successfully!\n";
