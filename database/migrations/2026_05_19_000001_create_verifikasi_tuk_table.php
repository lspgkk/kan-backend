<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifikasi_tuk', function (Blueprint $table) {
            $table->id();
            $table->string('jadwal_id');
            $table->string('id_izin');
            $table->string('no_surat_tugas')->nullable();
            $table->string('no_surat_ba')->nullable();
            $table->string('no_surat_sk')->nullable();
            $table->string('no_surat_peminjaman')->nullable();
            $table->string('nama_tuk');
            $table->text('alamat');
            $table->date('hari_tanggal');
            $table->string('metode_verifikasi')->default('Daring');
            $table->integer('jumlah_peserta');
            $table->string('skema_sertifikasi');
            $table->string('jenjang')->nullable();
            $table->json('ruangan_uji_images')->nullable();
            $table->json('laptop_images')->nullable();
            $table->json('kecepatan_jaringan_images')->nullable();
            $table->enum('kondisi_ruangan', ['baik', 'rusak'])->nullable();
            $table->string('keterangan_ruangan')->nullable();
            $table->enum('kondisi_laptop', ['baik', 'rusak'])->nullable();
            $table->string('keterangan_laptop')->nullable();
            $table->enum('kondisi_jaringan', ['baik', 'rusak'])->nullable();
            $table->string('keterangan_jaringan')->nullable();
            $table->enum('rekomendasi', ['layak', 'tidak_layak'])->nullable();
            $table->unsignedBigInteger('verifikator_id')->nullable();
            $table->text('verifikator_qr')->nullable();
            $table->unsignedBigInteger('ketua_tuk_id')->nullable();
            $table->text('ketua_tuk_qr')->nullable();
            $table->unsignedBigInteger('direktur_id')->nullable();
            $table->text('direktur_qr')->nullable();
            $table->boolean('has_ttd_sk')->default(false);
            $table->text('direktur_sk_qr')->nullable();
            $table->enum('status', ['diajukan', 'dokumentasi_terisi', 'direktur_ttd', 'diverifikasi', 'selesai'])->default('diajukan');
            $table->string('pdf_link')->nullable();
            $table->timestamps();

            $table->index('jadwal_id');
            $table->index('id_izin');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifikasi_tuk');
    }
};
