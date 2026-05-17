#!/bin/bash

echo "========================================="
echo "🧹 بدء التنظيف الكامل لـ Horizon والكيوز"
echo "========================================="

# 1. إيقاف Horizon بشكل كامل
echo ""
echo "📌 إيقاف Horizon..."
php artisan horizon:terminate
sleep 2
pkill -f "horizon" 2>/dev/null
echo "✅ Horizon متوقف تماماً"

# 2. تفريغ Redis بالكامل (يمسح كل شيء)
echo ""
echo "📌 تفريغ Redis..."
redis-cli FLUSHALL
echo "✅ Redis تم تفريغه بالكامل"

# 3. حذف الجوبات الفاشلة من قاعدة البيانات
echo ""
echo "📌 حذف الجوبات الفاشلة..."
php artisan queue:flush 2>/dev/null
php artisan queue:forget --all 2>/dev/null
echo "✅ الجوبات الفاشلة تم حذفها"

# 4. تنظيف كل كاشات Laravel
echo ""
echo "📌 تنظيف الكاشات..."
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan optimize:clear
echo "✅ جميع الكاشات تم تنظيفها"

# 5. التأكد من عدم وجود عمليات PHP عالقة
echo ""
echo "📌 التأكد من عدم وجود عمليات عالقة..."
pkill -f "queue:work" 2>/dev/null
pkill -f "horizon" 2>/dev/null
echo "✅ لا توجد عمليات عالقة"

# 6. إعادة تشغيل Horizon (إذا كنت تريد)
echo ""
read -p "❓ هل تريد تشغيل Horizon الآن؟ (y/n): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php artisan horizon > /dev/null 2>&1 &
    sleep 3
    echo "✅ Horizon شغال من جديد"
    php artisan horizon:status
else
    echo "⚠️ Horizon لم يتم تشغيله (يمكنك تشغيله لاحقاً بـ: php artisan horizon)"
fi

echo ""
echo "========================================="
echo "🎉 التنظيف الكامل انتهى!"
echo "========================================="
