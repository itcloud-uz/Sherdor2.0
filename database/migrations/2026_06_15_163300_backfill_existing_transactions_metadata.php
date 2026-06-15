<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fetch all transactions with a contract_id
        $transactions = DB::table('transactions')->whereNotNull('contract_id')->get();

        foreach ($transactions as $transaction) {
            $contract = DB::table('contracts')->where('id', $transaction->contract_id)->first();
            if (!$contract) {
                continue;
            }

            $update = [];

            // 1. client_id
            if (empty($transaction->client_id) && !empty($contract->client_id)) {
                $update['client_id'] = $contract->client_id;
            }

            // 2. counterparty_id
            if (empty($transaction->counterparty_id) && !empty($contract->client_id)) {
                $counterparty = DB::table('counterparties')
                    ->where('customable_type', 'App\Models\Client')
                    ->where('customable_id', $contract->client_id)
                    ->first();
                if ($counterparty) {
                    $update['counterparty_id'] = $counterparty->id;
                }
            }

            // 3. building_id
            if (empty($transaction->building_id) && !empty($contract->unit_id)) {
                $unit = DB::table('units')->where('id', $contract->unit_id)->first();
                if ($unit && !empty($unit->floor_id)) {
                    $floor = DB::table('floors')->where('id', $unit->floor_id)->first();
                    if ($floor && !empty($floor->building_id)) {
                        $update['building_id'] = $floor->building_id;
                    }
                }
            }

            if (!empty($update)) {
                DB::table('transactions')->where('id', $transaction->id)->update($update);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback action needed for data migration
    }
};
