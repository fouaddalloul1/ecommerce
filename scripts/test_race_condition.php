<?php
// scripts/test_race_condition.php
// 🔬 اختبار البيع المزدوج (Race Condition) مع lockForUpdate()
// يتطلب تثبيت PHP مع pcntl (غالباً متوفر في Linux)

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

// 🔧 ثوابت الاختبار
const PRODUCT_ID = 1;        // تأكد من وجود هذا المنتج
const USER_1_ID = 1;
const USER_2_ID = 2;

// 1. جلب المنتج وعرض حالته الأولية
$product = Product::find(PRODUCT_ID);
if (!$product) {
    die("❌ المنتج غير موجود. تأكد من PRODUCT_ID.\n");
}

// اضبط المخزون على 1 لخلق حالة تنافس
$product->stock = 1;
$product->save();

echo "🧪 بدء اختبار البيع المزدوج (Race Condition)\n";
echo "===============================================\n";
echo "📦 المنتج: {$product->name}\n";
echo "📊 المخزون الابتدائي: {$product->stock}\n\n";
echo "🔄 محاكاة مستخدمين اثنين يضغطان على 'شراء' في نفس الوقت...\n\n";

// 2. تعريف دالة الشراء (سيتم تنفيذها في العمليات المنفصلة)
function purchase($userId, $productId) {
    try {
        $result = DB::transaction(function () use ($userId, $productId) {
            // 🔒 القفل يمنع أي عملية أخرى من التعديل حتى تنتهي هذه المعاملة
            $product = Product::where('id', $productId)->lockForUpdate()->first();
            
            if ($product->stock < 1) {
                return "❌ المستخدم {$userId}: المخزون نفد! (فشل)";
            }
            
            // محاكاة خصم المخزون وإنشاء الطلب (عملية تستغرق وقتاً)
            $product->decrement('stock', 1);
            
            // محاكاة إنشاء الطلب
            // DB::table('orders')->insert([...]);
            
            return "✅ المستخدم {$userId}: تم الشراء بنجاح!";
        }, 5);
        echo "  " . $result . "\n";
    } catch (\Exception $e) {
        echo "  ❌ المستخدم {$userId}: فشل - " . $e->getMessage() . "\n";
    }
}

// 3. إنشاء عمليتين منفصلتين (fork)
$pid1 = pcntl_fork();
if ($pid1 == 0) {
    // العملية الأولى (المستخدم 1)
    purchase(USER_1_ID, PRODUCT_ID);
    exit(0);
}

$pid2 = pcntl_fork();
if ($pid2 == 0) {
    // العملية الثانية (المستخدم 2) - تأخير بسيط لضمان التداخل
    usleep(100000); // 0.1 ثانية
    purchase(USER_2_ID, PRODUCT_ID);
    exit(0);
}

// 4. انتظار انتهاء العمليات الفرعية
pcntl_wait($status);
pcntl_wait($status);

// 5. عرض النتيجة النهائية
$finalProduct = Product::find(PRODUCT_ID);
echo "\n📊 المخزون النهائي: " . $finalProduct->stock . "\n";

if ($finalProduct->stock == 0) {
    echo "✅ نجح الاختبار: تم شراء قطعة واحدة فقط. لا يوجد بيع مزدوج!\n";
} elseif ($finalProduct->stock < 0) {
    echo "❌ فشل الاختبار: المخزون سالب ({$finalProduct->stock}). حدث بيع مزدوج!\n";
} else {
    echo "⚠️ المخزون بقي {$finalProduct->stock} (لم يحدث أي شراء). تحقق من البيانات.\n";
}
