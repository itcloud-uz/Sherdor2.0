<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage; // Kesh va fayllar uchun super radar

class SendBackupToTelegram extends Command
{
    protected $signature = 'backup:send';
    protected $description = 'Eng oxirgi zaxira faylini Telegramga jo\'natadi';

    public function handle()
    {
        $token = config('services.telegram.backup_bot_token');
        $chatId = config('services.telegram.backup_chat_id');

        if (!$token || !$chatId) {
            $this->error('Telegram sozlamalari topilmadi! (.env faylini tekshiring)');
            return;
        }

        $this->info("Zaxira fayli qidirilmoqda...");

        // Faqat backup nomi ostidagi papkadagi fayllarni olamiz (boshqa taqiqlangan papkalarni aylanib o'tish uchun)
        $backupName = config('backup.backup.name', 'laravel-backup');
        $files = Storage::disk('local')->files($backupName);
        
        // Ularning ichidan faqat .zip qisqartmali fayllarni ajratib olamiz
        $zipFiles = array_filter($files, function ($file) {
            return str_ends_with($file, '.zip');
        });
 
        if (empty($zipFiles)) {
            $this->error('Zaxira fayli umuman topilmadi!');
            return;
        }
 
        // Topilgan .zip fayllarni vaqti bo'yicha saralaymiz (eng yangisi 1-o'ringa chiqadi)
        usort($zipFiles, function($a, $b) {
            return Storage::disk('local')->lastModified($b) - Storage::disk('local')->lastModified($a);
        });
 
        // Eng yangi faylning mutlaq (to'liq) manzilini olamiz
        $latestBackupPath = Storage::disk('local')->path($zipFiles[0]);
 
        $this->info("Jo'natilmoqda: " . basename($latestBackupPath));
 
        // Telegram API orqali faylni sizga jo'natamiz
        $appName = config('app.name', 'Sherdor ERP');
        $response = Http::attach(
            'document', file_get_contents($latestBackupPath), basename($latestBackupPath)
        )->post("https://api.telegram.org/bot{$token}/sendDocument", [
            'chat_id' => $chatId,
            'caption' => "✅ {$appName}: Baza zaxirasi saqlandi!\n📅 Sana: " . now()->format('Y-m-d H:i:s')
        ]);
 
        if ($response->successful()) {
            $this->info('Telegramga muvaffaqiyatli jo\'natildi! Omad!');
        } else {
            $this->error('Telegramga jo\'natishda xatolik yuz berdi: ' . $response->body());
        }
    }
}
