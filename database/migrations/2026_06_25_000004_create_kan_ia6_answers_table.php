<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kan_ia6_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_izin');
            $table->unsignedBigInteger('dokumen_id');
            $table->unsignedBigInteger('soal_id');
            $table->text('jawaban')->nullable(); // essay answer text
            $table->tinyInteger('skor')->unsigned()->nullable(); // 0-3 rubric score
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kan_ia6_answers');
    }
};
