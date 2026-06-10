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
        // 1. cash_registers
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['cash', 'bank'])->default('cash');
            $table->string('currency', 3)->default('UZS');
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->foreignId('building_id')->nullable()->constrained('buildings')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. counterparties
        Schema::create('counterparties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['client', 'supplier', 'employee'])->default('client');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('customable_type')->nullable();
            $table->unsignedBigInteger('customable_id')->nullable();
            $table->decimal('balance', 15, 2)->default(0.00);
            $table->timestamps();

            $table->index(['customable_type', 'customable_id']);
        });

        // 3. invoices
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

        // 4. money_transfers
        Schema::create('money_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_account_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->foreignId('to_account_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->decimal('sent_amount', 15, 2);
            $table->string('sent_currency', 3);
            $table->decimal('exchange_rate', 12, 4)->default(1.0000);
            $table->decimal('received_amount', 15, 2);
            $table->string('received_currency', 3);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 5. Alter transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->constrained('cash_registers')->nullOnDelete();
            $table->foreignId('counterparty_id')->nullable()->constrained('counterparties')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
        });

        // 6. Data Synchronization
        // Seed default registers with existing company balance values
        $mainBalance = 0;
        $creditBalance = 0;
        $balanceRow = DB::table('company_balances')->first();
        if ($balanceRow) {
            $mainBalance = $balanceRow->total_balance ?? 0;
            $creditBalance = $balanceRow->credit_balance ?? 0;
        }

        DB::table('cash_registers')->insert([
            [
                'name' => 'Asosiy Kassa (UZS)',
                'type' => 'cash',
                'currency' => 'UZS',
                'balance' => $mainBalance,
                'building_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Asosiy Kassa (USD)',
                'type' => 'cash',
                'currency' => 'USD',
                'balance' => 0.00,
                'building_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Asosiy Bank (UZS)',
                'type' => 'bank',
                'currency' => 'UZS',
                'balance' => 0.00,
                'building_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Asosiy Bank (USD)',
                'type' => 'bank',
                'currency' => 'USD',
                'balance' => 0.00,
                'building_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kredit Kassasi (UZS)',
                'type' => 'cash',
                'currency' => 'UZS',
                'balance' => $creditBalance,
                'building_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // Sync Clients
        $clients = DB::table('clients')->get();
        foreach ($clients as $client) {
            $name = trim(($client->last_name ?? '') . ' ' . ($client->first_name ?? '') . ' ' . ($client->middle_name ?? ''));
            if (empty($name)) {
                $name = 'Mijoz #' . $client->id;
            }
            DB::table('counterparties')->insert([
                'name' => $name,
                'type' => 'client',
                'phone' => $client->phone ?? null,
                'email' => null,
                'customable_type' => 'App\Models\Client',
                'customable_id' => $client->id,
                'balance' => 0.00, // or calculate based on payment_schedules later
                'created_at' => $client->created_at ?? now(),
                'updated_at' => $client->updated_at ?? now(),
            ]);
        }

        // Sync Suppliers
        $suppliers = DB::table('suppliers')->get();
        foreach ($suppliers as $supplier) {
            DB::table('counterparties')->insert([
                'name' => $supplier->name ?? ('Ta\'minotchi #' . $supplier->id),
                'type' => 'supplier',
                'phone' => $supplier->phone ?? null,
                'email' => $supplier->email ?? null,
                'customable_type' => 'App\Models\Supplier',
                'customable_id' => $supplier->id,
                'balance' => $supplier->balance ?? 0.00,
                'created_at' => $supplier->created_at ?? now(),
                'updated_at' => $supplier->updated_at ?? now(),
            ]);
        }

        // Sync Workers
        $workers = DB::table('workers')->get();
        foreach ($workers as $worker) {
            DB::table('counterparties')->insert([
                'name' => $worker->name ?? ('Xodim #' . $worker->id),
                'type' => 'employee',
                'phone' => $worker->phone ?? null,
                'email' => null,
                'customable_type' => 'App\Models\Worker',
                'customable_id' => $worker->id,
                'balance' => $worker->balance ?? 0.00,
                'created_at' => $worker->created_at ?? now(),
                'updated_at' => $worker->updated_at ?? now(),
            ]);
        }

        // Sync Users
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            // Avoid duplicate with workers if names match, or just import as user employees
            $exists = DB::table('counterparties')
                ->where('customable_type', 'App\Models\User')
                ->where('customable_id', $user->id)
                ->exists();
            if (!$exists) {
                DB::table('counterparties')->insert([
                    'name' => $user->name ?? ('Tizim xodimi #' . $user->id),
                    'type' => 'employee',
                    'phone' => $user->phone ?? null,
                    'email' => $user->email ?? null,
                    'customable_type' => 'App\Models\User',
                    'customable_id' => $user->id,
                    'balance' => $user->balance ?? 0.00,
                    'created_at' => $user->created_at ?? now(),
                    'updated_at' => $user->updated_at ?? now(),
                ]);
            }
        }
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
    }
};
