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
        Schema::create('midtrans_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->string('invoice_number');
            $table->string('transaction_id')->nullable();
            $table->string('status');
            $table->string('payment_type');
            $table->decimal('gross_amount', 15, 2);
            $table->string('bank')->nullable();
            $table->string('va_number')->nullable();
            $table->json('payment_details')->nullable();
            $table->json('callback_response')->nullable();
            $table->timestamps();

            $table->foreign('invoice_number')->references('invoice_number')->on('invoices')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('midtrans_transactions');
    }
};