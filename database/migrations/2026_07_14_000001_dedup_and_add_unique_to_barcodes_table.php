<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add unique composite index to prevent future duplicates.
        // Note: if this fails with "Duplicate entry", existing dupes must be cleaned first
        // by running: php artisan kan:dedup-barcodes
        Schema::table('barcodes', function (Blueprint $table) {
            $table->unique(
                ['id_user', 'kan_praktisi_jabatan_id', 'jenis_dokumen'],
                'u_user_kpj_jenis_barcode'
            );
        });
    }

    public function down(): void
    {
        Schema::table('barcodes', function (Blueprint $table) {
            $table->dropUnique('u_user_kpj_jenis_barcode');
        });
    }
};
