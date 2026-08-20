<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Contract;
use App\Models\CallLog;
use App\Models\Post;
use App\Models\CrmSetting;
use App\Models\User;
use App\Services\EskizService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CrmController extends Controller
{
    public function index()
    {
        $tab = request('tab', 'dashboard');

        $leads = [];
        $clients = [];
        $latestClientId = null;
        $contracts = [];
        $units = [];
        $partners = [];
        $overdueSchedules = [];
        $strictCollectionSchedules = [];
        $posts = [];
        $crmSettings = [];
        $users = [];
        $templates = [];
        $rules = [];
        $campaigns = [];
        $settings = null;
        $smsLogs = [];
        $isConnected = false;
        $transactions = [];
        $counterparties = [];

        $leadSources = [];
        $cashRegisters = [];
        $categories = [];
        $buildings = [];
        $contractTemplates = [];

        // 1. Conditional eager-loaded helper tables depending on active tab
        if (in_array($tab, ['dashboard', 'leads', 'clients', 'clients_new', 'clients_active', 'clients_debtors', 'clients_blacklist'])) {
            $leadSources = \App\Models\LeadSource::orderBy('name', 'asc')->get();
        }

        if (in_array($tab, ['dashboard', 'client_payments', 'clients', 'clients_new', 'clients_active', 'clients_debtors', 'clients_blacklist', 'contracts', 'leads'])) {
            $cashRegisters = \App\Models\CashRegister::where('is_active', true)->get();
        }

        if (in_array($tab, ['client_payments', 'dashboard'])) {
            $categories = \App\Models\TransactionCategory::all();
        }

        if (in_array($tab, ['dashboard', 'leads', 'clients', 'clients_new', 'clients_active', 'clients_debtors', 'clients_blacklist', 'contracts', 'client_payments', 'client_payment_reports'])) {
            $buildings = \App\Models\Building::latest()->get();
        }

        if (in_array($tab, ['leads', 'clients', 'clients_new', 'clients_active', 'clients_debtors', 'clients_blacklist', 'contracts'])) {
            $contractTemplates = \App\Models\ContractTemplate::with('variantGroups.variants')->latest()->get();
        }

        if (in_array($tab, ['dashboard', 'leads', 'clients', 'clients_new', 'clients_active', 'clients_debtors', 'clients_blacklist', 'contracts'])) {
            $partners = \App\Models\Partner::with('user:id,name')->get();
            $units = \App\Models\Unit::with([
                'floor' => function($q) {
                    $q->select('id', 'building_id', 'floor_number', 'floor_number as number');
                },
                'floor.building' => function($q) {
                    $q->select('id', 'name', 'block_name');
                }
            ])
            ->select('id', 'floor_id', 'unit_number', 'unit_number as number', 'status', 'area_sqm', 'price', 'rooms_count', 'podyezd')
            ->get();
        }

        // 2. Dashboard Tab
        if ($tab === 'dashboard') {
            $leads = Lead::with('user')->latest()->limit(500)->get();
        }

        // 3. Leads Tab
        if ($tab === 'leads') {
            $leads = Lead::with('user')->latest()->limit(500)->get();
            $users = User::with('roles')->orderBy('name', 'asc')->get();
        }


        // 4. Clients tabs
        $clients = \App\Models\Client::with([
            'contracts.unit.floor.building',
            'contracts.partner.user',
            'contracts.user',
            'contracts.paymentSchedules',
            'contracts.transactions'
        ])->orderBy('id', 'desc')->limit(500)->get();
        $latestClientId = \App\Models\Client::orderBy('id', 'desc')->first()?->id;
        if (in_array($tab, ['clients', 'clients_new', 'clients_active', 'clients_debtors', 'clients_blacklist'])) {
            $users = User::with('roles')->orderBy('name', 'asc')->get();
        }

        // 5. Contracts Tab
        if ($tab === 'contracts') {
            $contracts = Contract::with(['client', 'partner.user', 'unit.floor.building', 'paymentSchedules', 'transactions.category', 'refundSchedules'])->latest()->limit(500)->get();
            $users = User::with('roles')->orderBy('name', 'asc')->get();
        }

        // 11. Sales Agents Tab
        if ($tab === 'sales_agents') {
            $users = User::role('Sotuvchi')->with(['leads', 'contracts.unit.floor.building', 'roles'])->get();
        }

        // 6. Overdue Tab
        if ($tab === 'overdue') {
            $allOverdueSchedules = \App\Models\PaymentSchedule::with('contract.client')
                                ->where('status', 'overdue')
                                ->orderBy('due_date', 'asc')
                                ->limit(500)
                                ->get();
            $now = now();
            foreach ($allOverdueSchedules as $schedule) {
                if (!$schedule->contract) {
                    $overdueSchedules[] = $schedule;
                    continue;
                }

                $contractCreatedAt = \Carbon\Carbon::parse($schedule->contract->created_at);
                $contractEndDate = $contractCreatedAt->copy()->addMonths($schedule->contract->term_months);
                $daysOverdue = \Carbon\Carbon::parse($schedule->due_date)->diffInDays($now, false);

                if ($now->greaterThan($contractEndDate) || $daysOverdue >= 90) {
                    $strictCollectionSchedules[] = $schedule;
                } else {
                    $overdueSchedules[] = $schedule;
                }
            }
        }

        // 6. Posts Tab
        if ($tab === 'posts') {
            $posts = Post::with('user')->latest()->limit(500)->get();
        }

        // 7. Settings Tab
        if ($tab === 'settings') {
            $crmSettings = CrmSetting::all()->pluck('value', 'key');
            $users = User::with('roles')->orderBy('name', 'asc')->get();
        }

        // 8. SMS automation / settings
        if ($tab === 'sms') {
            $templates = \Illuminate\Support\Facades\DB::table('sms_templates')->get();
            $rules = \Illuminate\Support\Facades\DB::table('sms_automation_rules')->get();
            $campaigns = \Illuminate\Support\Facades\DB::table('sms_campaigns')->orderByDesc('id')->take(10)->get();
            $settings = \Illuminate\Support\Facades\DB::table('eskiz_settings')->first();
            $smsLogs = \App\Models\SmsLog::with('client')->orderByDesc('id')->take(50)->get();
            
            if ($settings && $settings->bearer_token) {
                $res = \Illuminate\Support\Facades\Http::withToken($settings->bearer_token)->get('https://notify.eskiz.uz/api/auth/user');
                if ($res->successful()) {
                    $isConnected = true;
                }
            }
        }

        // 9. Client Payments Tab
        if (in_array($tab, ['client_payments', 'dashboard', 'clients', 'contracts']) || empty($tab)) {
            $transactions = \App\Models\Transaction::with([
                'creator', 
                'approver', 
                'client',
                'contract.client', 
                'contract.unit.floor.building',
                'category', 
                'cashRegister', 
                'counterparty', 
                'invoice',
                'building'
            ])
            ->where('type', 'income')
            ->where(function($q) {
                $q->whereHas('counterparty', function($cp) {
                    $cp->where('type', 'client');
                })
                ->orWhereNotNull('contract_id')
                ->orWhereNotNull('client_id')
                ->orWhere('target_type', 'client')
                ->orWhere('target_type', \App\Models\Client::class);
            })
            ->latest()
            ->limit(2000)
            ->get();

            $counterparties = \App\Models\Counterparty::with([
                'customable.contracts.paymentSchedules',
                'customable.contracts.transactions',
                'transactions'
            ])->where('type', 'client')->get();
        }

        // 10. Client Payment Reports Tab (MIJOZ TO'LOV HISOBOTLARI)
        if ($tab === 'client_payment_reports') {
            $contracts = Contract::with(['client', 'partner.user', 'unit.floor.building', 'paymentSchedules', 'transactions.category', 'refundSchedules'])->latest()->get();
        }

        $conversionLogs = \App\Models\LeadConversionLog::with(['user:id,name', 'client:id,first_name,last_name,phone'])
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('Admin/CRM/Index', [
            'leads' => $leads,
            'clients' => $clients,
            'latestClientId' => $latestClientId,
            'contracts' => $contracts,
            'units' => $units,
            'partners' => $partners,
            'overdueSchedules' => $overdueSchedules,
            'strictCollectionSchedules' => $strictCollectionSchedules,
            'posts' => $posts,
            'crmSettings' => $crmSettings,
            'users' => $users,
            'smsTemplates' => $templates,
            'smsRules' => $rules,
            'smsCampaigns' => $campaigns,
            'smsSettings' => $settings,
            'smsLogs' => $smsLogs,
            'isEskizConnected' => $isConnected,
            'leadSources' => $leadSources,
            'cashRegisters' => $cashRegisters,
            'transactions' => $transactions,
            'counterparties' => $counterparties,
            'categories' => $categories,
            'buildings' => $buildings,
            'contractTemplates' => $contractTemplates,
            'conversionLogs' => $conversionLogs,
        ])->toResponse(request())->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function storeClient(\Illuminate\Http\Request $request)
    {
        $rules = [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'middle_name' => 'nullable|string',
            'passport_series' => 'nullable|string',
            'pinfl' => 'nullable|string',
            'phone' => 'required|string',
            'address' => 'nullable|string',
            'passport_issued_date' => 'nullable|date',
            'passport_issued_by' => 'nullable|string',
            'is_blacklisted' => 'nullable|boolean',
            'created_at' => 'nullable|date',
            'region' => 'nullable|string',
            'district' => 'nullable|string',
            'phone_2' => 'nullable|string',
            'gender' => 'nullable|string',
            'source' => 'nullable|string',
            'contract_language' => 'nullable|string',
            'contract_id' => 'nullable|string',
            'passport_photo' => 'nullable',
            'profile_photo' => 'nullable'
        ];

        if ($request->hasFile('passport_photo')) {
            $rules['passport_photo'] = 'file|mimes:jpeg,png,jpg,pdf|max:20480';
        }
        if ($request->hasFile('profile_photo')) {
            $rules['profile_photo'] = 'file|mimes:jpeg,png,jpg|max:20480';
        }

        $validated = $request->validate($rules);

        if (array_key_exists('passport_series', $validated)) {
            $validated['passport_number'] = $validated['passport_series'];
            unset($validated['passport_series']);
        }

        if ($request->hasFile('passport_photo')) {
            $validated['passport_photo'] = $request->file('passport_photo')->store('passport_photos', 'public');
        } else {
            unset($validated['passport_photo']);
        }



        if ($request->hasFile('profile_photo')) {
            $validated['profile_photo'] = $request->file('profile_photo')->store('profile_photos', 'public');
        } else {
            unset($validated['profile_photo']);
        }

        try {
            $client = new \App\Models\Client($validated);
            $client->is_blacklisted = $request->has('is_blacklisted') ? (bool)$request->is_blacklisted : false;
            if (!empty($validated['created_at'])) {
                $client->created_at = $validated['created_at'];
            }
            $client->save();
            if ($request->wantsJson()) {
                return response()->json(['status' => 'success', 'client' => $client]);
            }
            return redirect()->back()->with('message', 'Client created successfully.');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
            return back()->withErrors(['server_error' => $e->getMessage()]);
        }
    }

    public function updateClient(\Illuminate\Http\Request $request, \App\Models\Client $client)
    {
        $rules = [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'middle_name' => 'nullable|string',
            'passport_series' => 'nullable|string',
            'pinfl' => 'nullable|string',
            'phone' => 'required|string',
            'address' => 'nullable|string',
            'passport_issued_date' => 'nullable|date',
            'passport_issued_by' => 'nullable|string',
            'is_blacklisted' => 'nullable|boolean',
            'created_at' => 'nullable|date',
            'region' => 'nullable|string',
            'district' => 'nullable|string',
            'phone_2' => 'nullable|string',
            'gender' => 'nullable|string',
            'source' => 'nullable|string',
            'contract_language' => 'nullable|string',
            'contract_id' => 'nullable|string',
            'passport_photo' => 'nullable',
            'profile_photo' => 'nullable'
        ];

        if ($request->hasFile('passport_photo')) {
            $rules['passport_photo'] = 'file|mimes:jpeg,png,jpg,pdf|max:20480';
        }
        if ($request->hasFile('profile_photo')) {
            $rules['profile_photo'] = 'file|mimes:jpeg,png,jpg|max:20480';
        }

        $validated = $request->validate($rules);

        if (array_key_exists('passport_series', $validated)) {
            $validated['passport_number'] = $validated['passport_series'];
            unset($validated['passport_series']);
        }

        if ($request->hasFile('passport_photo')) {
            if ($client->passport_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($client->passport_photo)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($client->passport_photo);
            }
            $validated['passport_photo'] = $request->file('passport_photo')->store('passport_photos', 'public');
        } else {
            unset($validated['passport_photo']);
        }



        if ($request->hasFile('profile_photo')) {
            if ($client->profile_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($client->profile_photo)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($client->profile_photo);
            }
            $validated['profile_photo'] = $request->file('profile_photo')->store('profile_photos', 'public');
        } else {
            unset($validated['profile_photo']);
        }

        try {
            $client->fill($validated);
            $client->is_blacklisted = $request->has('is_blacklisted') ? (bool)$request->is_blacklisted : false;
            if (!empty($validated['created_at'])) {
                $client->created_at = $validated['created_at'];
            }
            $client->save();
            return redirect()->back()->with('message', 'Client updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['server_error' => $e->getMessage()]);
        }
    }

    public function deleteClient(\App\Models\Client $client)
    {
        $client->delete();
        return redirect()->back()->with('message', 'Client deleted successfully.');
    }

    public function getClientDetails(\App\Models\Client $client)
    {
        $client->load([
            'comments.user',
            'contracts.unit.floor.building',
            'contracts.paymentSchedules' => function ($query) {
                $query->orderBy('due_date', 'asc');
            },
            'contracts.transactions.creator',
            'contracts.transactions.approver',
            'contracts.transactions.category',
            'contracts.refundSchedules' => function ($query) {
                $query->orderBy('due_date', 'asc');
            },
        ]);
        
        return response()->json([
            'status' => 'success',
            'client' => $client
        ]);
    }

    public function addClientComment(Request $request, \App\Models\Client $client)
    {
        $validated = $request->validate([
            'comment' => 'required|string|min:1'
        ]);

        $comment = $client->comments()->create([
            'user_id' => auth()->id(),
            'comment' => $validated['comment']
        ]);

        $comment->load('user');

        return response()->json([
            'status' => 'success',
            'comment' => $comment
        ]);
    }

    public function getClientComments(\App\Models\Client $client)
    {
        $comments = $client->comments()->with('user')->get();

        return response()->json([
            'status' => 'success',
            'comments' => $comments
        ]);
    }

    public function updateScheduleNotes(Request $request, \App\Models\PaymentSchedule $schedule)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string'
        ]);

        $schedule->update([
            'notes' => $validated['notes']
        ]);

        return response()->json([
            'status' => 'success',
            'schedule' => $schedule
        ]);
    }

    public function logContact(Request $request, Lead $lead)
    {
        // Mock a CallLog entry
        CallLog::create([
            'client_id' => null,
            'worker_id' => auth()->id(),
            'phone_number' => $lead->phone,
            'type' => 'outbound',
            'status' => 'answered',
            'duration_seconds' => rand(30, 120),
        ]);

        $lead->status = 'contacted';
        $lead->save();

        return redirect()->back()->with('message', 'Bog\'lanildi va status yangilandi.');
    }

    public function updateLeadStatus(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,contacted,qualified,lost',
            'reason' => 'nullable|string'
        ]);

        $lead->status = $validated['status'];
        if ($validated['status'] === 'lost' && !empty($validated['reason'])) {
            $lead->reason = $validated['reason'];
        }
        $lead->save();

        if ($validated['status'] === 'qualified') {
            return $this->syncLeadToClient($lead, $request, false);
        }

        if (!empty($lead->passport_series) && !empty($lead->region) && !empty($lead->district) && !empty($lead->address)) {
            return $this->syncLeadToClient($lead, $request, false);
        }

        return redirect()->back()->with('message', 'Status yangilandi.');
    }

    public function quickSms(Request $request, Lead $lead, EskizService $eskiz)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000'
        ]);

        try {
            $eskiz->sendSms($lead->phone, $validated['message'], 'quick_sms');
            $lead->status = 'contacted';
            $lead->save();
            return redirect()->back()->with('message', 'SMS yuborildi va status yangilandi.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => 'SMS yuborishda xatolik: ' . $e->getMessage()]);
        }
    }

    public function convertLeadToClient(Lead $lead, \Illuminate\Http\Request $request = null)
    {
        return $this->syncLeadToClient($lead, $request, true);
    }

    public function syncLeadToClient(Lead $lead, \Illuminate\Http\Request $request = null, bool $deleteLead = false)
    {
        $cleanLeadPhone = preg_replace('/[^0-9]/', '', $lead->phone ?? '');
        $leadFullName = $lead->full_name ?: trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''));
        if (empty($leadFullName)) {
            $leadFullName = 'Lid #' . $lead->id;
        }

        $lastName = $lead->last_name;
        $firstName = $lead->first_name;
        $middleName = $lead->middle_name;

        if (empty($firstName) && empty($lastName)) {
            $names = explode(' ', trim($lead->full_name));
            $lastName = $names[0] ?? '';
            $firstName = $names[1] ?? '';
            $middleName = $names[2] ?? '';

            if (empty($firstName)) {
                $firstName = $lastName ?: ($lead->full_name ?: ('Lid #' . $lead->id));
                $lastName = '';
            }
        }

        // Search for existing client ONLY if phone matches AND names are similar or existing client name is empty
        $existingClient = null;
        if (!empty($cleanLeadPhone) && strlen($cleanLeadPhone) >= 7) {
            $allClients = \App\Models\Client::select('id', 'phone', 'passport_number', 'region', 'district', 'address', 'source', 'pinfl', 'first_name', 'last_name')->get();
            foreach ($allClients as $c) {
                $cleanClientPhone = preg_replace('/[^0-9]/', '', $c->phone ?? '');
                if (!empty($cleanClientPhone) && strlen($cleanClientPhone) >= 7 && $cleanClientPhone === $cleanLeadPhone) {
                    $cFullName = trim(($c->last_name ?? '') . ' ' . ($c->first_name ?? ''));
                    if (empty($cFullName) || 
                        mb_strtolower(trim($c->first_name)) === mb_strtolower(trim($firstName)) ||
                        mb_strtolower(trim($c->last_name)) === mb_strtolower(trim($lastName)) ||
                        str_contains(mb_strtolower($cFullName), mb_strtolower(trim($firstName))) ||
                        str_contains(mb_strtolower($leadFullName), mb_strtolower(trim($c->first_name ?? '___')))) {
                        $existingClient = $c;
                        break;
                    }
                }
            }
        }

        if ($existingClient) {
            // Update existing client with lead data if client fields are empty
            $updateData = [];
            if (empty($existingClient->passport_number) && !empty($lead->passport_series)) $updateData['passport_number'] = $lead->passport_series;
            if (empty($existingClient->region) && !empty($lead->region)) $updateData['region'] = $lead->region;
            if (empty($existingClient->district) && !empty($lead->district)) $updateData['district'] = $lead->district;
            if (empty($existingClient->address) && !empty($lead->address)) $updateData['address'] = $lead->address;
            if (empty($existingClient->source) && !empty($lead->source)) $updateData['source'] = $lead->source;
            if (empty($existingClient->pinfl) && !empty($lead->pinfl)) $updateData['pinfl'] = $lead->pinfl;
            if (empty($existingClient->first_name) && !empty($firstName)) $updateData['first_name'] = $firstName;
            if (empty($existingClient->last_name) && !empty($lastName)) $updateData['last_name'] = $lastName;

            if (!empty($updateData)) {
                $existingClient->update($updateData);
            }

            if (class_exists('\App\Models\LeadConversionLog')) {
                \App\Models\LeadConversionLog::create([
                    'lead_id' => $lead->id,
                    'client_id' => $existingClient->id,
                    'user_id' => auth()->id() ?? 1,
                    'lead_name' => $leadFullName,
                    'lead_phone' => $lead->phone,
                    'action' => 'merged',
                    'description' => "Lid ({$leadFullName}, tel: {$lead->phone}) Saralanganlarga o'tkazildi hamda ma'lumotlari mavjud mijozga (#{$existingClient->id} - {$existingClient->last_name} {$existingClient->first_name}) biriktirildi.",
                    'status' => 'merged',
                ]);
            }

            $client = $existingClient;
        } else {
            $client = \App\Models\Client::create([
                'first_name' => $firstName ?: ($lead->full_name ?: 'Lid #' . $lead->id),
                'last_name' => $lastName ?: '',
                'middle_name' => $middleName ?: '',
                'phone' => $lead->phone ?: '',
                'phone_2' => $lead->phone_2,
                'passport_number' => $lead->passport_series,
                'region' => $lead->region,
                'district' => $lead->district,
                'address' => $lead->address,
                'source' => $lead->source,
                'pinfl' => $lead->pinfl,
                'passport_photo' => $lead->passport_photo,
                'passport_photo_back' => $lead->passport_photo_back,
                'passport_issued_date' => $lead->passport_issued_date,
                'passport_issued_by' => $lead->passport_issued_by,
            ]);

            if (class_exists('\App\Models\LeadConversionLog')) {
                \App\Models\LeadConversionLog::create([
                    'lead_id' => $lead->id,
                    'client_id' => $client->id,
                    'user_id' => auth()->id() ?? 1,
                    'lead_name' => $leadFullName,
                    'lead_phone' => $lead->phone,
                    'action' => 'converted',
                    'description' => "Lid ({$leadFullName}, tel: {$lead->phone}) Saralanganlarga o'tkazildi hamda Mijozlar bazasiga qo'shildi (#{$client->id} - {$client->last_name} {$client->first_name}).",
                    'status' => 'success',
                ]);
            }
        }

        if ($deleteLead) {
            $lead->delete();
        } else {
            $lead->status = 'qualified';
            $lead->save();
        }

        if ($request && $request->wantsJson()) {
            return response()->json([
                'message' => 'Lid Saralanganlarga o\'tkazildi va Mijozlar bazasiga qo\'shildi!',
                'client' => $client,
                'lead' => $lead
            ]);
        }

        return redirect()->back()->with('message', 'Lid Saralanganlarga o\'tkazildi va Mijozlar bazasiga qo\'shildi!');
    }

    public function updateLead(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'full_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'pinfl' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'phone_2' => 'nullable|string|max:20',
            'passport_series' => 'nullable|string|max:255',
            'gender' => 'nullable|string|in:Erkak,Ayol',
            'birth_date' => 'nullable|date',
            'region' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'assigned_employee_id' => 'nullable|exists:users,id',
            'email' => 'nullable|string|email|max:255',
            'social_url' => 'nullable|string|max:1000',
            'source' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
            'passport_photo' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:20480',
            'passport_photo_back' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:20480',
            'passport_issued_date' => 'nullable|date',
            'passport_issued_by' => 'nullable|string|max:500',
        ]);

        $fullName = trim(($validated['last_name'] ?? '') . ' ' . ($validated['first_name'] ?? '') . ' ' . ($validated['middle_name'] ?? ''));
        if (empty($fullName)) {
            $fullName = $validated['full_name'] ?? $lead->full_name;
        }

        $lead->full_name = $fullName;
        $lead->first_name = $validated['first_name'] ?? $lead->first_name;
        $lead->last_name = $validated['last_name'] ?? $lead->last_name;
        $lead->middle_name = $validated['middle_name'] ?? $lead->middle_name;
        $lead->pinfl = $validated['pinfl'] ?? $lead->pinfl;
        $lead->phone = $validated['phone'];
        $lead->phone_2 = $validated['phone_2'] ?? null;
        $lead->passport_series = $validated['passport_series'] ?? null;
        $lead->gender = $validated['gender'] ?? null;
        $lead->birth_date = $validated['birth_date'] ?? null;
        $lead->region = $validated['region'] ?? null;
        $lead->district = $validated['district'] ?? null;
        $lead->address = $validated['address'] ?? null;
        $lead->assigned_employee_id = $validated['assigned_employee_id'] ?? null;
        $lead->passport_issued_date = $validated['passport_issued_date'] ?? null;
        $lead->passport_issued_by = $validated['passport_issued_by'] ?? null;
        if (isset($validated['email'])) $lead->email = $validated['email'];
        if (isset($validated['social_url'])) $lead->social_url = $validated['social_url'];
        if (isset($validated['source'])) $lead->source = $validated['source'];
        if (isset($validated['user_id'])) $lead->user_id = $validated['user_id'];
        
        if ($request->hasFile('passport_photo')) {
            if ($lead->passport_photo && \Storage::disk('public')->exists($lead->passport_photo)) {
                \Storage::disk('public')->delete($lead->passport_photo);
            }
            $lead->passport_photo = $request->file('passport_photo')->store('passport_photos', 'public');
        }

        if ($request->hasFile('passport_photo_back')) {
            if ($lead->passport_photo_back && \Storage::disk('public')->exists($lead->passport_photo_back)) {
                \Storage::disk('public')->delete($lead->passport_photo_back);
            }
            $lead->passport_photo_back = $request->file('passport_photo_back')->store('passport_photos', 'public');
        }

        $lead->save();

        if (!empty($lead->passport_series) && !empty($lead->region) && !empty($lead->district) && !empty($lead->address)) {
            $this->convertLeadToClient($lead);
            return redirect()->back()->with('message', 'Mijoz to\'liq to\'ldirilgani uchun avtomatik Mijozlar bo\'limiga o\'tkazildi.');
        }

        return redirect()->back()->with('message', 'Lid yangilandi.');
    }

    public function destroyLead(Lead $lead)
    {
        $lead->delete();
        return redirect()->back()->with('message', 'Lid muvaffaqiyatli o\'chirildi.');
    }

    public function paySchedule(Request $request, \App\Models\PaymentSchedule $schedule)
    {
        $validated = $request->validate([
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|in:naqd,bank,karta',
            'actual_payment_date' => 'nullable|date'
        ]);

        $contract = $schedule->contract;
        if (!$contract) {
            return redirect()->back()->withErrors(['message' => 'Shartnoma topilmadi.']);
        }

        $newPaymentAmount = $validated['paid_amount'];
        $remainingAmount = $newPaymentAmount;

        if ($remainingAmount <= 0) {
            return redirect()->back()->with('message', 'To\'lov summasi 0 dan katta bo\'lishi kerak.');
        }

        // Get all unpaid schedules for this contract ordered by due_date
        $schedules = \App\Models\PaymentSchedule::where('contract_id', $contract->id)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->get();

        foreach ($schedules as $s) {
            if ($remainingAmount <= 0) break;
            
            $needed = $s->expected_amount - $s->paid_amount;
            if ($needed <= 0) continue;

            if ($remainingAmount >= $needed) {
                $s->paid_amount += $needed;
                $s->status = 'paid';
                $remainingAmount -= $needed;
            } else {
                $s->paid_amount += $remainingAmount;
                $s->status = 'partial';
                $remainingAmount = 0;
            }

            $s->actual_payment_date = $validated['actual_payment_date'] ?? now()->format('Y-m-d');
            if (isset($validated['payment_method'])) {
                $s->payment_method = $validated['payment_method'];
            }
            if ($s->id === $schedule->id) {
                $s->original_payment_amount = $newPaymentAmount;
            }
            
            $s->remaining_debt = max(0, $s->expected_amount - $s->paid_amount);
            
            if ($s->status === 'paid') {
                $s->penalty_for_this_month = 0;
            } else {
                // Re-evaluate penalty if overdue
                if (new \DateTime($s->due_date) < new \DateTime()) {
                    $daysOverdue = floor((time() - strtotime($s->due_date)) / (60 * 60 * 24));
                    if ($daysOverdue > 0) {
                        $s->penalty_for_this_month = $daysOverdue * $s->remaining_debt * ($contract->daily_penalty_rate / 100);
                    }
                } else {
                    $s->penalty_for_this_month = 0;
                }
            }
            
            $s->save();
        }

        // Update total accumulated penalty
        $totalPenalty = $contract->paymentSchedules()->sum('penalty_for_this_month');
        $contract->total_penalty_accumulated = $totalPenalty;
        $contract->save();

        return redirect()->back()->with('message', 'To\'lov muvaffaqiyatli yoyildi va saqlandi.');
    }

    public function recalculatePenalty(Request $request, \App\Models\Contract $contract)
    {
        $validated = $request->validate([
            'daily_penalty_rate' => 'required|numeric|min:0|max:100'
        ]);

        $contract->daily_penalty_rate = $validated['daily_penalty_rate'];
        $contract->save();

        $totalPenalty = 0;
        foreach ($contract->paymentSchedules as $schedule) {
            $unpaidBalance = max(0, $schedule->expected_amount - $schedule->paid_amount);
            $schedule->remaining_debt = $unpaidBalance;

            if ($schedule->status !== 'paid' && new \DateTime($schedule->due_date) < new \DateTime()) {
                $daysOverdue = floor((time() - strtotime($schedule->due_date)) / (60 * 60 * 24));
                if ($daysOverdue > 0) {
                    $penalty = $daysOverdue * $unpaidBalance * ($contract->daily_penalty_rate / 100);
                    $schedule->penalty_for_this_month = $penalty;
                    $schedule->status = 'overdue';
                    $schedule->save();
                    $totalPenalty += $penalty;
                } else {
                    $schedule->penalty_for_this_month = 0;
                    $schedule->save();
                }
            } else {
                $schedule->penalty_for_this_month = 0;
                $schedule->save();
            }
        }

        $contract->total_penalty_accumulated = $totalPenalty;
        $contract->save();

        return redirect()->back()->with('message', 'Penya qayta hisoblandi.');
    }

    public function sendWarningSms(Request $request, \App\Models\PaymentSchedule $schedule, EskizService $eskiz)
    {
        $contract = $schedule->contract;
        if (!$contract || !$contract->client || !$contract->client->phone) {
            return redirect()->back()->withErrors(['message' => 'Mijoz telefon raqami topilmadi.']);
        }

        $amount = number_format($schedule->expected_amount - $schedule->paid_amount, 2, ',', ' ');
        $message = "Hurmatli mijoz, sizning shartnomangiz bo'yicha {$amount} so'm to'lov muddati o'tgan. Iltimos to'lovni amalga oshiring.";

        try {
            $eskiz->sendSms($contract->client->phone, $message, 'warning_sms');
            $schedule->last_sms_sent_at = now();
            $schedule->save();
            return redirect()->back()->with('message', 'Ogohlantiruvchi SMS yuborildi.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => 'SMS yuborishda xatolik: ' . $e->getMessage()]);
        }
    }

    public function storePost(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'status' => 'required|string|in:draft,published',
            'image' => 'nullable|image|max:2048',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('posts', 'public');
        }

        $postData = [
            'title' => $validated['title'],
            'content' => $validated['content'],
            'status' => $validated['status'],
            'published_at' => $validated['status'] === 'published' ? now() : null,
            'user_id' => auth()->id(),
        ];

        if ($imagePath) {
            $postData['image_path'] = $imagePath;
        }

        if (!empty($validated['id'])) {
            $post = Post::findOrFail($validated['id']);
            $post->update($postData);
            $msg = 'Post muvaffaqiyatli tahrirlandi.';
        } else {
            Post::create($postData);
            $msg = 'Post muvaffaqiyatli yaratildi.';
        }

        return redirect()->back()->with('message', $msg);
    }

    public function deletePost(Post $post)
    {
        $post->delete();
        return redirect()->back()->with('message', 'Post o\'chirildi.');
    }

    public function saveSettings(Request $request)
    {
        $settings = $request->all();

        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            CrmSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return redirect()->back()->with('message', 'CRM sozlamalari muvaffaqiyatli saqlandi.');
    }

    public function updateCustomSchedule(Request $request, \App\Models\Contract $contract)
    {
        $validated = $request->validate([
            'schedules' => 'required|array|min:1',
            'schedules.*.due_date' => 'required|date',
            'schedules.*.expected_amount' => 'required|numeric|min:0.01',
            'schedules.*.payment_type' => 'required|string|in:down_payment,installment,full',
        ]);

        \DB::transaction(function () use ($contract, $validated) {
            // Delete existing payment schedules
            $contract->paymentSchedules()->delete();

            $totalSum = 0;
            $downPaymentSum = 0;
            $installmentCount = 0;

            // Create new payment schedules
            foreach ($validated['schedules'] as $index => $row) {
                $amount = (float)$row['expected_amount'];
                $totalSum += $amount;

                if ($row['payment_type'] === 'down_payment') {
                    $downPaymentSum += $amount;
                } else {
                    $installmentCount++;
                }

                \App\Models\PaymentSchedule::create([
                    'contract_id' => $contract->id,
                    'expected_amount' => $amount,
                    'remaining_debt' => $amount,
                    'paid_amount' => 0,
                    'due_date' => $row['due_date'],
                    'status' => 'pending',
                    'original_payment_amount' => $amount,
                    'payment_type' => $row['payment_type'],
                ]);
            }

            if ($installmentCount === 0) {
                $installmentCount = count($validated['schedules']);
            }

            // Sync contract totals & installment term count so 2-ilova and contract header match custom schedule
            $contract->update([
                'total_amount' => $totalSum > 0 ? $totalSum : $contract->total_amount,
                'down_payment' => $downPaymentSum,
                'term_months' => $installmentCount,
            ]);

            // Reconcile payments
            \App\Http\Controllers\Admin\FinanceController::reconcileContractPayments($contract->id);
        });

        return redirect()->back()->with('message', 'To\'lov grafigi va 2-Ilova ma\'lumotlari muvaffaqiyatli yangilandi.');
    }
}
