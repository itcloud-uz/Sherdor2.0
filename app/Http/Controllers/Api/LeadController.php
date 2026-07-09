<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadController extends Controller
{
    /**
     * Store a newly created lead in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'phone_2' => 'nullable|string|max:20',
            'passport_series' => 'nullable|string|max:255',
            'gender' => 'nullable|string|in:Erkak,Ayol',
            'birth_date' => 'nullable|date',
            'region' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:2000',
            'assigned_employee_id' => 'nullable|exists:users,id',
            'email' => 'nullable|string|email|max:255',
            'social_url' => 'nullable|string|max:1000',
            'source' => 'nullable|string|max:255',
            'user_id' => 'nullable|exists:users,id',
        ]);

        try {
            $source = $validated['source'] ?? 'landing_page';
            
            // Override source if authenticated user is submitting it manually
            if ((auth()->check() && $request->is('admin/*') || $request->has('is_manual')) && empty($validated['source'])) {
                $source = 'manual';
            }

            $lead = Lead::create([
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'],
                'phone_2' => $validated['phone_2'] ?? null,
                'passport_series' => $validated['passport_series'] ?? null,
                'gender' => $validated['gender'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'region' => $validated['region'] ?? null,
                'district' => $validated['district'] ?? null,
                'address' => $validated['address'] ?? null,
                'comment' => $validated['comment'] ?? null,
                'assigned_employee_id' => $validated['assigned_employee_id'] ?? null,
                'email' => $validated['email'] ?? null,
                'social_url' => $validated['social_url'] ?? null,
                'source' => $source,
                'status' => 'new',
                'user_id' => $validated['user_id'] ?? null,
            ]);

            // Auto-convert to Client if full info is provided
            if (!empty($lead->passport_series) && !empty($lead->region) && !empty($lead->district) && !empty($lead->address)) {
                // Check if phone already exists in clients
                if (!\App\Models\Client::where('phone', $lead->phone)->exists()) {
                    $names = explode(' ', trim($lead->full_name));
                    $lastName = $names[0] ?? '';
                    $firstName = $names[1] ?? '';
                    $middleName = $names[2] ?? '';

                    if (empty($firstName)) {
                        $firstName = $lastName;
                        $lastName = '';
                    }

                    $client = \App\Models\Client::create([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'middle_name' => $middleName,
                        'phone' => $lead->phone,
                        'phone_2' => $lead->phone_2,
                        'passport_number' => $lead->passport_series,
                        'region' => $lead->region,
                        'district' => $lead->district,
                        'address' => $lead->address,
                        'source' => $lead->source,
                    ]);

                    $lead->delete();

                    return response()->json([
                        'message' => 'Mijoz to\'liq to\'ldirilgani uchun avtomatik Mijozlar bo\'limiga o\'tkazildi!',
                        'client' => $client
                    ], 201);
                }
            }

            // Send Telegram Notification
            $settings = \App\Models\SystemSetting::first();
            if ($settings && $settings->telegram_lead_bot_token && $settings->telegram_lead_chat_id) {
                $token = $settings->telegram_lead_bot_token;
                $chatId = $settings->telegram_lead_chat_id;
                
                $message = "🔔 *YANGI LID* (Manba: {$source})\n\n";
                $message .= "👤 *FIO:* {$lead->full_name}\n";
                $message .= "📞 *Tel:* {$lead->phone}\n";
                
                if ($lead->region) $message .= "📍 *Viloyat:* {$lead->region}\n";
                if ($lead->district) $message .= "🏢 *Tuman:* {$lead->district}\n";
                if ($lead->comment) $message .= "💬 *Izoh:* {$lead->comment}\n";
                
                $message .= "\n_CRM tizimiga avtomatik qo'shildi._";
                
                try {
                    \Illuminate\Support\Facades\Http::withoutVerifying()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => $message,
                        'parse_mode' => 'Markdown'
                    ]);
                } catch (\Exception $e) {
                    Log::error('Telegram lead notification error: ' . $e->getMessage());
                }
            }

            $successMsg = $settings->promo_success_message ?? 'Rahmat! Menejerimiz tez orada aloqaga chiqadi';

            return response()->json([
                'message' => $successMsg,
                'lead' => $lead
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Lead generation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Xatolik yuz berdi. Iltimos keyinroq qayta urinib ko\'ring.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
