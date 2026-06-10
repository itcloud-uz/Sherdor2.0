<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;



Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('admin.dashboard');
    }
    return Inertia::render('Public/Landing');
})->name('landing');

Route::redirect('/dashboard', '/admin/dashboard')->name('dashboard');

// Public Receipt Verification Route
Route::get('/receipt/verify/{hash}', [\App\Http\Controllers\Public\PublicReceiptController::class, 'verify'])->name('public.receipt.verify');
Route::post('/receipt/verify/{hash}/auth', [\App\Http\Controllers\Public\PublicReceiptController::class, 'authenticate'])->name('public.receipt.auth');

// Public Lead Form (Landing Page)
Route::get('/promo/offers', function () {
    $settings = \App\Models\SystemSetting::first();
    return Inertia::render('Public/LeadForm', [
        'promoSettings' => $settings ? [
            'promo_title' => $settings->promo_title,
            'promo_subtitle' => $settings->promo_subtitle,
            'promo_description' => $settings->promo_description,
            'promo_image' => $settings->promo_image,
        ] : null,
    ]);
})->name('promo.offers');

Route::middleware(['auth', 'verified', 'role.restrictions'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/financial-reports', [\App\Http\Controllers\Admin\FinancialReportController::class, 'index'])->name('financial.reports');
    Route::get('/crm', [\App\Http\Controllers\Admin\CrmController::class, 'index'])->name('crm');
    Route::post('/crm/clients', [\App\Http\Controllers\Admin\CrmController::class, 'storeClient'])->name('crm.clients.store');
    Route::put('/crm/clients/{client}', [\App\Http\Controllers\Admin\CrmController::class, 'updateClient'])->name('crm.clients.update');
    Route::delete('/crm/clients/{client}', [\App\Http\Controllers\Admin\CrmController::class, 'deleteClient'])->name('crm.clients.delete');
    Route::get('/crm/clients/{client}/details', [\App\Http\Controllers\Admin\CrmController::class, 'getClientDetails'])->name('crm.clients.details');
    Route::get('/crm/clients/export/excel', [\App\Http\Controllers\Admin\ClientExportController::class, 'exportExcel'])->name('crm.export.clients.excel');
    Route::get('/crm/clients/export/pdf', [\App\Http\Controllers\Admin\ClientExportController::class, 'exportPdf'])->name('crm.export.clients.pdf');
    Route::put('/crm/leads/{lead}', [\App\Http\Controllers\Admin\CrmController::class, 'updateLead'])->name('crm.leads.update');
    Route::delete('/crm/leads/{lead}', [\App\Http\Controllers\Admin\CrmController::class, 'destroyLead'])->name('crm.leads.destroy');
    Route::post('/crm/schedules/{schedule}/pay', [\App\Http\Controllers\Admin\CrmController::class, 'paySchedule'])->name('crm.schedules.pay');
    Route::post('/crm/schedules/{schedule}/send-warning-sms', [\App\Http\Controllers\Admin\CrmController::class, 'sendWarningSms'])->name('crm.schedules.send_warning_sms');
    Route::post('/crm/contracts/{contract}/recalculate-penalty', [\App\Http\Controllers\Admin\CrmController::class, 'recalculatePenalty'])->name('crm.contracts.recalculate_penalty');
    
    // Leads / Kanban Actions
    Route::post('/crm/leads/{lead}/log-contact', [\App\Http\Controllers\Admin\CrmController::class, 'logContact'])->name('crm.leads.log_contact');
    Route::post('/crm/leads/{lead}/update-status', [\App\Http\Controllers\Admin\CrmController::class, 'updateLeadStatus'])->name('crm.leads.update_status');
    Route::post('/crm/leads/{lead}/quick-sms', [\App\Http\Controllers\Admin\CrmController::class, 'quickSms'])->name('crm.leads.quick_sms');

    // CRM Posts & Settings
    Route::post('/crm/posts', [\App\Http\Controllers\Admin\CrmController::class, 'storePost'])->name('crm.posts.store');
    Route::delete('/crm/posts/{post}', [\App\Http\Controllers\Admin\CrmController::class, 'deletePost'])->name('crm.posts.destroy');
    Route::post('/crm/settings', [\App\Http\Controllers\Admin\CrmController::class, 'saveSettings'])->name('crm.settings.save');

    Route::post('/contracts', [\App\Http\Controllers\Admin\ContractController::class, 'store'])->name('contracts.store');
    Route::put('/contracts/{contract}', [\App\Http\Controllers\Admin\ContractController::class, 'update'])->name('contracts.update');
    Route::delete('/contracts/{contract}', [\App\Http\Controllers\Admin\ContractController::class, 'destroy'])->name('contracts.destroy');
    Route::post('/contracts/{contract}/update-penalty', [\App\Http\Controllers\Admin\ContractController::class, 'updatePenaltyRate'])->name('contracts.update_penalty');
    Route::post('/contracts/{contract}/cancel', [\App\Http\Controllers\Admin\ContractController::class, 'cancel'])->name('contracts.cancel');
    Route::post('/contracts/{contract}/force-activate', [\App\Http\Controllers\Admin\ContractController::class, 'forceActivate'])->name('contracts.force_activate');
    Route::get('/contracts/cancelled', [\App\Http\Controllers\Admin\ContractController::class, 'getCancelledContracts'])->name('contracts.cancelled');
    
    // Multi-format Contract Exports (Client Ledger)
    Route::get('/crm/ledger/{contract}/export/pdf', [\App\Http\Controllers\Admin\LedgerExportController::class, 'exportPdf'])->name('contracts.export.pdf');
    Route::get('/crm/ledger/{contract}/export/docx', [\App\Http\Controllers\Admin\LedgerExportController::class, 'exportDocx'])->name('contracts.export.docx');
    Route::get('/crm/ledger/{contract}/export/xlsx', [\App\Http\Controllers\Admin\LedgerExportController::class, 'exportXlsx'])->name('contracts.export.xlsx');
    
    Route::get('/crm/contracts/{contract}/export-doc/pdf', [\App\Http\Controllers\Admin\LedgerExportController::class, 'exportContractPdf'])->name('contracts.document.export.pdf');
    
    Route::post('/contracts/{contract}/upload-signature', [\App\Http\Controllers\Admin\ContractController::class, 'uploadAndVerifySignature'])->name('contracts.upload_signature');
    Route::get('/facility', [\App\Http\Controllers\Admin\FacilityController::class, 'index'])->name('facility');
    Route::post('/facility/buildings', [\App\Http\Controllers\Admin\FacilityController::class, 'storeBuilding'])->name('facility.buildings.store');
    Route::put('/facility/buildings/{building}', [\App\Http\Controllers\Admin\FacilityController::class, 'updateBuilding'])->name('facility.buildings.update');
    Route::delete('/facility/buildings/{building}', [\App\Http\Controllers\Admin\FacilityController::class, 'destroyBuilding'])->name('facility.buildings.destroy');
    Route::post('/facility/stages', [\App\Http\Controllers\Admin\FacilityController::class, 'storeStage'])->name('facility.stages.store');
    Route::post('/facility/floors', [\App\Http\Controllers\Admin\FacilityController::class, 'storeFloor'])->name('facility.floors.store');
    Route::put('/facility/floors/{floor}', [\App\Http\Controllers\Admin\FacilityController::class, 'updateFloor'])->name('facility.floors.update');
    Route::delete('/facility/floors/{floor}', [\App\Http\Controllers\Admin\FacilityController::class, 'destroyFloor'])->name('facility.floors.destroy');
    Route::post('/facility/units', [\App\Http\Controllers\Admin\FacilityController::class, 'storeUnit'])->name('facility.units.store');
    Route::put('/facility/units/{unit}', [\App\Http\Controllers\Admin\FacilityController::class, 'updateUnit'])->name('facility.units.update');

    // Developer Settings
    Route::get('/developer/settings', [\App\Http\Controllers\Admin\DeveloperSettingsController::class, 'index'])->name('developer.settings');
    Route::post('/developer/lock', [\App\Http\Controllers\Admin\DeveloperSettingsController::class, 'lock'])->name('developer.lock');
    Route::post('/developer/otp/request', [\App\Http\Controllers\Admin\DeveloperSettingsController::class, 'requestOtp'])->name('developer.otp.request');
    Route::post('/developer/otp/verify', [\App\Http\Controllers\Admin\DeveloperSettingsController::class, 'verifyOtp'])->name('developer.otp.verify');
    Route::post('/developer/keys', [\App\Http\Controllers\Admin\DeveloperSettingsController::class, 'generateKey'])->name('developer.keys.store');
    Route::delete('/developer/keys/{id}', [\App\Http\Controllers\Admin\DeveloperSettingsController::class, 'revokeKey'])->name('developer.keys.destroy');
    Route::post('/developer/sip', [\App\Http\Controllers\Admin\DeveloperSettingsController::class, 'updateSipSettings'])->name('developer.sip.update');
    Route::post('/developer/promo', [\App\Http\Controllers\Admin\DeveloperSettingsController::class, 'updatePromoSettings'])->name('developer.promo.update');
    Route::put('/developer/users/{id}/chat-id', [\App\Http\Controllers\Admin\DeveloperSettingsController::class, 'updateUserChatId'])->name('developer.users.update_chat_id');

    Route::delete('/facility/units/{unit}', [\App\Http\Controllers\Admin\FacilityController::class, 'destroyUnit'])->name('facility.units.destroy');
    
    // Finance Module
    Route::get('/finance', [\App\Http\Controllers\Admin\FinanceController::class, 'index'])->name('finance');
    
    Route::prefix('finance')->name('finance.')->group(function () {
        Route::get('/expenses-breakdown', [\App\Http\Controllers\Admin\FinanceController::class, 'expensesBreakdown'])->name('expenses.breakdown');
        Route::get('/expenses-report/export/pdf', [\App\Http\Controllers\Admin\ReportExportController::class, 'exportExpensesReport'])->name('expenses.report.export.pdf');

        Route::post('/transactions', [\App\Http\Controllers\Admin\FinanceController::class, 'store'])->name('transactions.store');
        Route::put('/transactions/{transaction}', [\App\Http\Controllers\Admin\FinanceController::class, 'update'])->name('transactions.update');
        Route::delete('/transactions/{transaction}', [\App\Http\Controllers\Admin\FinanceController::class, 'destroy'])->name('transactions.destroy');
        Route::get('/transactions/{transaction}/export/pdf', [\App\Http\Controllers\Admin\TransactionExportController::class, 'exportPdf'])->name('transactions.export.pdf');
        Route::get('/transactions/{transaction}/print', [\App\Http\Controllers\Admin\TransactionExportController::class, 'print'])->name('transactions.export.print');
        Route::post('/transactions/{transaction}/approve', [\App\Http\Controllers\Admin\FinanceController::class, 'approveTransaction'])->name('transactions.approve');
        Route::post('/transactions/{transaction}/reject', [\App\Http\Controllers\Admin\FinanceController::class, 'rejectTransaction'])->name('transactions.reject');
        
        // Supplier Payments from Finance
        Route::post('/suppliers/pay', [\App\Http\Controllers\Admin\FinanceController::class, 'paySupplier'])->name('suppliers.pay');
        Route::post('/categories', [\App\Http\Controllers\Admin\FinanceController::class, 'storeCategory'])->name('categories.store');
        Route::post('/liabilities', [\App\Http\Controllers\Admin\FinanceController::class, 'storeLiability'])->name('liabilities.store');
        Route::post('/internal-transfer', [\App\Http\Controllers\Admin\FinanceController::class, 'internalTransfer'])->name('internal_transfer');

        // Cash Registers
        Route::post('/registers', [\App\Http\Controllers\Admin\FinanceController::class, 'storeRegister'])->name('registers.store');
        Route::put('/registers/{register}', [\App\Http\Controllers\Admin\FinanceController::class, 'updateRegister'])->name('registers.update');
        Route::delete('/registers/{register}', [\App\Http\Controllers\Admin\FinanceController::class, 'destroyRegister'])->name('registers.destroy');

        // Counterparties
        Route::post('/counterparties', [\App\Http\Controllers\Admin\FinanceController::class, 'storeCounterparty'])->name('counterparties.store');
        Route::put('/counterparties/{counterparty}', [\App\Http\Controllers\Admin\FinanceController::class, 'updateCounterparty'])->name('counterparties.update');
        Route::delete('/counterparties/{counterparty}', [\App\Http\Controllers\Admin\FinanceController::class, 'destroyCounterparty'])->name('counterparties.destroy');

        // Invoices
        Route::post('/invoices', [\App\Http\Controllers\Admin\FinanceController::class, 'storeInvoice'])->name('invoices.store');
        Route::put('/invoices/{invoice}', [\App\Http\Controllers\Admin\FinanceController::class, 'updateInvoice'])->name('invoices.update');
        Route::delete('/invoices/{invoice}', [\App\Http\Controllers\Admin\FinanceController::class, 'destroyInvoice'])->name('invoices.destroy');

        // Excel Exports
        Route::get('/invoices/export/xlsx', [\App\Http\Controllers\Admin\FinanceController::class, 'exportInvoicesExcel'])->name('invoices.export.xlsx');
        Route::get('/transactions/export/xlsx', [\App\Http\Controllers\Admin\FinanceController::class, 'exportTransactionsExcel'])->name('transactions.export.xlsx');
        Route::post('/rates', [\App\Http\Controllers\Admin\FinanceController::class, 'updateRates'])->name('rates.update');
    });

    Route::put('/finance/liabilities/{liability}', [\App\Http\Controllers\Admin\FinanceController::class, 'updateLiability'])->name('finance.liabilities.update');
    Route::delete('/finance/liabilities/{liability}', [\App\Http\Controllers\Admin\FinanceController::class, 'destroyLiability'])->name('finance.liabilities.destroy');
    Route::post('/finance/liabilities/{liability}/payments', [\App\Http\Controllers\Admin\FinanceController::class, 'storeLiabilityPayment'])->name('finance.liabilities.payments.store');
    
    Route::get('/finance/transactions/{transaction}/export/pdf', [\App\Http\Controllers\Admin\TransactionExportController::class, 'exportPdf'])->name('finance.transactions.export.pdf');
    Route::get('/finance/category-export/{category}/excel', [\App\Http\Controllers\Admin\TransactionExportController::class, 'exportCategoryExcel'])->name('finance.transactions.export.category.excel');
    Route::get('/finance/category-export/{category}/pdf', [\App\Http\Controllers\Admin\TransactionExportController::class, 'exportCategoryPdf'])->name('finance.transactions.export.category.pdf');
    
    // Liability Exports
    Route::get('/finance/liabilities/{liability}/export/pdf', [\App\Http\Controllers\Admin\LiabilityExportController::class, 'exportPdf'])->name('finance.liabilities.export.pdf');
    Route::get('/finance/liabilities/{liability}/export/docx', [\App\Http\Controllers\Admin\LiabilityExportController::class, 'exportDocx'])->name('finance.liabilities.export.docx');
    Route::get('/finance/liabilities/{liability}/export/xlsx', [\App\Http\Controllers\Admin\LiabilityExportController::class, 'exportXlsx'])->name('finance.liabilities.export.xlsx');
    
    Route::get('/partners', [\App\Http\Controllers\Admin\PartnerHubController::class, 'index'])->name('partners');
    Route::post('/partners', [\App\Http\Controllers\Admin\PartnerHubController::class, 'storePartner'])->name('partners.store');
    Route::put('/partners/{partner}', [\App\Http\Controllers\Admin\PartnerHubController::class, 'updatePartner'])->name('partners.update');
    Route::delete('/partners/{partner}', [\App\Http\Controllers\Admin\PartnerHubController::class, 'destroyPartner'])->name('partners.destroy');
    Route::post('/partners/{partner}/advance', [\App\Http\Controllers\Admin\PartnerHubController::class, 'provideAdvance'])->name('partners.advance');
    Route::post('/partners/{partner}/expense', [\App\Http\Controllers\Admin\PartnerHubController::class, 'reportExpense'])->name('partners.expense');
    Route::get('/partners/{partner}/ledger', [\App\Http\Controllers\Admin\PartnerHubController::class, 'getPartnerLedger'])->name('partners.ledger');
    
    // Fixed Assets / Assosiy Vositalar
    Route::post('/partners/assets', [\App\Http\Controllers\Admin\PartnerHubController::class, 'storeAsset'])->name('partners.assets.store');
    Route::put('/partners/assets/{asset}', [\App\Http\Controllers\Admin\PartnerHubController::class, 'updateAsset'])->name('partners.assets.update');
    Route::delete('/partners/assets/{asset}', [\App\Http\Controllers\Admin\PartnerHubController::class, 'destroyAsset'])->name('partners.assets.destroy');
    
    // Approvals / Tasdiqlar
    Route::get('/approvals', [\App\Http\Controllers\Admin\ApprovalController::class, 'index'])->name('approvals');
    Route::post('/approvals/{approval}/approve', [\App\Http\Controllers\Admin\ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/{approval}/reject', [\App\Http\Controllers\Admin\ApprovalController::class, 'reject'])->name('approvals.reject');

    Route::get('/hr', [\App\Http\Controllers\Admin\HrController::class, 'index'])->name('hr');
    
    // Brigades & Machine Owners
    Route::post('/hr/brigades', [\App\Http\Controllers\Admin\HrController::class, 'storeBrigade'])->name('hr.brigade.store');
    Route::put('/hr/brigades/{brigade}', [\App\Http\Controllers\Admin\HrController::class, 'updateBrigade'])->name('hr.brigade.update');
    Route::delete('/hr/brigades/{brigade}', [\App\Http\Controllers\Admin\HrController::class, 'deleteBrigade'])->name('hr.brigade.destroy');
    Route::get('/hr/brigades/{brigade}/transactions', [\App\Http\Controllers\Admin\HrController::class, 'getBrigadeLedger'])->name('hr.brigade.transactions');
    Route::post('/hr/brigades/{brigade}/transaction', [\App\Http\Controllers\Admin\HrController::class, 'storeBrigadeTransaction'])->name('hr.brigade.transaction.store');
    Route::get('/hr/brigades/{brigade}/export-ledger', [\App\Http\Controllers\Admin\HrController::class, 'exportBrigadeLedger'])->name('hr.brigade.ledger.export');
    
    Route::post('/hr/machine-owners', [\App\Http\Controllers\Admin\HrController::class, 'storeMachineOwner'])->name('hr.machine_owner.store');
    Route::put('/hr/machine-owners/{machine_owner}', [\App\Http\Controllers\Admin\HrController::class, 'updateMachineOwner'])->name('hr.machine_owner.update');
    Route::delete('/hr/machine-owners/{machine_owner}', [\App\Http\Controllers\Admin\HrController::class, 'deleteMachineOwner'])->name('hr.machine_owner.destroy');
    Route::post('/hr/owner/{owner}/transaction', [\App\Http\Controllers\Admin\HrController::class, 'storeOwnerTransaction'])->name('hr.owner.transaction.store');
    Route::post('/hr/worker', [\App\Http\Controllers\Admin\HrController::class, 'storeWorker'])->name('hr.worker.store');
    Route::post('/hr/workers/{worker}', [\App\Http\Controllers\Admin\HrController::class, 'updateWorker'])->name('hr.worker.update');
    Route::delete('/hr/workers/{worker}', [\App\Http\Controllers\Admin\HrController::class, 'deleteWorker'])->name('hr.worker.destroy');
    Route::post('/hr/manual-log', [\App\Http\Controllers\Admin\HrController::class, 'storeManualLog'])->name('hr.manual_log.store');
    Route::get('/hr/worker/{worker}/ledger', [\App\Http\Controllers\Admin\HrController::class, 'getWorkerLedger'])->name('hr.worker.ledger');
    Route::post('/hr/worker/{worker}/transaction', [\App\Http\Controllers\Admin\HrController::class, 'storeWorkerTransaction'])->name('hr.worker.transaction.store');
    
    // Guard Ledger
    Route::get('/hr/guard/{guard}/transactions', [\App\Http\Controllers\Admin\HrController::class, 'getGuardLedger'])->name('hr.guard.transactions');
    Route::post('/hr/guard/{guard}/transaction', [\App\Http\Controllers\Admin\HrController::class, 'storeGuardTransaction'])->name('hr.guard.transaction.store');
    
    Route::post('/hr/users', [\App\Http\Controllers\Admin\HrController::class, 'storeUser'])->name('hr.users.store');
    Route::post('/hr/users/{user}', [\App\Http\Controllers\Admin\HrController::class, 'updateUser'])->name('hr.users.update');
    Route::delete('/hr/users/{user}', [\App\Http\Controllers\Admin\HrController::class, 'deleteUser'])->name('hr.users.delete');
    Route::get('/hr/users/{user}/ledger', [\App\Http\Controllers\Admin\HrController::class, 'getUserLedger'])->name('hr.users.ledger');
    Route::post('/hr/users/{user}/transaction', [\App\Http\Controllers\Admin\HrController::class, 'storeUserTransaction'])->name('hr.users.transaction.store');
    
    Route::post('/hr/machinery', [\App\Http\Controllers\Admin\HrController::class, 'storeMachinery'])->name('hr.machinery.store');
    Route::post('/hr/machinery/{machinery}', [\App\Http\Controllers\Admin\HrController::class, 'updateMachinery'])->name('hr.machinery.update');
    Route::delete('/hr/machinery/{machinery}', [\App\Http\Controllers\Admin\HrController::class, 'deleteMachinery'])->name('hr.machinery.destroy');
    Route::get('/hr/machinery/{machinery}/ledger', [\App\Http\Controllers\Admin\HrController::class, 'getMachineryLedger'])->name('hr.machinery.ledger');
    Route::get('/hr/owner/{owner}/transactions', [\App\Http\Controllers\Admin\HrController::class, 'getOwnerLedger'])->name('hr.owner.transactions');
    Route::get('/hr/owner/{owner}/ledger/export', [\App\Http\Controllers\Admin\HrController::class, 'exportOwnerLedger'])->name('hr.owner.ledger.export');
    Route::post('/hr/machinery/{machinery}/transaction', [\App\Http\Controllers\Admin\HrController::class, 'storeMachineryTransaction'])->name('hr.machinery.transaction.store');
    
    Route::post('/hr/log', [\App\Http\Controllers\Admin\HrController::class, 'storeLog'])->name('hr.log.store');
    Route::put('/hr/log/{log}', [\App\Http\Controllers\Admin\HrController::class, 'updateLog'])->name('hr.log.update');
    Route::delete('/hr/log/{log}', [\App\Http\Controllers\Admin\HrController::class, 'deleteLog'])->name('hr.log.destroy');
    
    Route::put('/hr/transactions/{type}/{id}', [\App\Http\Controllers\Admin\HrController::class, 'updateTransaction'])->name('hr.transactions.update');
    Route::delete('/hr/transactions/{type}/{id}', [\App\Http\Controllers\Admin\HrController::class, 'deleteTransaction'])->name('hr.transactions.destroy');
    
    Route::post('/hr/biometric', [\App\Http\Controllers\Admin\HrController::class, 'storeBiometric'])->name('hr.biometric.store');
    Route::post('/hr/payroll', [\App\Http\Controllers\Admin\HrController::class, 'generatePayroll'])->name('hr.payroll.generate');
    Route::get('/hr/payroll/{id}/print', [\App\Http\Controllers\Admin\HrController::class, 'printPayroll'])->name('hr.payroll.print');
    Route::post('/hr/kpi-settings', [\App\Http\Controllers\Admin\HrController::class, 'storeKpiSettings'])->name('hr.kpi_settings.store');

    Route::post('/hr/cash-requests', [\App\Http\Controllers\Admin\HrController::class, 'storeCashRequest'])->name('hr.cash_requests.store');
    Route::post('/hr/cash-requests/{cashRequest}/approve', [\App\Http\Controllers\Admin\HrController::class, 'approveCashRequest'])->name('hr.cash_requests.approve');
    Route::post('/hr/cash-requests/{cashRequest}/reject', [\App\Http\Controllers\Admin\HrController::class, 'rejectCashRequest'])->name('hr.cash_requests.reject');

    Route::get('/ai-agents', [\App\Http\Controllers\Admin\AiAgentController::class, 'index'])->name('ai_agents');
    Route::post('/ai-agents/settings', [\App\Http\Controllers\Admin\AiAgentController::class, 'saveSettings'])->name('ai_agents.settings');
    Route::post('/ai-agents/test-sms', [\App\Http\Controllers\Admin\AiAgentController::class, 'testSms'])->name('ai_agents.test_sms');
    Route::post('/ai-agents/template', [\App\Http\Controllers\Admin\AiAgentController::class, 'saveTemplate'])->name('ai_agents.template.save');
    Route::delete('/ai-agents/template/{id}', [\App\Http\Controllers\Admin\AiAgentController::class, 'deleteTemplate'])->name('ai_agents.template.delete');
    Route::post('/ai-agents/rule', [\App\Http\Controllers\Admin\AiAgentController::class, 'saveRule'])->name('ai_agents.rule.save');
    Route::delete('/ai-agents/rule/{id}', [\App\Http\Controllers\Admin\AiAgentController::class, 'deleteRule'])->name('ai_agents.rule.delete');
    
    // Telephony
    Route::get('/telephony', [\App\Http\Controllers\Admin\TelephonyController::class, 'index'])->name('telephony.index');
    Route::get('/telephony/logs', [\App\Http\Controllers\Admin\TelephonyController::class, 'getLogs'])->name('telephony.logs');
    Route::get('/telephony/audios', [\App\Http\Controllers\Admin\TelephonyController::class, 'getAudios'])->name('telephony.audios.get');
    Route::post('/telephony/audios', [\App\Http\Controllers\Admin\TelephonyController::class, 'uploadAudio'])->name('telephony.audios.upload');
    Route::post('/telephony/campaigns', [\App\Http\Controllers\Admin\TelephonyController::class, 'storeCampaign'])->name('telephony.campaigns.store');
    Route::get('/telephony/ai-config', [\App\Http\Controllers\Admin\TelephonyController::class, 'getAiConfig'])->name('telephony.ai_config.get');
    Route::post('/telephony/ai-config', [\App\Http\Controllers\Admin\TelephonyController::class, 'saveAiConfig'])->name('telephony.ai_config.save');
    Route::post('/telephony/call', [\App\Http\Controllers\Api\TelephonyController::class, 'triggerCall'])->name('telephony.call');

    // Migration Hub routes removed
    Route::get('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [\App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/lead-sources', [\App\Http\Controllers\Admin\SettingsController::class, 'storeLeadSource'])->name('settings.lead_sources.store');
    Route::put('/settings/lead-sources/{leadSource}', [\App\Http\Controllers\Admin\SettingsController::class, 'updateLeadSource'])->name('settings.lead_sources.update');
    Route::delete('/settings/lead-sources/{leadSource}', [\App\Http\Controllers\Admin\SettingsController::class, 'destroyLeadSource'])->name('settings.lead_sources.destroy');
    Route::post('/settings/users', [\App\Http\Controllers\Admin\SettingsController::class, 'storeUser'])->name('settings.users.store');
    Route::put('/settings/users/{user}', [\App\Http\Controllers\Admin\SettingsController::class, 'updateUser'])->name('settings.users.update');
    Route::delete('/settings/users/{user}', [\App\Http\Controllers\Admin\SettingsController::class, 'destroyUser'])->name('settings.users.destroy');
    Route::post('/settings/roles-permissions', [\App\Http\Controllers\Admin\SettingsController::class, 'updateRolesPermissions'])->name('settings.roles_permissions.update');
    Route::post('/settings/sms/send', [\App\Http\Controllers\Admin\SettingsController::class, 'sendSms'])->name('settings.sms.send');

    // Receipts / Cheklar
    Route::get('/receipts', [\App\Http\Controllers\Admin\ReceiptController::class, 'index'])->name('receipts');

    // Warehouse & Procurement
    Route::prefix('warehouse')->name('warehouse.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\WarehouseController::class, 'index'])->name('index');
        Route::get('/inventory', [\App\Http\Controllers\Admin\WarehouseController::class, 'inventory'])->name('inventory');
        Route::get('/suppliers', [\App\Http\Controllers\Admin\WarehouseController::class, 'suppliers'])->name('suppliers');
        Route::post('/suppliers', [\App\Http\Controllers\Admin\WarehouseController::class, 'storeSupplier'])->name('suppliers.store');
        Route::put('/suppliers/{supplier}', [\App\Http\Controllers\Admin\WarehouseController::class, 'updateSupplier'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [\App\Http\Controllers\Admin\WarehouseController::class, 'destroySupplier'])->name('suppliers.destroy');
        Route::post('/suppliers/{supplier}/upload', [\App\Http\Controllers\Admin\WarehouseController::class, 'uploadSupplierDocument'])->name('suppliers.upload');
        Route::post('/suppliers/{supplier}/telegram', [\App\Http\Controllers\Admin\WarehouseController::class, 'sendSupplierDocumentToTelegram'])->name('suppliers.telegram');
        Route::post('/suppliers/{supplier}/pay', [\App\Http\Controllers\Admin\WarehouseController::class, 'paySupplier'])->name('suppliers.pay');
        Route::get('/suppliers/{supplier}/ledger', [\App\Http\Controllers\Admin\WarehouseController::class, 'supplierLedgerPdf'])->name('suppliers.ledger');
        Route::get('/suppliers/{supplier}/ledger/excel', [\App\Http\Controllers\Admin\WarehouseController::class, 'supplierLedgerExcel'])->name('suppliers.ledger.excel');
        Route::get('/orders', [\App\Http\Controllers\Admin\WarehouseController::class, 'orders'])->name('orders');
        Route::post('/orders', [\App\Http\Controllers\Admin\WarehouseController::class, 'storeOrder'])->name('orders.store');
        Route::put('/orders/{order}', [\App\Http\Controllers\Admin\WarehouseController::class, 'updateOrder'])->name('orders.update');
        Route::delete('/orders/{order}', [\App\Http\Controllers\Admin\WarehouseController::class, 'destroyOrder'])->name('orders.destroy');
        Route::get('/orders/{order}/pdf', [\App\Http\Controllers\Admin\WarehouseController::class, 'downloadOrderPdf'])->name('orders.pdf');
        Route::post('/orders/{order}/receive', [\App\Http\Controllers\Admin\WarehouseController::class, 'receiveOrder'])->name('orders.receive');
    });
});

Route::get('/operator-panel', [\App\Http\Controllers\OperatorController::class, 'index'])->middleware(['auth', 'verified'])->name('operator');
Route::post('/operator-panel/requests', [\App\Http\Controllers\OperatorController::class, 'submitRequest'])->middleware(['auth', 'verified'])->name('operator.requests.store');

Route::get('/partner-panel', [\App\Http\Controllers\Admin\PartnerController::class, 'index'])->middleware(['auth', 'verified'])->name('partner');
Route::post('/partner-panel/vote/{vote}', [\App\Http\Controllers\Admin\PartnerController::class, 'vote'])->middleware(['auth', 'verified'])->name('partner.vote');
Route::post('/partner-panel/transaction', [\App\Http\Controllers\Admin\PartnerController::class, 'storeTransaction'])->middleware(['auth', 'verified'])->name('partner.transaction.store');

Route::middleware('auth')->group(function () {
    Route::get('/settings', [\App\Http\Controllers\UserSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [\App\Http\Controllers\UserSettingsController::class, 'update'])->name('settings.update');
    
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
