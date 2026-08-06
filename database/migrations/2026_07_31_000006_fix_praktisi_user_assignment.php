<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 1. Assign the correct user for "Ir. IR. SAHAT OLOAN SIHOMBING" (typod name in JSON)
     *    → maps to user id=5722 "Ir. SAHAT OLOAN SIHOMBING" (role_id=16)
     * 2. Assign NURYONO (id=5623) to 4 skema where not yet assigned:
     *    AHLI MUDA HIDROLOGI, AHLI MADYA HIDROLOGI,
     *    AHLI MUDA BIDANG KEAHLIAN TEKNIK SUMBER DAYA AIR,
     *    AHLI MADYA BIDANG KEAHLIAN TEKNIK SUMBER DAYA AIR
     */
    public function up(): void
    {
        $jsonPath = database_path('data/kan_date_sync_161.json');
        if (!file_exists($jsonPath)) return;

        $json = json_decode(file_get_contents($jsonPath), true);
        $entries = $json['data'] ?? [];
        if (empty($entries)) return;

        // ── Resolve skema → id_jabatan_kerja ──
        $allSkemaNames = array_unique(array_map(fn($e) => trim($e['skema']), $entries));
        $lowerNames = array_map('mb_strtolower', $allSkemaNames);
        $placeholders = implode(',', array_fill(0, count($lowerNames), '?'));

        $skemaRows = DB::table('jabatan_kerjas')
            ->select('id_jabatan_kerja', 'jabatan_kerja')
            ->whereRaw("LOWER(jabatan_kerja) IN ({$placeholders})", $lowerNames)
            ->orderBy('id_jabatan_kerja', 'desc')
            ->get();

        $skemaMap = [];
        foreach ($skemaRows as $row) {
            $key = mb_strtolower($row->jabatan_kerja);
            if (!isset($skemaMap[$key])) {
                $skemaMap[$key] = $row->id_jabatan_kerja;
            }
        }

        // ── Name correction map (JSON name → correct user ID) ──
        $nameCorrections = [
            'Ir. IR. SAHAT OLOAN SIHOMBING' => 5722, // Ir. SAHAT OLOAN SIHOMBING
        ];

        $assignments = []; // ['id_jabatan_kerja' => X, 'id_user' => Y]
        $created = 0;

        foreach ($entries as $entry) {
            $skemaName = trim($entry['skema']);
            $lowerName = mb_strtolower($skemaName);
            $idJabatanKerja = $skemaMap[$lowerName] ?? null;
            if (!$idJabatanKerja) continue;

            $asesor = $entry['asesor'] ?? [];
            if (empty($asesor)) continue;

            foreach ($asesor as $name) {
                $name = trim($name);

                // Check name correction map first
                $userId = $nameCorrections[$name] ?? null;

                // If no correction, try case-insensitive DB lookup
                if (!$userId) {
                    $user = DB::table('users')
                        ->where('name', $name)
                        ->where('role_id', 16)
                        ->first();
                    $userId = $user->id ?? null;
                }

                if (!$userId) continue;

                // Check if already assigned
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
        }

        echo "  Praktisi assignments created: {$created}\n";
    }

    public function down(): void
    {
        // No-op
    }
};
