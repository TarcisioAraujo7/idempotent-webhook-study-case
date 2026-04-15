<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bank_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('payer_name');
            $table->string('payer_document', 20);
            $table->integer('amount_in_cents');
            $table->string('bank_code', 10);
            $table->string('branch_number', 20);
            $table->string('account_number', 20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_transfers');
    }
};
