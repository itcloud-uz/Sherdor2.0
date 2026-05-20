<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Worker;
use App\Models\User;
use App\Models\MachineOwner;

class FixBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:balances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up accidental daily salary accruals and fix balances';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting balance fix...');
        
        DB::transaction(function () {
            // 1. Worker Transactions (Qorovul, etc)
            $workerTx = DB::table('worker_transactions')
                ->where('description', 'Kunlik ish haqi (oylik maoshdan)')
                ->get();
                
            foreach ($workerTx as $tx) {
                Worker::where('id', $tx->worker_id)->decrement('balance', $tx->amount);
                $this->info("Worker {$tx->worker_id} balance decremented by {$tx->amount}");
            }
            
            $deletedWorkers = DB::table('worker_transactions')
                ->where('description', 'Kunlik ish haqi (oylik maoshdan)')
                ->delete();
                
            $this->info("Deleted {$deletedWorkers} worker transactions.");

            // 2. User Transactions (Operator, etc)
            $userTx = DB::table('user_transactions')
                ->where('description', 'Kunlik ish haqi (oylik maoshdan)')
                ->get();
                
            foreach ($userTx as $tx) {
                User::where('id', $tx->user_id)->decrement('balance', $tx->amount);
                $this->info("User {$tx->user_id} balance decremented by {$tx->amount}");
            }
            
            $deletedUsers = DB::table('user_transactions')
                ->where('description', 'Kunlik ish haqi (oylik maoshdan)')
                ->delete();
                
            $this->info("Deleted {$deletedUsers} user transactions.");
            
            // 3. Machine Owner Transactions
            $ownerTx = DB::table('machine_owner_transactions')
                ->where('description', 'Kunlik ish haqi (oylik maoshdan)')
                ->get();
                
            foreach ($ownerTx as $tx) {
                MachineOwner::where('id', $tx->machine_owner_id)->decrement('balance', $tx->amount);
                $this->info("Machine Owner {$tx->machine_owner_id} balance decremented by {$tx->amount}");
            }
            
            $deletedOwners = DB::table('machine_owner_transactions')
                ->where('description', 'Kunlik ish haqi (oylik maoshdan)')
                ->delete();
                
            $this->info("Deleted {$deletedOwners} machine owner transactions.");

        });

        $this->info('Done.');
    }
}
