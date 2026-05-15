#!/bin/bash

# scripts/benchmark-workers.sh
# هذا السكربت يختبر 3 سيناريوهات: 1 worker, 3 workers, 5 workers

PROJECT_PATH="/run/media/kali/Fouad 2/A-Projects/ecommers/" # غير هذا المسار
cd "$PROJECT_PATH"  

# دالة لتعديل عدد الـ workers في horizon.php
set_workers() {
    local workers=$1
    sed -i "s/'processes' => [0-9]\+/'processes' => $workers/" config/horizon.php
    echo "✅ تم ضبط عدد الـ workers إلى $workers"
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

    # قياس CPU قبل الاختبار
    CPU_BEFORE=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)

    # تشغيل اختبار k6
    k6 run --out json="results_${workers}workers.json" load-test-orders.js

    # قياس CPU بعد الاختبار
    CPU_AFTER=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)

    # إيقاف Horizon
    kill $HORIZON_PID

    # تسجيل النتائج
    echo "$workers,$CPU_BEFORE,$CPU_AFTER,$(date +%s)" >> benchmark_results.csv

    echo "✅ انتهى اختبار $workers workers"
    sleep 5
}

# تهيئة ملف النتائج
echo "workers,cpu_before,cpu_after,timestamp" > benchmark_results.csv

# تشغيل الاختبارات
run_test 1 "Single Worker"
run_test 3 "Medium Workers"
run_test 5 "High Workers"

echo "🎉 جميع الاختبارات انتهت!"
echo "📊 النتائج保存在 benchmark_results.csv"

# عرض ملخص النتائج
cat benchmark_results.csv
