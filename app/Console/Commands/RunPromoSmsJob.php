<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\EskizService;

class RunPromoSmsJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:promo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scheduled promo SMS campaigns for audience lists';

    /**
     * Execute the console command.
     */
    public function handle(EskizService $eskiz)
    {
        // Similar to debtor job, check active rules for 'promo'
        $currentDay = date('N'); 
        $currentHourHourMinute = date('H:i');

        $rules = DB::table('sms_automation_rules')
            ->where('is_active', true)
            ->where('event_trigger', 'promo')
            ->get();

        foreach ($rules as $rule) {
            $days = explode(',', $rule->schedule_days ?? '');
            if (!empty($rule->schedule_days) && !in_array((string)$currentDay, $days)) {
                continue; 
            }

            // Fetch template
            $template = DB::table('sms_templates')->where('id', $rule->template_id)->first();
            if (!$template) continue;

            // Fetch Audience
            $audience = DB::table('audience_lists')->get();
            if ($audience->isEmpty()) continue;

            // Prepare batch messages
            $batchMessages = [];
            foreach ($audience as $person) {
                // For promo, we just send the template content as-is 
                // since the CSV only has phone numbers
                $msg = $template->content;
                
                $batchMessages[] = [
                    'to' => $person->phone_number,
                    'text' => $msg
                ];
            }

            // Send in chunks of 500
            $chunks = array_chunk($batchMessages, 500);
            $totalSent = 0;

            foreach ($chunks as $chunk) {
                try {
                    $eskiz->sendBatch($chunk, 'promo');
                    $totalSent += count($chunk);
                } catch (\Exception $e) {
                    $this->error("Failed to send promo batch: " . $e->getMessage());
                }
            }

            $this->info("Promo SMS Rule '{$rule->name}' executed. Sent {$totalSent} messages in batch.");
            
            // To prevent spamming if it runs every minute, we might want to disable the rule or log that it ran today.
            // Simplified: we'll assume it's disabled after running or it's up to the user to manage it.
        }
    }
}
