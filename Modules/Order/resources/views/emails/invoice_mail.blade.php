<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $order->id }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6;">
    <h2>Hello {{ $order->user->first_name }},</h2>

    <p>Thank you for your order!</p>

    <p>
        Your invoice for <strong>Order #{{ $order->id }}</strong> has been generated.
        You will find the PDF invoice attached to this email.
    </p>

    <p>
        <strong>Total:</strong> {{ $order->total }}<br>
        <strong>Date:</strong> {{ $order->created_at->format('Y-m-d H:i') }}
    </p>

    <p>If you have any questions, feel free to reply to this email.</p>

    <br>
    <p>Best regards,<br>AI Shop System</p>
</body>
</html>
