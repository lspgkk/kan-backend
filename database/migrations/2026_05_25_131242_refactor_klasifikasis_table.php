<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('klasifikasis', function (Blueprint $table) {
            $table->renameColumn('kode', 'id_klasifikasi');
            $table->renameColumn('deskripsi', 'klasifikasi');
            $table->dropColumn('name');
            $table->string('klasifikasi_en')->nullable()->after('klasifikasi');
        });
    }

    public function down(): void
    {
        Schema::table('klasifikasis', function (Blueprint $table) {
            $table->renameColumn('id_klasifikasi', 'kode');
            $table->renameColumn('klasifikasi', 'deskripsi');
            $table->string('name')->after('id');
            $table->dropColumn('klasifikasi_en');
        });
    }
};
