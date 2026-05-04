<?php

namespace App\Services;

use App\Models\EskizSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EskizSmsService
{
    protected $baseUrl = 'https://notify.eskiz.uz/api';

    /**
     * Authenticate with Eskiz API and get Bearer Token.
     */
    public function login()
    {
        $setting = EskizSetting::first();
        if (!$setting || !$setting->email || !$setting->password) {
            Log::error('EskizSmsService: Missing credentials.');
            return false;
        }

        try {
            $response = Http::post("{$this->baseUrl}/auth/login", [
                'email' => $setting->email,
                'password' => $setting->password,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $setting->bearer_token = $data['data']['token'];
                // Tokens usually valid for 30 days, we set a safe limit (e.g. 29 days)
                $setting->token_expires_at = now()->addDays(29);
                $setting->save();
                return true;
            }
        } catch (\Exception $e) {
            Log::error('EskizSmsService login failed: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Ensure we have a valid token. If expired, re-login.
     */
    protected function ensureToken()
    {
        $setting = EskizSetting::first();
        if (!$setting || !$setting->bearer_token || $setting->token_expires_at < now()) {
            return $this->login() ? EskizSetting::first()->bearer_token : null;
        }
        return $setting->bearer_token;
    }

    /**
     * Get current SMS balance from Eskiz.
     */
    public function getBalance()
    {
        $token = $this->ensureToken();
        if (!$token) return 0;

        try {
            $response = Http::withToken($token)->get("{$this->baseUrl}/user/get-limit");
            
            if ($response->successful()) {
                $data = $response->json();
                $setting = EskizSetting::first();
                $setting->current_balance = $data['data']['balance'] ?? 0;
                $setting->save();
                return $setting->current_balance;
            }
        } catch (\Exception $e) {
            Log::error('EskizSmsService getBalance failed: ' . $e->getMessage());
        }

        return 0;
    }

    /**
     * Send SMS to a specific phone number.
     */
    public function sendSms($phone, $message)
    {
        $token = $this->ensureToken();
        if (!$token) return false;

        // Clean phone number (remove +, spaces, etc. usually Eskiz wants 998901234567 format)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        // Ensure it starts with 998 if it's an Uzbekistan number
        if (strlen($cleanPhone) == 9) {
            $cleanPhone = '998' . $cleanPhone;
        }

        try {
            $response = Http::withToken($token)->post("{$this->baseUrl}/message/sms/send", [
                'mobile_phone' => $cleanPhone,
                'message' => $message,
                'from' => '4546', // Default alphanumeric sender for Eskiz (or configured name)
            ]);

            if ($response->successful()) {
                return true;
            } else {
                Log::error('EskizSmsService send failed: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('EskizSmsService send Exception: ' . $e->getMessage());
        }

        return false;
    }
}
