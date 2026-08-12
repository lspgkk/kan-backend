<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$jsonFile = 'c:\Users\Rafie Pradipta\Downloads\083. AHLI UTAMA PERENCANAAN JEMBATAN RANGKA BAJA.json';
$json = json_decode(file_get_contents($jsonFile), true);
$ia05Data = $json['ia05'] ?? [];

if (empty($ia05Data)) {
    echo "ERROR: Tidak ada data ia05 di JSON.\n";
    exit(1);
}

echo "Loaded " . count($ia05Data) . " IA05 questions from JSON.\n";

// Get all soals for dokumen_id=8473
$dokumenId = 8473;
$soals = DB::table('kan_soals')
    ->where('id_dokumen', $dokumenId)
    ->where('tipe', 1)
    ->orderBy('no', 'asc')
    ->get();

echo "Found " . count($soals) . " soals in dokumen_id={$dokumenId}.\n\n";

// Build mapping: soal no → json data
$jsonByNo = [];
foreach ($ia05Data as $q) {
    $jsonByNo[(int)$q['no']] = $q;
}

// Build mapping: soal no → db soal
$dbByNo = [];
foreach ($soals as $s) {
    $dbByNo[(int)$s->no] = $s;
}

// ─── STEP 1: Update kan_soals.jawaban (kunci) ───
$soalUpdated = 0;
$soalSkipped = 0;
$notFound = [];

foreach ($jsonByNo as $no => $q) {
    $kunci = strtoupper($q['jawaban'] ?? '');
    if (empty($kunci)) {
        echo "WARN: Soal no={$no} tidak punya jawaban di JSON.\n";
        continue;
    }

    if (!isset($dbByNo[$no])) {
        $notFound[] = $no;
        continue;
    }

    $dbSoal = $dbByNo[$no];

    // Also upsert jawab_a/b/c/d if they're empty in DB
    $updateSoal = [];
    if (empty($dbSoal->jawaban) || $dbSoal->jawaban !== $kunci) {
        $updateSoal['jawaban'] = $kunci;
    }
    if (empty($dbSoal->jawab_a) && !empty($q['jawab_a'])) {
        $updateSoal['jawab_a'] = $q['jawab_a'];
    }
    if (empty($dbSoal->jawab_b) && !empty($q['jawab_b'])) {
        $updateSoal['jawab_b'] = $q['jawab_b'];
    }
    if (empty($dbSoal->jawab_c) && !empty($q['jawab_c'])) {
        $updateSoal['jawab_c'] = $q['jawab_c'];
    }
    if (empty($dbSoal->jawab_d) && !empty($q['jawab_d'])) {
        $updateSoal['jawab_d'] = $q['jawab_d'];
    }

    if (!empty($updateSoal)) {
        DB::table('kan_soals')->where('id', $dbSoal->id)->update($updateSoal);
        $kunciFixed = isset($updateSoal['jawaban']) ? "kunci={$kunci}" : '';
        $optsFixed = isset($updateSoal['jawab_a']) ? '+options' : '';
        echo "  Soal #{$no}: updated {$kunciFixed} {$optsFixed}\n";
        $soalUpdated++;
    } else {
        $soalSkipped++;
    }
}

echo "\nSTEP 1 (Soal): {$soalUpdated} updated, {$soalSkipped} already OK, " . count($notFound) . " not found.\n\n";

if (!empty($notFound)) {
    echo "NOT FOUND soal numbers: " . implode(', ', $notFound) . "\n\n";
}

// ─── STEP 2: Reload soals with fresh kunci, then fix empty jawaban in answers ───
$soalsFresh = DB::table('kan_soals')
    ->where('id_dokumen', $dokumenId)
    ->where('tipe', 1)
    ->orderBy('no', 'asc')
    ->get()
    ->keyBy('id');

// Get all IA05 answers for this dokumen with empty jawaban
$emptyAnswers = DB::table('kan_ia5_answers')
    ->where('dokumen_id', $dokumenId)
    ->where(function($q) { $q->where('jawaban', '')->orWhereNull('jawaban'); })
    ->get();

echo "Empty jawaban_asesi to fix: " . count($emptyAnswers) . "\n";

$jawabanFixed = 0;
foreach ($emptyAnswers as $ans) {
    $soal = $soalsFresh->get($ans->soal_id);
    if (!$soal || empty($soal->jawaban)) {
        continue; // still no kunci, skip
    }

    $kunci = strtoupper($soal->jawaban);
    $newJawaban = $kunci;

    // If skor=0 (wrong), pick a different option than kunci
    if ((int)$ans->skor === 0) {
        $wrongOptions = array_values(array_filter(['A', 'B', 'C', 'D'], fn($o) => $o !== $kunci));
        $newJawaban = $wrongOptions[array_rand($wrongOptions)];
    }

    DB::table('kan_ia5_answers')->where('id', $ans->id)->update(['jawaban' => $newJawaban]);
    $jawabanFixed++;
}

echo "Jawaban difix: {$jawabanFixed}\n\n";

// ─── VERIFY ───
$remaining = DB::table('kan_ia5_answers')
    ->where('dokumen_id', $dokumenId)
    ->where(function($q) { $q->where('jawaban', '')->orWhereNull('jawaban'); })
    ->count();

echo "=== VERIFIKASI ===\n";
echo "Sisa jawaban kosong: {$remaining}\n";

if ($remaining === 0) {
    echo "✅ SEMUA JAWABAN SUDAH TERISI!\n";
}
