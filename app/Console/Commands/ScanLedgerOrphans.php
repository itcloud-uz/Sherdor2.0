<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScanLedgerOrphans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scan:orphans';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan and remove orphaned ledger transactions and recalculate balances';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Syncing sub-ledger transactions with parent transactions...");

        $ledgers = [
            'worker_transactions' => [\App\Models\Worker::class, 'worker_id'],
            'brigade_transactions' => [\App\Models\Brigade::class, 'brigade_id'],
            'machine_owner_transactions' => [\App\Models\MachineOwner::class, 'machine_owner_id'],
            'machinery_transactions' => [\App\Models\Machinery::class, 'machinery_id'],
            'user_transactions' => [\App\Models\User::class, 'user_id'],
        ];

        foreach ($ledgers as $table => $config) {
            $foreignKey = $config[1];

            if (!Schema::hasTable($table)) continue;

            $this->info("Syncing table: {$table}");
            $transactions = DB::table($table)->whereNotNull('finance_transaction_id')->get();

            foreach ($transactions as $tx) {
                $parent = DB::table('transactions')->where('id', $tx->finance_transaction_id)->first();
                if ($parent) {
                    $updates = [];
                    if ($tx->date !== $parent->transaction_date) {
                        $updates['date'] = $parent->transaction_date;
                    }
                    if ((float)$tx->amount !== (float)$parent->amount) {
                        $updates['amount'] = $parent->amount;
                    }

                    // Determine the expected entity ID from the parent transaction
                    $expectedEntityId = null;
                    if ($table === 'worker_transactions') {
                        $expectedEntityId = $parent->worker_id;
                    } elseif ($table === 'user_transactions') {
                        $expectedEntityId = ($parent->target_type === 'user') ? $parent->target_id : null;
                    } elseif ($table === 'brigade_transactions') {
                        $expectedEntityId = ($parent->target_type === 'brigade') ? $parent->target_id : null;
                    } elseif ($table === 'machine_owner_transactions') {
                        $expectedEntityId = ($parent->target_type === 'machine_owner') ? $parent->target_id : null;
                    } elseif ($table === 'machinery_transactions') {
                        $expectedEntityId = $parent->machinery_id;
                    }

                    if ($expectedEntityId && $tx->$foreignKey !== $expectedEntityId) {
                        $updates[$foreignKey] = $expectedEntityId;
                    }

                    // Sync description/notes
                    $expectedDesc = "Moliya: " . ($parent->notes ?? ($parent->description ?? 'Xarajat'));
                    if ($tx->description !== $expectedDesc) {
                        $updates['description'] = $expectedDesc;
                    }

                    if (!empty($updates)) {
                        DB::table($table)->where('id', $tx->id)->update($updates);
                        $this->line("Synced sub-ledger ID {$tx->id} in {$table} with parent Transaction {$parent->id}");
                    }
                }
            }
        }

        $this->info("Scanning for orphaned ledger transactions...");

        $deletedCount = 0;
        $updatedBalances = 0;

        foreach ($ledgers as $table => $config) {
            $modelClass = $config[0];
            $foreignKey = $config[1];

            if (!Schema::hasTable($table)) continue;

            $this->info("Scanning table: {$table}");
            $transactions = DB::table($table)->whereIn('type', ['payment', 'advance_payment', 'payment_received'])->get();

            foreach ($transactions as $tx) {
                // If it already has a finance_transaction_id, check if that finance transaction exists
                if ($tx->finance_transaction_id) {
                    $exists = DB::table('transactions')->where('id', $tx->finance_transaction_id)->exists();
                    if (!$exists) {
                        DB::table($table)->where('id', $tx->id)->delete();
                        $deletedCount++;
                        $this->line("Deleted orphan ID {$tx->id} in {$table} (Finance TX not found)");
                    }
                    continue;
                }

                // If no finance_transaction_id (old data), try to find a matching finance transaction
                $match = DB::table('transactions')
                    ->where('amount', $tx->amount)
                    ->where('transaction_date', $tx->date)
                    ->exists();

                if (!$match) {
                    // Try matching by created_at date
                    $match2 = DB::table('transactions')
                        ->where('amount', $tx->amount)
                        ->whereDate('created_at', $tx->date)
                        ->exists();

                    if (!$match2) {
                        DB::table($table)->where('id', $tx->id)->delete();
                        $deletedCount++;
                        $this->line("Deleted orphan ID {$tx->id} in {$table} (No matching amount & date in Finance)");
                    }
                }
            }

            // Recalculate balances for all entities in this table
            $entities = $modelClass::all();
            foreach ($entities as $entity) {
                $allTxs = DB::table($table)->where($foreignKey, $entity->id)->get();
                $newBalance = 0;

                foreach ($allTxs as $t) {
                    if ($t->type === 'salary_accrual') {
                        $newBalance += $t->amount;
                    } else {
                        $newBalance -= $t->amount;
                    }
                }

                if (abs((float)$entity->balance - (float)$newBalance) > 0.01) {
                    $this->line("Fixing balance for {$modelClass} ID {$entity->id}: {$entity->balance} -> {$newBalance}");
                    $entity->balance = $newBalance;
                    $entity->save();
                    $updatedBalances++;
                }
            }
        }

        $this->info("Scan complete!");
        $this->info("Deleted Orphans: {$deletedCount}");
        $this->info("Fixed Balances: {$updatedBalances}");
    }
}
