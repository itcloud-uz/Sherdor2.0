<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\ContractTemplate;
use App\Models\Contract;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $shopHtml = view('contracts.shop_template', [
            'contract' => new Contract([
                'id' => 1,
                'contract_number' => '4Б/9',
                'created_at' => now(),
                'total_amount' => 285840000,
                'down_payment' => 57168000,
                'price_per_sqm' => 8000000,
                'term_months' => 36,
                'floor' => 1,
                'rooms' => 2,
            ]),
            'client' => (object)[
                'name' => 'Рахмонов Жахонгир Туроб ўғли',
                'last_name' => 'Рахмонов',
                'first_name' => 'Жахонгир',
                'middle_name' => 'Туроб ўғли',
                'address' => 'Ургут туман Дўстлик махалласида',
                'pinfl' => '32308703990033',
                'passport_number' => '5716836',
                'passport_series' => 'AD',
                'passport_issued_date' => '2024-01-11',
                'passport_issued_by' => 'Самарқанд вилояти Ургут тумани И.И.Б',
                'phone' => '(93) 196-07-21'
            ],
            'unit' => (object)[
                'unit_number' => '9',
                'area_sqm' => 71.46,
                'price' => 8000000,
                'rooms_count' => 2,
                'type' => 'commercial',
                'floor' => (object)[
                    'floor_number' => 1,
                    'building' => (object)[
                        'block_name' => '3',
                        'corpus_name' => '3'
                    ]
                ]
            ]
        ])->render();

        ContractTemplate::updateOrCreate(
            ['type' => 'commercial', 'name' => 'ҲИССАДОРЛИК ШАРТЛАРИ АСОСИДА САВДО ДЎКОНИ ҚУРИШ ШАРТНОМАСИ'],
            [
                'language' => 'uz',
                'description' => '«Шер-Дор» ХК Савдо дўкони қуриш бўйича расмий 11-бўлимли шартнома',
                'is_active' => true,
                'preview_html' => $shopHtml
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
