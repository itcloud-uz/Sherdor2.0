<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
        $target = TransactionCategory::firstOrCreate(
            ['name' => "Mijoz To'lovi (Shartnoma)"],
            ['type' => 'income', 'description' => 'Mijoz to\'lovi']
        );

        // 2. Find any duplicate categories (case insensitive where possible)
        // Since we are running in production we should cover "Mijoz to'lovi", "Mijoz to'lov" etc.
        $duplicates = TransactionCategory::where('id', '!=', $target->id)
            ->where(function($query) {
                $query->where('name', 'LIKE', '%Mijoz to\'lovi%')
                      ->orWhere('name', 'LIKE', '%mijoz to\'lovi%');
            })
            ->get();

        foreach ($duplicates as $dup) {
            // 3. Move all transactions from duplicate to target
            Transaction::where('category_id', $dup->id)
                ->update(['category_id' => $target->id]);
            
            // 4. Delete the duplicate category
            $dup->delete();
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
