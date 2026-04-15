<?php

namespace App\Http\Controllers;

use App\Services\BankTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        private readonly BankTransferService $bankTransferService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $this->bankTransferService->process($request);

        return response()->json([
            'message' => 'Bank transfer queued successfully.',
        ], 202);
    }
}
