<?php

namespace App\Jobs;

use App\Models\DailyProductStat;
use App\Models\DailySalesReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Order\Enums\OrderStatus;
use Throwable;

class ProcessDailySalesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800;
    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public string $reportDate;
    public bool $force;

    public function __construct(?string $reportDate = null, bool $force = false)
    {
        $this->reportDate = Carbon::parse($reportDate ?? now()->subDay()->toDateString())
            ->toDateString();
        $this->force = $force;

        // Reports have a longer timeout/retry_after than normal notification jobs.
        $this->onConnection('redis-reports');
        $this->onQueue('reports');
    }

    public function uniqueId(): string
    {
        return "daily-sales-report:{$this->reportDate}";
    }

    public function uniqueVia(): CacheRepository
    {
        return Cache::driver('redis');
    }

    public function handle(): void
    {
        $startDate = Carbon::parse($this->reportDate)->startOfDay();
        $endDate = Carbon::parse($this->reportDate)->endOfDay();
        $chunkSize = max(1, (int) config('batch.daily_sales_chunk_size', 500));

        $existingReport = DailySalesReport::query()
            ->whereDate('report_date', $this->reportDate)
            ->first();

        if ($existingReport?->status === 'completed' && ! $this->force) {
            Log::info('Daily sales report already exists; job skipped.', [
                'report_date' => $this->reportDate,
                'report_id' => $existingReport->id,
            ]);

            return;
        }

        $oldPdfPath = $existingReport?->pdf_path;

        $report = DB::transaction(function () use ($startDate, $endDate, $chunkSize): DailySalesReport {
            $report = DailySalesReport::query()->updateOrCreate(
                ['report_date' => $this->reportDate],
                [
                    'period_start' => $startDate,
                    'period_end' => $endDate,
                    'status' => 'processing',
                    'chunk_size' => $chunkSize,
                    'processed_rows' => 0,
                    'pdf_path' => null,
                    'error_message' => null,
                    'started_at' => now(),
                    'processed_at' => null,
                ]
            );

            // A retry or --force rebuild starts from a clean, deterministic state.
            $report->productStats()->delete();

            return $report;
        });

        if ($oldPdfPath && Storage::disk('local')->exists($oldPdfPath)) {
            Storage::disk('local')->delete($oldPdfPath);
        }

        $newPdfPath = null;

        try {
            $orderSummary = DB::table('orders')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('status', OrderStatus::COMPLETED->value)
                ->selectRaw('COUNT(*) AS total_orders, COALESCE(SUM(total), 0) AS total_sales')
                ->first();

            $totalOrders = (int) ($orderSummary->total_orders ?? 0);
            $totalSales = round((float) ($orderSummary->total_sales ?? 0), 2);
            $averageOrderValue = $totalOrders > 0
                ? round($totalSales / $totalOrders, 2)
                : 0.0;

            $processedRows = 0;

            /*
             * Synchronization / memory boundary:
             * raw order_items are read by primary key in bounded chunks. Each
             * chunk is aggregated and persisted immediately, so the complete
             * dataset is never retained in PHP memory.
             */
            DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->join('products as p', 'p.id', '=', 'oi.product_id')
                ->whereBetween('o.created_at', [$startDate, $endDate])
                ->where('o.status', OrderStatus::COMPLETED->value)
                ->select([
                    'oi.id',
                    'oi.product_id',
                    'oi.quantity',
                    'oi.line_total',
                    'p.name as product_name',
                    'p.price as current_price',
                ])
                ->chunkById($chunkSize, function ($rows) use ($report, &$processedRows): void {
                    $chunkTotals = [];

                    foreach ($rows as $row) {
                        $productId = (int) $row->product_id;
                        $quantity = (int) $row->quantity;
                        $revenue = $row->line_total !== null
                            ? (float) $row->line_total
                            : $quantity * (float) $row->current_price;

                        if (! isset($chunkTotals[$productId])) {
                            $chunkTotals[$productId] = [
                                'product_name' => (string) $row->product_name,
                                'quantity_sold' => 0,
                                'total_revenue' => 0.0,
                            ];
                        }

                        $chunkTotals[$productId]['quantity_sold'] += $quantity;
                        $chunkTotals[$productId]['total_revenue'] += $revenue;
                        $processedRows++;
                    }

                    DB::transaction(function () use ($report, $chunkTotals): void {
                        foreach ($chunkTotals as $productId => $totals) {
                            $stat = DailyProductStat::query()->firstOrCreate(
                                [
                                    'daily_report_id' => $report->id,
                                    'product_id' => $productId,
                                ],
                                [
                                    'product_name' => $totals['product_name'],
                                    'quantity_sold' => 0,
                                    'total_revenue' => 0,
                                ]
                            );

                            $stat->product_name = $totals['product_name'];
                            $stat->quantity_sold += (int) $totals['quantity_sold'];
                            $stat->total_revenue = round(
                                (float) $stat->total_revenue + (float) $totals['total_revenue'],
                                2
                            );
                            $stat->save();
                        }
                    }, 3);
                }, 'oi.id', 'id');

            $this->assignRanks($report);

            $topProduct = $report->productStats()
                ->orderBy('rank')
                ->first();

            $bottomProduct = $report->productStats()
                ->orderByDesc('rank')
                ->first();

            $report->update([
                'total_orders' => $totalOrders,
                'total_sales' => $totalSales,
                'average_order_value' => $averageOrderValue,
                'top_product_name' => $topProduct?->product_name,
                'top_product_quantity' => $topProduct?->quantity_sold ?? 0,
                'bottom_product_name' => $bottomProduct?->product_name,
                'bottom_product_quantity' => $bottomProduct?->quantity_sold ?? 0,
                'processed_rows' => $processedRows,
            ]);

            // Product stats are committed before rendering, so the first PDF is complete.
            $newPdfPath = $this->generatePdf($report->fresh());

            $report->update([
                'pdf_path' => $newPdfPath,
                'status' => 'completed',
                'processed_at' => now(),
                'error_message' => null,
            ]);

            Log::info('Daily sales report completed.', [
                'report_date' => $this->reportDate,
                'report_id' => $report->id,
                'orders' => $totalOrders,
                'processed_rows' => $processedRows,
                'chunk_size' => $chunkSize,
            ]);
        } catch (Throwable $exception) {
            if ($newPdfPath && Storage::disk('local')->exists($newPdfPath)) {
                Storage::disk('local')->delete($newPdfPath);
            }

            $report->update([
                'status' => 'failed',
                'pdf_path' => null,
                'error_message' => Str::limit($exception->getMessage(), 2000),
                'processed_at' => now(),
            ]);

            Log::error('Daily sales report failed.', [
                'report_date' => $this->reportDate,
                'report_id' => $report->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function assignRanks(DailySalesReport $report): void
    {
        $rank = 1;

        $report->productStats()
            ->orderByDesc('quantity_sold')
            ->orderByDesc('total_revenue')
            ->orderBy('product_id')
            ->chunk(200, function ($stats) use (&$rank): void {
                foreach ($stats as $stat) {
                    $stat->update(['rank' => $rank++]);
                }
            });
    }

    private function generatePdf(DailySalesReport $report): string
    {
        $productLimit = max(1, (int) config('batch.daily_sales_pdf_product_limit', 100));
        $productCount = $report->productStats()->count();
        $productStats = $report->productStats()
            ->orderBy('rank')
            ->limit($productLimit)
            ->get();

        $pdf = Pdf::loadView('pdf.daily-report', [
            'report' => $report,
            'productStats' => $productStats,
            'productCount' => $productCount,
            'productLimit' => $productLimit,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ]);

        $filename = "daily_reports/sales_{$this->reportDate}.pdf";

        if (! Storage::disk('local')->put($filename, $pdf->output())) {
            throw new \RuntimeException("Could not store daily report PDF: {$filename}");
        }

        return $filename;
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('Daily sales job exhausted all retries.', [
            'report_date' => $this->reportDate,
            'error' => $exception->getMessage(),
        ]);
    }
}
