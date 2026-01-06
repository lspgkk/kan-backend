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
        Schema::create('invoices', function (Blueprint $table) {

            // PRIMARY KEY
            $table->string('invoice_number', 30)->primary();
            $table->string('nama', 100);

            $table->tinyInteger('inv_type');
            $table->tinyInteger('bulan');
            $table->integer('tahun');
            $table->integer('nomor');

            $table->tinyInteger('jatuh_tempo');

            $table->string('nama_tuk', 100)->nullable();
            $table->string('class_uji', 30)->nullable();
            $table->string('kode_rekening', 3)->nullable();

            $table->string('keterangan', 100)->nullable();

            $table->double('total_biaya', 10, 0)->default(0);

            $table->double('bayar_tahap1', 10, 0)->default(0);
            $table->date('tgl_tahap1')->nullable();
            $table->string('kode_bayar1', 100)->nullable();
            $table->string('input_bayar1', 30)->nullable();

            $table->double('bayar_tahap2', 10, 0)->default(0);
            $table->date('tgl_tahap2')->nullable();
            $table->string('kode_bayar2', 100)->nullable();
            $table->string('input_bayar2', 30)->nullable();

            $table->double('refund_bayar', 10, 0)->default(0);
            $table->date('tgl_uji')->nullable();

            $table->tinyInteger('status_jadwal')->default(0);

            $table->string('inv_link', 255)->nullable();

            $table->timestamp('input_date')->useCurrent();
            $table->string('input_by', 255)->nullable();

            $table->string('sin_server', 2)->nullable();

            // indexes (based on MUL in your screenshot)
            $table->index('inv_type');
            $table->index('nama_tuk');
            $table->index('class_uji');
            $table->index('kode_rekening');
            $table->index('bayar_tahap2');
            $table->index('status_jadwal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
