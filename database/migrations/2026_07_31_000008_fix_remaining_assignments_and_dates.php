<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix remaining issues:
     * 1. Assign Nuryono/NURYONO to 8 skema via kan_praktisi_jabatan
     * 2. Fix 3 barcode date mismatches for Ir. SAHAT in AHLI UTAMA MATERIAL JALAN
     */
    public function up(): void
    {
        // ── 1. Assign users to skema ──
        $assignments = [
            // "Nuryono" (title case in JSON) → user id=5497
            'Pelaksana Pemasangan Pintu Air (Level 6)'             => 5497,
            'Ahli Utama Teknik Perencanaan Irigasi Rawa'           => 5497,
            'Ahli Utama Teknik Rawa'                               => 5497,
            'Ahli Madya Teknik Perencanaan Irigasi Rawa'           => 5497,
            // "NURYONO" (caps in JSON) → user id=5623
            'AHLI MUDA HIDROLOGI'                                 => 5623,
            'AHLI MADYA HIDROLOGI'                                => 5623,
            'AHLI MUDA BIDANG KEAHLIAN TEKNIK SUMBER DAYA AIR'    => 5623,
            'AHLI MADYA BIDANG KEAHLIAN TEKNIK SUMBER DAYA AIR'   => 5623,
        ];

        $created = 0;
        foreach ($assignments as $skemaName => $userId) {
            $jabatan = DB::table('jabatan_kerjas')
                ->where('jabatan_kerja', $skemaName)
                ->first();

            if (!$jabatan) {
                echo "  WARN: skema not found: {$skemaName}\n";
                continue;
            }

            $exists = DB::table('kan_praktisi_jabatan')
                ->where('id_jabatan_kerja', $jabatan->id_jabatan_kerja)
                ->where('id_user', $userId)
                ->exists();

            if ($exists) continue;

            DB::table('kan_praktisi_jabatan')->insert([
                'id_jabatan_kerja' => $jabatan->id_jabatan_kerja,
                'id_user'          => $userId,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
            $created++;
        }
        echo "  Assignments created: {$created}\n";

        // ── 2. Fix Ir. SAHAT barcode dates in AHLI UTAMA MATERIAL JALAN ──
        $json = json_decode(file_get_contents(database_path('data/kan_date_sync_161.json')), true);
        $targetSkema = 'AHLI UTAMA MATERIAL JALAN';
        $tglAsesmen = null;

        foreach (($json['data'] ?? []) as $entry) {
            if (trim($entry['skema']) === $targetSkema) {
                $tgl = $entry['tgl_asesmen'] ?? null;
                if ($tgl) {
                    $tglAsesmen = Carbon::parse($tgl)->format('Y-m-d');
                }
                break;
            }
        }

        if ($tglAsesmen) {
            // Find the jabatan_kerja → kpj for user 5722
            $jabatan = DB::table('jabatan_kerjas')->where('jabatan_kerja', $targetSkema)->first();
            if ($jabatan) {
                $kpj = DB::table('kan_praktisi_jabatan')
                    ->where('id_jabatan_kerja', $jabatan->id_jabatan_kerja)
                    ->where('id_user', 5722)
                    ->first();
                if ($kpj) {
                    $updated = DB::table('barcodes')
                        ->where('kan_praktisi_jabatan_id', $kpj->id)
                        ->whereIn('jenis_dokumen', ['IA04B', 'IA05', 'IA06'])
                        ->update([
                            'created_at' => $tglAsesmen,
                            'updated_at' => $tglAsesmen,
                        ]);
                    echo "  Barcode dates fixed: {$updated}\n";
                }
            }
        }
    }

    public function down(): void
    {
        // No-op
    }
};
