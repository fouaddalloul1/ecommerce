# High-Performance E-Commerce Backend Engine

Laravel 12 API project for the Parallel Programming course. The project focuses on concurrency control, Redis queues, distributed caching, daily batch processing, resource control, and load testing.

## Requirements

- PHP 8.2+
- Composer
- MySQL
- Redis
- Node.js (only for frontend tooling)
- k6 (for load tests)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan optimize:clear
```

Configure MySQL and Redis in `.env`. For a safe local mail demonstration, keep:

```env
MAIL_MAILER=log
```

Emails will then be written to `storage/logs/laravel.log` instead of being sent through a real SMTP account.

## Start the application

```bash
php artisan serve
php artisan schedule:work
```

Start queue workers:

### Linux / macOS

```bash
./scripts/start-workers.sh
```

### Windows PowerShell

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\start-workers.ps1
```

The project intentionally uses two queue connections:

- `redis`: invoices, notifications, and normal jobs (`timeout=240`).
- `redis-reports`: long daily reports (`timeout=1800`, `retry_after=1900`).

## Daily batch processing

Queue yesterday's report:

```bash
php artisan sales:daily-process
```

Queue a specific date:

```bash
php artisan sales:daily-process --date=2026-06-20 --force
```

Run synchronously only for debugging / demonstration:

```bash
php artisan sales:daily-process --date=2026-06-20 --force --sync
```

Generated reports are saved under:

```text
storage/app/private/daily_reports/
```

The scheduled job runs every day at 02:00 and processes the previous calendar day.

## Asynchronous order flow

```text
POST /api/v1/orders
  -> database transaction commits
  -> GeneratePdfJob (invoices queue)
  -> SendEmailWithPdfJob (notifications queue)
  -> SendNotificationJob (notifications queue)
```

The HTTP response does not wait for PDF generation or email delivery. `GET /api/v1/orders/{id}` exposes the async status fields.

## Important files

- `app/Jobs/ProcessDailySalesJob.php`
- `app/Console/Commands/ProcessDailySalesCommand.php`
- `routes/console.php`
- `Modules/Order/Jobs/GeneratePdfJob.php`
- `Modules/Order/Jobs/SendEmailWithPdfJob.php`
- `Modules/Order/Jobs/SendNotificationJob.php`
- `Modules/Order/app/Repositories/OrderRepository.php`
- `config/queue.php`
- `config/batch.php`
- `docs/INTERVIEW_BATCH_ASYNC_AR.md`

## Security note

Never commit `.env`. It may contain database, SMTP, API, or other credentials. Use `.env.example` as the shareable template.
