<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_tuk', function (Blueprint $table) {
            $table->id();
            $table->string('ref_number', 50)->unique();
            $table->enum('tuk_type', ['mandiri', 'sewaktu']);
            $table->enum('status', ['diajukan', 'diverifikasi', 'ditolak'])->default('diajukan');
            $table->json('data_tuk');
            $table->json('asesor_list')->nullable();
            $table->json('data_sewaktu')->nullable();
            $table->string('dokumen_permohonan')->nullable();
            $table->string('lampiran')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('tuk_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_tuk');
    }
};
