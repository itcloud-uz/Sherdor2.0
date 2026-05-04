<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Building
        $building = \App\Models\Building::create([
            'name' => 'Cyberpunk Tower',
            'address' => 'Tashkent City',
            'total_floors' => 20,
            'estimated_budget' => 5000000,
            'status' => 'active'
        ]);

        // 2. Create Floors
        for ($f = 1; $f <= 5; $f++) {
            $floor = \App\Models\Floor::create([
                'building_id' => $building->id,
                'floor_number' => $f
            ]);

            // 3. Create Units per Floor
            for ($i = 1; $i <= 4; $i++) {
                \App\Models\Unit::create([
                    'floor_id' => $floor->id,
                    'unit_number' => 'A' . $f . '0' . $i,
                    'type' => 'residential',
                    'area_sqm' => rand(50, 120),
                    'price' => rand(50000, 120000),
                    'status' => 'available'
                ]);
            }
        }

        // 3. Create Clients
        $client1 = \App\Models\Client::create([
            'first_name' => 'Azizbek',
            'last_name' => 'Rahmonov',
            'phone' => '+998901234567',
            'passport_number' => 'AA1234567',
            'pinfl' => '31234567890123'
        ]);

        $client2 = \App\Models\Client::create([
            'first_name' => 'Malika',
            'last_name' => 'Usmonova',
            'phone' => '+998939876543',
            'passport_number' => 'AC9876543',
            'pinfl' => '49876543210987'
        ]);

        // 4. Create Contract for Client 1 (With active penalty)
        $unit1 = \App\Models\Unit::where('status', 'available')->first();
        $unit1->update(['status' => 'sold']);

        $contract1 = \App\Models\Contract::create([
            'client_id' => $client1->id,
            'unit_id' => $unit1->id,
            'total_amount' => 85000,
            'down_payment' => 25000,
            'term_months' => 12,
            'status' => 'active',
            'daily_penalty_rate' => 0.1,
            'total_penalty_accumulated' => 0
        ]);

        // Create Schedules for Contract 1 (One paid, one overdue, others pending)
        \App\Models\PaymentSchedule::create([
            'contract_id' => $contract1->id,
            'expected_amount' => 5000,
            'paid_amount' => 5000,
            'remaining_debt' => 0,
            'due_date' => now()->subMonth(),
            'status' => 'paid'
        ]);

        \App\Models\PaymentSchedule::create([
            'contract_id' => $contract1->id,
            'expected_amount' => 5000,
            'paid_amount' => 1000,
            'remaining_debt' => 4000,
            'penalty_for_this_month' => 40,
            'due_date' => now()->subDays(10),
            'status' => 'partial'
        ]);

        \App\Models\PaymentSchedule::create([
            'contract_id' => $contract1->id,
            'expected_amount' => 5000,
            'paid_amount' => 0,
            'remaining_debt' => 0,
            'due_date' => now()->addMonth(),
            'status' => 'pending'
        ]);

        // 5. Create Leads
        \App\Models\Lead::create([
            'full_name' => 'Sardor Karimov',
            'phone' => '+998991112233',
            'status' => 'new'
        ]);
        
        \App\Models\Lead::create([
            'full_name' => 'Guli Mirzayeva',
            'phone' => '+998904445566',
            'status' => 'qualified'
        ]);

        // 6. Default Settings
        \App\Models\SystemSetting::create([
            'company_name' => 'ITCloud Sher-Dor ERP',
            'primary_theme_color' => '#00F0FF',
            'neon_glow_intensity' => '0 0 15px',
            'default_currency' => 'USD',
            'tax_rate' => 12.00,
            'default_penalty_rate' => 0.10
        ]);
    }
}
