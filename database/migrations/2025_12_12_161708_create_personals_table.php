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
        Schema::create('personals', function (Blueprint $table) {
            // $table->id();
            $table->string('id_izin')->primary();
            $table->integer('id_pemohon');
            $table->enum('type', ['balai', 'reguler', 'fg']);
            $table->string('updated');
            $table->string('created');
            $table->string('creator');
            $table->integer('data_id');
            $table->string('nik');
            $table->string('nama');
            $table->string('tempat_lahir');
            $table->date('tanggal_lahir');
            $table->string('email');
            $table->string('telepon');
            $table->string('npwp');
            $table->string('status')->nullable();
            $table->string('jenis_kelamin');
            $table->string('alamat')->nullable();
            $table->string('negara')->nullable();
            $table->string('propinsi');
            $table->string('kabupaten');
            $table->string('kodepos')->nullable();
            $table->text('ktp')->nullable();
            $table->text('surat_pernyataan_kebenaran_data')->nullable();
            $table->text('file_npwp')->nullable();
            $table->text('pas_foto')->nullable();
            $table->string("inputby_id")->nullable();
            $table->timestamp("inputby_time")->nullable();
            $table->timestamp("verif_time")->nullable();
            $table->string("verifby_id")->nullable();
            $table->timestamp("valid_time")->nullable();
            $table->string("validby_id")->nullable();
            $table->boolean("is_deleted")->default(false);
            $table->string("deletedby_id")->nullable();
            $table->timestamp("deleted_at")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personals');
    }
};
