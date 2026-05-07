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

            $batchMessages = [];

            if ($rule->audience_type === 'manual_number' && !empty($rule->manual_phone)) {
                $phones = explode(',', $rule->manual_phone);
                foreach ($phones as $p) {
                    $p = trim($p);
                    $p = preg_replace('/[^0-9]/', '', $p);
                    if (strlen($p) >= 9) {
                        $batchMessages[] = [
                            'to' => $p,
                            'text' => $template->content
                        ];
                    }
                }
            } elseif ($rule->audience_type === 'upload_file') {
                // Fetch from audience_lists (assuming all audience_lists are from uploaded files for now)
                // In a real app we might filter by specific upload, but for now we take the whole list
                $audience = DB::table('audience_lists')->get();
                foreach ($audience as $person) {
                    $batchMessages[] = [
                        'to' => $person->phone_number,
                        'text' => $template->content
                    ];
                }
            } elseif ($rule->audience_type === 'system_clients') {
                // Fetch all clients EXCEPT debtors
                // Find debtor IDs
                $debtorContracts = \App\Models\Contract::with('paymentSchedules')
                    ->where('status', 'active')
                    ->get()
                    ->filter(function ($contract) {
                        return $contract->paymentSchedules->contains(function ($payment) {
                            return $payment->status === 'pending' && \Carbon\Carbon::parse($payment->due_date)->isPast();
                        });
                    });
                
                $debtorClientIds = $debtorContracts->pluck('client_id')->unique()->toArray();

                $clients = \App\Models\Client::whereNotIn('id', $debtorClientIds)->whereNotNull('phone_number')->get();
                
                foreach ($clients as $client) {
                    // Replace tags if any
                    $msg = str_replace('[Ism]', $client->first_name ?? '', $template->content);
                    $msg = str_replace('[Familiya]', $client->last_name ?? '', $msg);
                    
                    $batchMessages[] = [
                        'to' => preg_replace('/[^0-9]/', '', $client->phone_number),
                        'text' => $msg
                    ];
                }
            } else {
                // 'all' or fallback -> send to audience_lists
                $audience = DB::table('audience_lists')->get();
                foreach ($audience as $person) {
                    $batchMessages[] = [
                        'to' => $person->phone_number,
                        'text' => $template->content
                    ];
                }
            }

            if (empty($batchMessages)) continue;

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
            
            // To prevent spamming, we deactivate it after running once
            DB::table('sms_automation_rules')->where('id', $rule->id)->update(['is_active' => false]);
        }
    }
}
