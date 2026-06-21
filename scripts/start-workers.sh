#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

cleanup() {
    kill "${NORMAL_WORKER_PID:-}" "${REPORT_WORKER_PID:-}" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

echo "Starting invoice / notification worker..."
php artisan queue:work redis \
    --queue=invoices,notifications,default \
    --sleep=1 --tries=3 --timeout=240 &
NORMAL_WORKER_PID=$!

echo "Starting long-running reports worker..."
php artisan queue:work redis-reports \
    --queue=reports \
    --sleep=1 --tries=3 --timeout=1800 &
REPORT_WORKER_PID=$!

wait
