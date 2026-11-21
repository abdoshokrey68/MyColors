<?php

namespace Modules\Order\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Modules\Order\Events\OrderPlaced;

class ProcessOrderPlaced implements ShouldQueue
{
    public function handle(OrderPlaced $event): void
    {
        Log::info('Order placed and queued for processing.', [
            'order_id' => $event->order->id,
            'user_id' => $event->order->user_id,
            'total' => $event->order->total,
        ]);
    }
}
