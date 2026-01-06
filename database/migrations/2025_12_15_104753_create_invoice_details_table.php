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
        Schema::create('invoice_details', function (Blueprint $table) {
            $table->id();

            $table->string('no_invoice', 150);
            $table->text('id_izin');
            $table->string('metode_pembayaran', 255);
            $table->string('tahap_invoice', 150);
            $table->string('jenis_permohonan', 255);

            $table->decimal('biaya', 10, 2)->nullable();
            $table->double('deposit', 12, 2);
            $table->text('ditagihkan');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_details');
    }
};
