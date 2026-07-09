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

// Clear old transactions for these partners
Transaction::whereIn('partner_id', [$dostonPartner->id, $jaxongirPartner->id])->forceDelete();

// 15 items from the screenshot
$items = [
    ['date' => '2021-08-29', 'name' => 'Shlang', 'qty' => 80, 'price' => 4000],
    ['date' => '2021-08-29', 'name' => 'Shlang', 'qty' => 46.10, 'price' => 30000],
    ['date' => '2021-08-29', 'name' => 'Nivillir', 'qty' => 1, 'price' => 100000],
    ['date' => '2021-08-29', 'name' => 'Etik', 'qty' => 2, 'price' => 30000],
    ['date' => '2021-08-29', 'name' => 'Projektor', 'qty' => 4, 'price' => 240000],
    ['date' => '2021-08-29', 'name' => 'Suvga', 'qty' => 1, 'price' => 50000],
    ['date' => '2021-08-29', 'name' => 'Svet schyotchik', 'qty' => 1, 'price' => 1500000],
    ['date' => '2021-08-29', 'name' => 'Kabel', 'qty' => 100, 'price' => 1450],
    ['date' => '2021-08-29', 'name' => 'Aftomat', 'qty' => 1, 'price' => 290000],
    ['date' => '2021-08-29', 'name' => 'Shit', 'qty' => 1, 'price' => 105000],
    ['date' => '2021-08-29', 'name' => 'Abed', 'qty' => 1, 'price' => 150000],
    ['date' => '2021-08-31', 'name' => 'Ujin', 'qty' => 1, 'price' => 200000],
    ['date' => '2021-08-31', 'name' => 'Oziq ovqat', 'qty' => 1, 'price' => 220000],
    ['date' => '2021-08-31', 'name' => 'Kraska sepgich', 'qty' => 1, 'price' => 20000],
    ['date' => '2021-08-31', 'name' => 'Klyonka', 'qty' => 1, 'price' => 24000],
];

// Let's create these as a single transaction or separate transactions?
// Actually, creating them as a single transaction with JSON items or separate transactions is fine.
// Let's create them as separate transactions so they look like a real ledger, or we can store them in one/few transactions.
// Creating separate transactions is cleaner for display and audits!
// We'll set notes to JSON for each to store qty/price.
foreach ([$dostonPartner, $jaxongirPartner] as $p) {
    foreach ($items as $item) {
        $amount = $item['qty'] * $item['price'];
        $notesData = [
            'notes' => '',
            'items' => [
                [
                    'name' => $item['name'],
                    'quantity' => $item['qty'],
                    'price' => $item['price'],
                    'total' => $amount
                ]
            ]
        ];
        
        Transaction::create([
            'type' => 'expense',
            'amount' => $amount,
            'currency' => 'UZS',
            'payment_method' => 'cash',
            'account_type' => 'main',
            'category' => 'Boshqa xarajatlar',
            'notes' => json_encode($notesData, JSON_UNESCAPED_UNICODE),
            'status' => 'approved',
            'created_by' => $p->user_id,
            'partner_id' => $p->id,
            'transaction_date' => $item['date'] . ' 12:00:00',
            'created_at' => $item['date'] . ' 12:00:00',
            'updated_at' => $item['date'] . ' 12:00:00'
        ]);
    }

    // Now let's seed the "Jami olingan so'mma" = 63,590,000 UZS
    Transaction::create([
        'type' => 'expense',
        'amount' => 63590000,
        'currency' => 'UZS',
        'payment_method' => 'cash',
        'account_type' => 'main',
        'category' => 'Partner Advance',
        'notes' => 'Tasischi tomonidan olingan pul mablag\'lari',
        'status' => 'approved',
        'created_by' => $p->user_id,
        'partner_id' => $p->id,
        'transaction_date' => '2021-09-15 14:00:00',
        'created_at' => '2021-09-15 14:00:00',
        'updated_at' => '2021-09-15 14:00:00'
    ]);
}

echo "Database transactions seeded successfully!\n";
