<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\SmsLog;
use App\Services\EskizService;

class AiAgentController extends Controller
{
    public function index()
    {
        $templates = DB::table('sms_templates')->get();
        $rules = DB::table('sms_automation_rules')->get();
        $campaigns = DB::table('sms_campaigns')->orderByDesc('id')->take(10)->get();
        $settings = DB::table('eskiz_settings')->first();
        $logs = SmsLog::with('client')->orderByDesc('id')->take(50)->get();

        return Inertia::render('Admin/AiAgent/Index', [
            'templates' => $templates,
            'rules' => $rules,
            'campaigns' => $campaigns,
            'settings' => $settings,
            'logs' => $logs
        ]);
    }

    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        DB::table('eskiz_settings')->updateOrInsert(
            ['id' => 1],
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'bearer_token' => null, // Reset token to force new login
                'token_expires_at' => null,
            ]
        );

        return redirect()->back()->with('message', 'Eskiz API sozlamalari saqlandi.');
    }

    public function testSms(Request $request, EskizService $eskiz)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string|max:160'
        ]);

        try {
            $response = $eskiz->sendSms($validated['phone'], $validated['message'], 'test');
            return redirect()->back()->with('message', 'Sinov SMS yuborildi!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function saveTemplate(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:sms_templates,id',
            'name' => 'required|string|max:255',
            'content' => 'required|string|max:160'
        ]);

        if (isset($validated['id'])) {
            DB::table('sms_templates')->where('id', $validated['id'])->update([
                'name' => $validated['name'],
                'content' => $validated['content'],
                'updated_at' => now(),
            ]);
        } else {
            DB::table('sms_templates')->insert([
                'name' => $validated['name'],
                'content' => $validated['content'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->back()->with('message', 'Shablon saqlandi.');
    }

    public function deleteTemplate($id)
    {
        DB::table('sms_templates')->where('id', $id)->delete();
        return redirect()->back()->with('message', 'Shablon o\'chirildi.');
    }

    public function saveRule(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:sms_automation_rules,id',
            'name' => 'required|string',
            'event_trigger' => 'required|string', // auto, debtor, promo
            'template_id' => 'required|integer|exists:sms_templates,id',
            'schedule_time' => 'nullable|string', // e.g. 10:00
            'schedule_days' => 'nullable|string', // e.g. 1,3,5
            'audience_type' => 'nullable|string', // system_clients, upload_file, manual_number
            'manual_phone' => 'nullable|string',
            'file' => 'nullable|file|mimes:csv,txt'
        ]);

        $filePath = null;
        if ($request->hasFile('file') && $validated['audience_type'] === 'upload_file') {
            $file = $request->file('file');
            $filePath = $file->store('audiences', 'public');
            
            // Read and store in audience_lists for future reference
            $handle = fopen($file->getRealPath(), 'r');
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (isset($data[0])) {
                    $phone = preg_replace('/[^0-9]/', '', $data[0]);
                    if (strlen($phone) >= 9) {
                        DB::table('audience_lists')->insert([
                            'phone_number' => $phone,
                            'first_name' => null,
                            'last_name' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
            fclose($handle);
        }

        $data = [
            'name' => $validated['name'],
            'event_trigger' => $validated['event_trigger'],
            'template_id' => $validated['template_id'],
            'schedule_time' => $validated['schedule_time'] ?? 'immediate',
            'schedule_days' => $validated['schedule_days'],
            'audience_type' => $validated['audience_type'] ?? 'all',
            'manual_phone' => $validated['manual_phone'] ?? null,
            'updated_at' => now(),
        ];

        if ($filePath) {
            $data['uploaded_file_path'] = $filePath;
        }

        if (isset($validated['id'])) {
            DB::table('sms_automation_rules')->where('id', $validated['id'])->update($data);
        } else {
            $data['created_at'] = now();
            DB::table('sms_automation_rules')->insert($data);
        }

        return redirect()->back()->with('message', 'Avtomatlashtirish qoidasi saqlandi.');
    }

    public function deleteRule($id)
    {
        DB::table('sms_automation_rules')->where('id', $id)->delete();
        return redirect()->back()->with('message', 'Qoida o\'chirildi.');
    }

    // Keep chat for legacy or drop it. We'll drop it since it's fully SMS now.
}
