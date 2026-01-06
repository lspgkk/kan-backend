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
        Schema::create('proyeks', function (Blueprint $table) {
            $table->id();
            $table->string('id_izin');
            $table->string('id_proyek');
            $table->integer('user_id')->nullable();
            $table->date('updated')->nullable();
            $table->date('created')->nullable();
            $table->string('creator')->nullable();
            $table->integer('data_id');
            $table->string('nama_proyek')->nullable();
            $table->string('lokasi_proyek')->nullable();
            $table->date('tanggal_awal')->nullable();
            $table->date('tanggal_akhir')->nullable();
            $table->string('jabatan');
            $table->string('nilai_proyek')->nullable();
            $table->text('surat_referensi')->nullable();
            $table->string('jenis_pengalaman');
            $table->string('pemberi_kerja')->nullable();
            $table->string('nik');
            $table->string('no_registrasi')->nullable();
            $table->string("inputby_id")->nullable();
            $table->timestamp("inputby_time")->nullable();
            $table->timestamps();

            $table->foreign('id_izin')->references('id_izin')->on('personals')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proyeks');
    }
};
