<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Sync barcode created_at & updated_at with JSON tgl_asesmen
     * for all praktisi (role_id=16) barcodes (IA04B, IA05, IA06 only — skip AK02).
     */
    public function up(): void
    {
        $jsonPath = database_path('data/kan_date_sync_161.json');

        if (!file_exists($jsonPath)) {
            echo "  SKIP: JSON file not found at {$jsonPath}\n";
            return;
        }

        $json = json_decode(file_get_contents($jsonPath), true);
        $entries = $json['data'] ?? [];

        if (empty($entries)) {
            echo "  SKIP: JSON data is empty\n";
            return;
        }

        // ── Step 1: Resolve skema → id_jabatan_kerja ──
        $allSkemaNames = array_unique(array_map(fn($e) => trim($e['skema']), $entries));
        $lowerNames = array_map('mb_strtolower', $allSkemaNames);
        $placeholders = implode(',', array_fill(0, count($lowerNames), '?'));

        $skemaRows = DB::table('jabatan_kerjas')
            ->select('id_jabatan_kerja', 'jabatan_kerja')
            ->whereRaw("LOWER(jabatan_kerja) IN ({$placeholders})", $lowerNames)
            ->orderBy('id_jabatan_kerja', 'desc')
            ->get();

        $skemaMap = []; // lower_name => id_jabatan_kerja
        foreach ($skemaRows as $row) {
            $key = mb_strtolower($row->jabatan_kerja);
            if (!isset($skemaMap[$key])) {
                $skemaMap[$key] = $row->id_jabatan_kerja;
            }
        }

        // ── Step 2: Collect all asesor names, resolve to user IDs ──
        $allAsesorNames = [];
        $entryAsesorMap = []; // id_jabatan_kerja => [[name, tgl_asesmen], ...]
        $idJabatanKerjaList = [];

        foreach ($entries as $entry) {
            $skemaName = trim($entry['skema']);
            $lowerName = mb_strtolower($skemaName);
            $idJabatanKerja = $skemaMap[$lowerName] ?? null;
            if (!$idJabatanKerja) continue;

            $tglAsesmen = $this->parseDate($entry['tgl_asesmen'] ?? null);
            if (!$tglAsesmen) continue;

            $asesor = $entry['asesor'] ?? [];
            if (empty($asesor)) continue;

            $idJabatanKerjaList[] = $idJabatanKerja;

            foreach ($asesor as $name) {
                $name = trim($name);
                if (empty($name)) continue;
                $allAsesorNames[] = $name;
                $entryAsesorMap[] = [
                    'id_jabatan_kerja' => $idJabatanKerja,
                    'name'             => $name,
                    'tgl_asesmen'      => $tglAsesmen,
                ];
            }
        }

        $allAsesorNames = array_unique($allAsesorNames);
        $idJabatanKerjaList = array_unique($idJabatanKerjaList);

        // ── Step 3: Resolve users ──
        $userRows = DB::table('users')
            ->whereIn('name', $allAsesorNames)
            ->where('role_id', 16)
            ->pluck('id', 'name');

        // ── Step 4: Resolve kan_praktisi_jabatan IDs ──
        $kpjRows = DB::table('kan_praktisi_jabatan')
            ->whereIn('id_jabatan_kerja', $idJabatanKerjaList)
            ->get();

        $kpjMap = []; // "id_jabatan_kerja|id_user" => kpj_id
        foreach ($kpjRows as $row) {
            $key = $row->id_jabatan_kerja . '|' . $row->id_user;
            $kpjMap[$key] = $row->id;
        }

        // ── Step 5: Resolve barcodes ──
        $allKpjIds = array_values($kpjMap);
        $barcodeRows = DB::table('barcodes')
            ->whereIn('kan_praktisi_jabatan_id', $allKpjIds)
            ->whereIn('jenis_dokumen', ['IA04B', 'IA05', 'IA06']) // skip AK02
            ->get()
            ->groupBy('kan_praktisi_jabatan_id');

        // ── Step 6: Find and fix mismatches ──
        $updated = 0;
        $jenisDok = ['IA04B', 'IA05', 'IA06'];

        foreach ($entryAsesorMap as $item) {
            $userId = $userRows[$item['name']] ?? null;
            if (!$userId) continue;

            $kpjKey = $item['id_jabatan_kerja'] . '|' . $userId;
            $kpjId = $kpjMap[$kpjKey] ?? null;
            if (!$kpjId) continue;

            $userBarcodes = $barcodeRows[$kpjId] ?? collect();

            foreach ($jenisDok as $jenis) {
                $bc = $userBarcodes->firstWhere('jenis_dokumen', $jenis);
                if (!$bc || !$bc->created_at) continue;

                $bcDate = Carbon::parse($bc->created_at)->format('Y-m-d');
                if ($bcDate === $item['tgl_asesmen']) continue;

                DB::table('barcodes')
                    ->where('id', $bc->id)
                    ->update([
                        'created_at' => $item['tgl_asesmen'],
                        'updated_at' => $item['tgl_asesmen'],
                    ]);
                $updated++;
            }
        }

        echo "  Praktisi barcode dates fixed: {$updated}\n";
    }

    public function down(): void
    {
        // No-op
    }

    private function parseDate(mixed $value): ?string
    {
        if (empty($value)) return null;
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
};
