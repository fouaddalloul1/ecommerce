<?php

namespace Modules\Order\DTOs;

use Modules\Order\Models\Order;

class OrderMessage
{
    public Order $order;
    public $notifiable;

    public function __construct(Order $order, $notifiable = null)
    {
        $this->order = $order;
        $this->notifiable = $notifiable;
    }
}
