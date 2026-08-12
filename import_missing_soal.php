<?php
/**
 * Import missing IA05 & IA06 soal for 4 jabatan from ALL_SOAL_MERGED.json
 *
 * Usage:
 *   php import_missing_soal.php          # dry-run (audit only)
 *   php import_missing_soal.php --run    # execute the import
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\KanSoal;
use Illuminate\Support\Facades\DB;

$isRun = in_array('--run', $argv ?? []);

$jsonFile = 'c:/Users/Rafie Pradipta/Downloads/ALL_SOAL_MERGED.json';
if (!file_exists($jsonFile)) {
    die("ERROR: JSON file not found: {$jsonFile}\n");
}

$json = json_decode(file_get_contents($jsonFile), true);

echo str_repeat("═", 70) . "\n";
echo "  IMPORT MISSING SOAL DARI ALL_SOAL_MERGED.JSON\n";
echo str_repeat("═", 70) . "\n\n";
echo "Mode: " . ($isRun ? "RUN (WILL IMPORT)" : "DRY RUN (AUDIT ONLY)") . "\n\n";

// ─── Mapping ───
$targets = [
    [
        'json_key' => '118. AHLI MUDA GROUTING',
        'jabker_id' => 'SI231002',
        'dok_id' => 8606,
        'tipe' => 'IA05', // tipe=1
        'section' => 'ia05',
    ],
    [
        'json_key' => '210. AHLI MADYA HIDROGRAFI LEPAS PANTAI',
        'jabker_id' => 'SI161011',
        'dok_id' => 8416,
        'tipe' => 'IA05',
        'section' => 'ia05',
    ],
    [
        'json_key' => '209. SPESIALIS SIG',
        'jabker_id' => 'SI161018',
        'dok_id' => 8419,
        'tipe' => 'IA05',
        'section' => 'ia05',
    ],
    [
        'json_key' => '072. PELAKSANA PENGEBORAN AIR TANAH (LEVEL 4)',
        'jabker_id' => 'SI102008',
        'dok_id' => 14416,
        'tipe' => 'IA06', // tipe=8
        'section' => 'ia06',
    ],
];

// ─── Helper functions ───
function cleanKodeUnit(string $kode): string {
    $kode = trim($kode);
    $kode = preg_replace('/^Kode\s*Unit\s*:\s*/i', '', $kode);
    $kode = str_replace('*', '', $kode);
    $kode = rtrim($kode, '.');
    $kode = preg_replace('/\p{Zs}/u', ' ', $kode);
    return trim($kode);
}

function resolveUnitId(string $kodeUnit): ?int {
    static $cache = [];
    if (array_key_exists($kodeUnit, $cache)) return $cache[$kodeUnit];

    $tryCodes = [$kodeUnit];

    // Normalize version suffix
    if (preg_match('/^(.+)\.(\d)$/', $kodeUnit, $m)) {
        $tryCodes[] = $m[1] . '.0' . $m[2]; // .2 → .02
    }
    if (preg_match('/^(.+)\.0(\d)$/', $kodeUnit, $m)) {
        $tryCodes[] = $m[1] . '.' . $m[2]; // .02 → .2
    }

    // Strip trailing .\d+
    if (preg_match('/^(.+)\.\d+$/', $kodeUnit, $m)) {
        $tryCodes[] = $m[1];
    }

    // Try M.71 zero-padding
    if (preg_match('/^M\.(\d+)(\..+)$/', $kodeUnit, $m)) {
        $numBlock = $m[1];
        for ($i = 0; $i <= strlen($numBlock); $i++) {
            $tryCodes[] = 'M.' . substr($numBlock, 0, $i) . '0' . substr($numBlock, $i) . $m[2];
        }
    }

    // Concatenation detection (5+ segments)
    $segments = explode('.', $kodeUnit);
    if (count($segments) >= 5) {
        $lastKukSeg = array_pop($segments);
        $mergedSeg = array_pop($segments);
        $unitPrefix = implode('.', $segments);
        for ($i = 1; $i < strlen($mergedSeg); $i++) {
            $tryCodes[] = $unitPrefix . '.' . substr($mergedSeg, 0, $i);
        }
        $tryCodes[] = $unitPrefix . '.' . $mergedSeg;
    }

    $tryCodes = array_unique($tryCodes);
    foreach ($tryCodes as $code) {
        $unit = DB::table('unit_kompetensis')->where('kode', $code)->first(['id']);
        if ($unit) {
            $cache[$kodeUnit] = $unit->id;
            return $unit->id;
        }
    }

    $cache[$kodeUnit] = null;
    return null;
}

// ─── Collect existing soal numbers per dokumen ───
function getExistingNos(int $dokId, int $tipe): array {
    return KanSoal::where('id_dokumen', $dokId)
        ->where('tipe', $tipe)
        ->pluck('no')
        ->map(fn($n) => (int)$n)
        ->toArray();
}

// ─── Process each target ───
$grandTotal = ['imported' => 0, 'skipped' => 0, 'unit_not_found' => 0];

foreach ($targets as $idx => $t) {
    $jsonKey = $t['json_key'];
    $section = $t['section'];
    $dokId = $t['dok_id'];
    $tipeKode = $t['tipe'];

    echo "─── [" . ($idx + 1) . "/" . count($targets) . "] {$jsonKey} ({$t['jabker_id']}) ───\n";

    if (!isset($json[$jsonKey])) {
        echo "  ✗ JSON key NOT FOUND!\n\n";
        continue;
    }

    $sectionData = $json[$jsonKey][$section] ?? [];
    if (empty($sectionData)) {
        echo "  ✗ Section '{$section}' is empty in JSON!\n\n";
        continue;
    }

    $jsonCount = count($sectionData);
    $tipe = ($tipeKode === 'IA05') ? 1 : 8;

    // Verify dokumen exists and is correct
    $dok = DB::table('jabatan_dokumens')->where('id', $dokId)->first();
    if (!$dok) {
        echo "  ✗ Dokumen #{$dokId} not found!\n\n";
        continue;
    }
    echo "  Dokumen: dok#{$dokId} ({$dok->kode}), tipe={$tipe} ({$tipeKode})\n";

    $existingNos = getExistingNos($dokId, $tipe);
    echo "  Status: " . count($existingNos) . "/{$jsonCount} soal exist in DB\n";

    $imported = 0;
    $skipped = 0;
    $unitNotFound = 0;

    foreach ($sectionData as $soal) {
        $no = (int)$soal['no'];

        // Skip if this soal number already exists
        if (in_array($no, $existingNos)) {
            $skipped++;
            continue;
        }

        // Extract kode_unit
        $kodeUnit = '';
        if ($tipeKode === 'IA05') {
            // IA05 uses kode_kuk with format "UNIT_CODE\nKUK_NUMBER" or "UNIT_CODE KUK_NUMBER"
            $kodeKuk = $soal['kode_kuk'] ?? '';
            if (!empty($kodeKuk)) {
                $kodeKuk = cleanKodeUnit($kodeKuk);
                // Split by whitespace (space or newline)
                $parts = preg_split('/\s+/', $kodeKuk, 2);
                $kodeUnit = $parts[0] ?? '';
            }
        } else {
            // IA06 uses kode_kuk or kode_unit
            $kodeKuk = $soal['kode_kuk'] ?? '';
            $kodeUnitRaw = $soal['kode_unit'] ?? '';
            if (!empty($kodeKuk)) {
                $kodeKuk = cleanKodeUnit($kodeKuk);
                $parts = preg_split('/\s+/', $kodeKuk, 2);
                $kodeUnit = $parts[0] ?? '';
            } elseif (!empty($kodeUnitRaw)) {
                $cleaned = cleanKodeUnit($kodeUnitRaw);
                $lines = array_values(array_filter(array_map('trim', explode("\n", $cleaned))));
                $kodeUnit = $lines[0] ?? '';
            }
        }

        // Clean
        $kodeUnit = rtrim($kodeUnit, '.');

        $idUnitKompetensi = resolveUnitId($kodeUnit);
        if (!$idUnitKompetensi && !empty($kodeUnit)) {
            $unitNotFound++;
            if ($unitNotFound <= 3) {
                echo "  ⚠ kode_unit not found: '{$kodeUnit}' (no={$no})\n";
            }
            // Still import with null unit (can fix later)
        }

        if (!$isRun) {
            $imported++;
            continue; // Dry run: count but don't insert
        }

        $data = [
            'id_dokumen' => $dokId,
            'id_unitkompetensi' => $idUnitKompetensi,
            'no' => $soal['no'],
            'urut' => $soal['no'],
            'tipe' => $tipe,
            'jenis' => $tipeKode === 'IA05' ? 'IA.5' : 'IA.6',
            'soal' => $soal['soal'] ?? '',
            'is_komentar' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($tipeKode === 'IA05') {
            // Pilihan ganda fields
            $data['jawaban'] = strtoupper($soal['jawaban'] ?? '');
            $data['jawab_a'] = $soal['jawab_a'] ?? null;
            $data['jawab_b'] = $soal['jawab_b'] ?? null;
            $data['jawab_c'] = $soal['jawab_c'] ?? null;
            $data['jawab_d'] = $soal['jawab_d'] ?? null;
            if (!empty($soal['lingkup'])) {
                $data['soal1'] = $soal['lingkup'];
            }
        } else {
            // IA06 essay
            $data['soal1'] = $soal['lingkup'] ?? null;
        }

        KanSoal::create($data);
        $imported++;
    }

    echo "  → To import: {$imported} soal\n";
    echo "  → Already exist (skip): {$skipped} soal\n";
    if ($unitNotFound > 0) {
        echo "  → Kode unit not found: {$unitNotFound} soal (imported with null unit)\n";
    }
    echo "\n";

    $grandTotal['imported'] += $imported;
    $grandTotal['skipped'] += $skipped;
    $grandTotal['unit_not_found'] += $unitNotFound;
}

echo str_repeat("═", 70) . "\n";
echo "  TOTAL\n";
echo "  To import: {$grandTotal['imported']} soal\n";
echo "  Already exist: {$grandTotal['skipped']} soal\n";
if ($grandTotal['unit_not_found'] > 0) {
    echo "  Unit not found: {$grandTotal['unit_not_found']} soal\n";
}

if (!$isRun) {
    echo "\n  (DRY RUN — no changes made. Run with --run to import.)\n";
    echo "  php import_missing_soal.php --run\n";
} else {
    echo "\n  Import complete!\n";
}
echo str_repeat("═", 70) . "\n";

echo "\nDone.\n";
