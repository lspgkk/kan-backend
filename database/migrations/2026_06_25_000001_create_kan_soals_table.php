<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kan_soals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_dokumen');
            $table->unsignedBigInteger('id_unitkompetensi')->nullable();
            $table->unsignedBigInteger('id_subunitkompetensi')->nullable();
            $table->unsignedBigInteger('id_kuk')->nullable();
            $table->integer('urut')->nullable();
            $table->integer('no')->nullable();
            $table->string('jenis')->nullable();
            $table->tinyInteger('tipe')->default(1); // 1=PG, 3=rekomendasi, 8=esai
            $table->text('soal')->nullable();
            $table->text('soal1')->nullable();
            $table->text('soal2')->nullable();
            $table->text('jawab_a')->nullable();
            $table->text('jawab_b')->nullable();
            $table->text('jawab_c')->nullable();
            $table->text('jawab_d')->nullable();
            $table->string('file_a')->nullable();
            $table->string('file_b')->nullable();
            $table->string('file_c')->nullable();
            $table->string('file_d')->nullable();
            $table->string('jawaban', 1)->nullable(); // correct answer for PG
            $table->boolean('is_komentar')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kan_soals');
    }
};
