<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Clean up duplicates: keep only the row with the highest `id` per group ──
        $tables = ['kan_ia4b_answers', 'kan_ia5_answers', 'kan_ia6_answers'];

        foreach ($tables as $table) {
            // Delete duplicates where there is a row with a higher id for the same composite key
            DB::statement("
                DELETE t1 FROM {$table} t1
                INNER JOIN {$table} t2
                WHERE t1.kan_praktisi_jabatan_id = t2.kan_praktisi_jabatan_id
                  AND t1.dokumen_id = t2.dokumen_id
                  AND t1.soal_id = t2.soal_id
                  AND t1.id < t2.id
            ");
        }

        // Also handle rows where kan_praktisi_jabatan_id IS NULL (old id_izin-based records)
        foreach ($tables as $table) {
            DB::statement("
                DELETE t1 FROM {$table} t1
                INNER JOIN {$table} t2
                WHERE t1.kan_praktisi_jabatan_id IS NULL
                  AND t2.kan_praktisi_jabatan_id IS NULL
                  AND t1.dokumen_id = t2.dokumen_id
                  AND t1.soal_id = t2.soal_id
                  AND t1.id_izin = t2.id_izin
                  AND t1.id < t2.id
            ");
        }

        // ── 2. Add unique composite indexes ──
        Schema::table('kan_ia4b_answers', function (Blueprint $table) {
            $table->unique(['kan_praktisi_jabatan_id', 'dokumen_id', 'soal_id'], 'u_kpj_dok_soal_ia4b');
        });

        Schema::table('kan_ia5_answers', function (Blueprint $table) {
            $table->unique(['kan_praktisi_jabatan_id', 'dokumen_id', 'soal_id'], 'u_kpj_dok_soal_ia5');
        });

        Schema::table('kan_ia6_answers', function (Blueprint $table) {
            $table->unique(['kan_praktisi_jabatan_id', 'dokumen_id', 'soal_id'], 'u_kpj_dok_soal_ia6');
        });
    }

    public function down(): void
    {
        Schema::table('kan_ia4b_answers', function (Blueprint $table) {
            $table->dropUnique('u_kpj_dok_soal_ia4b');
        });

        Schema::table('kan_ia5_answers', function (Blueprint $table) {
            $table->dropUnique('u_kpj_dok_soal_ia5');
        });

        Schema::table('kan_ia6_answers', function (Blueprint $table) {
            $table->dropUnique('u_kpj_dok_soal_ia6');
        });
    }
};
