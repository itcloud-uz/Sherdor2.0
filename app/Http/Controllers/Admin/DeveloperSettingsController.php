<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use App\Models\HardwareApiKey;
use App\Models\SystemSetting;

class DeveloperSettingsController extends Controller
{
    public function lock()
    {
        Session::forget('developer_unlocked');
        return redirect()->route('admin.dashboard')->with('success', 'Dasturchi bloki quluflab qo\'yildi.');
    }

    public function index()
    {
        $isUnlocked = Session::get('developer_unlocked', false);
        $keys = [];
        $users = [];
        
        if ($isUnlocked) {
            $keys = HardwareApiKey::latest()->get();
            $users = \App\Models\User::with('roles')->get()->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames(),
                    'telegram_chat_id' => $user->telegram_chat_id,
                ];
            });
        }

        $systemSettings = SystemSetting::first();

        return Inertia::render('Admin/Developer/Settings', [
            'isUnlocked' => $isUnlocked,
            'apiKeys' => $keys,
            'users' => $users,
            'sipSettings' => $systemSettings ? [
                'asterisk_sip_host' => $systemSettings->asterisk_sip_host,
                'asterisk_sip_username' => $systemSettings->asterisk_sip_username,
                'asterisk_port' => $systemSettings->asterisk_port,
                'has_password' => !empty($systemSettings->asterisk_sip_password),
            ] : null,
            'promoSettings' => $systemSettings ? [
                'promo_title' => $systemSettings->promo_title,
                'promo_subtitle' => $systemSettings->promo_subtitle,
                'promo_description' => $systemSettings->promo_description,
                'promo_image' => $systemSettings->promo_image,
                'telegram_lead_bot_token' => $systemSettings->telegram_lead_bot_token,
                'telegram_lead_chat_id' => $systemSettings->telegram_lead_chat_id,
                'fb_pixel_id' => $systemSettings->fb_pixel_id,
            ] : null,
        ]);
    }

    public function updateUserChatId(Request $request, $id)
    {
        if (!Session::get('developer_unlocked', false)) {
            abort(403);
        }

        $request->validate([
            'telegram_chat_id' => 'nullable|string|max:255'
        ]);

        $user = \App\Models\User::findOrFail($id);
        $user->telegram_chat_id = $request->telegram_chat_id;
        $user->save();

        return back()->with('success', 'Telegram Chat ID muvaffaqiyatli yangilandi!');
    }

    public function requestOtp()
    {
        $rawToken = config('services.telegram.pin_bot_token') ?: env('PIN_BOT_TOKEN');
        $chatId = config('services.telegram.pin_chat_id') ?: env('PIN_CHAT_ID');

        // Sanitize token (trim quotes and whitespace)
        $token = trim($rawToken ?? '', " \t\n\r\0\x0B\"'");
        $chatId = trim($chatId ?? '', " \t\n\r\0\x0B\"'");

        // Handle accidental duplicate prepended token
        if (preg_match('/^(\d+):(\d+:[A-Za-z0-9_-]+)$/', $token, $matches)) {
            $token = $matches[2];
        }

        if (!$token || !$chatId) {
            return back()->withErrors(['otp' => 'Telegram Bot sozlamalari (.env da PIN_BOT_TOKEN va PIN_CHAT_ID) kiritilmagan!']);
        }

        // Generate 6-digit OTP
        $otp = rand(100000, 999999);
        Session::put('developer_otp', $otp);
        Session::put('developer_otp_expires_at', now()->addMinutes(5));

        $appName = config('app.name', 'IQS ERP');
        $message = "🔐 *{$appName} - XAVFSIZLIK TIZIMI*\n\n";
        $message .= "🏢 *Loyiha:* {$appName} (Boshqaruv Tizimi)\n";
        $message .= "⚙️ *Qism:* Dasturchi bloki (Maxsus Ruxsat)\n\n";
        $message .= "Tizimga kirishga urinish aniqlandi. Agar bu siz bo'lsangiz, quyidagi tasdiqlash kodidan foydalaning:\n\n";
        $message .= "🔑 *KOD:* `{$otp}`\n\n";
        $message .= "⚠️ _Kod 5 daqiqa davomida amal qiladi. Begonalarga bermang!_";

        try {
            $response = Http::withoutVerifying()->timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ]);

            if (!$response->successful()) {
                $err = $response->json('description') ?: ('HTTP ' . $response->status());
                \Illuminate\Support\Facades\Log::error("Telegram PIN send failed: " . $err);
                return back()->withErrors(['otp' => 'Telegram xatoligi: ' . $err]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Telegram connection error: " . $e->getMessage());
            return back()->withErrors(['otp' => 'Telegram serveriga ulanishda xatolik: ' . $e->getMessage()]);
        }

        return back()->with('success', 'OTP kod Telegramga muvaffaqiyatli yuborildi!');
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

    public function updateSipSettings(Request $request)
    {
        if (!Session::get('developer_unlocked', false)) {
            abort(403);
        }

        $validated = $request->validate([
            'asterisk_sip_host' => 'nullable|string|max:255',
            'asterisk_sip_username' => 'nullable|string|max:255',
            'asterisk_sip_password' => 'nullable|string|max:255',
            'asterisk_port' => 'nullable|integer',
        ]);

        $settings = SystemSetting::first() ?? new SystemSetting();
        $settings->asterisk_sip_host = $validated['asterisk_sip_host'];
        $settings->asterisk_sip_username = $validated['asterisk_sip_username'];
        $settings->asterisk_port = $validated['asterisk_port'] ?? 5060;
        
        if (!empty($validated['asterisk_sip_password'])) {
            $settings->asterisk_sip_password = encrypt($validated['asterisk_sip_password']);
        }
        
        $settings->save();

        return back()->with('success', 'IP Telefoniya sozlamalari saqlandi va Asterisk uchun yangilandi!');
    }

    public function updatePromoSettings(Request $request)
    {
        if (!Session::get('developer_unlocked', false)) {
            abort(403);
        }

        $validated = $request->validate([
            'promo_title' => 'nullable|string|max:255',
            'promo_subtitle' => 'nullable|string|max:255',
            'promo_description' => 'nullable|string',
            'promo_image' => 'nullable|image|max:5120', // Max 5MB
            
            'promo_form_title' => 'nullable|string|max:255',
            'promo_form_subtitle' => 'nullable|string|max:255',
            'promo_button_text' => 'nullable|string|max:255',
            'promo_success_message' => 'nullable|string',
        ]);

        $settings = SystemSetting::first() ?? new SystemSetting();
        $settings->promo_title = $validated['promo_title'];
        $settings->promo_subtitle = $validated['promo_subtitle'];
        $settings->promo_description = $validated['promo_description'];
        
        $settings->promo_form_title = $validated['promo_form_title'];
        $settings->promo_form_subtitle = $validated['promo_form_subtitle'];
        $settings->promo_button_text = $validated['promo_button_text'];
        $settings->promo_success_message = $validated['promo_success_message'];
        
        if ($request->hasFile('promo_image')) {
            $path = $request->file('promo_image')->store('promo', 'public');
            $settings->promo_image = $path;
        }
        
        $settings->save();

        return back()->with('success', 'Promo Sayt va Forma sozlamalari muvaffaqiyatli saqlandi!');
    }
    
    public function updateIntegrationSettings(Request $request)
    {
        if (!Session::get('developer_unlocked', false)) {
            abort(403);
        }

        $validated = $request->validate([
            'telegram_lead_bot_token' => 'nullable|string|max:255',
            'telegram_lead_chat_id' => 'nullable|string|max:255',
            'fb_pixel_id' => 'nullable|string|max:255',
        ]);

        $settings = SystemSetting::first() ?? new SystemSetting();
        $settings->telegram_lead_bot_token = $validated['telegram_lead_bot_token'];
        $settings->telegram_lead_chat_id = $validated['telegram_lead_chat_id'];
        $settings->fb_pixel_id = $validated['fb_pixel_id'];
        $settings->save();

        return back()->with('success', 'Integratsiyalar muvaffaqiyatli saqlandi!');
    }
}
