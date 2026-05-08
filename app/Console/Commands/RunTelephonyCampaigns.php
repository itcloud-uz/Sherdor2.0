<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telephony:run-campaigns')]
#[Description('Runs active telephony campaigns based on schedule')]
class RunTelephonyCampaigns extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $currentDay = now()->dayOfWeekIso; // 1 (Mon) to 7 (Sun)
        $currentTime = now()->format('H:i');

        // Find campaigns that match current day and time
        $campaigns = \App\Models\TelephonyCampaign::where('is_active', true)
            ->where('schedule_time', 'like', $currentTime . '%')
            ->get();

        foreach ($campaigns as $campaign) {
            $days = explode(',', $campaign->schedule_days);
            if (in_array($currentDay, $days)) {
                $this->info("Running campaign ID: {$campaign->id}");
                $this->processCampaign($campaign);
            }
        }
    }

    protected function processCampaign($campaign)
    {
        if ($campaign->target_group === 'new_leads') {
            $leads = $campaign->leads()->where('status', 'pending')->get();
            foreach ($leads as $lead) {
                // Mock call logic
                $this->info("Calling lead: {$lead->phone_number}");
                $lead->update(['status' => 'called']);
            }
        } elseif ($campaign->target_group === 'debtors') {
            // Find debtors logic
            $this->info("Calling debtors for campaign {$campaign->id}");
        }
    }
}
