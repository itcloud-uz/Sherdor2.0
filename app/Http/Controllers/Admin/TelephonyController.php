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
        // Mocking logs since telephony logic is WIP
        $logs = [
            // Sample mock data for Pragmata design
        ];

        return response()->json($logs);
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
