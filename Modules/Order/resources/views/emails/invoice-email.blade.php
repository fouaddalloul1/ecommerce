@component('mail::message')
# Invoice for Order #{{ $msg->order->id }}

Hello {{ $msg->order->user->name }},

Please find attached the invoice for your order #{{ $msg->order->id }}.

**Order Total:** ${{ number_format($msg->order->total, 2) }}

Thank you for shopping with us.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
