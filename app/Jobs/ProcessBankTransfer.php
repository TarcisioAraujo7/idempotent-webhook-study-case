<?php

namespace App\Jobs;

use App\Models\BankTransfer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBankTransfer implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        private readonly array $bankTransferData,
    ) {
    }

    public function handle(): void
    {
        BankTransfer::create($this->bankTransferData);
    }
}
