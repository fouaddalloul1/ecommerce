<?php

namespace App\Console\Commands;

use App\Models\WeeklyProductStat;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use App\Models\WeeklySalesReport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class ProcessWeeklySalesCommand extends Command
{
    protected $signature = 'sales:weekly-process {--force : Force process even if already exists}';
    protected $description = 'Process weekly sales report and generate PDF';

    public function handle(): int
    {
        // $startDate = Carbon::now()->startOfWeek()->subDay();
        // $endDate = $startDate->copy()->subDays(6);

        // واستبدلها بـ (للتجربة فقط):
        $startDate = Carbon::now()->subDays(30);  // آخر 30 يوم
        $endDate = Carbon::now();                 // حتى اليوم
        $year = $startDate->year;
        $weekNumber = $startDate->weekOfYear;

        $this->info("📊 Starting weekly report for week {$weekNumber}, {$year}");
        Log::info('Weekly sales report started', ['week' => $weekNumber]);

        // التحقق من Idempotency
        $existingReport = WeeklySalesReport::where('year', $year)
            ->where('week_number', $weekNumber)
            ->first();

        if ($existingReport && $existingReport->status === 'completed' && !$this->option('force')) {
            $this->warn("⏭️ Report already exists for week {$weekNumber}");
            return 0;
        }

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

            // حساب المبيعات
            $ordersQuery = Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('status', 'completed');

            $totalOrders = $ordersQuery->count();
            $totalSales = $ordersQuery->sum('total');
            $averageOrderValue = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

            // أفضل منتج
            $topProduct = OrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', 'completed');
            })
                ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
                ->with('product:id,name')
                ->groupBy('product_id')
                ->orderByDesc('total_quantity')
                ->first();

            // أسوأ منتج
            $bottomProduct = OrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', 'completed');
            })
                ->select('product_id', DB::raw('SUM(quantity) as total_quantity'))
                ->with('product:id,name')
                ->groupBy('product_id')
                ->orderBy('total_quantity')
                ->first();
            $report->update([
                'total_orders' => $totalOrders,
                'total_sales' => $totalSales,
                'average_order_value' => $averageOrderValue,
                'top_product_name' => $topProduct?->product?->name ?? 'nothing',
                'top_product_quantity' => $topProduct?->total_quantity ?? 0,
                'bottom_product_name' => $bottomProduct?->product?->name ?? 'nothing',
                'bottom_product_quantity' => $bottomProduct?->total_quantity ?? 0,
            ]);

            // إنشاء PDF
            $pdfPath = $this->generatePdf($report);
            $report->update([
                'pdf_path' => $pdfPath,
                'status' => 'completed',
                'processed_at' => now()
            ]);


            //#new

            // بعد حساب topProduct و bottomProduct، أضف هذا:

            // 5. حساب إحصاءات كل المنتجات (Chunking عشان البيانات الكبيرة)
            $productStats = [];
            OrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', 'completed');
            })
                ->select('product_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(quantity * (SELECT price FROM products WHERE id = order_items.product_id)) as total_revenue'))
                ->with('product:id,name,price')
                ->groupBy('product_id')
                ->orderByDesc('total_quantity')
                ->chunk(100, function ($items) use (&$productStats) {
                    foreach ($items as $item) {
                        $productStats[] = [
                            'product_id' => $item->product_id,
                            'product_name' => $item->product->name ?? 'Unknown',
                            'quantity_sold' => $item->total_quantity,
                            'total_revenue' => $item->total_revenue ?? 0,
                        ];
                    }
                });

            // حذف الإحصائيات القديمة لهذا التقرير
            WeeklyProductStat::where('weekly_report_id', $report->id)->delete();

            // إضافة الإحصائيات الجديدة مع الترتيب (rank)
            foreach ($productStats as $index => $stat) {
                WeeklyProductStat::create([
                    'weekly_report_id' => $report->id,
                    'product_id' => $stat['product_id'],
                    'product_name' => $stat['product_name'],
                    'quantity_sold' => $stat['quantity_sold'],
                    'total_revenue' => $stat['total_revenue'],
                    'rank' => $index + 1, // الأول rank=1، الثاني rank=2، إلخ
                ]);
            }

            $this->info("📦 Product stats saved: " . count($productStats) . " products");




            DB::commit();

            $this->info("✅ Report completed: {$totalOrders} orders, {$totalSales} sales");
            Log::info('Weekly sales report completed', ['week' => $weekNumber, 'orders' => $totalOrders]);

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $report->update(['status' => 'failed']);
            $this->error("❌ Failed: " . $e->getMessage());
            Log::error('Weekly sales report failed', ['week' => $weekNumber, 'error' => $e->getMessage()]);
            return 1;
        }
    }

    private function generatePdf($report): string
    {
        $pdf = Pdf::loadView('pdf.weekly-report', [
            'report' => $report,
            'generated_at' => now()->format('Y-m-d H:i:s')
        ]);

        $filename = "weekly_reports/week_{$report->year}_{$report->week_number}.pdf";
        Storage::put($filename, $pdf->output());

        return $filename;
    }
}
