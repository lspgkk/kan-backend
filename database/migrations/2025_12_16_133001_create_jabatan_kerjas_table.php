<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jabatan_kerjas', function (Blueprint $table) {
            // Primary key
            $table->string('id_jabatan_kerja', 10)->primary();

            $table->string('id', 6);
            $table->string('lsp_id_klasifikasi', 6);
            $table->string('klasifikasi', 100);
            $table->string('klasifikasi_en', 100);

            $table->string('lsp_sub_klasifikasi_id', 6);
            $table->string('subklasifikasi', 100);
            $table->string('subklasifikasi_en', 100);

            $table->string('lsp_kualifikasi_id', 4);
            $table->string('kualifikasi', 100);
            $table->string('kualifikasi_en', 100);

            $table->string('id_jabker', 6);
            $table->string('jabatan_kerja', 100);
            $table->string('work_position', 100);

            $table->string('jenjang_id', 4);

            $table->string('ACUAN', 255);
            $table->string('KETERANGAN', 50);
            $table->string('Status', 3);

            $table->timestamp('InputDate')->useCurrent();
            $table->string('InputBy', 30);
            $table->string('SinServer', 2);

            $table->text('peralatan')->nullable();
            $table->text('persyaratan_dasar')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jabatan_kerjas');
    }
};
