<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WeeklySalesReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf; // تأكد من تثبيت الحزمة: composer require barryvdh/laravel-dompdf

class ProcessWeeklySalesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;           // يحاول 3 مرات
    public $backoff = [60, 300]; // ينتظر 1 دقيقة، ثم 5 دقائق

    public function __construct()
    {
        $this->onQueue('reports'); // Queue مخصص للتقارير
    }

    public function handle(): void
    {
        // تحديد تاريخ الأسبوع الماضي (من الأحد إلى السبت)
        $endDate = Carbon::now()->startOfWeek()->subDay(); // السبت الماضي
        $startDate = $endDate->copy()->subDays(6); // الأحد الذي قبله

        $year = $startDate->year;
        $weekNumber = $startDate->weekOfYear;

        Log::info('📊 Starting weekly sales report', [
            'week' => $weekNumber,
            'from' => $startDate->toDateString(),
            'to' => $endDate->toDateString()
        ]);

        // التحقق من Idempotency: هل التقرير موجود مسبقاً؟
        $existingReport = WeeklySalesReport::where('year', $year)
            ->where('week_number', $weekNumber)
            ->first();

        if ($existingReport && $existingReport->status === 'completed') {
            Log::info('⏭️ Weekly report already exists, skipping', ['week' => $weekNumber]);
            return;
        }

        // إنشاء أو تحديث التقرير (Idempotency)
        $report = WeeklySalesReport::updateOrCreate(
            ['year' => $year, 'week_number' => $weekNumber],
            [
                'week_start_date' => $startDate,
                'week_end_date' => $endDate,
                'status' => 'processing'
            ]
        );

        try {
            DB::beginTransaction();

            // 1. حساب إجماليات المبيعات (باستخدام chunking للبيانات الكبيرة)
            $ordersQuery = Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed');

            $totalOrders = $ordersQuery->count();
            $totalSales = $ordersQuery->sum('total');
            $averageOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

            // 2. حساب أفضل وأسوأ المنتجات مبيعاً
            $topProduct = OrderItem::whereHas('order', function($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                      ->where('status', 'completed');
                })
                ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
                ->with('product:id,name')
                ->groupBy('product_id')
                ->orderByDesc('total_quantity')
                ->first();

            $bottomProduct = OrderItem::whereHas('order', function($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate])
                      ->where('status', 'completed');
                })
                ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
                ->with('product:id,name')
                ->groupBy('product_id')
                ->orderBy('total_quantity')
                ->first();

            // 3. تحديث التقرير
            $report->update([
                'total_orders' => $totalOrders,
                'total_sales' => $totalSales,
                'average_order_value' => $averageOrderValue,
                'top_product_name' => $topProduct?->product?->name ?? 'nothing',
                'top_product_quantity' => $topProduct?->total_quantity ?? 0,
                'bottom_product_name' => $bottomProduct?->product?->name ?? 'nothing',
                'bottom_product_quantity' => $bottomProduct?->total_quantity ?? 0,
            ]);

            // 4. إنشاء PDF للتقرير
            $pdfPath = $this->generatePdfReport($report);
            $report->update(['pdf_path' => $pdfPath]);

            DB::commit();

            // 5. تحديث الحالة نهائياً
            $report->update([
                'status' => 'completed',
                'processed_at' => now()
            ]);

            Log::info('✅ Weekly sales report completed', [
                'week' => $weekNumber,
                'orders' => $totalOrders,
                'sales' => $totalSales
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $report->update(['status' => 'failed']);
            Log::error('❌ Weekly sales report failed', [
                'week' => $weekNumber,
                'error' => $e->getMessage()
            ]);
            throw $e; // يعيد المحاولة حسب $tries
        }
    }

    /**
     * إنشاء ملف PDF للتقرير
     */
    private function generatePdfReport(WeeklySalesReport $report): string
    {
        $data = [
            'report' => $report,
            'generated_at' => now()->format('Y-m-d H:i:s')
        ];

        $pdf = Pdf::loadView('pdf.weekly-report', $data);

        $filename = "weekly_reports/week_{$report->year}_{$report->week_number}.pdf";
        Storage::put($filename, $pdf->output());

        return $filename;
    }
}
