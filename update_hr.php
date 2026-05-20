<?php
$file = 'd:/Sherdor 2.0/app/Http/Controllers/Admin/HrController.php';
$content = file_get_contents($file);

// Replace insert with insertGetId for worker_transactions, brigade_transactions, machine_owner_transactions
// and capture it to $ledgerTxId.

// 1. Worker (Guard)
$content = preg_replace(
    "/(\\\\Illuminate\\\\Support\\\\Facades\\\\DB::table\('worker_transactions'\)->)insert(\[.*?\]);/s",
    "\$ledgerTableName = 'worker_transactions';\n                \$ledgerTxId = $1insertGetId($2);",
    $content
);

// 2. Machine Owner
$content = preg_replace(
    "/(\\\\Illuminate\\\\Support\\\\Facades\\\\DB::table\('machine_owner_transactions'\)->)insert(\[.*?\]);/s",
    "\$ledgerTableName = 'machine_owner_transactions';\n                \$ledgerTxId = $1insertGetId($2);",
    $content
);

// 3. Brigade
$content = preg_replace(
    "/(\\\\Illuminate\\\\Support\\\\Facades\\\\DB::table\('brigade_transactions'\)->)insert(\[.*?\]);/s",
    "\$ledgerTableName = 'brigade_transactions';\n                \$ledgerTxId = $1insertGetId($2);",
    $content
);

// Now for Transaction::create -> capture it as $financeTx
$content = preg_replace(
    "/\\\\App\\\\Models\\\\Transaction::create\((.*?)\);/s",
    "\$financeTx = \\App\\Models\\Transaction::create($1);\n                if (isset(\$ledgerTxId) && isset(\$ledgerTableName)) { \\Illuminate\\Support\\Facades\\DB::table(\$ledgerTableName)->where('id', \$ledgerTxId)->update(['finance_transaction_id' => \$financeTx->id]); unset(\$ledgerTxId); unset(\$ledgerTableName); }",
    $content
);

file_put_contents($file, $content);
echo "Updated file.\n";
