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

    public function storePhase1(Request $request): JsonResponse
    {
        $this->bankTransferService->process($request);

        return response()->json([
            'message' => 'Phase 1 webhook processed successfully.',
        ], 201);
    }

    public function storePhase2(Request $request): JsonResponse
    {
        $this->bankTransferService->process($request);

        return response()->json([
            'message' => 'Phase 2 webhook processed successfully.',
        ], 201);
    }

    public function storePhase3(Request $request): JsonResponse
    {
        $this->bankTransferService->process($request);

        return response()->json([
            'message' => 'Phase 3 webhook processed successfully.',
        ], 201);
    }
}
