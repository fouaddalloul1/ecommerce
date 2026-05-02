<!-- resources/views/vendor/order/emails/invoice.blade.php -->
<h1>Invoice for Order #{{ $order->id }}</h1>
<p>Dear {{ $order->user->first_name }} {{ $order->user->last_name }},</p>
<p>Thank you for your order. Total: {{ $order->total }}</p>
<!-- list items, totals, link to PDF if available -->
