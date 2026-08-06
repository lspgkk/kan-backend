<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Assign NURYONO (5623) to the correct duplicate skema IDs
     * that the command uses (highest ID for duplicate names).
     */
    public function up(): void
    {
        // The command resolves these skema names to IDs:
        // Ahli Muda Hidrologi -> SI101001 (already assigned)
        // Ahli Madya Hidrologi -> SI101002 (already assigned)
        // Ahli Muda Bidang Keahlian Teknik Sumber Daya Air -> SI101013 (NOT assigned)
        // Ahli Madya Bidang Keahlian Teknik Sumber Daya Air -> SI101014 (NOT assigned)
        $assignments = [
            ['SI101013', 5623], // Ahli Muda Bidang Keahlian Teknik Sumber Daya Air
            ['SI101014', 5623], // Ahli Madya Bidang Keahlian Teknik Sumber Daya Air
            // Also add to other duplicate for completeness
            ['SI101004', 5623], // Already assigned? Check and skip if exists
            ['SI101005', 5623], // Already assigned? Check and skip if exists
        ];

        // Already assigned ones
        $already = [
            'SI101001|5623' => true, // Ahli Muda Hidrologi
            'SI101002|5623' => true, // Ahli Madya Hidrologi
            'SI101004|5623' => true, // Ahli Muda Bidang Keahlian Teknik SDA (old ID)
            'SI101005|5623' => true, // Ahli Madya Bidang Keahlian Teknik SDA (old ID)
        ];

        $created = 0;
        foreach ($assignments as [$idJabatanKerja, $userId]) {
            $key = $idJabatanKerja . '|' . $userId;
            if (isset($already[$key])) continue;

            $exists = DB::table('kan_praktisi_jabatan')
                ->where('id_jabatan_kerja', $idJabatanKerja)
                ->where('id_user', $userId)
                ->exists();
            if ($exists) continue;

            DB::table('kan_praktisi_jabatan')->insert([
                'id_jabatan_kerja' => $idJabatanKerja,
                'id_user'          => $userId,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
            $created++;
        }
        echo "  Assignments created: {$created}\n";
    }

    public function down(): void
    {
        // No-op
    }
};
