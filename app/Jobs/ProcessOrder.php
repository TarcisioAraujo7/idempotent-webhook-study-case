<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class ProcessOrder implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        private readonly array $orderData,
    ) {
    }

    public function handle(): void
    {
        Order::create($this->orderData);
    }
}
