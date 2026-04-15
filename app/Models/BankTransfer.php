<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransfer extends Model
{
    protected $fillable = [
        'payer_name',
        'payer_document',
        'amount_in_cents',
        'bank_code',
        'branch_number',
        'account_number',
    ];
}
