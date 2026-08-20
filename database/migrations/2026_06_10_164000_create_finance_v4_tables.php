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
        if (!DB::table('currency_rates')->where('code', 'USD')->exists()) {
            DB::table('currency_rates')->insert([
                ['code' => 'USD', 'rate' => 12350.0000, 'created_at' => now(), 'updated_at' => now()],
                ['code' => 'EUR', 'rate' => 16890.0000, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (DB::table('cash_registers')->count() === 0) {
            DB::table('cash_registers')->insert([
                [
                    'name' => 'Aloqa Bank Hisobi',
                    'type' => 'bank',
                    'initial_balance_uzs' => 0.00,
                    'initial_balance_usd' => 0.00,
                    'initial_balance_eur' => 0.00,
                    'balance' => 0.00,
                    'bank_name' => 'Aloqa bank',
                    'account_number' => '0001 0002 0003 0004 0005',
                    'mfo_code' => '00015',
                    'building_id' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Naqd Kassa',
                    'type' => 'cash',
                    'initial_balance_uzs' => 0.00,
                    'initial_balance_usd' => 0.00,
                    'initial_balance_eur' => 0.00,
                    'balance' => 0.00,
                    'bank_name' => null,
                    'account_number' => null,
                    'mfo_code' => null,
                    'building_id' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
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
