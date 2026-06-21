#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

echo "Stopping queue workers..."
pkill -f "artisan queue:work" 2>/dev/null || true

# Restart long-running workers after deployments or code changes.
php artisan queue:restart || true

# Clear only Laravel caches. Do not FLUSHALL because Redis is shared by
# queues, cache, scheduler locks and distributed application locks.
php artisan optimize:clear
php artisan queue:flush || true

echo "Cleanup completed. Start workers with scripts/start-workers.sh."
