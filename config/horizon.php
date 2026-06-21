<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    |
    | الاسم الذي سيظهر في لوحة Horizon.
    |
    */

    'name' => env('HORIZON_NAME', 'Horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | يمكن تحديد Domain مستقل للوحة Horizon.
    | عند تركه null ستعمل اللوحة على Domain التطبيق نفسه.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | المسار الذي تفتح منه لوحة Horizon.
    | مثال: http://127.0.0.1:8000/horizon
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | هذا الاتصال يستخدمه Horizon لحفظ معلوماته الداخلية:
    | supervisors, metrics, failed jobs, monitoring...
    |
    | هذا لا يعني أن Horizon سيعالج default queue فقط.
    | اتصال كل Queue يحدد داخل كل Supervisor.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix يمنع تعارض بيانات Horizon إذا كانت عدة تطبيقات
    | تستخدم Redis Server نفسه.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware التي تحمي لوحة Horizon.
    | في بيئة Production يفضّل إضافة Authorization داخل
    | HorizonServiceProvider لمنع وصول أي مستخدم إلى اللوحة.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | عدد الثواني التي بعد تجاوزها يعتبر Horizon أن Job
    | انتظرت طويلًا داخل الطابور.
    |
    | الصيغة:
    | queue-connection:queue-name
    |
    */

    'waits' => [
        'redis:invoices' => 60,
        'redis:notifications' => 30,
        'redis:default' => 60,

        // Daily Batch Processing queue.
        'redis-reports:reports' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | القيم بالدقائق، وتحدد مدة الاحتفاظ بتاريخ الـJobs
    | داخل لوحة Horizon.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,

        // Keep failed jobs for seven days.
        'recent_failed' => 10080,
        'failed' => 10080,

        // Keep monitored jobs for seven days.
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | يمكن وضع أسماء Jobs لا نريد ظهورها ضمن قائمة Completed Jobs.
    |
    */

    'silenced' => [],

    /*
    |--------------------------------------------------------------------------
    | Silenced Tags
    |--------------------------------------------------------------------------
    */

    'silenced_tags' => [],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | عدد Metrics Snapshots التي يحتفظ بها Horizon.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | عند false ينتظر Horizon العمليات القديمة لتنتهي عند إعادة التشغيل.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Horizon Master Memory Limit
    |--------------------------------------------------------------------------
    |
    | الحد الخاص بعملية Horizon الرئيسية وليس بكل Worker.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Default Supervisor Configuration
    |--------------------------------------------------------------------------
    |
    | Template افتراضي. كل Supervisor داخل environments
    | يحتوي أيضًا على إعداداته الكاملة والمستقلة.
    |
    */

    'defaults' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default'],

            'balance' => 'simple',
            'processes' => 1,

            'maxTime' => 0,
            'maxJobs' => 0,

            'memory' => 128,
            'tries' => 3,
            'timeout' => 180,
            'nice' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment-Specific Supervisors
    |--------------------------------------------------------------------------
    |
    | لكل نوع من المهام Supervisor مستقلة:
    |
    | 1. invoices:
    |    توليد ملفات PDF.
    |
    | 2. notifications:
    |    إرسال الفواتير والإشعارات.
    |
    | 3. default:
    |    المهام العامة.
    |
    | 4. reports:
    |    جرد المبيعات اليومية ومعالجة البيانات على دفعات.
    |
    */

    'environments' => [

        /*
        |--------------------------------------------------------------------------
        | Production Environment
        |--------------------------------------------------------------------------
        */

        'production' => [

            /*
             * PDF invoice generation.
             *
             * GeneratePdfJob timeout: 180 seconds.
             * Horizon timeout:        240 seconds.
             * Redis retry_after:      300 seconds.
             *
             * Safe order:
             * 180 < 240 < 300
             */
            'supervisor-invoices' => [
                'connection' => 'redis',
                'queue' => ['invoices'],

                'balance' => 'simple',
                'processes' => 3,

                'maxTime' => 0,
                'maxJobs' => 0,

                'memory' => 256,
                'tries' => 3,
                'timeout' => 240,
                'nice' => 0,
            ],

            /*
             * Invoice email and order notification jobs.
             *
             * Longest Job timeout: 120 seconds.
             * Horizon timeout:     180 seconds.
             * Redis retry_after:   300 seconds.
             *
             * Safe order:
             * 120 < 180 < 300
             */
            'supervisor-notifications' => [
                'connection' => 'redis',
                'queue' => ['notifications'],

                'balance' => 'simple',
                'processes' => 5,

                'maxTime' => 0,
                'maxJobs' => 0,

                'memory' => 128,
                'tries' => 5,
                'timeout' => 180,
                'nice' => 0,
            ],

            /*
             * General background jobs.
             */
            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default'],

                'balance' => 'simple',
                'processes' => 1,

                'maxTime' => 0,
                'maxJobs' => 0,

                'memory' => 128,
                'tries' => 3,
                'timeout' => 180,
                'nice' => 0,
            ],

            /*
             * Daily Sales Batch Processing.
             *
             * ProcessDailySalesJob timeout: 1800 seconds.
             * Horizon timeout:              1850 seconds.
             * redis-reports retry_after:    1900 seconds.
             *
             * Safe order:
             * 1800 < 1850 < 1900
             *
             * Worker واحدة فقط لأن التقرير مهمة ثقيلة
             * على CPU وRAM وقاعدة البيانات.
             */
            'supervisor-reports' => [
                'connection' => 'redis-reports',
                'queue' => ['reports'],

                'balance' => 'simple',
                'processes' => 1,

                'maxTime' => 0,
                'maxJobs' => 0,

                'memory' => 512,
                'tries' => 3,
                'timeout' => 1850,
                'nice' => 0,
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Local Environment
        |--------------------------------------------------------------------------
        |
        | إعدادات جهاز التطوير والتجارب.
        |
        | يمكن تعديل processes للفواتير والإشعارات إلى 1 أو 3 أو 5
        | عند إجراء Benchmark لقياس أثر عدد الـWorkers.
        |
        */

        'local' => [

            'supervisor-invoices' => [
                'connection' => 'redis',
                'queue' => ['invoices'],

                'balance' => 'simple',
                'processes' => 2,

                'maxTime' => 0,
                'maxJobs' => 0,

                'memory' => 256,
                'tries' => 3,
                'timeout' => 240,
                'nice' => 0,
            ],

            'supervisor-notifications' => [
                'connection' => 'redis',
                'queue' => ['notifications'],

                'balance' => 'simple',
                'processes' => 3,

                'maxTime' => 0,
                'maxJobs' => 0,

                'memory' => 128,
                'tries' => 5,
                'timeout' => 180,
                'nice' => 0,
            ],

            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default'],

                'balance' => 'simple',
                'processes' => 1,

                'maxTime' => 0,
                'maxJobs' => 0,

                'memory' => 128,
                'tries' => 3,
                'timeout' => 180,
                'nice' => 0,
            ],

            'supervisor-reports' => [
                'connection' => 'redis-reports',
                'queue' => ['reports'],

                'balance' => 'simple',
                'processes' => 1,

                'maxTime' => 0,
                'maxJobs' => 0,

                'memory' => 512,
                'tries' => 3,
                'timeout' => 1850,
                'nice' => 0,
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Fallback Environment
        |--------------------------------------------------------------------------
        |
        | يستخدم إذا كانت APP_ENV ليست local أو production.
        |
        */

        '*' => [

            'supervisor-invoices' => [
                'connection' => 'redis',
                'queue' => ['invoices'],

                'balance' => 'simple',
                'processes' => 1,

                'maxTime' => 0,
                'maxJobs' => 0,

                'memory' => 256,
                'tries' => 3,
                'timeout' => 240,
                'nice' => 0,
            ],

            'supervisor-notifications' => [
                'connection' => 'redis',
                'queue' => ['notifications'],

                'balance' => 'simple',
                'processes' => 1,

                'maxTime' => 0,
                'maxJobs' => 0,

                'memory' => 128,
                'tries' => 5,
                'timeout' => 180,
                'nice' => 0,
            ],

            'supervisor-default' => [
                'connection' => 'redis',
                'queue' => ['default'],

                'balance' => 'simple',
                'processes' => 1,

                'maxTime' => 0,
                'maxJobs' => 0,

                'memory' => 128,
                'tries' => 3,
                'timeout' => 180,
                'nice' => 0,
            ],

            'supervisor-reports' => [
                'connection' => 'redis-reports',
                'queue' => ['reports'],

                'balance' => 'simple',
                'processes' => 1,

                'maxTime' => 0,
                'maxJobs' => 0,

                'memory' => 512,
                'tries' => 3,
                'timeout' => 1850,
                'nice' => 0,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watcher Configuration
    |--------------------------------------------------------------------------
    |
    | الملفات التي تتم مراقبتها عند استخدام وضع المراقبة
    | المتوفر في نسخة Horizon المثبتة لديكم.
    |
    */

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'Modules/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],

];
