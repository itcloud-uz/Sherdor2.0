<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('hr:accrue-daily-salaries')->daily();
Schedule::command('sms:debtors')->hourly();
Schedule::command('sms:promo')->hourly();
