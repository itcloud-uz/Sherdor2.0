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
        Schema::disableForeignKeyConstraints();
        // 1. currency_rates
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->decimal('rate', 12, 4)->default(1.0000);
            $table->timestamps();
        });

        // 2. cash_registers
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['cash', 'bank'])->default('cash');
            $table->decimal('initial_balance_uzs', 15, 2)->default(0.00);
            $table->decimal('initial_balance_usd', 15, 2)->default(0.00);
            $table->decimal('initial_balance_eur', 15, 2)->default(0.00);
            $table->string('currency', 3)->default('UZS');
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('mfo_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. counterparties
        Schema::create('counterparties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['client', 'supplier', 'employee'])->default('client');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('customable_type')->nullable();
            $table->unsignedBigInteger('customable_id')->nullable();
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('inn')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['customable_type', 'customable_id']);
        });

        // 4. invoices
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('counterparty_id')->constrained('counterparties')->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0.00);
            $table->string('currency', 3)->default('UZS');
            $table->date('due_date');
            $table->enum('status', ['new', 'partially_paid', 'paid', 'cancelled'])->default('new');
            $table->enum('type', ['monthly_schedule', 'manual_expense', 'procurement', 'loan'])->default('monthly_schedule');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 5. money_transfers
        Schema::create('money_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_account_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->foreignId('to_account_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->decimal('sent_amount', 15, 2);
            $table->string('sent_currency', 3)->default('UZS');
            $table->decimal('exchange_rate', 12, 4)->default(1.0000);
            $table->decimal('received_amount', 15, 2);
            $table->string('received_currency', 3)->default('UZS');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 6. Alter transactions table
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'account_id')) {
                $table->foreignId('account_id')->nullable()->constrained('cash_registers')->nullOnDelete();
            }
            if (!Schema::hasColumn('transactions', 'counterparty_id')) {
                $table->foreignId('counterparty_id')->nullable()->constrained('counterparties')->nullOnDelete();
            }
            if (!Schema::hasColumn('transactions', 'invoice_id')) {
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            }
        });

        // 7. Data Seeding & Synchronization
        $buildingId = DB::table('buildings')->where('name', 'Nuovo')->value('id');
        if (!$buildingId) {
            $buildingId = DB::table('buildings')->insertGetId([
                'name' => 'Nuovo',
                'address' => 'Tashkent City',
                'total_floors' => 20,
                'estimated_budget' => 5000000,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('currency_rates')->insert([
            ['code' => 'USD', 'rate' => 12350.0000, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'EUR', 'rate' => 16890.0000, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $aloqaId = DB::table('cash_registers')->insertGetId([
            'name' => 'Aloqa Bank Hisobi',
            'type' => 'bank',
            'initial_balance_uzs' => 0.00,
            'initial_balance_usd' => 0.00,
            'initial_balance_eur' => 0.00,
            'bank_name' => 'Aloqa bank',
            'account_number' => '0001 0002 0003 0004 0005',
            'mfo_code' => '00015',
            'building_id' => $buildingId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $naqdId = DB::table('cash_registers')->insertGetId([
            'name' => 'Naqd Kassa',
            'type' => 'cash',
            'initial_balance_uzs' => 10000000.00,
            'initial_balance_usd' => 0.00,
            'initial_balance_eur' => 0.00,
            'building_id' => $buildingId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Clients
        $clientId1 = DB::table('clients')->insertGetId([
            'first_name' => 'Azimjon',
            'last_name' => 'Samirov',
            'phone' => '+998 99 894 24 00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clientId2 = DB::table('clients')->insertGetId([
            'first_name' => 'Ikrom',
            'last_name' => 'Xusanov',
            'phone' => '+998 97 705 56 10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clientId3 = DB::table('clients')->insertGetId([
            'first_name' => 'Aziz',
            'last_name' => 'Bluga',
            'phone' => '+998 89 745 73 48',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Workers
        $workerId1 = DB::table('workers')->insertGetId([
            'name' => 'Call Centre3',
            'phone' => '+998 99 834 32 43',
            'role' => 'operator',
            'salary_type' => 'fixed_monthly',
            'base_rate' => 1500000,
            'type' => 'fixed',
            'rate' => 1500000,
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $workerId2 = DB::table('workers')->insertGetId([
            'name' => 'Call Centre1',
            'phone' => '+998 99 890 95 23',
            'role' => 'operator',
            'salary_type' => 'fixed_monthly',
            'base_rate' => 1500000,
            'type' => 'fixed',
            'rate' => 1500000,
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $workerId3 = DB::table('workers')->insertGetId([
            'name' => 'Doniyor Ziyaev',
            'phone' => '+998 99 890 95 23',
            'role' => 'operator',
            'salary_type' => 'fixed_monthly',
            'base_rate' => 2000000,
            'type' => 'fixed',
            'rate' => 2000000,
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Counterparties
        $cpSamirov = DB::table('counterparties')->insertGetId([
            'name' => 'Azimjon Samirov',
            'type' => 'client',
            'phone' => '+998 99 894 24 00',
            'customable_type' => 'App\Models\Client',
            'customable_id' => $clientId1,
            'balance' => 0,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cpXusanov = DB::table('counterparties')->insertGetId([
            'name' => 'Ikrom Xusanov',
            'type' => 'client',
            'phone' => '+998 97 705 56 10',
            'customable_type' => 'App\Models\Client',
            'customable_id' => $clientId2,
            'balance' => 0,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cpBluga = DB::table('counterparties')->insertGetId([
            'name' => 'Aziz Bluga',
            'type' => 'client',
            'phone' => '+998 89 745 73 48',
            'customable_type' => 'App\Models\Client',
            'customable_id' => $clientId3,
            'balance' => 0,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cpCc3 = DB::table('counterparties')->insertGetId([
            'name' => 'Call Centre3',
            'type' => 'employee',
            'phone' => '+998 99 834 32 43',
            'customable_type' => 'App\Models\Worker',
            'customable_id' => $workerId1,
            'balance' => 0,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cpCc1 = DB::table('counterparties')->insertGetId([
            'name' => 'Call Centre1',
            'type' => 'employee',
            'phone' => '+998 99 890 95 23',
            'customable_type' => 'App\Models\Worker',
            'customable_id' => $workerId2,
            'balance' => 0,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cpDoniyor = DB::table('counterparties')->insertGetId([
            'name' => 'Doniyor Ziyaev',
            'type' => 'employee',
            'phone' => '+998 99 890 95 23',
            'customable_type' => 'App\Models\Worker',
            'customable_id' => $workerId3,
            'balance' => 0,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cpZk = DB::table('counterparties')->insertGetId([
            'name' => 'ZK',
            'type' => 'supplier',
            'phone' => '+998 90 999 88 77',
            'balance' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Money Transfer
        DB::table('money_transfers')->insert([
            'from_account_id' => $naqdId,
            'to_account_id' => $aloqaId,
            'sent_amount' => 10000000.00,
            'sent_currency' => 'UZS',
            'exchange_rate' => 1.0000,
            'received_amount' => 10000000.00,
            'received_currency' => 'UZS',
            'description' => 'Boshlang\'ich kassa to\'ldirish',
            'created_at' => '2026-03-30 17:39:00',
            'updated_at' => '2026-03-30 17:39:00',
        ]);

        // Transactions
        // Outflow transfer from Naqd Kassa
        DB::table('transactions')->insert([
            'type' => 'expense',
            'amount' => 10000000.00,
            'currency' => 'UZS',
            'payment_method' => 'cash',
            'account_id' => $naqdId,
            'status' => 'approved',
            'transaction_date' => '2026-03-30 17:39:00',
            'notes' => '[Chiqim - O\'tkazma] ID: #1. Boshlang\'ich kassa to\'ldirish',
            'created_by' => 1,
            'approved_by' => 1,
            'created_at' => '2026-03-30 17:39:00',
            'updated_at' => '2026-03-30 17:39:00',
        ]);

        // Inflow transfer to Aloqa Bank
        DB::table('transactions')->insert([
            'type' => 'income',
            'amount' => 10000000.00,
            'currency' => 'UZS',
            'payment_method' => 'bank_transfer',
            'account_id' => $aloqaId,
            'status' => 'approved',
            'transaction_date' => '2026-03-30 17:39:00',
            'notes' => '[Kirim - O\'tkazma] ID: #1. Boshlang\'ich kassa to\'ldirish',
            'created_by' => 1,
            'approved_by' => 1,
            'created_at' => '2026-03-30 17:39:00',
            'updated_at' => '2026-03-30 17:39:00',
        ]);

        // Transaction 1: Ikrom Xusanov payment of 69,683,854.00 UZS
        DB::table('transactions')->insert([
            'type' => 'income',
            'amount' => 69683854.00,
            'currency' => 'UZS',
            'payment_method' => 'bank_transfer',
            'account_id' => $aloqaId,
            'counterparty_id' => $cpXusanov,
            'status' => 'approved',
            'transaction_date' => '2026-04-22 22:10:00',
            'notes' => 'Mijoz to\'lovi',
            'created_by' => 1,
            'approved_by' => 1,
            'building_id' => $buildingId,
            'created_at' => '2026-04-22 22:10:00',
            'updated_at' => '2026-04-22 22:10:00',
        ]);

        // Transaction 2: Ikrom Xusanov payment of 1,100.00 USD
        DB::table('transactions')->insert([
            'type' => 'income',
            'amount' => 1100.00,
            'currency' => 'USD',
            'payment_method' => 'bank_transfer',
            'account_id' => $aloqaId,
            'counterparty_id' => $cpXusanov,
            'status' => 'approved',
            'transaction_date' => '2026-04-22 22:09:00',
            'notes' => 'Mijoz to\'lovi',
            'created_by' => 1,
            'approved_by' => 1,
            'building_id' => $buildingId,
            'created_at' => '2026-04-22 22:09:00',
            'updated_at' => '2026-04-22 22:09:00',
        ]);

        // Transaction 3: ZK expense of 200,000.00 UZS
        DB::table('transactions')->insert([
            'type' => 'expense',
            'amount' => 200000.00,
            'currency' => 'UZS',
            'payment_method' => 'bank_transfer',
            'account_id' => $aloqaId,
            'counterparty_id' => $cpZk,
            'status' => 'approved',
            'transaction_date' => '2026-04-30 14:35:00',
            'notes' => 'Ta\'minotchi uchun',
            'created_by' => 1,
            'approved_by' => 1,
            'building_id' => $buildingId,
            'created_at' => '2026-04-30 14:35:00',
            'updated_at' => '2026-04-30 14:35:00',
        ]);

        // Transaction 4: Azimjon Samirov payment of 85,500,000.00 UZS
        DB::table('transactions')->insert([
            'type' => 'income',
            'amount' => 85500000.00,
            'currency' => 'UZS',
            'payment_method' => 'bank_transfer',
            'account_id' => $aloqaId,
            'counterparty_id' => $cpSamirov,
            'status' => 'approved',
            'transaction_date' => '2026-05-11 01:32:00',
            'notes' => 'Mijoz to\'lovi',
            'created_by' => 1,
            'approved_by' => 1,
            'building_id' => $buildingId,
            'created_at' => '2026-05-11 01:32:00',
            'updated_at' => '2026-05-11 01:32:00',
        ]);

        // Transaction 5: Azimjon Samirov payment of 1,000.00 USD
        DB::table('transactions')->insert([
            'type' => 'income',
            'amount' => 1000.00,
            'currency' => 'USD',
            'payment_method' => 'bank_transfer',
            'account_id' => $aloqaId,
            'counterparty_id' => $cpSamirov,
            'status' => 'approved',
            'transaction_date' => '2026-05-11 15:19:00',
            'notes' => 'Mijoz to\'lovi',
            'created_by' => 1,
            'approved_by' => 1,
            'building_id' => $buildingId,
            'created_at' => '2026-05-11 15:19:00',
            'updated_at' => '2026-05-11 15:19:00',
        ]);
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropForeign(['counterparty_id']);
            $table->dropForeign(['invoice_id']);
            $table->dropColumn(['account_id', 'counterparty_id', 'invoice_id']);
        });

        Schema::dropIfExists('money_transfers');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('counterparties');
        Schema::dropIfExists('cash_registers');
        Schema::dropIfExists('currency_rates');
    }
};
