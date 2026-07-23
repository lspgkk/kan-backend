<?php

namespace App\Jobs;

use App\Models\JabatanDokumen;
use App\Models\JabatanKerja;
use App\Models\KanIa4bAnswer;
use App\Models\KanIa5Answer;
use App\Models\KanIa6Answer;
use App\Models\KanPraktisiJabatan;
use App\Models\KanSoal;
use App\Models\User;
use App\Services\LlmAutoFillService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;

class GenerateJawabanKanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const PRAKTISI_KEYS = ['A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A8', 'A9', 'A10'];
    private const ROLE_PRAKTISI = 16;

    public int $timeout = 600; // 10 minutes per job
    public int $tries = 2;

    private array $record;

    public function __construct(array $record)
    {
        $this->record = $record;
    }

    public function handle(): void
    {
        $record = $this->record;
        $skemaName = trim($record['skema'] ?? '');
        $noSkema = $record['no_skema'] ?? '';

        // 1. Resolve jabatan kerja
        $jabatan = JabatanKerja::where('jabatan_kerja', $skemaName)
            ->orderBy('id_jabatan_kerja', 'desc')
            ->first();
        if (!$jabatan) {
            logger()->info("[GenerateJawabanKanJob] Skema '{$skemaName}' tidak ditemukan, skip.");
            return;
        }
        $idJabatanKerja = $jabatan->id_jabatan_kerja;

        // 2. Resolve dokumens
        $dokumenKodes = [
            'ia04b' => 'FR.IA.04.B',
            'ia05'  => 'FR.IA.05',
            'ia06'  => 'FR.IA.06',
        ];
        $dokumenNama = [
            'FR.IA.04.B' => 'LEMBAR PERIKSA KEGIATAN TERSTRUKTUR',
            'FR.IA.05' => 'PERTANYAAN TERTULIS PILIHAN GANDA',
            'FR.IA.06' => 'PERTANYAAN TERTULIS ESAI',
        ];

        $dokumens = [];
        foreach ($dokumenKodes as $key => $kode) {
            $dok = JabatanDokumen::where('id_jabatan', $idJabatanKerja)
                ->where('kode', $kode)
                ->where('is_active', true)
                ->first();
            if (!$dok) {
                $dok = JabatanDokumen::create([
                    'id_jabatan' => $idJabatanKerja,
                    'kode' => $kode,
                    'nama_dokumen' => $dokumenNama[$kode],
                    'is_active' => true,
                ]);
            }
            $dokumens[$key] = $dok;
        }

        // 3. Resolve praktisi users & kan_praktisi_jabatan
        $asesorNames = $record['asesor_names'] ?? [];
        $praktisiJabatanIds = [];
        foreach (self::PRAKTISI_KEYS as $key) {
            $name = trim($asesorNames[$key] ?? '');
            if (empty($name)) continue;

            // Find or create user
            $user = User::where('name', $name)->where('role_id', self::ROLE_PRAKTISI)->first();
            if (!$user) {
                $email = $this->generateEmailFromName($name);
                $user = User::create([
                    'name' => $name, 'email' => $email, 'phone' => '0',
                    'role_id' => self::ROLE_PRAKTISI, 'password' => Hash::make('123qweasd'),
                ]);
            }

            // Find or create praktisi jabatan
            $pj = KanPraktisiJabatan::firstOrCreate(
                ['id_jabatan_kerja' => $idJabatanKerja, 'id_user' => $user->id]
            );
            $praktisiJabatanIds[$key] = $pj->id;
        }

        $scores = $record['scores'] ?? [];
        $llm = app(LlmAutoFillService::class);
        $timestamp = now()->toDateTimeString();

        // ═══════════════════════════════════════
        // IA05: Pilihan Ganda (no LLM needed)
        // ═══════════════════════════════════════
        if (!empty($dokumens['ia05']) && !empty($scores['Pg'])) {
            $expectedCount = count($scores['Pg']) * count($praktisiJabatanIds);
            $existing = KanIa5Answer::where('dokumen_id', $dokumens['ia05']->id)
                ->whereIn('kan_praktisi_jabatan_id', $praktisiJabatanIds)->count();

            if ($existing < $expectedCount) {
                $this->generateIa05($dokumens['ia05'], $scores['Pg'], $praktisiJabatanIds, $timestamp);
            }
        }

        // ═══════════════════════════════════════
        // IA06: Esai (LLM)
        // ═══════════════════════════════════════
        if (!empty($dokumens['ia06']) && !empty($scores['Esai']) && is_array($scores['Esai'])) {
            $expectedCount = count($scores['Esai']) * count($praktisiJabatanIds);
            $existing = KanIa6Answer::where('dokumen_id', $dokumens['ia06']->id)
                ->whereIn('kan_praktisi_jabatan_id', $praktisiJabatanIds)->count();

            if ($existing < $expectedCount) {
                $this->generateIa06($dokumens['ia06'], $scores['Esai'], $praktisiJabatanIds, $asesorNames, $skemaName, $noSkema, $llm, $timestamp);
            }
        }

        // ═══════════════════════════════════════
        // IA04B: Lisan (LLM)
        // ═══════════════════════════════════════
        if (!empty($dokumens['ia04b']) && !empty($scores['Lisan']) && is_array($scores['Lisan'])) {
            $expectedCount = count($scores['Lisan']) * count($praktisiJabatanIds);
            $existing = KanIa4bAnswer::where('dokumen_id', $dokumens['ia04b']->id)
                ->whereIn('kan_praktisi_jabatan_id', $praktisiJabatanIds)->count();

            if ($existing < $expectedCount) {
                $this->generateIa04b($dokumens['ia04b'], $scores['Lisan'], $praktisiJabatanIds, $asesorNames, $skemaName, $noSkema, $llm, $timestamp);
            }
        }

        logger()->info("[GenerateJawabanKanJob] Selesai: {$noSkema} — {$skemaName}");
    }

    // ═══════════════════════════════════════════
    //  IA05: PILIHAN GANDA
    // ═══════════════════════════════════════════

    private function generateIa05($dokumen, array $pgScores, array $praktisiJabatanIds, string $timestamp): void
    {
        $soals = KanSoal::where('id_dokumen', $dokumen->id)->where('tipe', 1)->orderBy('no')->get()->all();
        $rows = [];

        foreach ($pgScores as $i => $scoreEntry) {
            if ($i >= count($soals)) break;
            $soal = $soals[$i];
            $kunci = strtoupper($soal->jawaban ?? '');
            $wrongOptions = array_values(array_filter(['A', 'B', 'C', 'D'], fn($o) => $o !== $kunci));

            foreach (self::PRAKTISI_KEYS as $key) {
                if (!isset($praktisiJabatanIds[$key])) continue;
                $value = (int) ($scoreEntry[$key] ?? 0);
                $jawaban = $value === 1 ? $kunci : $wrongOptions[array_rand($wrongOptions)];

                $rows[] = [
                    'kan_praktisi_jabatan_id' => $praktisiJabatanIds[$key],
                    'dokumen_id'              => $dokumen->id,
                    'soal_id'                 => $soal->id,
                    'jawaban'                 => $jawaban,
                    'skor'                    => $value === 1 ? 1 : 0,
                    'updated_at'              => $timestamp,
                    'created_at'              => $timestamp,
                ];
            }
        }

        if (!empty($rows)) {
            KanIa5Answer::upsert($rows, ['kan_praktisi_jabatan_id', 'dokumen_id', 'soal_id'], ['jawaban', 'skor', 'updated_at']);
        }
    }

    // ═══════════════════════════════════════════
    //  IA06: ESAI
    // ═══════════════════════════════════════════

    private function generateIa06($dokumen, array $esaiScores, array $praktisiJabatanIds, array $asesorNames, string $skemaName, string $noSkema, LlmAutoFillService $llm, string $timestamp): void
    {
        $soals = KanSoal::where('id_dokumen', $dokumen->id)->where('tipe', 8)->orderBy('no')->get()->all();
        $chunkSize = 5;
        $soalChunks = array_chunk($esaiScores, $chunkSize, true);
        $rows = [];

        foreach ($soalChunks as $chunk) {
            $fields = [];
            $fieldKeys = [];

            foreach ($chunk as $i => $scoreEntry) {
                if ($i >= count($soals)) break 2;
                $soal = $soals[$i];

                foreach (self::PRAKTISI_KEYS as $key) {
                    if (!isset($praktisiJabatanIds[$key])) continue;
                    $value = (int) ($scoreEntry[$key] ?? 0);
                    $fieldType = $value === 1 ? 'kan_esai_benar' : 'kan_esai_salah';
                    $userName = $asesorNames[$key] ?? $key;

                    $fieldIdx = count($fields);
                    $fields[] = [$fieldType, [
                        'nama_asesi' => $userName, 'jabatan_kerja' => $skemaName,
                        'nomor_skema' => $noSkema, 'asesor' => 'Asesor KAN',
                        'soal' => $soal->soal ?? '',
                    ]];
                    $fieldKeys[$fieldIdx] = [$soal->id, $praktisiJabatanIds[$key], $value];
                }
            }

            if (empty($fields)) continue;

            $llmResults = $llm->generateBatchTexts($fields);

            foreach ($fieldKeys as $fieldIdx => [$soalId, $kpjId, $value]) {
                $fieldType = $value === 1 ? 'kan_esai_benar' : 'kan_esai_salah';
                $resultKey = $fieldType . "_{$fieldIdx}";
                $jawaban = $llmResults[$resultKey] ?? $llm->getFallback($fieldType);

                $rows[] = [
                    'kan_praktisi_jabatan_id' => $kpjId,
                    'dokumen_id'              => $dokumen->id,
                    'soal_id'                 => $soalId,
                    'jawaban'                 => $jawaban,
                    'skor'                    => $value === 1 ? random_int(2, 3) : 0,
                    'updated_at'              => $timestamp,
                    'created_at'              => $timestamp,
                ];
            }
        }

        if (!empty($rows)) {
            KanIa6Answer::upsert($rows, ['kan_praktisi_jabatan_id', 'dokumen_id', 'soal_id'], ['jawaban', 'skor', 'updated_at']);
        }
    }

    // ═══════════════════════════════════════════
    //  IA04B: LISAN
    // ═══════════════════════════════════════════

    private function generateIa04b($dokumen, array $lisanScores, array $praktisiJabatanIds, array $asesorNames, string $skemaName, string $noSkema, LlmAutoFillService $llm, string $timestamp): void
    {
        $soals = KanSoal::where('id_dokumen', $dokumen->id)->where('tipe', 8)->orderBy('no')->get()->all();
        $chunkSize = 5;
        $soalChunks = array_chunk($lisanScores, $chunkSize, true);
        $rows = [];

        foreach ($soalChunks as $chunk) {
            $fields = [];
            $fieldKeys = [];

            foreach ($chunk as $i => $scoreEntry) {
                if ($i >= count($soals)) break 2;
                $soal = $soals[$i];
                $soalText = implode("\n", array_filter([$soal->soal ?? '', $soal->soal1 ?? '', $soal->soal2 ?? '']));

                foreach (self::PRAKTISI_KEYS as $key) {
                    if (!isset($praktisiJabatanIds[$key])) continue;
                    $value = (int) ($scoreEntry[$key] ?? 0);
                    $fieldType = $value === 1 ? 'kan_lisan_benar' : 'kan_lisan_salah';
                    $userName = $asesorNames[$key] ?? $key;

                    $fieldIdx = count($fields);
                    $fields[] = [$fieldType, [
                        'nama_asesi' => $userName, 'jabatan_kerja' => $skemaName,
                        'nomor_skema' => $noSkema, 'asesor' => 'Asesor KAN',
                        'soal' => $soalText ?: ($soal->soal ?? ''),
                    ]];
                    $fieldKeys[$fieldIdx] = [$soal->id, $praktisiJabatanIds[$key], $value];
                }
            }

            if (empty($fields)) continue;

            $llmResults = $llm->generateBatchTexts($fields);

            foreach ($fieldKeys as $fieldIdx => [$soalId, $kpjId, $value]) {
                $fieldType = $value === 1 ? 'kan_lisan_benar' : 'kan_lisan_salah';
                $resultKey = $fieldType . "_{$fieldIdx}";
                $jawaban = $llmResults[$resultKey] ?? $llm->getFallback($fieldType);

                $rows[] = [
                    'kan_praktisi_jabatan_id' => $kpjId,
                    'dokumen_id'              => $dokumen->id,
                    'soal_id'                 => $soalId,
                    'jawaban'                 => $jawaban,
                    'skor'                    => $value === 1 ? random_int(2, 3) : 0,
                    'is_rekomendasi'          => false,
                    'updated_at'              => $timestamp,
                    'created_at'              => $timestamp,
                ];
            }
        }

        if (!empty($rows)) {
            KanIa4bAnswer::upsert($rows, ['kan_praktisi_jabatan_id', 'dokumen_id', 'soal_id'], ['jawaban', 'skor', 'is_rekomendasi', 'updated_at']);
        }
    }

    // ═══════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════

    private function generateEmailFromName(string $name): string
    {
        $sanitized = preg_replace('/[^a-z0-9]/', '', strtolower($name));
        $base = $sanitized . '@lspgatensi.id';
        if (!User::where('email', $base)->exists()) return $base;
        $suffix = 1;
        while (User::where('email', $sanitized . '+' . $suffix . '@lspgatensi.id')->exists()) $suffix++;
        return $sanitized . '+' . $suffix . '@lspgatensi.id';
    }
}
