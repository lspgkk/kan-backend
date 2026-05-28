<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuk_checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->string('kategori');        // 'fasilitas' or 'peralatan'
            $table->string('nama');            // e.g. 'Lokasi Gedung Dengan Akses Masuk & Keluar'
            $table->string('spesifikasi')->nullable(); // e.g. 'Min. 10 Mbps'
            $table->integer('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuk_checklist_templates');
    }
};
