<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sync barcodes.created_at & updated_at with jabatan_config_a.kan_manajer_tgl
     * for ALL manajer sertifikasi (id_role=7) barcodes.
     *
     * Covers two id_izin formats:
     *  1. MANAGER_MUTU_KAN_{id_jabatan}  (jenis_dokumen = MANAGER_MUTU_KAN)
     *  2. PENYUSUN_{id_jabatan}          (jenis_dokumen = MANAJER)
     */
    public function up(): void
    {
        // Format 1: MANAGER_MUTU_KAN_{id_jabatan}
        DB::statement("
            UPDATE barcodes b
            INNER JOIN jabatan_config_a j
                ON j.id_jabatan = REPLACE(b.id_izin, 'MANAGER_MUTU_KAN_', '')
            SET b.created_at = j.kan_manajer_tgl,
                b.updated_at = j.kan_manajer_tgl
            WHERE b.id_role = 7
              AND b.id_izin LIKE 'MANAGER_MUTU_KAN_%'
              AND j.kan_manajer_tgl IS NOT NULL
              AND b.created_at != j.kan_manajer_tgl
        ");

        // Format 2: PENYUSUN_{id_jabatan}
        DB::statement("
            UPDATE barcodes b
            INNER JOIN jabatan_config_a j
                ON j.id_jabatan = REPLACE(b.id_izin, 'PENYUSUN_', '')
            SET b.created_at = j.kan_manajer_tgl,
                b.updated_at = j.kan_manajer_tgl
            WHERE b.id_role = 7
              AND b.id_izin LIKE 'PENYUSUN_%'
              AND j.kan_manajer_tgl IS NOT NULL
              AND b.created_at != j.kan_manajer_tgl
        ");
    }

    public function down(): void
    {
        // No-op: rollback would restore incorrect dates
    }
};
