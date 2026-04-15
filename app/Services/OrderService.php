<?php

namespace App\Services;

use App\Jobs\ProcessOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderService
{
    public function process(Request $request): void
    {
        $validatedOrder = Validator::make($request->all(), [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'total_amount' => ['required', 'integer', 'min:0'],
            'product_code' => ['required', 'string', 'max:10'],
        ])->validate();

        ProcessOrder::dispatch($validatedOrder);
    }
}
