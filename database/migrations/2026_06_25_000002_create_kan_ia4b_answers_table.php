<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kan_ia4b_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_izin');
            $table->unsignedBigInteger('dokumen_id');
            $table->unsignedBigInteger('soal_id');
            $table->text('jawaban')->nullable();
            $table->tinyInteger('skor')->unsigned()->nullable(); // 0-3
            $table->boolean('is_rekomendasi')->default(false);
            $table->boolean('rekomendasi')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kan_ia4b_answers');
    }
};
