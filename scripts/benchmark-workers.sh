#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

measure_resources() {
    local label=$1
    local workers=$2
    local cpu mem_mb mem_percent
    cpu=$(top -bn1 | awk '/Cpu\(s\)/ {print $2; exit}')
    mem_mb=$(free -m | awk 'NR==2 {print $3}')
    mem_percent=$(free -m | awk 'NR==2 {printf "%.2f", $3*100/$2}')
    echo "$(date --iso-8601=seconds),$label,$workers,$cpu,$mem_mb,$mem_percent" >> resources_measurements.csv
}

run_test() {
    local workers=$1

    echo "Testing with $workers invoice/notification workers..."
    pkill -f "artisan queue:work redis" 2>/dev/null || true

    pids=()
    for ((i=1; i<=workers; i++)); do
        php artisan queue:work redis \
            --queue=invoices,notifications,default \
            --sleep=1 --tries=3 --timeout=240 >/dev/null 2>&1 &
        pids+=("$!")
    done

    sleep 3
    measure_resources BEFORE "$workers"
    k6 run --out json="results_${workers}workers.json" load-test-orders.js
    measure_resources AFTER "$workers"

    kill "${pids[@]}" 2>/dev/null || true
    sleep 2
}

echo "timestamp,stage,workers,cpu_percent,ram_mb,ram_percent" > resources_measurements.csv
run_test 1
run_test 3
run_test 5

echo "Worker benchmark completed."
