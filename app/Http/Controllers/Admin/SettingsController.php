<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\LeadSource;
use App\Models\Warehouse;
use App\Models\ScheduledSms;
use App\Models\User;
use App\Models\Building;
use App\Models\Client;
use App\Models\SmsTemplate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Services\EskizService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Settings/Index', [
            'settings' => SystemSetting::first(),
            'leadSources' => LeadSource::orderBy('name', 'asc')->get(),
            'roles' => Role::with('permissions')->get(),
            'permissions' => Permission::where('guard_name', 'web')->get(),
            'users' => User::with(['roles', 'building', 'warehouse'])->orderBy('name', 'asc')->get(),
            'buildings' => Building::select('id', 'name')->orderBy('name', 'asc')->get(),
            'warehouses' => Warehouse::select('id', 'name')->orderBy('name', 'asc')->get(),
            'clients' => Client::select('id', 'first_name', 'last_name', 'phone')->orderBy('first_name', 'asc')->get(),
            'smsTemplates' => SmsTemplate::where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'company_name' => 'nullable|string|max:255',
            'company_address' => 'nullable|string|max:255',
            'company_bank_account' => 'nullable|string|max:255',
            'company_bank_name' => 'nullable|string|max:255',
            'company_mfo' => 'nullable|string|max:255',
            'company_inn' => 'nullable|string|max:255',
            'company_director' => 'nullable|string|max:255',
            'company_phone' => 'nullable|string|max:255',
            'primary_theme_color' => 'nullable|string|max:20',
            'neon_glow_intensity' => 'nullable|string|max:50',
            'default_currency' => 'nullable|string|max:10',
            'tax_rate' => 'nullable|numeric',
            'default_penalty_rate' => 'nullable|numeric',
            'logo' => 'nullable|image|max:2048', // Max 2MB image
            'background_image' => 'nullable|image|max:10240', // Max 10MB image
        ]);

        $settings = SystemSetting::first() ?? new SystemSetting();

        $fields = [
            'company_name', 'company_address', 'company_bank_account', 'company_bank_name',
            'company_mfo', 'company_inn', 'company_director', 'company_phone',
            'primary_theme_color', 'neon_glow_intensity', 'default_currency', 'tax_rate', 'default_penalty_rate'
        ];
        foreach ($fields as $field) {
            if ($request->has($field)) {
                $settings->{$field} = $request->{$field};
            }
        }

        if ($request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $path = $request->file('logo')->store('branding', 'public');
            $settings->logo_path = $path;
        }
        if ($request->hasFile('background_image')) {
            if ($settings->background_image_path) {
                Storage::disk('public')->delete($settings->background_image_path);
            }
            $bgPath = $request->file('background_image')->store('branding', 'public');
            $settings->background_image_path = $bgPath;
        }

        $settings->save();

        \Illuminate\Support\Facades\Cache::forget('system_settings_v2');

        return back()->with('success', 'System settings updated successfully.');
    }

    // Lead Sources CRUD
    public function storeLeadSource(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:lead_sources,name|max:255',
        ]);
        LeadSource::create($validated);
        return back()->with('success', 'Lead source created successfully.');
    }

    public function updateLeadSource(Request $request, LeadSource $leadSource)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:lead_sources,name,' . $leadSource->id,
        ]);
        $leadSource->update($validated);
        return back()->with('success', 'Lead source updated successfully.');
    }

    public function destroyLeadSource(LeadSource $leadSource)
    {
        $leadSource->delete();
        return back()->with('success', 'Lead source deleted successfully.');
    }

    // User Management CRUD
    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'password' => 'required|string|min:6',
            'role' => 'required|string|exists:roles,name',
            'building_id' => 'nullable|exists:buildings,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        // Mock email to fulfill DB constraints
        $email = $validated['username'] . '@sherdor.uz';

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            return back()->withErrors(['username' => 'This login already exists in the system.']);
        }

        $user = User::create([
            'name' => $validated['name'],
            'last_name' => $validated['last_name'] ?? null,
            'middle_name' => $validated['middle_name'] ?? null,
            'username' => $validated['username'],
            'phone' => $validated['phone'] ?? null,
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'building_id' => $validated['building_id'] ?? null,
            'warehouse_id' => $validated['warehouse_id'] ?? null,
        ]);

        $user->assignRole($validated['role']);

        return back()->with('success', 'User created successfully.');
    }

    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'phone' => 'nullable|string|max:20|unique:users,phone,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role' => 'required|string|exists:roles,name',
            'building_id' => 'nullable|exists:buildings,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $user->name = $validated['name'];
        $user->last_name = $validated['last_name'] ?? null;
        $user->middle_name = $validated['middle_name'] ?? null;
        $user->username = $validated['username'];
        $user->phone = $validated['phone'] ?? null;
        $user->building_id = $validated['building_id'] ?? null;
        $user->warehouse_id = $validated['warehouse_id'] ?? null;

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Sync role
        $user->syncRoles([$validated['role']]);

        return back()->with('success', 'User updated successfully.');
    }

    public function destroyUser(User $user)
    {
        $user->delete();
        return back()->with('success', 'User deleted successfully.');
    }

    // Spatie Roles & Permissions
    public function updateRolesPermissions(Request $request)
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::findById($validated['role_id']);
        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', 'Permissions updated successfully.');
    }

    // SMS sending logic (individual/batch, immediate/scheduled)
    public function sendSms(Request $request, EskizService $eskiz)
    {
        $validated = $request->validate([
            'client_ids' => 'required|array',
            'message' => 'required|string|max:1000',
            'send_type' => 'required|string|in:immediate,scheduled',
            'scheduled_at' => 'nullable|date',
        ]);

        $message = $validated['message'];
        $sendType = $validated['send_type'];
        $scheduledAt = $validated['scheduled_at'];

        // Get recipients
        if (in_array('all', $validated['client_ids'])) {
            $recipients = Client::all();
        } else {
            $recipients = Client::whereIn('id', $validated['client_ids'])->get();
        }

        if ($recipients->isEmpty()) {
            return back()->withErrors(['client_ids' => 'No recipients found.']);
        }

        if ($sendType === 'scheduled') {
            if (empty($scheduledAt)) {
                return back()->withErrors(['scheduled_at' => 'Scheduled time is required.']);
            }

            foreach ($recipients as $client) {
                if (!$client->phone) continue;
                ScheduledSms::create([
                    'phone_number' => $client->phone,
                    'message' => $message,
                    'scheduled_at' => $scheduledAt,
                    'client_id' => $client->id,
                    'status' => 'pending',
                ]);
            }

            return back()->with('success', count($recipients) . ' scheduled SMS created successfully.');
        } else {
            // Immediate
            $successCount = 0;
            $failCount = 0;

            foreach ($recipients as $client) {
                if (!$client->phone) continue;
                try {
                    $eskiz->sendSms($client->phone, $message, 'general_broadcast', $client->id);
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                }
            }

            return back()->with('success', "SMS dispatch completed. Sent: {$successCount}, Failed: {$failCount}.");
        }
    }
}
