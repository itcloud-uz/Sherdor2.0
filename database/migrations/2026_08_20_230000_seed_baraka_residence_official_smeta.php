<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Building;
use App\Models\SmetaItem;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find or create Baraka Residence building
        $building = Building::where('name', 'LIKE', '%Baraka%')->first();
        if (!$building) {
            $building = Building::first();
        }

        if (!$building) {
            return;
        }

        $buildingId = $building->id;

        // Official Smeta Categories & Items from Ekspertiza Xulosasi
        $items = [
            // QMI - Qurilish Montaj Ishlari
            [
                'building_id' => $buildingId,
                'block_name' => 'Blok A-1 (A1 Tipi)',
                'category_name' => 'Qurilish-montaj ishlari (QMI)',
                'item_name' => 'Blok A-1: Monolit Temir-beton karkas va umumiy qurilish montaj ishlari (5 773.5 m²)',
                'unit_type' => 'm²',
                'planned_quantity' => 5773.50,
                'planned_price' => 4165600.00,
                'planned_total' => 24050724898.00,
                'notes' => 'Ekspertiza tasdiqlagan QMI ulushi (33.1%)',
                'created_by' => 1,
            ],
            [
                'building_id' => $buildingId,
                'block_name' => 'Blok A-2 (A2 Tipi)',
                'category_name' => 'Qurilish-montaj ishlari (QMI)',
                'item_name' => 'Blok A-2: Monolit Temir-beton karkas va umumiy qurilish montaj ishlari (6 169.5 m²)',
                'unit_type' => 'm²',
                'planned_quantity' => 6169.50,
                'planned_price' => 4175500.00,
                'planned_total' => 25760777550.00,
                'notes' => 'Ekspertiza tasdiqlagan QMI ulushi (35.4%)',
                'created_by' => 1,
            ],
            [
                'building_id' => $buildingId,
                'block_name' => 'Blok B (B Tipi)',
                'category_name' => 'Qurilish-montaj ishlari (QMI)',
                'item_name' => 'Blok B: Monolit Temir-beton karkas va umumiy qurilish montaj ishlari (5 016.0 m²)',
                'unit_type' => 'm²',
                'planned_quantity' => 5016.00,
                'planned_price' => 4178000.00,
                'planned_total' => 20956848000.00,
                'notes' => 'Ekspertiza tasdiqlagan QMI ulushi (28.8%)',
                'created_by' => 1,
            ],
            [
                'building_id' => $buildingId,
                'block_name' => 'Avtoturargoh (Parking)',
                'category_name' => 'Qurilish-montaj ishlari (QMI)',
                'item_name' => 'Yer osti Avtoturargoh (-1 qavat): Monolit temir-beton konstruksiyalar (536.8 m²)',
                'unit_type' => 'm²',
                'planned_quantity' => 536.80,
                'planned_price' => 3727431.00,
                'planned_total' => 2000884926.00,
                'notes' => 'Yer osti parkovkasi QMI smetasi (2.7%)',
                'created_by' => 1,
            ],

            // Asbob-uskunalar va Jihozlar
            [
                'building_id' => $buildingId,
                'block_name' => 'Umumiy',
                'category_name' => 'Asbob-uskunalar va jihozlar',
                'item_name' => 'Muhandislik asbob-uskunalari, liftlar, transformator va yong\'in xavfsizligi jihozlari',
                'unit_type' => 'komplekt',
                'planned_quantity' => 1.00,
                'planned_price' => 3438979980.00,
                'planned_total' => 3438979980.00,
                'notes' => 'Tasdiqlangan asbob-uskunalar smetasi (4.03%)',
                'created_by' => 1,
            ],

            // Qo'shilgan Qiymat Solig'i (QQS - 12%)
            [
                'building_id' => $buildingId,
                'block_name' => 'Umumiy',
                'category_name' => 'Soliqlar va Majburiy To\'lovlar',
                'item_name' => 'Qo\'shilgan qiymat solig\'i (QQS - 12%)',
                'unit_type' => 'summa',
                'planned_quantity' => 1.00,
                'planned_price' => 9144985842.00,
                'planned_total' => 9144985842.00,
                'notes' => 'Rasmiy tasdiqlangan QQS 12% summasi (10.72%)',
                'created_by' => 1,
            ],
        ];

        foreach ($items as $itemData) {
            SmetaItem::updateOrCreate([
                'building_id' => $itemData['building_id'],
                'item_name' => $itemData['item_name'],
            ], $itemData);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        SmetaItem::where('category_name', 'Qurilish-montaj ishlari (QMI)')->delete();
    }
};
