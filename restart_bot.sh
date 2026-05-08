#!/bin/bash
echo "🛑 Barcha eski botlarni to'xtatamiz..."
pkill -9 -f "python.*bot.py"
pkill -9 -f "bot.py"
sleep 2

echo "✅ Yangi kodlarni qabul qilamiz..."
bash update.sh

echo "🚀 Botni yagona nusxada ishga tushiramiz..."
cd ~/Sherdor2.0/telegram_bot || cd telegram_bot
nohup python3 bot.py > bot.log 2>&1 &

echo "🎉 Tayyor! Bot ishga tushdi."
