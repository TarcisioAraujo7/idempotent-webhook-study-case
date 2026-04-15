<?php

namespace App\Services;

use App\Jobs\ProcessBankTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankTransferService
{
    public function process(Request $request): void
    {
        $validatedBankTransfer = Validator::make($request->all(), [
            'payer_name' => ['required', 'string', 'max:255'],
            'payer_document' => ['required', 'string', 'max:20'],
            'amount_in_cents' => ['required', 'integer', 'min:0'],
            'bank_code' => ['required', 'string', 'max:10'],
            'branch_number' => ['required', 'string', 'max:20'],
            'account_number' => ['required', 'string', 'max:20'],
        ])->validate();

        ProcessBankTransfer::dispatch($validatedBankTransfer);
    }
}
