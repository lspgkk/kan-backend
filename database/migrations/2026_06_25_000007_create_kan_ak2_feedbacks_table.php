<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kan_ak2_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_izin');
            $table->decimal('total_skor_dit', 8, 2)->nullable();     // IA04B total
            $table->decimal('total_skor_pg', 8, 2)->nullable();      // IA05 total
            $table->decimal('total_skor_esai', 8, 2)->nullable();    // IA06 total
            $table->decimal('nilai_skor_dit', 8, 2)->nullable();     // weighted
            $table->decimal('nilai_skor_pg', 8, 2)->nullable();      // weighted
            $table->decimal('nilai_skor_esai', 8, 2)->nullable();    // weighted
            $table->decimal('skor_nilai_akhir', 8, 2)->nullable();
            $table->boolean('is_kompeten')->nullable();
            $table->decimal('threshold_passing', 8, 2)->nullable();   // 65 or 70
            $table->string('tindak_lanjut')->nullable();
            $table->text('komentar')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kan_ak2_feedbacks');
    }
};
