<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenjang_jabatan_kerjas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('jabatan_kerja_id', 10);
            $table->foreignId('jenjang_id')->constrained('jenjang_siki_bnsp')->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('jabatan_kerja_id')->references('id_jabatan_kerja')->on('jabatan_kerjas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenjang_jabatan_kerjas');
    }
};
