#!/bin/bash
# Auto-update script running on server startup
echo "[$(date)] Starting auto-update on boot..." >> /tmp/sherdor_boot_update.log

# Wait for network connectivity
sleep 10

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd "$DIR" || exit 1

git fetch origin >> /tmp/sherdor_boot_update.log 2>&1
git reset --hard origin/main >> /tmp/sherdor_boot_update.log 2>&1
composer install --optimize-autoloader --no-dev >> /tmp/sherdor_boot_update.log 2>&1
php artisan migrate --force >> /tmp/sherdor_boot_update.log 2>&1
npm run build >> /tmp/sherdor_boot_update.log 2>&1
php artisan optimize:clear >> /tmp/sherdor_boot_update.log 2>&1
php artisan optimize >> /tmp/sherdor_boot_update.log 2>&1

# Restart Telegram Bot
pkill -9 -f 'telegram_bot/venv/bin/python' || pkill -9 -f 'bot.py' || true
if [ -d "telegram_bot" ]; then
    cd telegram_bot
    nohup ./venv/bin/python bot.py </dev/null >/dev/null 2>&1 &
fi

echo "[$(date)] Auto-update on boot finished successfully!" >> /tmp/sherdor_boot_update.log
