<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SmsLog; // Reusing SmsLog or TelephonyLog if we have it

class TelephonyController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Telephony/Index');
    }

    public function getLogs()
    {
        $logs = \App\Models\CallLog::orderByDesc('created_at')->take(50)->get();
        return response()->json($logs);
    }

    public function getAiConfig()
    {
        $settings = \App\Models\SystemSetting::first();
        return response()->json([
            'is_active' => (bool)($settings->ai_is_active ?? false),
            'prompt' => $settings->ai_prompt ?? '',
            'audio_path' => $settings->ai_audio_path ?? '',
            'min_score' => (int)($settings->ai_min_score ?? 50),
            'max_duration' => (int)($settings->ai_max_duration ?? 60),
            'openai_api_key' => $settings->openai_api_key ?? '',
            'vapi_api_key' => $settings->vapi_api_key ?? '',
            'vapi_assistant_id' => $settings->vapi_assistant_id ?? '',
            'auto_call' => (bool)($settings->ai_auto_call ?? false),
        ]);
    }

    public function saveAiConfig(Request $request)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
            'prompt' => 'nullable|string',
            'audio_path' => 'nullable|string',
            'min_score' => 'required|integer|min:0|max:100',
            'max_duration' => 'required|integer|min:0',
            'openai_api_key' => 'nullable|string',
            'vapi_api_key' => 'nullable|string',
            'vapi_assistant_id' => 'nullable|string',
            'auto_call' => 'required|boolean',
        ]);

        $settings = \App\Models\SystemSetting::first() ?? new \App\Models\SystemSetting();
        $settings->fill([
            'ai_is_active' => $validated['is_active'],
            'ai_prompt' => $validated['prompt'] ?? '',
            'ai_audio_path' => $validated['audio_path'] ?? '',
            'ai_min_score' => $validated['min_score'],
            'ai_max_duration' => $validated['max_duration'],
            'openai_api_key' => $validated['openai_api_key'] ?? '',
            'vapi_api_key' => $validated['vapi_api_key'] ?? '',
            'vapi_assistant_id' => $validated['vapi_assistant_id'] ?? '',
            'ai_auto_call' => $validated['auto_call'],
        ])->save();

        return response()->json(['message' => 'AI Configuration saved successfully.', 'status' => 'success']);
    }

    public function getAudios()
    {
        $audios = \App\Models\TelephonyAudio::latest()->get();
        return response()->json($audios);
    }

    public function uploadAudio(Request $request)
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav|max:10240', // 10MB max
            'name' => 'required|string|max:255',
        ]);

        if ($request->hasFile('audio')) {
            $file = $request->file('audio');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('telephony_audios', $fileName, 'public');

            \App\Models\TelephonyAudio::create([
                'name' => $request->name,
                'file_path' => $path,
            ]);

            return redirect()->back()->with('success', 'Audio yuklandi');
        }

        return redirect()->back()->with('error', 'Fayl yuklashda xatolik');
    }
    public function storeCampaign(Request $request)
    {
        $request->validate([
            'target_group' => 'required|string',
            'audio_id' => 'required|exists:telephony_audios,id',
            'schedule_time' => 'required|date_format:H:i',
            'schedule_days' => 'required|array',
            'excel_file' => 'required_if:target_group,new_leads|file|mimes:xlsx,xls,csv'
        ]);

        $campaign = \App\Models\TelephonyCampaign::create([
            'target_group' => $request->target_group,
            'audio_id' => $request->audio_id,
            'schedule_time' => $request->schedule_time,
            'schedule_days' => implode(',', $request->schedule_days),
            'is_active' => true,
        ]);

        if ($request->target_group === 'new_leads' && $request->hasFile('excel_file')) {
            \Maatwebsite\Excel\Facades\Excel::import(
                new \App\Imports\TelephonyLeadsImport($campaign->id), 
                $request->file('excel_file')
            );
        }

        return redirect()->back()->with('success', 'Avto-qo\'ng\'iroq kampaniyasi muvaffaqiyatli saqlandi!');
    }
}
