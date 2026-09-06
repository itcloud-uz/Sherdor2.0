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
            'gender' => 'nullable|string|in:Erkak,Ayol,erkak,ayol',
            'birth_date' => 'nullable|date',
            'region' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'comment' => 'nullable|string|max:2000',
            'assigned_employee_id' => 'nullable|exists:users,id',
            'email' => 'nullable|string|email|max:255',
            'social_url' => 'nullable|string|max:1000',
            'source' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:new,contacted,qualified,lost',
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

            $targetStatus = $request->input('status', 'new');
            if (!in_array($targetStatus, ['new', 'contacted', 'qualified', 'lost'])) {
                $targetStatus = 'new';
            }

            $comment = $validated['comment'] ?? null;
            if ($request->filled('budget')) {
                $budgetStr = "Byudjet: " . $request->input('budget') . " " . strtoupper($request->input('currency', 'uzs'));
                $comment = !empty($comment) ? "[{$budgetStr}] " . $comment : $budgetStr;
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
                'comment' => $comment,
                'assigned_employee_id' => $validated['assigned_employee_id'] ?? null,
                'email' => $validated['email'] ?? null,
                'social_url' => $validated['social_url'] ?? null,
                'source' => $source,
                'status' => $targetStatus,
                'user_id' => $validated['user_id'] ?? null,
                'passport_issued_date' => $validated['passport_issued_date'] ?? null,
                'passport_issued_by' => $validated['passport_issued_by'] ?? null,
            ]);

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

            // If explicitly created as 'qualified' (e.g. via '+ Yangi Lid qo\'shish'), sync to Client table
            if ($targetStatus === 'qualified') {
                $crmController = new \App\Http\Controllers\Admin\CrmController();
                return $crmController->syncLeadToClient($lead, $request, false);
            }

            $successMsg = $settings->promo_success_message ?? 'Yangi lid muvaffaqiyatli saqlandi!';

            return response()->json([
                'message' => 'Yangi lid muvaffaqiyatli saqlandi!',
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
