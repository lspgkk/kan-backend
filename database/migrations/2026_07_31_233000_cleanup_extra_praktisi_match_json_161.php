<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Bersihkan 7 record kan_praktisi_jabatan yg tidak sesuai mapping JSON 161 skema.
     *
     * Target akhir: 1610 records (161 skema × 10 asesor).
     *
     * Yang dihapus:
     *   1. Nuryono (user 5497) dari 4 skema yg sudah punya NURYONO (user 5623):
     *      - SI101001 (Ahli Muda Hidrologi)         kpj=4438
     *      - SI101002 (Ahli Madya Hidrologi)         kpj=4448
     *      - SI101013 (Ahli Muda Bidang Keahlian SDA) kpj=4458
     *      - SI101014 (Ahli Madya Bidang Keahlian SDA) kpj=4468
     *      JSON hanya mencantumkan "NURYONO" (5623) sebagai A4, bukan "Nuryono" (5497).
     *
     *   2. Duplikat skema ID (nama skema sama, ID berbeda):
     *      - SI101004 (duplikat dr SI101013) → NURYONO kpj=5838
     *      - SI101005 (duplikat dr SI101014) → NURYONO kpj=5839
     *      - SI081009 (duplikat dr SI081019) → Nuryono kpj=5835
     */
    public function up(): void
    {
        // kpj.id → [alasan singkat]
        $kpjIdsToDelete = [
            4438, // Nuryono extra di SI101001
            4448, // Nuryono extra di SI101002
            4458, // Nuryono extra di SI101013
            4468, // Nuryono extra di SI101014
            5835, // Duplikat skema SI081009
            5838, // Duplikat skema SI101004
            5839, // Duplikat skema SI101005
        ];

        // 1. Hapus barcode terkait
        $deletedBarcodes = DB::table('barcodes')
            ->whereIn('kan_praktisi_jabatan_id', $kpjIdsToDelete)
            ->delete();
        echo "  Barcodes deleted: {$deletedBarcodes}\n";

        // 2. Hapus kan_praktisi_jabatan
        $deletedKpj = DB::table('kan_praktisi_jabatan')
            ->whereIn('id', $kpjIdsToDelete)
            ->delete();
        echo "  kan_praktisi_jabatan deleted: {$deletedKpj}\n";

        // 3. Verifikasi
        $total = DB::table('kan_praktisi_jabatan')->count();
        $distinct = DB::table('kan_praktisi_jabatan')
            ->distinct()
            ->count('id_jabatan_kerja');
        echo "  Final: total={$total}, distinct skema={$distinct}\n";

        if ($total !== 1610) {
            // Cek skema dengan count ≠ 10
            $anomalies = DB::table('kan_praktisi_jabatan')
                ->selectRaw('id_jabatan_kerja, COUNT(*) as cnt')
                ->groupBy('id_jabatan_kerja')
                ->having('cnt', '!=', 10)
                ->get();

            if ($anomalies->isNotEmpty()) {
                echo "  WARNING: Masih ada skema dgn count != 10:\n";
                foreach ($anomalies as $a) {
                    $skema = DB::table('jabatan_kerjas')
                        ->where('id_jabatan_kerja', $a->id_jabatan_kerja)
                        ->value('jabatan_kerja') ?? '???';
                    echo "    {$a->id_jabatan_kerja} cnt={$a->cnt}  {$skema}\n";
                }
            }
        }
    }

    public function down(): void
    {
        // Restore is not practical — the records were created by previous
        // migrations that had side effects. Manual intervention needed.
    }
};
