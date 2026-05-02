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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_method')->default('manual')->after('input_by');
            $table->string('payment_status')->default('unpaid')->after('payment_method');
            $table->string('midtrans_order_id')->nullable()->after('payment_status');
            $table->string('midtrans_transaction_id')->nullable()->after('midtrans_order_id');
            $table->string('midtrans_payment_type')->nullable()->after('midtrans_transaction_id');
            $table->string('midtrans_va_number')->nullable()->after('midtrans_payment_type');
            $table->text('midtrans_callback_response')->nullable()->after('midtrans_va_number');
            $table->timestamp('midtrans_paid_at')->nullable()->after('midtrans_callback_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_status',
                'midtrans_order_id',
                'midtrans_transaction_id',
                'midtrans_payment_type',
                'midtrans_va_number',
                'midtrans_callback_response',
                'midtrans_paid_at'
            ]);
        });
    }
};