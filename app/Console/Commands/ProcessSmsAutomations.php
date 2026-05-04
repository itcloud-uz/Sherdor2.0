<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SmsAutomationRule;
use App\Services\EskizSmsService;

class ProcessSmsAutomations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:process-automations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes active SMS automation rules and sends messages via Eskiz.uz';

    /**
     * Execute the console command.
     */
    public function handle(EskizSmsService $smsService)
    {
        $this->info('Starting SMS Automation processing...');
        
        $activeRules = SmsAutomationRule::where('is_active', true)->with('template')->get();
        
        if ($activeRules->isEmpty()) {
            $this->info('No active rules found.');
            return;
        }

        foreach ($activeRules as $rule) {
            $this->info("Processing rule: {$rule->name}");
            // TODO: Implement complex event trigger and scheduling query logic here.
            // Example:
            // if ($rule->event_trigger === 'birthday') {
            //     $clients = Client::whereDay('dob', now()->day)->whereMonth('dob', now()->month)->get();
            //     foreach($clients as $client) {
            //         $smsService->sendSms($client->phone, $rule->template->content);
            //     }
            // }
        }

        $this->info('SMS Automations processed successfully.');
    }
}
