<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\TransactionCategory;
use App\Models\Transaction;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create or Find the correct target category
        $targetId = DB::table('transaction_categories')->where('name', "Mijoz To'lovi (Shartnoma)")->value('id');
        if (!$targetId) {
            $targetId = DB::table('transaction_categories')->insertGetId([
                'name' => "Mijoz To'lovi (Shartnoma)",
                'type' => 'income',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Find any duplicate categories
        $duplicates = DB::table('transaction_categories')
            ->where('id', '!=', $targetId)
            ->where(function($query) {
                $query->where('name', 'LIKE', '%Mijoz to\'lovi%')
                      ->orWhere('name', 'LIKE', '%mijoz to\'lovi%');
            })
            ->get();

        foreach ($duplicates as $dup) {
            // 3. Move all transactions from duplicate to target
            DB::table('transactions')
                ->where('category_id', $dup->id)
                ->update(['category_id' => $targetId]);
            
            // 4. Delete the duplicate category
            DB::table('transaction_categories')
                ->where('id', $dup->id)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration
    }
};
