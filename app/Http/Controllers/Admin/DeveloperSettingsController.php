<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use App\Models\HardwareApiKey;

class DeveloperSettingsController extends Controller
{
    public function index()
    {
        $isUnlocked = Session::get('developer_unlocked', false);
        $keys = [];
        
        if ($isUnlocked) {
            $keys = HardwareApiKey::latest()->get();
        }

        return Inertia::render('Admin/Developer/Settings', [
            'isUnlocked' => $isUnlocked,
            'apiKeys' => $keys,
        ]);
    }

    public function requestOtp()
    {
        $token = env('TELEGRAM_BOT_TOKEN');
        $chatId = env('TELEGRAM_CHAT_ID');

        if (!$token || !$chatId) {
            return back()->withErrors(['otp' => 'Telegram Bot sozlamalari (.env) kiritilmagan!']);
        }

        // Generate 6-digit OTP
        $otp = rand(100000, 999999);
        Session::put('developer_otp', $otp);
        Session::put('developer_otp_expires_at', now()->addMinutes(5));

        $message = "🔐 *SHER-DOR 2.0 - XAVFSIZLIK TIZIMI*\n\n";
        $message .= "🏢 *Loyiha:* Sher-Dor ERP (Qurilish Nazorati)\n";
        $message .= "⚙️ *Qism:* Dasturchi bloki (API Kalitlar)\n\n";
        $message .= "Tizimga kirishga urinish aniqlandi. Agar bu siz bo'lsangiz, quyidagi tasdiqlash kodidan foydalaning:\n\n";
        $message .= "🔑 *KOD:* `{$otp}`\n\n";
        $message .= "⚠️ _Kod 5 daqiqa davomida amal qiladi. Begonalarga bermang!_";

        Http::withoutVerifying()->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ]);

        return back()->with('success', 'OTP kod Telegramga yuborildi!');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['otp' => 'required|numeric']);

        $storedOtp = Session::get('developer_otp');
        $expiresAt = Session::get('developer_otp_expires_at');

        if (!$storedOtp || now()->greaterThan($expiresAt)) {
            return back()->withErrors(['otp' => 'OTP kod eskirgan yoki noto\'g\'ri! Qaytadan so\'rang.']);
        }

        if ((int)$request->otp === (int)$storedOtp) {
            // Unlock for 30 minutes
            Session::put('developer_unlocked', true);
            Session::forget('developer_otp');
            return back()->with('success', 'Dasturchi bloki muvaffaqiyatli ochildi!');
        }

        return back()->withErrors(['otp' => 'Noto\'g\'ri kod kiritildi!']);
    }

    public function generateKey(Request $request)
    {
        if (!Session::get('developer_unlocked', false)) {
            abort(403);
        }

        $request->validate(['name' => 'required|string|max:255']);

        $key = 'sk_' . Str::random(32);

        HardwareApiKey::create([
            'name' => $request->name,
            'api_key' => $key,
            'is_active' => true
        ]);

        return back()->with('success', 'Yangi API Kalit yaratildi!');
    }

    public function revokeKey($id)
    {
        if (!Session::get('developer_unlocked', false)) {
            abort(403);
        }

        $key = HardwareApiKey::findOrFail($id);
        $key->delete(); // Or just $key->update(['is_active' => false]); but user said 'o'chirib olishi'

        return back()->with('success', 'API Kalit o\'chirildi!');
    }
}
