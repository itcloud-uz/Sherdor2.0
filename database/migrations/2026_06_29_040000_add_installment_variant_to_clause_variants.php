<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\ClauseVariantGroup;
use App\Models\ClauseVariant;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $groups = ClauseVariantGroup::where('anchor', 'TOLOV_SHARTLARI')
            ->orWhere('label', 'like', '%To\'lov shartlari%')
            ->get();

        foreach ($groups as $group) {
            $exists = ClauseVariant::where('clause_variant_group_id', $group->id)
                ->where('key', 'INSTALLMENT')
                ->exists();

            if (!$exists) {
                ClauseVariant::create([
                    'clause_variant_group_id' => $group->id,
                    'key' => 'INSTALLMENT',
                    'label' => 'Bo\'lib to\'lash',
                    'content' => '2. 3.1-bandda ko‘rsatilgan shartnoma summasining kamida {{ BOSHLANGICH_FOIZ }}% , {{ BOSHLANGICH_TOLOV }} ({{ BOSHLANGICH_TOLOV_SOZ_BILAN }}) so‘m miqdoridagi boshlang‘ich mablag‘ni «XARIDOR» tomonidan shartnoma imzolangan kundan boshlab {{ TOLOV_MUDDAT_KUN }} bank ish kuni ichida, qolgan summa boshlang‘ich to‘lov to‘langandan so‘ng {{ MUDDAT_OY }} ({{ MUDDAT_OY_SOZ_BILAN }}) oy davomida bo‘lib to‘lash shartlari asosida to‘lanadi.',
                    'placeholders' => ['BOSHLANGICH_FOIZ', 'TOLOV_MUDDAT_KUN', 'MUDDAT_OY']
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        ClauseVariant::where('key', 'INSTALLMENT')->delete();
    }
};
