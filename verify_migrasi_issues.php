<?php
/**
 * Verifikasi issue dari Excel "MIGRASI DATA" terhadap database
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo str_repeat("=", 70) . "\n";
echo "  VERIFIKASI ISSUE MIGRASI DATA vs DATABASE\n";
echo str_repeat("=", 70) . "\n\n";

// ═══════════════════════════════════════════════════════════════
// LIST ISSUE DARI EXCEL (type → [jabatan names...])
// ═══════════════════════════════════════════════════════════════

// Kategori 1: "IA05 kode unit tidak lengkap" / "IA05, IA06 kode unit tidak lengkap"
$isuKodeUnit = [
    'AHLI MUDA PERENCANA BETON PRACETAK UNTUK STRUKTUR BANGUNAN GEDUNG',
    'AHLI MUDA TEKNIK JALAN',
    'AHLI MADYA TEKNIK JALAN',
    'AHLI MUDA BIDANG KEAHLIAN TEKNIK SUMBER DAYA AIR',
    'AHLI MADYA BIDANG KEAHLIAN TEKNIK SUMBER DAYA AIR',
    'TEKNISI GEOTEKNIK (LEVEL 6)',
    'AHLI UTAMA MATERIAL JALAN',
    'AHLI UTAMA TEKNIK JEMBATAN',
];

// Kategori 2: "IA05/IA06 blm ada nomor KUK" (Revisi 1 & 2)
$isuNoKuk = [
    'SPESIALIS SIG' => 'IA 05: blm ada nomor KUK',
    'ANALIS STRUKTUR BANGUNAN RISHA' => 'IA 05: NO 1-10 BLM ADA NO KUK',
    'PELAKSANA LAPANGAN PEKERJAAN GEDUNG (LEVEL 6)' => 'IA 05 & IA 06',
    'PENGAWAS KONSTRUKSI, FABRIKASI, SIPIL DAN STRUKTUR' => 'IA 05 DAN IA 06',
    'TEKNISI GEOTEKNIK (LEVEL 5)' => 'IA 05: no 1-2',
    'PELAKSANA LAPANGAN PEKERJAAN JALAN (LEVEL 6)' => 'IA 05 DAN IA 06',
    'PELAKSANA LAPANGAN PEKERJAAN JALAN (LEVEL 4)' => 'IA 05: NO. 28-34, 61-80 / IA 06',
    'PELAKSANA LAPANGAN PEKERJAAN JALAN (LEVEL 5)' => 'IA 05 DAN IA 06',
    'PELAKSANA LAPANGAN PEKERJAAN BANGUNAN PENGAMAN PANTAI (LEVEL 5)' => 'IA 05: no 1-39, 53-65',
    // Revisi 2
    'TEKNISI LABORATORIUM BETON ASPAL (LEVEL 4)' => 'Revisi 2: IA 05 dan IA 06',
    'TEKNISI LABORATORIUM TANAH (LEVEL 4)' => 'Revisi 2: IA 05 dan IA 06',
    'PELAKSANA PRODUKSI CAMPURAN ASPAL PANAS (LEVEL 4)' => 'Revisi 2: IA 05 dan IA 06',
    'PELAKSANA LAPANGAN PEKERJAAN BANGUNAN AIR LIMBAH PERMUKIMAN (SETEMPAT DAN TERPUSAT) (LEVEL 4)' => 'Revisi 2',
    'PELAKSANA LAPANGAN PEKERJAAN PEMBANGUNAN JALAN REL (LEVEL 4)' => 'Revisi 2: IA 05 dan IA 06',
    'TEKNISI SONDIR (LEVEL 4)' => 'Revisi 2: IA 05 dan IA 06',
    'TEKNISI PENGEBORAN PENGUJIAN TANAH (LEVEL 4)' => 'Revisi 2: IA 05 dan IA 06',
    'PELAKSANA LAPANGAN PEKERJAAN BANGUNAN PENGAMAN PANTAI (LEVEL 4)' => 'Revisi 2: IA 05 dan IA 06',
    'OPERATOR UTAMA SURVEI TERESTRIS' => 'Revisi 2: IA 05 dan IA 06',
];

// Kategori 3: Jawaban issues
$isuJawaban = [
    'AHLI UTAMA PERENCANAAN JEMBATAN RANGKA BAJA' => 'kolom jawaban IA05 blm lengkap',
    'AHLI UTAMA PERENCANAAN OPERASI DAN PEMELIHARAAN PRASARANA SUNGAI SERTA PEMELIHARAAN SUNGAI' => 'jawaban IA06, IA04B sama semua',
    'AHLI UTAMA BIDANG KEAHLIAN TEKNIK SUMBER DAYA AIR' => 'jawaban IA06, IA04B sama semua',
    'AHLI UTAMA PERENCANAAN JARINGAN DRAINASE' => 'jawaban IA06, IA04B sama semua',
    'AHLI UTAMA GEOLOGI PEKERJAAN KONSTRUKSI' => 'jawaban IA04B sama semua',
];

// Kategori 4: Soal count issues
$isuSoalCount = [
    'AHLI MUDA GROUTING' => 'IA 05: cuma ada 12 soal',
    'AHLI MADYA HIDROGRAFI LEPAS PANTAI' => 'IA 05: tidak ada soal dan jawabannya',
    'AHLI MADYA SISTEM INFORMASI GEOGRAFIS' => 'IA 05: hanya ada 50 soal',
];

// Kategori 5: Specific soal issues
$isuSoalSpesifik = [
    'AHLI MUDA TEKNIK RAWA' => 'IA 06: soal no 11 tidak ada kode unit',
    'AHLI MADYA TEKNIK BENDUNGAN BESAR' => 'IA 05: ada soal yang tidak ada kode unit',
];

// Kategori 6: Revisi 2 parah
$isuRevisi2Parah = [
    'PELAKSANA PENGEBORAN AIR TANAH (LEVEL 4)' => 'IA 05: gada kode unit, pilihan jawaban abcd tidak ada / IA 06: gada jawaban dan soal',
    'JURU GAMBAR KEPALA BIDANG KONSTRUKSI' => 'Revisi 2: IA 04, IA 05, dan IA 06: blm ada barcode dan nama penyusun',
];

// ═══════════════════════════════════════════════════════════════
// HELPER
// ═══════════════════════════════════════════════════════════════
function findJabatanId(string $name): ?string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $rows = DB::table('jabatan_kerjas')->select('id_jabatan_kerja', 'jabatan_kerja')->get();
        foreach ($rows as $r) {
            $cache[mb_strtolower(trim($r->jabatan_kerja))] = $r->id_jabatan_kerja;
        }
    }
    return $cache[mb_strtolower(trim($name))] ?? null;
}

function getDokumenId(string $jabatanId, string $kode): ?int {
    static $cache = [];
    $key = $jabatanId . '|' . $kode;
    if (!isset($cache[$key])) {
        $d = DB::table('jabatan_dokumens')
            ->where('id_jabatan', $jabatanId)
            ->where('kode', $kode)
            ->where('is_active', true)
            ->first();
        $cache[$key] = $d ? $d->id : null;
    }
    return $cache[$key];
}

// ═══════════════════════════════════════════════════════════════
// 1. Cek "kode unit tidak lengkap" → id_unitkompetensi NULL
// ═══════════════════════════════════════════════════════════════
echo "━━━ 1. KODE UNIT TIDAK LENGKAP ━━━\n";
echo "   (Cek id_unitkompetensi NULL di kan_soals)\n\n";

$semuaKodeUnit = [];
foreach ($isuKodeUnit as $nama) {
    $jkId = findJabatanId($nama);
    if (!$jkId) { echo "  ✗ NOT FOUND: $nama\n"; continue; }

    $issues = [];
    foreach (['FR.IA.05' => 'IA05', 'FR.IA.06' => 'IA06'] as $kode => $label) {
        $dokId = getDokumenId($jkId, $kode);
        if (!$dokId) continue;

        $nullCount = DB::table('kan_soals')
            ->where('id_dokumen', $dokId)
            ->whereNull('id_unitkompetensi')
            ->count();

        if ($nullCount > 0) {
            $issues[] = "$label: $nullCount soal null";
        }
    }

    $status = empty($issues) ? '✅ SOLVED' : '❌ STILL ISSUE';
    echo "  $status | $nama\n";
    foreach ($issues as $iss) {
        echo "         └─ $iss\n";
    }

    if (empty($issues)) {
        $semuaKodeUnit[] = $nama;
    }
}

$masihKodeUnit = count($isuKodeUnit) - count($semuaKodeUnit);
echo "\n  → " . count($semuaKodeUnit) . "/" . count($isuKodeUnit) . " solved\n\n";

// ═══════════════════════════════════════════════════════════════
// 2. Cek "blm ada nomor KUK" → id_kuk NULL
// ═══════════════════════════════════════════════════════════════
echo "━━━ 2. BELUM ADA NOMOR KUK ━━━\n";
echo "   (Cek id_kuk NULL di kan_soals)\n\n";

$semuaNoKuk = [];
$masihNoKuk = [];
foreach ($isuNoKuk as $nama => $ket) {
    $jkId = findJabatanId($nama);
    if (!$jkId) { echo "  ✗ NOT FOUND: $nama\n"; $masihNoKuk[$nama] = 'Jabatan tidak ditemukan'; continue; }

    $issues = [];
    foreach (['FR.IA.05' => 'IA05', 'FR.IA.06' => 'IA06'] as $kode => $label) {
        $dokId = getDokumenId($jkId, $kode);
        if (!$dokId) continue;

        $nullCount = DB::table('kan_soals')
            ->where('id_dokumen', $dokId)
            ->whereNull('id_kuk')
            ->count();

        $totalSoal = DB::table('kan_soals')
            ->where('id_dokumen', $dokId)
            ->count();

        if ($nullCount > 0) {
            $issues[] = "$label: $nullCount/$totalSoal soal null KUK";
        }
    }

    $status = empty($issues) ? '✅ SOLVED' : '❌ STILL ISSUE';
    echo "  $status | $nama ($ket)\n";
    foreach ($issues as $iss) {
        echo "         └─ $iss\n";
    }

    if (empty($issues)) {
        $semuaNoKuk[] = $nama;
    } else {
        $masihNoKuk[$nama] = implode('; ', $issues);
    }
}

echo "\n  → " . count($semuaNoKuk) . "/" . count($isuNoKuk) . " solved\n\n";

// ═══════════════════════════════════════════════════════════════
// 3. Cek jawaban issues
// ═══════════════════════════════════════════════════════════════
echo "━━━ 3. JAWABAN ISSUES ━━━\n\n";

foreach ($isuJawaban as $nama => $ket) {
    $jkId = findJabatanId($nama);
    if (!$jkId) { echo "  ✗ NOT FOUND: $nama\n"; continue; }

    $issues = [];

    // Cek empty jawaban di IA05
    if (strpos($ket, 'IA05') !== false) {
        $dokId = getDokumenId($jkId, 'FR.IA.05');
        if ($dokId) {
            $emptyCount = DB::table('kan_ia5_answers')
                ->where('dokumen_id', $dokId)
                ->where(function($q) { $q->whereNull('jawaban')->orWhere('jawaban', ''); })
                ->count();
            if ($emptyCount > 0) $issues[] = "IA05: $emptyCount jawaban kosong";
        }
    }

    // Cek duplicate jawaban di IA06 & IA04B
    if (strpos($ket, 'IA06') !== false || strpos($ket, 'IA04B') !== false) {
        foreach (['FR.IA.06' => 'IA06', 'FR.IA.04.B' => 'IA04B'] as $kode => $label) {
            if (strpos($ket, $label) === false && strpos($ket, 'sama semua') === false) continue;
            $dokId = getDokumenId($jkId, $kode);
            if (!$dokId) continue;

            $table = $label === 'IA06' ? 'kan_ia6_answers' : 'kan_ia4b_answers';

            // Cek apakah semua jawaban identik (duplicate LLM)
            $uniqueJawaban = DB::table($table)
                ->where('dokumen_id', $dokId)
                ->whereNotNull('jawaban')
                ->where('jawaban', '!=', '')
                ->distinct()
                ->count('jawaban');

            $totalJawaban = DB::table($table)
                ->where('dokumen_id', $dokId)
                ->whereNotNull('jawaban')
                ->where('jawaban', '!=', '')
                ->count();

            if ($totalJawaban > 0 && $uniqueJawaban <= 2) {
                $issues[] = "$label: hanya $uniqueJawaban jawaban unik dari $totalJawaban (kemungkinan duplikat LLM)";
            } elseif ($totalJawaban == 0) {
                $issues[] = "$label: tidak ada jawaban";
            }
        }
    }

    $status = empty($issues) ? '✅ SOLVED' : '❌ STILL ISSUE';
    echo "  $status | $nama ($ket)\n";
    foreach ($issues as $iss) {
        echo "         └─ $iss\n";
    }
}
echo "\n";

// ═══════════════════════════════════════════════════════════════
// 4. Cek soal count
// ═══════════════════════════════════════════════════════════════
echo "━━━ 4. SOAL COUNT ISSUES ━━━\n\n";

foreach ($isuSoalCount as $nama => $ket) {
    $jkId = findJabatanId($nama);
    if (!$jkId) { echo "  ✗ NOT FOUND: $nama\n"; continue; }

    $dokId = getDokumenId($jkId, 'FR.IA.05');
    $count = $dokId ? DB::table('kan_soals')->where('id_dokumen', $dokId)->count() : 0;

    $expected = strpos($ket, '12') !== false ? 12 : (strpos($ket, '50') !== false ? 50 : 100);
    $status = $count > $expected ? '✅ SOLVED' : '❌ STILL ISSUE';
    echo "  $status | $nama ($ket) → now: $count soal\n";
}
echo "\n";

// ═══════════════════════════════════════════════════════════════
// 5. Cek soal spesifik
// ═══════════════════════════════════════════════════════════════
echo "━━━ 5. SOAL SPESIFIK ISSUES ━━━\n\n";

foreach ($isuSoalSpesifik as $nama => $ket) {
    $jkId = findJabatanId($nama);
    if (!$jkId) { echo "  ✗ NOT FOUND: $nama\n"; continue; }

    if (strpos($ket, 'IA 06') !== false) {
        $dokId = getDokumenId($jkId, 'FR.IA.06');
        if ($dokId) {
            $soalNo11 = DB::table('kan_soals')
                ->where('id_dokumen', $dokId)
                ->where('no', 11)
                ->first();
            if ($soalNo11) {
                $status = !is_null($soalNo11->id_unitkompetensi) ? '✅ SOLVED' : '❌ STILL ISSUE';
                echo "  $status | $nama → soal no=11 id_unitkompetensi=" . ($soalNo11->id_unitkompetensi ?? 'NULL') . "\n";
            } else {
                echo "  ⚠ WARN | $nama → soal no=11 not found\n";
            }
        }
    }
    if (strpos($ket, 'IA 05') !== false) {
        $dokId = getDokumenId($jkId, 'FR.IA.05');
        if ($dokId) {
            $nullCount = DB::table('kan_soals')
                ->where('id_dokumen', $dokId)
                ->whereNull('id_unitkompetensi')
                ->count();
            $status = $nullCount === 0 ? '✅ SOLVED' : '❌ STILL ISSUE';
            echo "  $status | $nama → $nullCount soal tanpa id_unitkompetensi\n";
        }
    }
}
echo "\n";

// ═══════════════════════════════════════════════════════════════
// 6. Cek Revisi 2 parah
// ═══════════════════════════════════════════════════════════════
echo "━━━ 6. REVISI 2 PARAH ━━━\n\n";

foreach ($isuRevisi2Parah as $nama => $ket) {
    $jkId = findJabatanId($nama);
    if (!$jkId) { echo "  ✗ NOT FOUND: $nama\n"; continue; }

    $issues = [];

    if (strpos($ket, 'IA 05') !== false) {
        $dokId = getDokumenId($jkId, 'FR.IA.05');
        if ($dokId) {
            $soalCount = DB::table('kan_soals')->where('id_dokumen', $dokId)->count();
            $nullUnit = DB::table('kan_soals')->where('id_dokumen', $dokId)->whereNull('id_unitkompetensi')->count();
            $noJawaban = DB::table('kan_soals')->where('id_dokumen', $dokId)->where(function($q) {
                $q->whereNull('jawab_a')->orWhere('jawab_a', '');
            })->count();
            if ($soalCount == 0) $issues[] = "IA05: tidak ada soal";
            if ($nullUnit > 0) $issues[] = "IA05: $nullUnit soal null unit";
            if ($noJawaban > 0) $issues[] = "IA05: $noJawaban soal tanpa pilihan jawaban";
        }
    }
    if (strpos($ket, 'IA 06') !== false) {
        $dokId = getDokumenId($jkId, 'FR.IA.06');
        if ($dokId) {
            $soalCount = DB::table('kan_soals')->where('id_dokumen', $dokId)->count();
            $jawabanCount = DB::table('kan_ia6_answers')->where('dokumen_id', $dokId)->count();
            if ($soalCount == 0) $issues[] = "IA06: tidak ada soal";
            if ($jawabanCount == 0) $issues[] = "IA06: tidak ada jawaban";
        }
    }

    $status = empty($issues) ? '✅ SOLVED' : '❌ STILL ISSUE';
    echo "  $status | $nama ($ket)\n";
    foreach ($issues as $iss) {
        echo "         └─ $iss\n";
    }
}

// ═══════════════════════════════════════════════════════════════
// RINGKASAN AKHIR
// ═══════════════════════════════════════════════════════════════
echo "\n";
echo str_repeat("=", 70) . "\n";
echo "  RINGKASAN ISSUE YANG BELUM SOLVED\n";
echo str_repeat("=", 70) . "\n\n";

// Kumpulkan semua yang masih issue
$pendingIssues = [];

// 1. Kode unit
$sisaKodeUnit = array_diff($isuKodeUnit, $semuaKodeUnit);
foreach ($sisaKodeUnit as $nama) {
    $pendingIssues[] = ['Jabatan' => $nama, 'Issue' => 'Kode unit tidak lengkap', 'Kategori' => 'IA05/IA06'];
}

// 2. No KUK
foreach ($masihNoKuk as $nama => $detail) {
    $pendingIssues[] = ['Jabatan' => $nama, 'Issue' => "Belum ada nomor KUK: $detail", 'Kategori' => 'IA05/IA06'];
}

// Display table
if (empty($pendingIssues)) {
    echo "  ✅ SEMUA ISSUE SUDAH SOLVED!\n";
} else {
    echo str_pad("#", 4) . str_pad("Jabatan Kerja", 55) . str_pad("Issue", 30) . "Kategori\n";
    echo str_repeat("─", 110) . "\n";
    $no = 1;
    foreach ($pendingIssues as $iss) {
        echo str_pad($no, 4)
            . str_pad(mb_substr($iss['Jabatan'], 0, 53), 55)
            . str_pad(mb_substr($iss['Issue'], 0, 28), 30)
            . $iss['Kategori'] . "\n";
        $no++;
    }
    echo "\nTotal pending issues: " . count($pendingIssues) . "\n";
}

echo "\nDone.\n";
