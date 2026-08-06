<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sync barcodes.created_at and updated_at with
     * jabatan_config_a.kan_manajer_tgl for MANAGER_MUTU_KAN records.
     * (Subset of the broader id_role=7 fix in the next migration.)
     */
    public function up(): void
    {
        DB::statement("
            UPDATE barcodes b
            INNER JOIN jabatan_config_a j
                ON j.id_jabatan = REPLACE(b.id_izin, 'MANAGER_MUTU_KAN_', '')
            SET b.created_at = j.kan_manajer_tgl,
                b.updated_at = j.kan_manajer_tgl
            WHERE b.id_role = 7
              AND j.kan_manajer_tgl IS NOT NULL
              AND b.created_at != j.kan_manajer_tgl
        ");
    }

    /**
     * Reverse is not meaningful — the old created_at values were incorrect.
     */
    public function down(): void
    {
        // No-op: rollback would restore incorrect dates
    }
};
