<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('data_pencatatans', function (Blueprint $table) {
            $table->id(); // primary key

            $table->string('nomor_sertifikasi');
            $table->string('id_izin');
            $table->string('nik');
            $table->string('nama');

            $table->string('id_propinsi');
            $table->string('propinsi');
            $table->string('id_kabupaten');
            $table->string('kabupaten');

            $table->string('id_kualifikasi');
            $table->string('kualifikasi');
            $table->string('kualifikasi_en');

            $table->string('id_klasifikasi');
            $table->string('id_subklasifikasi');
            $table->string('subklasifikasi');
            $table->string('subklasifikasi_en');

            $table->string('id_jabatan_kerja');
            $table->string('jabatan_kerja');
            $table->string('jabatan_kerja_en');

            $table->string('jenjang');

            $table->string('nomor_sertifikat_lengkap');
            $table->string('nomor_registrasi_lsp');
            $table->string('nomor_registrasi_lpjk');
            $table->string('nomor_blanko_bnsp');

            $table->date('tanggal_ditetapkan');
            $table->date('tanggal_masa_berlaku');

            $table->string('jenis_permohonan');

            $table->longText('qr');
            $table->longText('qr_signature');

            $table->string('link_e_sertifikat');
            $table->text('catatan');

            $table->string('ketua_pelaksana');
            $table->string('ttd_ketua_pelaksana');

            $table->timestamps(); // created_at & updated_at (nullable by default)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_pencatatans');
    }
};
