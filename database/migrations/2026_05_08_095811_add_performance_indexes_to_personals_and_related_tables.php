<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personals', function (Blueprint $table) {
            $table->index('nama');
            $table->index('created');
            $table->index(['type', 'nama']);
        });

        Schema::table('klasifikasi_kualifikasis', function (Blueprint $table) {
            $table->index('tuk');
        });

        Schema::table('mytuk', function (Blueprint $table) {
            $table->index('nama_tuk');
        });
    }

    public function down(): void
    {
        Schema::table('personals', function (Blueprint $table) {
            $table->dropIndex(['nama']);
            $table->dropIndex(['created']);
            $table->dropIndex(['type', 'nama']);
        });

        Schema::table('klasifikasi_kualifikasis', function (Blueprint $table) {
            $table->dropIndex(['tuk']);
        });

        Schema::table('mytuk', function (Blueprint $table) {
            $table->dropIndex(['nama_tuk']);
        });
    }
};
