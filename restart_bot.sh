#!/bin/bash
# Get the directory of this script
SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
cd "$SCRIPT_DIR"

echo "🛑 Barcha eski botlarni to'xtatamiz..."
pkill -9 -f "telegram_bot/venv/bin/python"
pkill -9 -f "bot.py"
sleep 2

echo "✅ Yangi kodlarni qabul qilamiz..."
if [ -f "update.sh" ]; then
    bash update.sh
else
    git pull
fi

echo "🚀 Botni yagona nusxada ishga tushiramiz..."
cd telegram_bot
nohup ./venv/bin/python bot.py > bot.log 2>&1 &

echo "🎉 Tayyor! Bot ishga tushdi."
