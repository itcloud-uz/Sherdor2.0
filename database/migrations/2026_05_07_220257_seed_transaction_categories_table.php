<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $categories = [
            ['name' => 'Mijoz to\'lovi', 'type' => 'income'],
            ['name' => 'Sarmoya (Investitsiya)', 'type' => 'income'],
            ['name' => 'Qarz tushumi', 'type' => 'income'],
            ['name' => 'Boshqa daromadlar', 'type' => 'income'],
            ['name' => 'Ishchi To\'lovi (Avans/Oylik)', 'type' => 'expense'],
            ['name' => 'Texnika To\'lovi', 'type' => 'expense'],
            ['name' => 'Ta\'minotchi To\'lovi', 'type' => 'expense'],
            ['name' => 'Obyekt Xarajati', 'type' => 'expense'],
            ['name' => 'Qurilish materiallari', 'type' => 'expense'],
            ['name' => 'Ofis xarajatlari', 'type' => 'expense'],
            ['name' => 'Soliq va to\'lovlar', 'type' => 'expense'],
            ['name' => 'Qarz qaytarish', 'type' => 'expense'],
            ['name' => 'Asosiy vositalar', 'type' => 'expense'],
            ['name' => 'Boshqa xarajatlar', 'type' => 'expense']
        ];

        foreach ($categories as $category) {
            \App\Models\TransactionCategory::firstOrCreate(
                ['name' => $category['name']],
                ['type' => $category['type']]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
