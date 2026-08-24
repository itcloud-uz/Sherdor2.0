<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class SendBackupToTelegram extends Command
{
    protected $signature = 'backup:send {--force : Avval backup:run qilib keyin yuborish}';
    protected $description = 'Eng oxirgi zaxira faylini Telegramga jo\'natadi';

    public function handle()
    {
        $rawToken = config('services.telegram.backup_bot_token') ?: env('BACKUP_BOT_TOKEN');
        $rawChatId = config('services.telegram.backup_chat_id') ?: env('BACKUP_CHAT_ID');

        $token = trim($rawToken ?? '', " \t\n\r\0\x0B\"'");
        $chatId = trim($rawChatId ?? '', " \t\n\r\0\x0B\"'");

        if (!$token || !$chatId) {
            $this->error('Telegram sozlamalari topilmadi! (.env faylida BACKUP_BOT_TOKEN va BACKUP_CHAT_ID tekshiring)');
            return 1;
        }

        $this->info("Zaxira fayli qidirilmoqda...");

        // If force requested or no backup files found, trigger backup:run --only-db
        $latestBackupPath = $this->findLatestBackupFile();

        if (!$latestBackupPath || $this->option('force')) {
            $this->info("Yangi ma'lumotlar bazasi zaxirasini yaratmoqdamiz (backup:run --only-db)...");
            try {
                Artisan::call('backup:run', ['--only-db' => true]);
                $this->line(Artisan::output());
            } catch (\Exception $e) {
                $this->warn("Spatie backup ogohlantirish berdi: " . $e->getMessage());
            }
            $latestBackupPath = $this->findLatestBackupFile();
        }

        // Fallback: If still no zip found, direct database file backup (SQLite or SQL dump)
        if (!$latestBackupPath || !file_exists($latestBackupPath)) {
            $latestBackupPath = $this->createDirectDbBackup();
        }

        if (!$latestBackupPath || !file_exists($latestBackupPath)) {
            $this->error('Zaxira fayli umuman topilmadi yoki yaratib bo\'lmadi!');
            return 1;
        }

        $fileSize = filesize($latestBackupPath);
        $fileSizeFormatted = $fileSize > 1048576 
            ? round($fileSize / 1048576, 2) . ' MB' 
            : round($fileSize / 1024, 2) . ' KB';

        $fileName = basename($latestBackupPath);
        $this->info("Jo'natilmoqda: {$fileName} ({$fileSizeFormatted})");

        $appName = config('app.name', 'IQS ERP');
        $caption = "📦 *{$appName} — BAZA ZAXIRASI (BACKUP)*\n\n"
                 . "🏢 *Tizim:* {$appName}\n"
                 . "📁 *Fayl:* `{$fileName}`\n"
                 . "📊 *Hajmi:* {$fileSizeFormatted}\n"
                 . "📅 *Sana:* " . now()->format('Y-m-d H:i:s') . "\n"
                 . "🔐 *Holat:* Muvaffaqiyatli saqlandi!";

        try {
            $response = Http::withoutVerifying()
                ->timeout(180)
                ->attach('document', file_get_contents($latestBackupPath), $fileName)
                ->post("https://api.telegram.org/bot{$token}/sendDocument", [
                    'chat_id' => $chatId,
                    'caption' => $caption,
                    'parse_mode' => 'Markdown'
                ]);

            if ($response->successful()) {
                $this->info("✅ Telegramga muvaffaqiyatli jo'natildi! ({$chatId})");
                return 0;
            } else {
                $errorDesc = $response->json('description') ?: $response->body();
                $this->error("❌ Telegramga jo'natishda xatolik yuz berdi: {$errorDesc}");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Telegram API ulanishida xatolik: " . $e->getMessage());
            return 1;
        }
    }

    protected function findLatestBackupFile(): ?string
    {
        $searchDirs = [
            storage_path('app/private/laravel-backup'),
            storage_path('app/laravel-backup'),
            storage_path('app/private'),
            storage_path('app'),
        ];

        $candidates = [];

        foreach ($searchDirs as $dir) {
            if (!is_dir($dir)) continue;

            try {
                $files = File::allFiles($dir);
                foreach ($files as $file) {
                    if (str_ends_with($file->getFilename(), '.zip') && !str_contains($file->getPathname(), 'backup-temp')) {
                        $candidates[] = [
                            'path' => $file->getPathname(),
                            'mtime' => $file->getMTime(),
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Ignore permission error on single subfolder and continue
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn($a, $b) => $b['mtime'] - $a['mtime']);

        return $candidates[0]['path'];
    }

    protected function createDirectDbBackup(): ?string
    {
        $dbConnection = config('database.default', 'sqlite');

        if ($dbConnection === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');
            if (file_exists($dbPath)) {
                $destDir = storage_path('app/backup-direct');
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0777, true);
                }
                $destFile = $destDir . '/database_backup_' . date('Y_m_d_His') . '.sqlite';
                if (copy($dbPath, $destFile)) {
                    return $destFile;
                }
            }
        }

        return null;
    }
}
