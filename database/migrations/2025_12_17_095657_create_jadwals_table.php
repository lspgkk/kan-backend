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
        Schema::create('jadwals', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('id_izin')->unique();

            $table->integer('jadwal_id')->nullable();
            $table->integer('tuk_id')->nullable();
            $table->integer('asesor_id')->nullable();
            $table->integer('asesor2_id')->nullable();
            $table->tinyInteger('is_fg')->nullable();
            $table->string('nik')->nullable();
            $table->string('nib')->nullable();
            $table->string('nama')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();

            $table->integer('jenis_kelamin')->nullable();
            $table->string('alamat')->nullable();

            $table->integer('kota_id')->nullable();
            $table->integer('provinsi_id')->nullable();
            $table->integer('negara_id')->nullable();

            $table->string('telepon')->nullable();
            $table->string('email')->nullable();

            $table->integer('jenis_mohon')->nullable();
            $table->integer('skema_id')->nullable();
            $table->string('keterangan')->nullable();
            $table->integer('jenjang_id')->nullable();

            $table->string('prodi')->nullable();
            $table->string('no_ijasah')->nullable();
            $table->string('tanggal_ijazah')->nullable();
            $table->string('tahun_lulus')->nullable();

            $table->integer('kota_sekolah')->nullable();
            $table->integer('prov_sekolah')->nullable();
            $table->integer('negara_sekolah')->nullable();
            $table->string('nama_sekolah')->nullable();

            $table->integer('pekerjaan')->nullable();
            $table->string('instansi_pekerjaan')->nullable();
            $table->string('jabatan_pekerjaan')->nullable();

            $table->string('file_foto')->nullable();
            $table->string('file_ktp')->nullable();
            $table->string('file_nib')->nullable();
            $table->string('file_ijazah')->nullable();

            $table->timestamps();

            $table->string('kompeten')->nullable();
            $table->string('komtek1');
            $table->string('komtek2');
            $table->string('komtek3');
            $table->string('ketua_lsp');
            $table->string('mgr_sertif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwals');
    }
};
