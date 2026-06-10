<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ScheduledSms;
use App\Services\EskizService;
use Illuminate\Support\Facades\Log;

class SendScheduledSmsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatches pending scheduled SMS messages';

    /**
     * Execute the console command.
     */
    public function handle(EskizService $eskiz)
    {
        $now = now();
        $this->info("Checking for scheduled SMS messages at: {$now}");

        $scheduledMessages = ScheduledSms::where('status', 'pending')
            ->where('scheduled_at', '<=', $now)
            ->get();

        if ($scheduledMessages->isEmpty()) {
            $this->info('No pending scheduled SMS messages to send.');
            return 0;
        }

        $this->info('Found ' . $scheduledMessages->count() . ' pending messages. Sending...');

        foreach ($scheduledMessages as $sms) {
            try {
                $eskiz->sendSms($sms->phone_number, $sms->message, 'scheduled_broadcast', $sms->client_id);
                $sms->update(['status' => 'sent']);
                $this->info("Successfully sent SMS to {$sms->phone_number}");
            } catch (\Exception $e) {
                Log::error("Failed to send scheduled SMS to {$sms->phone_number}: " . $e->getMessage());
                $sms->update(['status' => 'failed']);
                $this->error("Failed to send SMS to {$sms->phone_number}");
            }
        }

        $this->info('Scheduled SMS processing completed.');
        return 0;
    }
}
