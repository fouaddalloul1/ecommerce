#!/bin/bash

# scripts/benchmark-workers.sh
# اختبار 3 سيناريوهات: 1 worker, 3 workers, 5 workers

PROJECT_PATH="/run/media/kali/Fouad 2/A-Projects/ecommers"
cd "$PROJECT_PATH"

# دالة لتعديل عدد الـ workers في horizon.php
set_workers() {
    local workers=$1
    # تعديل عدد الـ workers لكل من invoices و notifications
    sed -i "s/'processes' => [0-9]\+/'processes' => $workers/" config/horizon.php
    echo "✅ تم ضبط عدد الـ workers إلى $workers"
}

# دالة لقياس الموارد
measure_resources() {
    local label=$1
    local cpu=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
    local mem=$(free -m | awk 'NR==2{printf "%.2f", $3*100/$2}')
    local mem_mb=$(free -m | awk 'NR==2{print $3}')
    echo "$label,$cpu,$mem,$mem_mb,$(date +%H:%M:%S)" >> "$PROJECT_PATH/resources_measurements.csv"
}

# دالة لاختبار سيناريو معين
run_test() {
    local workers=$1
    local test_name=$2

    echo "========================================="
    echo "🚀 بدء اختبار: $test_name ($workers workers)"
    echo "========================================="

    # تعديل عدد الـ workers
    set_workers $workers

    # إعادة تشغيل Horizon
    php artisan horizon:terminate
    sleep 2
    php artisan horizon > /dev/null 2>&1 &
    HORIZON_PID=$!
    sleep 5

    echo "✅ Horizon شغال مع $workers workers (PID: $HORIZON_PID)"

    # قياس الموارد قبل الاختبار
    measure_resources "BEFORE_${workers}"

    # تشغيل اختبار k6 وحفظ النتائج
    echo "🔄 تشغيل k6 load test..."
    k6 run --out json="results_${workers}workers.json" load-test-orders.js

    # قياس الموارد بعد الاختبار
    measure_resources "AFTER_${workers}"

    # إيقاف Horizon
    kill $HORIZON_PID 2>/dev/null

    echo "✅ انتهى اختبار $workers workers"
    echo "========================================="
    sleep 5
}

# تهيئة ملفات النتائج
echo "timestamp,workers,scenario,cpu_percent,ram_percent,ram_mb" > resources_measurements.csv
echo "workers,cpu_before,cpu_after,ram_before_mb,ram_after_mb,timestamp" > benchmark_summary.csv

# تشغيل الاختبارات
run_test 1 "Single Worker"
run_test 3 "Medium Workers"
run_test 5 "High Workers"

echo ""
echo "🎉 جميع الاختبارات انتهت!"
echo ""
echo "📁 الملفات الناتجة:"
ls -lh results_*.json resources_measurements.csv benchmark_summary.csv 2>/dev/null

echo ""
echo "📊 ملخص النتائج:"
cat benchmark_summary.csv
