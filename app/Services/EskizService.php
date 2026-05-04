<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\SmsLog;

class EskizService
{
    protected $baseUrl = 'https://notify.eskiz.uz/api';

    /**
     * Get or refresh the bearer token.
     */
    public function getToken()
    {
        $setting = DB::table('eskiz_settings')->first();
        if (!$setting || !$setting->email || !$setting->password) {
            throw new \Exception('Eskiz.uz API login/parol sozlanmagan.');
        }

        if ($setting->bearer_token && $setting->token_expires_at && now()->lessThan($setting->token_expires_at)) {
            return $setting->bearer_token;
        }

        // Login to get new token
        $response = Http::post("{$this->baseUrl}/auth/login", [
            'email' => $setting->email,
            'password' => $setting->password,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $token = $data['data']['token'];
            
            DB::table('eskiz_settings')->updateOrInsert(
                ['id' => $setting->id ?? 1],
                [
                    'bearer_token' => $token,
                    'token_expires_at' => now()->addDays(29), // Valid for 30 days usually
                ]
            );

            return $token;
        }

        throw new \Exception('Eskiz.uz tizimiga ulanib bo\'lmadi: ' . $response->body());
    }

    /**
     * Send a single SMS message.
     */
    public function sendSms($phone, $message, $campaignType = null, $clientId = null)
    {
        $token = $this->getToken();

        // Sanitize phone (e.g. format 998901234567 without +)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleanPhone) == 9) {
            $cleanPhone = '998' . $cleanPhone;
        }

        $response = Http::withToken($token)->post("{$this->baseUrl}/message/sms/send", [
            'mobile_phone' => $cleanPhone,
            'message' => $message,
            'from' => 'SHER-DOR', // Default sender, can be made dynamic later
        ]);

        $status = 'failed';
        $uuid = null;

        if ($response->successful()) {
            $status = 'pending'; // or 'sent'
            $uuid = $response->json('id');
        }

        SmsLog::create([
            'phone_number' => $cleanPhone,
            'message' => $message,
            'eskiz_uuid' => $uuid,
            'status' => $status,
            'campaign_type' => $campaignType,
            'client_id' => $clientId,
        ]);

        return $response->json();
    }

    /**
     * Check SMS status by UUID.
     */
    public function checkStatus($uuid)
    {
        $token = $this->getToken();
        $response = Http::withToken($token)->get("{$this->baseUrl}/message/sms/status_by_id/{$uuid}");
        return $response->json();
    }

    /**
     * Send batch SMS.
     */
    public function sendBatch(array $messages, $campaignType = null)
    {
        $token = $this->getToken();
        // $messages should be an array of ['to' => '99890...', 'text' => '...']
        $formattedMessages = [];
        foreach ($messages as $msg) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $msg['to']);
            if (strlen($cleanPhone) == 9) {
                $cleanPhone = '998' . $cleanPhone;
            }
            $formattedMessages[] = [
                'user_sms_id' => uniqid(),
                'to' => $cleanPhone,
                'text' => $msg['text'],
            ];
        }

        $response = Http::withToken($token)->post("{$this->baseUrl}/message/sms/send-batch", [
            'messages' => $formattedMessages,
            'from' => 'SHER-DOR',
            'dispatch_id' => 123 // Optional dispatch logic
        ]);

        if ($response->successful()) {
            // Can be extended to log each individually
            foreach ($formattedMessages as $fmsg) {
                SmsLog::create([
                    'phone_number' => $fmsg['to'],
                    'message' => $fmsg['text'],
                    'eskiz_uuid' => null, // Batch might return a dispatch id instead
                    'status' => 'pending_batch',
                    'campaign_type' => $campaignType,
                ]);
            }
        }

        return $response->json();
    }
}
