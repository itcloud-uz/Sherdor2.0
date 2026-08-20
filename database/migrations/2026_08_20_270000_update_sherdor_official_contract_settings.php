<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SystemSetting;
use App\Models\ContractTemplate;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Only run settings update if on Sherdor server or Sher-Dor system
        $setting = SystemSetting::first();
        if (!$setting) {
            $setting = new SystemSetting();
        }

        if (empty($setting->company_name) || str_contains(strtolower($setting->company_name), 'sher')) {
            $setting->company_name = '«Шер-Дор» Хусусий корхонаси';
            $setting->company_address = 'Ургут туман Дўстлик махалласи Навои-Шох кўчаси №3-уй';
            $setting->company_bank_account = '2020 8000 6005 5796 1001';
            $setting->company_bank_name = 'АТБ “Агробанк” Ургут туман филиали';
            $setting->company_mfo = '00300';
            $setting->company_inn = '202 162 496';
            $setting->company_director = 'Ж. Рахмонов';
            $setting->company_phone = '(97) 708-44-44, (93) 573-29-29';
            $setting->default_penalty_rate = '0.01';
            $setting->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
