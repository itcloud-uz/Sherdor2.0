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
            'full_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'pinfl' => 'nullable|string|max:255',
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
            'passport_photo' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:20480',
            'passport_photo_back' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:20480',
            'passport_issued_date' => 'nullable|date',
            'passport_issued_by' => 'nullable|string|max:500',
        ]);

        try {
            $source = $validated['source'] ?? 'landing_page';
            
            // Override source if authenticated user is submitting it manually
            if ((auth()->check() && $request->is('admin/*') || $request->has('is_manual')) && empty($validated['source'])) {
                $source = 'manual';
            }

            $fullName = trim(($validated['last_name'] ?? '') . ' ' . ($validated['first_name'] ?? '') . ' ' . ($validated['middle_name'] ?? ''));
            if (empty($fullName)) {
                $fullName = $validated['full_name'] ?? '';
            }

            $passportPhotoPath = null;
            if ($request->hasFile('passport_photo')) {
                $passportPhotoPath = $request->file('passport_photo')->store('passport_photos', 'public');
            }

            $passportPhotoBackPath = null;
            if ($request->hasFile('passport_photo_back')) {
                $passportPhotoBackPath = $request->file('passport_photo_back')->store('passport_photos', 'public');
            }

            $lead = Lead::create([
                'full_name' => $fullName,
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'middle_name' => $validated['middle_name'] ?? null,
                'pinfl' => $validated['pinfl'] ?? null,
                'passport_photo' => $passportPhotoPath,
                'passport_photo_back' => $passportPhotoBackPath,
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
                'passport_issued_date' => $validated['passport_issued_date'] ?? null,
                'passport_issued_by' => $validated['passport_issued_by'] ?? null,
            ]);

            // Always convert to Client directly so it lands in Mijozlar section!
            $cleanLeadPhone = preg_replace('/[^0-9]/', '', $lead->phone ?? '');
            $existingClient = null;
            if (!empty($lead->phone)) {
                $existingClient = \App\Models\Client::where('phone', $lead->phone)->first();
            }
            if (!$existingClient && !empty($cleanLeadPhone)) {
                $allClients = \App\Models\Client::select('id', 'phone')->get();
                foreach ($allClients as $c) {
                    if (!empty($c->phone) && preg_replace('/[^0-9]/', '', $c->phone) === $cleanLeadPhone) {
                        $existingClient = $c;
                        break;
                    }
                }
            }

            if ($existingClient) {
                $updateData = [];
                if (empty($existingClient->passport_number) && !empty($lead->passport_series)) $updateData['passport_number'] = $lead->passport_series;
                if (empty($existingClient->region) && !empty($lead->region)) $updateData['region'] = $lead->region;
                if (empty($existingClient->district) && !empty($lead->district)) $updateData['district'] = $lead->district;
                if (empty($existingClient->address) && !empty($lead->address)) $updateData['address'] = $lead->address;
                if (empty($existingClient->source) && !empty($lead->source)) $updateData['source'] = $lead->source;
                if (empty($existingClient->pinfl) && !empty($lead->pinfl)) $updateData['pinfl'] = $lead->pinfl;

                if (!empty($updateData)) {
                    $existingClient->update($updateData);
                }

                if (class_exists('\App\Models\LeadConversionLog')) {
                    \App\Models\LeadConversionLog::create([
                        'lead_id' => $lead->id,
                        'client_id' => $existingClient->id,
                        'user_id' => auth()->id() ?? 1,
                        'lead_name' => $lead->full_name ?: ($lead->phone ?: 'Mijoz'),
                        'lead_phone' => $lead->phone,
                        'action' => 'merged',
                        'description' => "Yangi kiritilgan ma'lumotlar mavjud mijozga (#{$existingClient->id}) biriktirildi va Mijozlar bo'limiga o'tkazildi.",
                        'status' => 'merged',
                    ]);
                }

                $lead->delete();

                return response()->json([
                    'message' => 'Mijoz allaqachon mavjud edi, ma\'lumotlar birlashtirildi va Mijozlar bo\'limiga o\'tkazildi!',
                    'client' => $existingClient
                ], 201);
            }

            $lastName = $lead->last_name;
            $firstName = $lead->first_name;
            $middleName = $lead->middle_name;

            if (empty($firstName) && empty($lastName)) {
                $names = explode(' ', trim($lead->full_name));
                $lastName = $names[0] ?? '';
                $firstName = $names[1] ?? '';
                $middleName = $names[2] ?? '';

                if (empty($firstName)) {
                    $firstName = $lastName ?: ($lead->full_name ?: ('Mijoz #' . $lead->id));
                    $lastName = '';
                }
            }

            $client = \App\Models\Client::create([
                'first_name' => $firstName ?: ($lead->full_name ?: 'Mijoz'),
                'last_name' => $lastName ?: '',
                'middle_name' => $middleName ?: '',
                'phone' => $lead->phone,
                'phone_2' => $lead->phone_2,
                'passport_number' => $lead->passport_series,
                'region' => $lead->region,
                'district' => $lead->district,
                'address' => $lead->address,
                'source' => $lead->source,
                'pinfl' => $lead->pinfl,
                'passport_photo' => $lead->passport_photo,
                'passport_photo_back' => $lead->passport_photo_back,
                'passport_issued_date' => $lead->passport_issued_date,
                'passport_issued_by' => $lead->passport_issued_by,
            ]);

            if (class_exists('\App\Models\LeadConversionLog')) {
                \App\Models\LeadConversionLog::create([
                    'lead_id' => $lead->id,
                    'client_id' => $client->id,
                    'user_id' => auth()->id() ?? 1,
                    'lead_name' => $lead->full_name ?: ($lead->phone ?: 'Mijoz'),
                    'lead_phone' => $lead->phone,
                    'action' => 'converted',
                    'description' => "Yangi Mijoz yaratildi va Mijozlar bo'limiga qo'shildi (#{$client->id} - {$client->last_name} {$client->first_name}).",
                    'status' => 'success',
                ]);
            }

            $lead->delete();

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
