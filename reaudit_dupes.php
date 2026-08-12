<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== RE-AUDIT: POST-REGENERATION DUPLICATE CHECK ===\n\n";

foreach (['kan_ia6_answers' => 'IA06 (Esai)', 'kan_ia4b_answers' => 'IA04B (Lisan)'] as $table => $label) {
    echo "── {$label} ──\n";

    $dupes = DB::table($table)
        ->where('jawaban', '!=', '')
        ->whereNotNull('jawaban')
        ->select('dokumen_id', 'soal_id', 'jawaban', DB::raw('COUNT(*) as dup_count'))
        ->groupBy('dokumen_id', 'soal_id', 'jawaban')
        ->having('dup_count', '>', 1)
        ->orderBy('dup_count', 'desc')
        ->get();

    if ($dupes->isEmpty()) {
        echo "  ✅ ZERO duplicates remaining!\n\n";
        continue;
    }

    $totalDupes = count($dupes);
    $totalAffected = $dupes->sum('dup_count') - $totalDupes; // minus 1 per group (the kept one)
    echo "  ⚠ {$totalDupes} duplicate groups still exist ({$totalAffected} answers still duplicated)\n";

    // Show top 5
    $shown = 0;
    foreach ($dupes as $d) {
        if ($shown++ >= 5) break;
        $preview = mb_substr($d->jawaban, 0, 80);
        echo "    dok#{$d->dokumen_id} soal#{$d->soal_id}: {$d->dup_count}× identik | \"{$preview}...\"\n";
    }
    echo "\n";
}

// Also check for fallback text still present
echo "── FALLBACK TEXT CHECK ──\n";
foreach (['kan_ia6_answers' => 'IA06', 'kan_ia4b_answers' => 'IA04B'] as $table => $label) {
    $fbEsaiBenar = DB::table($table)->where('jawaban', 'like', 'Berdasarkan analisis teknis dan standar operasional prosedur yang berlaku%')->count();
    $fbLisanBenar = DB::table($table)->where('jawaban', 'like', 'Langkah-langkah kegiatan terstruktur yang harus dilakukan meliputi%')->count();
    echo "  {$label}: {$fbEsaiBenar} esai-benar fallback, {$fbLisanBenar} lisan-benar fallback\n";
}

echo "\nDone.\n";
