<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kan_ia5_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_izin');
            $table->unsignedBigInteger('dokumen_id');
            $table->unsignedBigInteger('soal_id');
            $table->char('jawaban', 1)->nullable(); // A/B/C/D
            $table->tinyInteger('skor')->unsigned()->nullable(); // 0=wrong, 1=correct
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kan_ia5_answers');
    }
};
