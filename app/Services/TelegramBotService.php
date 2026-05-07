<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    /**
     * Save a lead from the Telegram Bot.
     * 
     * @param string $fullName
     * @param string $phone
     * @return Lead|null
     */
    public function saveLead(string $fullName, string $phone): ?Lead
    {
        try {
            // Basic cleanup
            $phone = preg_replace('/[^0-9+]/', '', $phone);
            
            // Avoid duplicates based on phone number if needed (optional)
            $existingLead = Lead::where('phone', $phone)->first();
            if ($existingLead) {
                return $existingLead;
            }

            $lead = Lead::create([
                'full_name' => $fullName,
                'phone' => $phone,
                'source' => 'telegram',
                'status' => 'new',
            ]);

            Log::info("New lead captured via Telegram: {$fullName} ({$phone})");

            return $lead;
        } catch (\Exception $e) {
            Log::error("Failed to save lead from Telegram: " . $e->getMessage());
            return null;
        }
    }
}
