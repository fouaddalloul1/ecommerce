# دليل المقابلة: Batch Processing وAsynchronous Queues

## 1. مسار Batch Processing اليومي

1. يشغّل Laravel Scheduler المهمة يوميًا الساعة 02:00.
2. ينشئ `ProcessDailySalesJob` لتقرير اليوم السابق.
3. تدخل المهمة إلى Redis ضمن الاتصال `redis-reports` والطابور `reports`.
4. يمنع `ShouldBeUnique` إضافة مهمتين للتاريخ نفسه.
5. يُنشأ سجل في `daily_sales_reports` بالحالة `processing`.
6. تُقرأ أسطر `order_items` الخام باستخدام `chunkById` بدل تحميلها كلها في الذاكرة.
7. كل دفعة تُجمع مؤقتًا داخل الذاكرة ثم تُحفظ فورًا في `daily_product_stats`.
8. بعد انتهاء الدفعات، يُحسب ترتيب المنتجات وأعلى وأقل منتج.
9. يُنشأ PDF بعد حفظ الإحصاءات، لذلك يظهر الجدول صحيحًا من أول تشغيل.
10. تتحول حالة التقرير إلى `completed`. عند الفشل تصبح `failed` ويعيد Worker المحاولة.

### لماذا chunkById؟

- يحد استهلاك RAM.
- لا يعتمد على OFFSET الذي يصبح أبطأ مع الجداول الكبيرة.
- يظل مستقرًا إذا تغيّرت صفوف أثناء التنفيذ لأنه يتقدم وفق المفتاح الأساسي.
- نعالج السجلات الخام، وليس فقط نتائج تجميع نهائية.

### لماذا لا توجد Transaction واحدة حول التقرير كاملًا؟

المهمة قد تكون طويلة. إبقاء Transaction مفتوحة طوال المعالجة قد يحتفظ بالأقفال ويزيد الضغط على قاعدة البيانات. لذلك نستخدم معاملات قصيرة عند تنظيف التقرير وعند حفظ كل دفعة، ونستخدم حقل `status` لتحديد الحالة الكلية. إعادة المحاولة تبدأ بحذف النتائج الجزئية وإعادة البناء، لذلك التنفيذ Idempotent.

### الحماية من التكرار

- `report_date` عليه Unique Constraint.
- `ShouldBeUnique` يمنع مهمتين للتاريخ نفسه في Redis.
- `onOneServer()` يمنع جميع خوادم التطبيق من جدولة التقرير ذاته.
- `withoutOverlapping()` يمنع تداخل تشغيل Scheduler.
- عند `--force` تُحذف النتائج القديمة ويُعاد بناء التقرير من الصفر.

## 2. مسار Asynchronous Queues

عند إنشاء الطلب، تنفذ العمليات الحرجة داخل Transaction واحدة:

- قفل المنتجات والمستخدم.
- فحص المخزون والرصيد.
- إنشاء الطلب وعناصره.
- خصم الرصيد والمخزون.

بعدها تُسجل المهمتان:

```php
GeneratePdfJob::dispatch($order->id)->afterCommit();
SendNotificationJob::dispatch($order->id)->afterCommit();
```

`afterCommit()` يعني أن Redis لا يستقبل المهمات إلا بعد نجاح المعاملة. إذا حدث Rollback، لا تُرسل فاتورة أو إشعار لطلب غير موجود.

### تسلسل الفاتورة

```text
GeneratePdfJob
    -> إنشاء PDF وتخزينه
    -> تحديث invoice_generated_at
    -> إرسال SendEmailWithPdfJob
    -> إرسال البريد مع المرفق
    -> تحديث invoice_sent_at
```

### لماذا طابوران؟

- `invoices`: توليد PDF أثقل ويحتاج وقتًا أطول.
- `notifications`: البريد والإشعارات أخف.

هذا يمنع مهمة PDF بطيئة من حجز جميع الإشعارات خلفها، ويسمح بتغيير عدد Workers لكل نوع بصورة مستقلة.

### Retry وBackoff

عند فشل SMTP أو التخزين، ترمي المهمة Exception. يعيد Worker المحاولة وفق `tries` و`backoff` بدل فقدان المهمة مباشرة. بعد استنفاد المحاولات تُسجل في `failed_jobs` ويعمل `failed()` على كتابة Log واضح.

### Idempotency وعدم التداخل

- إذا كان PDF موجودًا لا يعاد توليده.
- إذا كان البريد مرسلًا (`invoice_sent_at`) لا يعاد إرساله في التشغيل الطبيعي.
- إذا كان إشعار الطلب مرسلًا (`notification_sent_at`) يتم تجاوز المهمة.
- `WithoutOverlapping` يمنع مهمتين للطلب نفسه من التنفيذ في اللحظة نفسها.

> نظام Queues يضمن عادةً التسليم مرة واحدة على الأقل، وليس ضمانًا مطلقًا للإرسال مرة واحدة عند التعامل مع خدمة بريد خارجية. توجد نافذة نادرة جدًا إذا نجح SMTP ثم توقف Process قبل تحديث `invoice_sent_at`. الحل الإنتاجي الأقوى هو Outbox أو مفتاح Idempotency يدعمه مزود البريد.

## 3. أوامر العرض في المقابلة

```bash
php artisan migrate
php artisan optimize:clear
```

شغّل Workers:

```bash
php artisan queue:work redis --queue=invoices,notifications,default --sleep=1 --tries=3 --timeout=240
php artisan queue:work redis-reports --queue=reports --sleep=1 --tries=3 --timeout=1800
```

اختبار التقرير مباشرة:

```bash
php artisan sales:daily-process --date=2026-06-20 --force --sync
```

اختباره بصورة غير متزامنة:

```bash
php artisan sales:daily-process --date=2026-06-20 --force
```

ثم راقب Worker وحقول:

- `daily_sales_reports.status`
- `daily_sales_reports.processed_rows`
- `daily_sales_reports.chunk_size`
- `orders.invoice_generated_at`
- `orders.invoice_sent_at`
- `orders.notification_sent_at`

## 4. أجوبة قصيرة جاهزة

### ما الفرق بين Batch Processing وQueue؟

الـQueue تحدد أن المهمة تعمل خارج Request وبواسطة Worker. أما Batch Processing فيحدد أن البيانات الكبيرة تُقرأ وتُعالج على مجموعات صغيرة. تقريرنا يستخدم الاثنين معًا: Job خلفية، وداخلها `chunkById`.

### لماذا اخترنا 500 سجل؟

قيمة أولية قابلة للضبط من `.env`. الدفعة الصغيرة جدًا تزيد عدد استعلامات قاعدة البيانات، والكبيرة جدًا ترفع استهلاك الذاكرة. نحدد القيمة النهائية بالمقارنة الرقمية.

### ماذا يحدث لو تعطل Worker؟

تبقى المهمة في Redis أو تعاد بعد `retry_after`. وإذا رمت Exception يعيد Laravel المحاولة حسب `tries` و`backoff`. بعد استنفاد المحاولات تنتقل إلى `failed_jobs`.

### لماذا retry_after أكبر من timeout؟

حتى لا يعتبر Redis المهمة مفقودة ويعطيها إلى Worker ثانٍ بينما Worker الأول ما زال ينفذها. تقريرنا timeout يساوي 1800 ثانية وretry_after يساوي 1900 ثانية.

### لماذا afterCommit مهم؟

لأن Worker سريع قد يبدأ قبل تثبيت الطلب في قاعدة البيانات. `afterCommit` يضمن أن المهمة لا تظهر في الطابور إلا بعد نجاح Transaction، وتُلغى إذا حدث Rollback.
