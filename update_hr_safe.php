<?php
$file = 'd:/Sherdor 2.0/app/Http/Controllers/Admin/HrController.php';
$content = file_get_contents($file);

$replacements = [
    // Brigade Main Payment
    [
        "\\App\\Models\\BrigadeTransaction::create([\n                    'brigade_id' => \$brigade->id,\n                    'type' => 'payment',\n                    'amount' => \$amount,\n                    'description' => \$validated['description'] ?? 'To\'lov',\n                    'date' => \$validated['date']\n                ]);",
        "\$ledgerTx = \\App\\Models\\BrigadeTransaction::create([\n                    'brigade_id' => \$brigade->id,\n                    'type' => 'payment',\n                    'amount' => \$amount,\n                    'description' => \$validated['description'] ?? 'To\'lov',\n                    'date' => \$validated['date']\n                ]);\n                \$ledgerTxId = \$ledgerTx->id;\n                \$ledgerTableName = 'brigade_transactions';"
    ],
    // Brigade Discount
    [
        "\\App\\Models\\BrigadeTransaction::create([\n                        'brigade_id' => \$brigade->id,\n                        'type' => 'payment',\n                        'amount' => \$discount,\n                        'description' => 'Brigada xarajati (Chegirma)',\n                        'date' => \$validated['date']\n                    ]);",
        "\$ledgerTx = \\App\\Models\\BrigadeTransaction::create([\n                        'brigade_id' => \$brigade->id,\n                        'type' => 'payment',\n                        'amount' => \$discount,\n                        'description' => 'Brigada xarajati (Chegirma)',\n                        'date' => \$validated['date']\n                    ]);\n                    \$ledgerTxId = \$ledgerTx->id;\n                    \$ledgerTableName = 'brigade_transactions';"
    ],
    // Guard Main Payment
    [
        "\\Illuminate\\Support\\Facades\\DB::table('worker_transactions')->insert([\n                    'worker_id' => \$guard->id,\n                    'type' => 'payment',\n                    'amount' => \$amount,\n                    'description' => \$validated['description'] ?? 'To\'lov',\n                    'date' => \$validated['date'],\n                    'created_at' => now(),\n                    'updated_at' => now(),\n                ]);",
        "\$ledgerTxId = \\Illuminate\\Support\\Facades\\DB::table('worker_transactions')->insertGetId([\n                    'worker_id' => \$guard->id,\n                    'type' => 'payment',\n                    'amount' => \$amount,\n                    'description' => \$validated['description'] ?? 'To\'lov',\n                    'date' => \$validated['date'],\n                    'created_at' => now(),\n                    'updated_at' => now(),\n                ]);\n                \$ledgerTableName = 'worker_transactions';"
    ],
    // Guard Discount
    [
        "\\Illuminate\\Support\\Facades\\DB::table('worker_transactions')->insert([\n                        'worker_id' => \$guard->id,\n                        'type' => 'payment',\n                        'amount' => \$discount,\n                        'description' => 'Chegirma',\n                        'date' => \$validated['date'],\n                        'created_at' => now(),\n                        'updated_at' => now(),\n                    ]);",
        "\$ledgerTxId = \\Illuminate\\Support\\Facades\\DB::table('worker_transactions')->insertGetId([\n                        'worker_id' => \$guard->id,\n                        'type' => 'payment',\n                        'amount' => \$discount,\n                        'description' => 'Chegirma',\n                        'date' => \$validated['date'],\n                        'created_at' => now(),\n                        'updated_at' => now(),\n                    ]);\n                    \$ledgerTableName = 'worker_transactions';"
    ],
    // Owner Main Payment
    [
        "\\App\\Models\\MachineOwnerTransaction::create([\n                    'machine_owner_id' => \$owner->id,\n                    'type' => 'payment',\n                    'amount' => \$amount,\n                    'description' => \$validated['description'] ?? 'To\'lov',\n                    'date' => \$validated['date']\n                ]);",
        "\$ledgerTx = \\App\\Models\\MachineOwnerTransaction::create([\n                    'machine_owner_id' => \$owner->id,\n                    'type' => 'payment',\n                    'amount' => \$amount,\n                    'description' => \$validated['description'] ?? 'To\'lov',\n                    'date' => \$validated['date']\n                ]);\n                \$ledgerTxId = \$ledgerTx->id;\n                \$ledgerTableName = 'machine_owner_transactions';"
    ],
    // Owner Discount
    [
        "\\App\\Models\\MachineOwnerTransaction::create([\n                        'machine_owner_id' => \$owner->id,\n                        'type' => 'payment',\n                        'amount' => \$discount,\n                        'description' => 'Texnika xarajati (Chegirma)',\n                        'date' => \$validated['date']\n                    ]);",
        "\$ledgerTx = \\App\\Models\\MachineOwnerTransaction::create([\n                        'machine_owner_id' => \$owner->id,\n                        'type' => 'payment',\n                        'amount' => \$discount,\n                        'description' => 'Texnika xarajati (Chegirma)',\n                        'date' => \$validated['date']\n                    ]);\n                    \$ledgerTxId = \$ledgerTx->id;\n                    \$ledgerTableName = 'machine_owner_transactions';"
    ],
];

foreach ($replacements as $i => $rep) {
    if (strpos($content, $rep[0]) === false) {
        echo "Failed to find match for replacement index $i\n";
    }
    $content = str_replace($rep[0], $rep[1], $content);
}

// Now replace Transaction::create to save $financeTx and link them
$financePattern = "/(\\\\App\\\\Models\\\\Transaction::create\(\[\s*'.*?\]\);)/s";
$content = preg_replace_callback($financePattern, function($matches) {
    return "\$financeTx = " . $matches[1] . "\n                if (isset(\$ledgerTxId) && isset(\$ledgerTableName)) { \\Illuminate\\Support\\Facades\\DB::table(\$ledgerTableName)->where('id', \$ledgerTxId)->update(['finance_transaction_id' => \$financeTx->id]); unset(\$ledgerTxId); unset(\$ledgerTableName); }";
}, $content);

file_put_contents($file, $content);
echo "Done replacing.\n";
