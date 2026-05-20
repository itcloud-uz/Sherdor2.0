<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Schedule::command('hr:accrue-daily-salaries')->daily(); // Qorovullar (va boshqalar) uchun kunlik hisoblash o'chirildi
Schedule::command('sms:debtors')->hourly();
Schedule::command('sms:promo')->hourly();

// Zaxiralash (Backup) jadvali - Ertalabki vaqt
Schedule::command('backup:clean')->dailyAt('07:00');
Schedule::command('backup:run --only-db')->dailyAt('07:30');
Schedule::command('backup:send')->dailyAt('07:35');
Schedule::command('telephony:run-campaigns')->everyMinute();