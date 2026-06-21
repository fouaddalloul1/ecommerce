<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily Sales Report - {{ $report->report_date->format('Y-m-d') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #222; font-size: 12px; }
        h1, h2 { margin: 0 0 12px; }
        .header { text-align: center; margin-bottom: 24px; }
        .meta { color: #666; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 14px 0 24px; }
        th, td { border: 1px solid #d7d7d7; padding: 8px; text-align: center; }
        th { background: #f1f1f1; }
        .summary { background: #fafafa; padding: 10px; margin-bottom: 16px; }
        .footer { margin-top: 24px; color: #777; text-align: center; font-size: 10px; }
    </style>
</head>
<body>
<div class="header">
    <h1>Daily Sales Report</h1>
    <div class="meta">Date: {{ $report->report_date->format('Y-m-d') }}</div>
    <div class="meta">Generated: {{ $generatedAt }}</div>
</div>

<div class="summary">
    <h2>Execution Summary</h2>
    <table>
        <tr>
            <th>Total Orders</th>
            <th>Total Sales</th>
            <th>Average Order Value</th>
            <th>Processed Rows</th>
            <th>Chunk Size</th>
        </tr>
        <tr>
            <td>{{ number_format($report->total_orders) }}</td>
            <td>${{ number_format((float) $report->total_sales, 2) }}</td>
            <td>${{ number_format((float) $report->average_order_value, 2) }}</td>
            <td>{{ number_format($report->processed_rows) }}</td>
            <td>{{ number_format($report->chunk_size) }}</td>
        </tr>
    </table>
</div>

<h2>Top and Bottom Products</h2>
<table>
    <tr>
        <th>Category</th>
        <th>Product</th>
        <th>Quantity Sold</th>
    </tr>
    <tr>
        <td>Top</td>
        <td>{{ $report->top_product_name ?? 'No sales' }}</td>
        <td>{{ number_format($report->top_product_quantity) }}</td>
    </tr>
    <tr>
        <td>Bottom</td>
        <td>{{ $report->bottom_product_name ?? 'No sales' }}</td>
        <td>{{ number_format($report->bottom_product_quantity) }}</td>
    </tr>
</table>

<h2>Ranked Product Sales</h2>
<table>
    <thead>
    <tr>
        <th>Rank</th>
        <th>Product</th>
        <th>Quantity Sold</th>
        <th>Revenue</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($productStats as $stat)
        <tr>
            <td>#{{ $stat->rank }}</td>
            <td>{{ $stat->product_name }}</td>
            <td>{{ number_format($stat->quantity_sold) }}</td>
            <td>${{ number_format((float) $stat->total_revenue, 2) }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="4">No completed-order sales were found for this day.</td>
        </tr>
    @endforelse
    </tbody>
</table>

@if ($productCount > $productLimit)
    <p>PDF displays the top {{ $productLimit }} of {{ $productCount }} products. Full statistics remain stored in the database.</p>
@endif

<div class="footer">
    Generated asynchronously by the daily batch-processing queue.
</div>
</body>
</html>
