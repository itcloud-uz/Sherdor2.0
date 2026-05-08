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
}
