<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sync barcodes.created_at & updated_at with jabatan_config_a.kan_manajer_tgl
     * for all id_role=7 barcodes, matched by url_ttd = kan_manajer_url.
     */
    public function up(): void
    {
        DB::statement("
            UPDATE barcodes b
            INNER JOIN jabatan_config_a j
                ON j.kan_manajer_url = b.url_ttd
            SET b.created_at = j.kan_manajer_tgl,
                b.updated_at = j.kan_manajer_tgl
            WHERE b.id_role = 7
              AND j.kan_manajer_url IS NOT NULL
              AND j.kan_manajer_url != ''
              AND j.kan_manajer_tgl IS NOT NULL
              AND b.created_at != j.kan_manajer_tgl
        ");
    }

    public function down(): void
    {
        // No-op
    }
};
