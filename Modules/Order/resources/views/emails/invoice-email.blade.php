<x-mail::message>
# Invoice for Order #{{ $msg->order->id }}

Hello {{ $msg->order->user->name ?? 'Customer' }},

Please find attached the PDF invoice for your order **#{{ $msg->order->id }}**.

**Order Total:** ${{ number_format((float) $msg->order->total, 2) }}

Thank you for shopping with us.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
