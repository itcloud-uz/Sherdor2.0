<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Lead;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TelegramBotController extends Controller
{
    /**
     * Display the Telegram Bot management dashboard.
     */
    public function index()
    {
        $serviceStatus = $this->getServiceStatus();
        $logs = $this->fetchLogs(40);

        // Fetch tokens from env, config or systemSettings
        $systemSettings = SystemSetting::first();
        $leadChatId = $systemSettings ? $systemSettings->telegram_lead_chat_id : null;

        $financeToken = trim((string) (config('services.telegram.finance_bot_token') ?: (env('FINANCE_BOT_TOKEN') ?: $systemSettings?->telegram_lead_bot_token)));
        $botSecretKey = trim((string) env('BOT_SECRET_KEY', 'default_secret_key_for_dev'));
        $pinToken = trim((string) (config('services.telegram.pin_bot_token') ?: env('PIN_BOT_TOKEN')));
        $backupToken = trim((string) (config('services.telegram.backup_bot_token') ?: env('BACKUP_BOT_TOKEN')));

        // Test finance bot token to get bot details
        $botInfo = null;
        if (!empty($financeToken)) {
            $botInfo = $this->queryTelegramGetMe($financeToken);
        }

        // Connected users with roles
        $users = User::select('id', 'name', 'phone', 'email', 'telegram_chat_id')
            ->with(['roles:id,name'])
            ->orderByRaw('CASE WHEN telegram_chat_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'phone' => $u->phone,
                    'email' => $u->email,
                    'telegram_chat_id' => $u->telegram_chat_id,
                    'roles' => $u->roles->pluck('name')->toArray()
                ];
            });

        // Recent bot transactions
        $recentTransactions = Transaction::where('source', 'telegram')
            ->with(['creator:id,name', 'category:id,name', 'cashRegister:id,name'])
            ->latest()
            ->limit(20)
            ->get();

        // Recent bot leads
        $recentLeads = Lead::where('source', 'telegram')
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('Admin/TelegramBot/Index', [
            'serviceStatus' => $serviceStatus,
            'botInfo' => $botInfo,
            'config' => [
                'finance_bot_token' => $financeToken,
                'bot_secret_key' => $botSecretKey,
                'pin_bot_token' => $pinToken,
                'backup_bot_token' => $backupToken,
                'telegram_lead_chat_id' => $leadChatId,
            ],
            'users' => $users,
            'recentTransactions' => $recentTransactions,
            'recentLeads' => $recentLeads,
            'logs' => $logs
        ]);
    }

    /**
     * Start / Stop / Restart the moliyabot systemd service.
     */
    public function controlService(Request $request)
    {
        $request->validate([
            'action' => 'required|in:start,stop,restart'
        ]);

        $action = $request->action;
        $cmd = "sudo /usr/bin/systemctl {$action} moliyabot.service 2>&1";
        $output = shell_exec($cmd);

        Log::info("TelegramBot service control: {$action}, Output: {$output}");

        $newStatus = $this->getServiceStatus();

        return redirect()->back()->with('success', "Bot xizmati muvaffaqiyatli {$action} qilindi!");
    }

    /**
     * Get live journalctl logs.
     */
    public function getLogs()
    {
        $logs = $this->fetchLogs(60);
        return response()->json([
            'logs' => $logs,
            'service_status' => $this->getServiceStatus()
        ]);
    }

    /**
     * Test a Telegram Bot Token via getMe API.
     */
    public function testToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $info = $this->queryTelegramGetMe(trim((string) $request->token));

        if ($info && !empty($info['ok'])) {
            return response()->json([
                'success' => true,
                'bot' => $info['result'],
                'message' => "Bot faol: @" . ($info['result']['username'] ?? 'Noma\'lum')
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "Token noto'g'ri yoki Telegram serveriga ulanib bo'lmadi (" . ($info['description'] ?? 'Xato') . ")"
        ], 422);
    }

    /**
     * Update Bot Configuration & Tokens in .env and Database.
     */
    public function updateConfig(Request $request)
    {
        try {
            $validated = $request->validate([
                'finance_bot_token' => 'nullable|string',
                'bot_secret_key' => 'nullable|string',
                'pin_bot_token' => 'nullable|string',
                'backup_bot_token' => 'nullable|string',
                'telegram_lead_chat_id' => 'nullable|string',
            ]);

            // 1. Update .env variables
            $envUpdates = [];
            if ($request->has('finance_bot_token')) {
                $envUpdates['FINANCE_BOT_TOKEN'] = $validated['finance_bot_token'] ?? '';
            }
            if ($request->has('bot_secret_key')) {
                $envUpdates['BOT_SECRET_KEY'] = $validated['bot_secret_key'] ?? 'default_secret_key_for_dev';
            }
            if ($request->has('pin_bot_token')) {
                $envUpdates['PIN_BOT_TOKEN'] = $validated['pin_bot_token'] ?? '';
            }
            if ($request->has('backup_bot_token')) {
                $envUpdates['BACKUP_BOT_TOKEN'] = $validated['backup_bot_token'] ?? '';
            }

            $this->updateEnvFile($envUpdates);

            // 2. Update SystemSetting for lead chat id & bot token
            $settings = SystemSetting::first();
            if ($settings) {
                if ($request->has('telegram_lead_chat_id')) {
                    $settings->telegram_lead_chat_id = $validated['telegram_lead_chat_id'];
                }
                if (!empty($validated['finance_bot_token'])) {
                    $settings->telegram_lead_bot_token = $validated['finance_bot_token'];
                }
                $settings->save();
            }

            // 3. Clear cache
            try {
                Artisan::call('config:clear');
            } catch (\Throwable $e) {}

            // 4. If finance bot service is running, restart it to apply new token
            try {
                $status = $this->getServiceStatus();
                if ($status['status'] === 'active') {
                    shell_exec("sudo /usr/bin/systemctl restart moliyabot.service 2>&1");
                }
            } catch (\Throwable $e) {}

            return redirect()->back()->with('success', 'Bot sozlamalari muvaffaqiyatli saqlandi!');
        } catch (\Throwable $e) {
            Log::error("TelegramBot updateConfig error: " . $e->getMessage());
            return redirect()->back()->withErrors([
                'config' => 'Sozlamalarni saqlashda xatolik: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Update user's Telegram Chat ID.
     */
    public function updateUser(Request $request, User $user)
    {
        $validated = $request->validate([
            'telegram_chat_id' => 'nullable|string|max:255'
        ]);

        $chatId = !empty($validated['telegram_chat_id']) ? trim($validated['telegram_chat_id']) : null;

        if ($chatId) {
            // Agar ushbu chat_id boshqa xodimda bo'lsa, dublikat (unique constraint) xatolik bermaslik uchun undan ajratiladi
            User::where('telegram_chat_id', $chatId)
                ->where('id', '!=', $user->id)
                ->update(['telegram_chat_id' => null]);
        }

        $user->telegram_chat_id = $chatId;
        $user->save();

        return redirect()->back()->with('success', "{$user->name} ning Telegram Chat ID si yangilandi!");
    }

    /**
     * Send test message via Telegram Bot.
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'chat_id' => 'required|string',
            'message' => 'required|string',
            'bot_type' => 'nullable|in:finance,pin,backup'
        ]);

        $botType = $request->bot_type ?: 'finance';
        $token = match ($botType) {
            'pin' => config('services.telegram.pin_bot_token') ?: env('PIN_BOT_TOKEN'),
            'backup' => config('services.telegram.backup_bot_token') ?: env('BACKUP_BOT_TOKEN'),
            default => config('services.telegram.finance_bot_token') ?: (env('FINANCE_BOT_TOKEN') ?: SystemSetting::first()?->telegram_lead_bot_token),
        };
        $token = trim((string) $token);

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'Ushbu bot uchun token kiritilmagan!'
            ], 422);
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $request->chat_id,
                    'text' => $request->message,
                    'parse_mode' => 'HTML'
                ]);

            if ($response->successful() && $response->json('ok')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Xabar Telegramga muvaffaqiyatli yuborildi!'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Telegram xatosi: ' . ($response->json('description') ?? 'Noma\'lum xatolik')
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ulanish xatosi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper to get systemd service status.
     */
    protected function getServiceStatus(): array
    {
        $rawActive = trim((string) @shell_exec("systemctl is-active moliyabot.service 2>/dev/null"));
        $rawStatus = (string) @shell_exec("systemctl status moliyabot.service --no-pager 2>/dev/null");

        $pid = null;
        if (preg_match('/Main PID:\s*(\d+)/', $rawStatus, $matches)) {
            $pid = $matches[1];
        }

        $uptime = null;
        if (preg_match('/Active:\s*active \(running\) since (.+?);/i', $rawStatus, $matches)) {
            $uptime = trim($matches[1]);
        }

        $memory = null;
        if (preg_match('/Memory:\s*([^\n]+)/', $rawStatus, $matches)) {
            $memory = trim($matches[1]);
        }

        return [
            'status' => $rawActive ?: 'inactive',
            'pid' => $pid,
            'uptime' => $uptime,
            'memory' => $memory,
            'service_name' => 'moliyabot.service'
        ];
    }

    /**
     * Helper to fetch logs from journalctl or bot.log.
     */
    protected function fetchLogs(int $lines = 40): string
    {
        $output = @shell_exec("sudo /usr/bin/journalctl -u moliyabot.service -n {$lines} --no-pager 2>&1");
        if (!empty($output) && !str_contains($output, 'No entries')) {
            return $output;
        }

        $logFile = base_path('telegram_bot/bot.log');
        if (file_exists($logFile)) {
            $content = file($logFile);
            if ($content !== false) {
                return implode('', array_slice($content, -$lines));
            }
        }

        return "Hozircha loglar mavjud emas.";
    }

    /**
     * Query Telegram getMe API.
     */
    protected function queryTelegramGetMe(string $token): ?array
    {
        try {
            $res = Http::withoutVerifying()
                ->timeout(5)
                ->get("https://api.telegram.org/bot{$token}/getMe");

            return $res->json();
        } catch (\Throwable $e) {
            return ['ok' => false, 'description' => $e->getMessage()];
        }
    }

    /**
     * Safely update .env file variables.
     */
    protected function updateEnvFile(array $data): void
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $value = (string) $value;
            // Wrap in quotes if contains spaces or special characters
            if (str_contains($value, ' ') || str_contains($value, '#')) {
                $formattedValue = '"' . $value . '"';
            } else {
                $formattedValue = $value;
            }

            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$formattedValue}", $content);
            } else {
                $content .= "\n{$key}={$formattedValue}";
            }
        }

        try {
            $written = @file_put_contents($envPath, $content);
            if ($written === false) {
                Log::warning("Direct file_put_contents to .env failed, trying shell cp");
                $tmpFile = tempnam(sys_get_temp_dir(), 'env_');
                file_put_contents($tmpFile, $content);
                shell_exec("cp " . escapeshellarg($tmpFile) . " " . escapeshellarg($envPath) . " 2>&1");
                @unlink($tmpFile);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to update .env: " . $e->getMessage());
        }
    }
}
