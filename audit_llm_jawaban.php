<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== AUDIT LLM JAWABAN: DUPLICATE CHECK ===\n\n";

foreach (['kan_ia6_answers' => 'IA06 (Esai)', 'kan_ia4b_answers' => 'IA04B (Lisan)'] as $table => $label) {
    echo "── {$label} ──\n";

    // Find duplicates: same dokumen_id + soal_id + jawaban text
    $dupes = DB::table("{$table} as a1")
        ->join("{$table} as a2", function($join) {
            $join->on('a1.dokumen_id', '=', 'a2.dokumen_id')
                ->on('a1.soal_id', '=', 'a2.soal_id')
                ->on('a1.jawaban', '=', 'a2.jawaban')
                ->on('a1.id', '<', 'a2.id');
        })
        ->where('a1.jawaban', '!=', '')
        ->whereNotNull('a1.jawaban')
        ->select('a1.dokumen_id', 'a1.soal_id', 'a1.jawaban', DB::raw('COUNT(*) + 1 as dup_count'))
        ->groupBy('a1.dokumen_id', 'a1.soal_id', 'a1.jawaban')
        ->orderBy('dup_count', 'desc')
        ->get();

    if ($dupes->isEmpty()) {
        echo "  ✅ Tidak ada duplikat.\n\n";
        continue;
    }

    echo "  ⚠ " . count($dupes) . " group duplikat ditemukan.\n";

    // Count total affected answers
    $totalAffected = 0;
    $byDokumen = [];
    foreach ($dupes as $d) {
        // Count how many answers share this same text in this dokumen+soal
        $count = DB::table($table)
            ->where('dokumen_id', $d->dokumen_id)
            ->where('soal_id', $d->soal_id)
            ->where('jawaban', $d->jawaban)
            ->count();
        $totalAffected += $count;

        if (!isset($byDokumen[$d->dokumen_id])) {
            $byDokumen[$d->dokumen_id] = 0;
        }
        $byDokumen[$d->dokumen_id] += ($count - 1); // how many need regeneration (all except one)
    }

    echo "  Total jawaban yang perlu diregenerate: {$totalAffected}\n";
    echo "  By dokumen_id:\n";
    foreach ($byDokumen as $dokId => $cnt) {
        // Find jabatan for this dokumen
        $jd = DB::table('jabatan_dokumens')->where('id', $dokId)->first();
        $jkName = '???';
        if ($jd) {
            $jkName = DB::table('jabatan_kerjas')->where('id_jabatan_kerja', $jd->id_jabatan)->value('jabatan_kerja') ?? '???';
        }
        echo "    dok#{$dokId} ({$jkName}): {$cnt} jawaban\n";
    }

    // Show top dupes
    echo "\n  Top 10 duplikat terbesar:\n";
    $shown = 0;
    foreach ($dupes as $d) {
        if ($shown++ >= 10) break;
        $preview = mb_substr($d->jawaban, 0, 80);
        echo "    dok#{$d->dokumen_id} soal#{$d->soal_id}: {$d->dup_count}× identik | \"{$preview}...\"\n";
    }
    echo "\n";
}

// Also check for auto-generated placeholder text
echo "── PLACEHOLDER TEXT CHECK ──\n";
foreach (['kan_ia6_answers' => 'IA06', 'kan_ia4b_answers' => 'IA04B'] as $table => $label) {
    $placeholders = DB::table($table)
        ->whereIn('jawaban', ['Jawaban benar (auto)', 'Jawaban salah (auto)'])
        ->count();
    echo "  {$label}: {$placeholders} placeholder texts\n";
}

echo "\nDone.\n";
