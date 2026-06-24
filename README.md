# Laravel Modular E-Commerce API

A modular Laravel 12 e-commerce API designed to handle concurrent workloads while preserving data integrity and providing clear performance visibility.

The project includes transactional order processing, concurrency protection, Redis caching, asynchronous queues, batch reporting, Nginx load balancing, k6 load testing, and module-level performance monitoring.

---

## Project Architecture

The application follows a modular structure:

```text
Modules/
├── Product/
├── Category/
├── Order/
└── ...
```

Each module contains its own controllers, services, repositories, requests, data objects, models, routes, and related business logic.

The main tested modules are:

- Product
- Category
- Order

---

## Main Features

- Modular Laravel architecture
- RESTful API
- Sanctum authentication
- Transactional order processing
- Stock and balance consistency
- Database row locking for concurrent writes
- Redis distributed caching
- Cache stampede protection
- Asynchronous queue processing
- Horizon queue monitoring
- Daily and weekly batch reports
- Nginx load balancing
- Capacity-control middleware
- k6 load and stress testing
- Module-level performance logging
- Bottleneck detection for database, application, and lock contention

---

## API Endpoints

Base URL:

```text
/api/v1
```

### Product Endpoints

```http
GET    /products
GET    /products/{id}
GET    /products/popular
GET    /categories/{categoryId}/products
POST   /products
PUT    /products/update/{id}
DELETE /products/{id}
PUT    /products/decrease-stock/{id}
```

### Category Endpoints

```http
GET    /categories
GET    /categories/{id}
POST   /categories
PUT    /categories/{id}
DELETE /categories/{id}
```

### Order Endpoints

```http
POST /orders
GET  /orders/my
GET  /orders/{id}
POST /orders/{id}/cancel
PUT  /orders/{id}/status
```

---

## Concurrent Load Testing

The project includes a modular k6 test suite.

```text
general-test.js
support.js

Modules/
├── Product/
│   └── products.js
├── Category/
│   └── categories.js
└── Order/
    └── orders.js
```

The test simulates up to 100 concurrent users and executes multiple Product, Category, and Order routes together.

### Traffic Distribution

- 50% Product operations
- 20% Category operations
- 30% Order operations

The Product scenario includes both read and write operations:

- Browse products
- Get a product by ID
- Get popular products
- Get products by category
- Create a product
- Update a product

The Order scenario includes:

- Get authenticated-user orders
- Get order details
- Create an order
- Cancel an order

### Load Stages

```javascript
stages: [
    { duration: '30s', target: 50 },
    { duration: '1m',  target: 50 },
    { duration: '30s', target: 100 },
    { duration: '2m',  target: 100 },
    { duration: '30s', target: 0 },
]
```

### Run the Test

Update the configuration inside `support.js`:

```javascript
export const BASE_URL = 'http://127.0.0.1:8000/api/v1';
export const TOKEN = 'PUT_YOUR_SANCTUM_TOKEN_HERE';

export const PRODUCT_IDS = [1, 2, 3, 4, 5];
export const CATEGORY_IDS = [1, 2, 3, 4];
export const ORDER_IDS = [1, 2, 3];
```

Then run:

```bash
k6 run general-test.js
```

> Never commit a real Sanctum token to the repository.

---

## Latest Local Load-Test Result

A local test reached 100 concurrent users and executed requests across all three modules.

| Metric | Result |
|---|---:|
| Total checks | 1790 |
| Successful checks | 100% |
| Failed checks | 0% |
| API error rate | 0.00% |
| Throughput | 6.53 requests/second |
| Average HTTP duration | 10.66 seconds |
| P95 | 17.84 seconds |
| P99 | 18.52 seconds |
| Maximum duration | 19.35 seconds |

All tested API requests returned valid responses, but the latency results show a clear performance bottleneck under high concurrency.

The similar response times across Product, Category, and Order modules suggest that the bottleneck may be related to shared infrastructure or server capacity rather than one isolated endpoint.

Possible areas for further tuning include:

- Apache worker configuration
- PHP process capacity
- Database connection and thread limits
- Database lock waits
- Redis and queue configuration
- Disk I/O
- Local XAMPP environment limitations

---

## Module-Level Performance Monitoring

The project uses a centralized middleware:

```text
app/Http/Middleware/ModulePerformanceLogger.php
```

The middleware measures each API request without modifying controllers or services.

It records:

- Endpoint
- Response time
- HTTP status
- Detected bottleneck

The middleware is registered globally for API routes in:

```text
bootstrap/app.php
```

Example:

```php
$middleware->appendToGroup('api', [
    \App\Http\Middleware\ModulePerformanceLogger::class,
]);
```

---

## Module Log Files

Dedicated logging channels are configured in:

```text
config/logging.php
```

Runtime log files:

```text
storage/logs/product.log
storage/logs/category.log
storage/logs/order.log
```

Example:

```json
{
  "endpoint": "GET /api/v1/orders/my",
  "time_ms": 648.64,
  "status": 200,
  "bottleneck": "database"
}
```

### Bottleneck Values

| Value | Meaning |
|---|---|
| `none` | No performance warning was detected |
| `database` | Database queries consumed most of the request time |
| `slow_application` | The request was slow outside the measured database time |
| `possible_lock_contention` | A slow write, deadlock, or lock wait may have occurred |

HTTP status and bottleneck classification are intentionally separate.

For example:

```json
{
  "endpoint": "GET /api/v1/products",
  "time_ms": 48.37,
  "status": 500,
  "bottleneck": "none"
}
```

This means the request failed functionally, but no performance bottleneck was detected by the middleware.

---

## Distributed Caching

Popular products are cached using Redis.

A distributed cache lock prevents multiple application instances from rebuilding the same cache entry at the same time.

This protects the endpoint from cache stampede during concurrent traffic.

```http
GET /api/v1/products/popular
```

---

## Queue Processing

Time-consuming tasks are moved outside the main HTTP request through Laravel queues.

Examples include:

- PDF invoice generation
- Email delivery
- Notifications
- Report generation

Laravel Horizon can be used to monitor queue throughput, runtime, retries, and failures.

```bash
php artisan horizon
```

---

## Batch Processing

The application includes chunk-based batch processing for sales reports.

Example:

```bash
php artisan sales:daily-process --date=2026-06-21 --force
```

The implementation processes records in bounded chunks to reduce memory consumption and avoid loading the full dataset into memory.

---

## Load Balancing

The application can run behind Nginx with multiple Laravel/Apache backend instances.

The load balancer distributes incoming requests between the available application servers while Redis provides a shared cache and queue backend.

Example architecture:

```text
Client
  |
  v
Nginx Load Balancer
  |
  +-- Laravel Server 1
  +-- Laravel Server 2
  +-- Laravel Server 3
          |
          v
     Shared Redis
          |
          v
       Database
```

---

## Database Capacity

For local XAMPP testing, database capacity can be configured in:

```text
C:\xampp\mysql\bin\my.ini
```

Example:

```ini
[mysqld]
max_connections=200
```

After changing the value, restart MySQL and verify:

```sql
SHOW GLOBAL VARIABLES LIKE 'max_connections';
SHOW GLOBAL STATUS LIKE 'Max_used_connections';
```

Increasing the connection limit should only be done after verifying that the existing limit is actually being reached.

---

## Installation

```bash
git clone <repository-url>
cd ecommerce

composer install
cp .env.example .env

php artisan key:generate
php artisan migrate --seed
php artisan optimize:clear
```

Start the application using the configured Apache, Nginx, or PHP environment.

For production-like load testing, avoid relying only on the built-in development server.

---

## Technology Stack

- PHP
- Laravel 12
- Laravel Modules
- MySQL / MariaDB
- Redis
- Laravel Horizon
- Nginx
- Apache
- k6
- Laravel Sanctum

---

## Repository Topics

Recommended GitHub topics:

```text
laravel
php
ecommerce-api
modular-architecture
redis
distributed-locking
queue-processing
load-balancing
k6
performance-monitoring
```

---

Queue yesterday's report:

<<<<<<< HEAD
This project was developed for academic and technical evaluation purposes.
=======
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
>>>>>>> origin/tf-p2-3
