<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kan_praktisi_jabatan', function (Blueprint $table) {
            $table->id();
            $table->string('id_jabatan_kerja');
            $table->unsignedBigInteger('id_user');
            $table->timestamps();
            $table->unique(['id_jabatan_kerja', 'id_user'], 'kpj_jabatan_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kan_praktisi_jabatan');
    }
};
