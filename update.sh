#!/bin/bash

echo "🚀 Sherdor ERP tizimi yangilanmoqda..."
echo "------------------------------------------------"

echo "📦 1. GitHub'dan yangi kodlar olinmoqda..."
git pull origin main

echo "------------------------------------------------"
echo "⚙️ 2. PHP paketlari tekshirilmoqda..."
composer install --optimize-autoloader --no-dev

echo "------------------------------------------------"
echo "🗄 3. Ma'lumotlar bazasi yangilanmoqda..."
php artisan migrate --force

echo "------------------------------------------------"
echo "🎨 4. Frontend dizayn yig'ilmoqda (Vite)..."
npm install
npm run build

echo "------------------------------------------------"
echo "🧹 5. Keshlar tozalanib, papkalarga ruxsat berilmoqda..."
# Bu yerda sudo so'ralishi mumkin
sudo php artisan optimize
sudo chown -R www-data:www-data storage bootstrap/cache

echo "------------------------------------------------"
echo "✅ Barcha ishlar muvaffaqiyatli yakunlandi! Sayt to'liq ishga tushdi."
