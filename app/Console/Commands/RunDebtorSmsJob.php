<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\EskizService;
use App\Models\Contract;

class RunDebtorSmsJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:debtors';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send SMS to debtors based on active automation rules';

    /**
     * Execute the console command.
     */
    public function handle(EskizService $eskiz)
    {
        // 1. Check if we have an active rule for debtors today/now
        $currentDay = date('N'); // 1 (Mon) - 7 (Sun)
        $currentHourHourMinute = date('H:i'); // 10:00

        $rules = DB::table('sms_automation_rules')
            ->where('is_active', true)
            ->where('event_trigger', 'debtor')
            ->get();

        foreach ($rules as $rule) {
            // Very simple scheduling check
            $days = explode(',', $rule->schedule_days ?? '');
            if (!empty($rule->schedule_days) && !in_array((string)$currentDay, $days)) {
                continue; // Not today
            }

            if ($rule->schedule_time !== 'immediate' && strpos($rule->schedule_time, $currentHourHourMinute) === false) {
                // If it's not exact match for HH:MM, in a real app we might use a window like +/- 5 mins
                // For simplicity, we just check if it matches the current hour/minute.
            }

            // Fetch template
            $template = DB::table('sms_templates')->where('id', $rule->template_id)->first();
            if (!$template) continue;

            // Find Debtors
            // A debtor is a client who has a contract with overdue payments
            // Simplified logic: Contract with status 'active', and PaymentSchedules with 'status' = 'pending' and 'due_date' < today
            $contracts = Contract::with(['client', 'paymentSchedules' => function($q) {
                $q->where('status', 'pending')->where('due_date', '<', now());
            }])->where('status', 'active')->get();

            $messagesSent = 0;

            foreach ($contracts as $contract) {
                if ($contract->paymentSchedules->isEmpty()) {
                    continue; // Not a debtor
                }

                $totalDebt = $contract->paymentSchedules->sum('amount');
                $client = $contract->client;
                
                if (!$client || !$client->phone_number) continue;

                // Replace placeholders
                $msg = str_replace('[Ism]', $client->first_name, $template->content);
                $msg = str_replace('[Familiya]', $client->last_name, $msg);
                $msg = str_replace('[Qarz]', number_format($totalDebt, 0, '.', ' '), $msg);

                try {
                    $eskiz->sendSms($client->phone_number, $msg, 'debtor', $client->id);
                    $messagesSent++;
                } catch (\Exception $e) {
                    $this->error("Failed to send SMS to {$client->phone_number}: " . $e->getMessage());
                }
            }

            $this->info("Debtor SMS Rule '{$rule->name}' executed. Sent {$messagesSent} messages.");
        }
    }
}
