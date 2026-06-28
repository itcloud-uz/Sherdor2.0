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
        // 1. Update contract_templates table
        Schema::table('contract_templates', function (Blueprint $table) {
            if (Schema::hasColumn('contract_templates', 'content')) {
                $table->dropColumn('content');
            }
            $table->string('docx_path')->nullable()->after('type');
            $table->longText('preview_html')->nullable()->after('docx_path');
            $table->json('placeholders')->nullable()->after('preview_html');
        });

        // 2. Create clause_variant_groups table
        Schema::create('clause_variant_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_template_id')->constrained('contract_templates')->onDelete('cascade');
            $table->string('anchor'); // e.g. {{TO_LOV_SHARTI}}
            $table->string('label');  // e.g. "To'lov tartibi"
            $table->timestamps();
        });

        // 3. Create clause_variants table
        Schema::create('clause_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clause_variant_group_id')->constrained('clause_variant_groups')->onDelete('cascade');
            $table->string('key');    // e.g. NAQT, GRAFIK, IPOTEKA
            $table->string('label');  // e.g. "Naqd", "Grafik bo'yicha"
            $table->text('content');  // Variant text content
            $table->json('placeholders')->nullable(); // Sub-placeholders for this variant
            $table->timestamps();
        });

        // 4. Add company details to system_settings
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('company_address')->nullable()->after('company_name');
            $table->string('company_bank_account')->nullable()->after('company_address');
            $table->string('company_bank_name')->nullable()->after('company_bank_account');
            $table->string('company_mfo')->nullable()->after('company_bank_name');
            $table->string('company_inn')->nullable()->after('company_mfo');
            $table->string('company_director')->nullable()->after('company_inn');
            $table->string('company_phone')->nullable()->after('company_director');
        });

        // 5. Add selected_variants to contracts table
        Schema::table('contracts', function (Blueprint $table) {
            $table->json('selected_variants')->nullable()->after('contract_template_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('selected_variants');
        });

        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'company_address',
                'company_bank_account',
                'company_bank_name',
                'company_mfo',
                'company_inn',
                'company_director',
                'company_phone'
            ]);
        });

        Schema::dropIfExists('clause_variants');
        Schema::dropIfExists('clause_variant_groups');

        Schema::table('contract_templates', function (Blueprint $table) {
            $table->text('content')->nullable()->after('description');
            $table->dropColumn(['docx_path', 'preview_html', 'placeholders']);
        });
    }
};
