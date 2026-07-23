<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LlmAutoFillService
{
    private const API_URL = 'https://api.deepseek.com/chat/completions';
    private const API_TOKEN = 'sk-b36eabe489f64a9bb767ee052ec61319';
    private const MODEL = 'deepseek-chat';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Kamu adalah asesor profesional LSP (Lembaga Sertifikasi Profesi) Indonesia yang berpengalaman.
Tulis dalam bahasa Indonesia profesional.
Jangan mengulang frasa atau kalimat yang sama.
Spesifik terhadap konteks asesmen yang diberikan.
Jawab HANYA teks yang diminta, to the point, jangan terlalu panjang tanpa penjelasan tambahan. maksimal 255 huruf. Semua metode uji observasi, jangan sebut portofolio.
PROMPT;

    /** Circuit breaker: after this many consecutive failures, skip LLM calls */
    private const CIRCUIT_BREAKER_THRESHOLD = 3;
    /** Circuit breaker cooldown: reset after this many seconds */
    private const CIRCUIT_COOLDOWN = 60;

    private int $consecutiveFailures = 0;
    private ?int $circuitOpenedAt = null; // Unix timestamp when circuit opened

    /**
     * Generate text content for a document field using the LLM.
     * Falls back to a placeholder if the API is unreachable.
     */
    public function generateText(string $fieldType, array $context = []): string
    {
        $prompt = $this->buildPrompt($fieldType, $context);
        if (!$prompt) {
            logger()->warning("[LLM] generateText: no prompt for fieldType={$fieldType}, using fallback");
            return $this->fallbackText($fieldType);
        }

        $result = $this->callLlm($prompt);

        if ($result === null) {
            logger()->warning("[LLM] generateText: LLM returned null for fieldType={$fieldType}, using fallback");
            return $this->fallbackText($fieldType);
        }

        logger()->debug("[LLM] generateText: fieldType={$fieldType} result_len=" . strlen($result) . " preview=" . substr($result, 0, 80));
        return $result;
    }

    /**
     * Get fallback text without making any API call.
     * Use this in queue jobs to ensure no API retries on timeout.
     */
    public function getFallback(string $fieldType): string
    {
        return $this->fallbackText($fieldType);
    }

    /**
     * Generate ALL text fields in a single LLM API call.
     * Much faster than calling generateText() 20+ times sequentially.
     *
     * @param array $fields [[fieldType, context], ...]
     * @return array [fieldType => generatedText]
     */
    public function generateBatchTexts(array $fields): array
    {
        // Build mapped fields (prompts are built per-chunk later)
        $mapped = [];
        $fieldTypes = [];
        foreach ($fields as $i => [$fieldType, $context]) {
            $fieldTypes[] = $fieldType;
            $prompt = $this->buildPrompt($fieldType, $context);
            if (!$prompt) {
                logger()->warning("[LLM] generateBatchTexts: no prompt for field[{$i}]={$fieldType}, using fallback");
                $mapped["f_{$i}"] = $this->fallbackText($fieldType);
            } else {
                $mapped["f_{$i}"] = null;
            }
        }

        $pending = array_filter($mapped, fn ($v) => $v === null);
        logger()->debug("[LLM] generateBatchTexts: total={$i} pending=" . count($pending) . " types=" . json_encode($fieldTypes));
        if (empty($pending)) {
            logger()->debug("[LLM] generateBatchTexts: semua fields pakai fallback (no prompt)");
            return $this->rekeyResults($mapped, $fields);
        }

        // Chunk into 10 fields per API call — groups 1 soal's 10 praktisi
        // for uniqueness while keeping JSON output reliable.
        $chunkSize = 10;
        // Convert "f_0" keys to integer indices
        $pendingIndices = array_map(fn($k) => (int) str_replace('f_', '', $k), array_keys($pending));
        $chunks = array_chunk($pendingIndices, $chunkSize);

        logger()->debug("[LLM] generateBatchTexts: splitting " . count($pending) . " fields into " . count($chunks) . " chunks of max {$chunkSize}");

        foreach ($chunks as $chunk) {
            $chunkPromptParts = [];
            foreach ($chunk as $idx) {
                $chunkPromptParts[] = "[Field {$idx}]: " . $this->buildPrompt($fields[$idx][0], $fields[$idx][1]);
            }

            $batchPrompt = "Generate ALL of the following assessment texts in Indonesian. "
                         . "Return ONLY a valid JSON object with the numeric field ID as key, e.g. {\"" . $chunk[0] . "\": \"...\", \"" . ($chunk[1] ?? '') . "\": \"...\"}.\n\n"
                         . "CRITICAL: Setiap field HARUS memiliki teks jawaban yang BENAR-BENAR UNIK dan BERBEDA satu sama lain.\n"
                         . "Jangan mengulang kalimat, struktur, atau frasa yang sama antar field.\n"
                         . "Variasikan pilihan kata, urutan kalimat, contoh teknis, dan detail untuk setiap field.\n"
                         . "Dua field tidak boleh memiliki kemiripan substansial. Ini syarat mutlak.\n\n"
                         . implode("\n---\n", $chunkPromptParts);

            $response = $this->callLlm($batchPrompt);

            if ($response) {
                logger()->debug("[LLM] generateBatchTexts: API response len=" . strlen($response) . " preview=" . substr($response, 0, 150));
                $parsed = $this->tryParseJson($response);
                if ($parsed) {
                    $matchedKeys = 0;
                    foreach ($parsed as $key => $text) {
                        $normalizedKey = is_numeric($key) ? "f_{$key}" : $key;

                        $value = $text;
                        if (is_array($value)) {
                            $value = $value['text'] ?? $value['content'] ?? current($value) ?? '';
                        }
                        if (!is_string($value)) {
                            $value = (string) $value;
                        }

                        if (array_key_exists($normalizedKey, $mapped) && is_string($value) && strlen(trim($value)) >= 3) {
                            $mapped[$normalizedKey] = trim($value);
                            $matchedKeys++;
                        }
                    }
                    logger()->debug("[LLM] generateBatchTexts: chunk parsed JSON matched {$matchedKeys}/" . count($parsed) . " keys");
                    if ($matchedKeys === 0 && count($parsed) > 0) {
                        $sampleKey = array_keys($parsed)[0] ?? 'none';
                        logger()->warning("[LLM] generateBatchTexts: 0 matched! sample parsed key=" . json_encode($sampleKey));
                    }
                } else {
                    logger()->warning("[LLM] generateBatchTexts: GAGAL parse JSON dari response API");
                    logger()->warning("[LLM] generateBatchTexts: response (first 1000 chars): " . substr($response, 0, 1000));
                }
            } else {
                logger()->warning("[LLM] generateBatchTexts: API call returned null for chunk, semua fields pakai fallback");
            }
        }

        // Retry any null KAN fields individually (simpler prompts, more reliable)
        $retried = 0;
        foreach ($mapped as $key => $val) {
            if ($val !== null) continue;
            $idx = (int) str_replace('f_', '', $key);
            $fieldType = $fields[$idx][0] ?? '';
            $context = $fields[$idx][1] ?? [];

            if (str_starts_with($fieldType, 'kan_')) {
                // Retry up to 3 times with individual (non-batch) call
                for ($retry = 0; $retry < 3; $retry++) {
                    $text = $this->generateText($fieldType, $context);
                    if ($text && strlen(trim($text)) >= 3) {
                        $mapped[$key] = trim($text);
                        $retried++;
                        logger()->debug("[LLM] generateBatchTexts: retry #" . ($retry + 1) . " SUCCESS for field[{$idx}]={$fieldType}");
                        break;
                    }
                    logger()->warning("[LLM] generateBatchTexts: retry #" . ($retry + 1) . " FAILED for field[{$idx}]={$fieldType}");
                }

                // If all retries failed, throw
                if ($mapped[$key] === null) {
                    throw new \RuntimeException(
                        "LLM gagal generate jawaban untuk field '{$fieldType}' (index {$idx}) setelah 3 retry. " .
                        "Semua jawaban harus di-generate oleh LLM, tidak boleh fallback."
                    );
                }
            } else {
                $mapped[$key] = $this->fallbackText($fieldType);
            }
        }

        if ($retried > 0) {
            logger()->debug("[LLM] generateBatchTexts: retried {$retried} fields individually");
        }

        return $this->rekeyResults($mapped, $fields);
    }

    private function rekeyResults(array $mapped, array $fields): array
    {
        $result = [];
        foreach ($fields as $i => [$fieldType]) {
            $result[$fieldType . '_' . $i] = $mapped["f_{$i}"];
        }
        return $result;
    }

    private function tryParseJson(string $text): ?array
    {
        // Strip markdown code blocks if present
        $text = preg_replace('/^```(?:json)?\s*\n?|\n?```\s*$/i', '', trim($text));

        // Remove BOM and null bytes
        $text = preg_replace('/^\xEF\xBB\xBF|\x00/', '', $text);

        // Try full decode first (fast path for clean responses)
        $data = json_decode($text, true, 512, JSON_INVALID_UTF8_IGNORE);
        if (is_array($data)) return $data;

        // Log why it failed
        logger()->warning("[LLM] tryParseJson: json_decode error=" . json_last_error_msg() . " at position=" . json_last_error());

        // ── Auto-repair common LLM JSON errors before retry ──
        $repaired = $this->repairLlmJson($text);
        $data = json_decode($repaired, true, 512, JSON_INVALID_UTF8_IGNORE);
        if (is_array($data)) {
            logger()->debug("[LLM] tryParseJson: repaired JSON parsed successfully");
            return $data;
        }

        // ── State machine: extract first top-level JSON object ──
        $firstBrace = strpos($text, '{');
        if ($firstBrace === false) return null;

        $depth = 0;
        $inString = false;
        $escaped = false;
        $jsonStart = $firstBrace;
        $jsonEnd = null;

        for ($i = $jsonStart; $i < strlen($text); $i++) {
            $ch = $text[$i];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($ch === '\\' && $inString) {
                $escaped = true;
                continue;
            }

            if ($ch === '"') {
                $inString = !$inString;
                continue;
            }

            if ($inString) continue;

            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $jsonEnd = $i;
                    break;
                }
            }
        }

        if ($jsonEnd === null) return null;

        $jsonCandidate = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);
        $data = json_decode($jsonCandidate, true);
        if (is_array($data)) return $data;

        // Try repairing the state-machine candidate too
        $repairedCandidate = $this->repairLlmJson($jsonCandidate);
        $data = json_decode($repairedCandidate, true);
        if (is_array($data)) {
            logger()->debug("[LLM] tryParseJson: repaired state-machine JSON parsed successfully");
            return $data;
        }

        logger()->warning("[LLM] tryParseJson: state machine candidate also failed: " . json_last_error_msg());

        // ── Last resort: regex extraction of all "N" or "N": "value" pairs ──
        // Handles both well-formed "3": "text" and malformed "3: "text"
        preg_match_all('/"(\d+)"?\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $text, $matches);
        if (!empty($matches[1])) {
            $result = [];
            foreach ($matches[1] as $idx => $key) {
                $val = $matches[2][$idx];
                // Unescape common escape sequences
                $val = str_replace(['\\"', '\\\\', '\\n', '\\t'], ['"', '\\', "\n", "\t"], $val);
                $result[$key] = $val;
            }
            if (count($result) > 0) {
                logger()->debug("[LLM] tryParseJson: regex fallback extracted " . count($result) . " keys");
                return $result;
            }
        }

        logger()->warning("[LLM] tryParseJson: semua metode gagal");
        return null;
    }

    /**
     * Repair common JSON formatting errors made by LLMs.
     */
    private function repairLlmJson(string $text): string
    {
        // 1. Fix malformed numeric keys: "N: → "N": (missing closing quote)
        $text = preg_replace('/"(\d+):(\s*")/', '"$1":$2', $text);

        // 2. Remove trailing commas before } or ]
        $text = preg_replace('/,(\s*[}\]])/', '$1', $text);

        // 3. Fix missing comma between consecutive string values
        //    e.g. "text1" "text2" → "text1", "text2"
        $text = preg_replace('/"\s*\n\s*"/', '",' . "\n" . '"', $text);

        // 4. Fix double-colons "key":: → "key":
        $text = preg_replace('/"(\s*):(\s*):/', '"$1:', $text);

        // 5. Fix unescaped newlines/tabs inside string values
        //    (already handled by JSON_INVALID_UTF8_IGNORE, but belt-and-suspenders)

        return $text;
    }

    private function callLlm(string $userPrompt): ?string
    {
        // Circuit breaker: skip API calls if LLM is failing
        if ($this->consecutiveFailures >= self::CIRCUIT_BREAKER_THRESHOLD) {
            if ($this->circuitOpenedAt && (time() - $this->circuitOpenedAt) > self::CIRCUIT_COOLDOWN) {
                // Cooldown expired, reset and try again
                logger()->info('[LLM] Circuit breaker cooldown expired, resetting');
                $this->consecutiveFailures = 0;
                $this->circuitOpenedAt = null;
            } else {
                logger()->warning('[LLM] Circuit breaker OPEN — skipping API call (failures=' . $this->consecutiveFailures . ')');
                return null;
            }
        }

        $maxRetries = 5;
        $attempt = 0;
        $delay = 3000; // ms

        while ($attempt < $maxRetries) {
            try {
                logger()->debug("[LLM] API call attempt=" . ($attempt + 1) . " url=" . self::API_URL . " prompt_len=" . strlen($userPrompt));
                $start = microtime(true);
                $response = Http::timeout(120)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . self::API_TOKEN,
                    ])
                    ->post(self::API_URL, [
                        'model' => self::MODEL,
                        'max_tokens' => 8192,
                        'temperature' => 0.3,
                        'messages' => [
                            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                            ['role' => 'user', 'content' => $userPrompt],
                        ],
                    ]);
                $elapsed = round((microtime(true) - $start) * 1000);

                if ($response->successful()) {
                    $data = $response->json();
                    // OpenAI-compatible format: choices[0].message.content
                    $text = $data['choices'][0]['message']['content'] ?? null;
                    // DeepSeek reasoning models return content in reasoning_content instead
                    if (!$text || strlen(trim($text)) <= 5) {
                        $reasoning = $data['choices'][0]['message']['reasoning_content'] ?? null;
                        if ($reasoning && strlen(trim($reasoning)) > 5) {
                            $this->consecutiveFailures = 0; // Reset circuit breaker on success
                            logger()->debug("[LLM] API success in {$elapsed}ms, using reasoning_content len=" . strlen($reasoning));
                            return trim($reasoning);
                        }
                    }
                    logger()->debug("[LLM] API success in {$elapsed}ms, has_text=" . ($text ? 'YES' : 'NO') . " len=" . strlen($text ?? ''));
                    if (!$text) {
                        logger()->warning("[LLM] API response raw (first 500 chars): " . substr(json_encode($data), 0, 500));
                    }
                    if ($text && strlen(trim($text)) > 5) {
                        $this->consecutiveFailures = 0; // Reset circuit breaker on success
                        return trim($text);
                    }
                    if ($text && strlen(trim($text)) <= 5) {
                        logger()->warning("[LLM] API response text terlalu pendek: '" . $text . "'");
                    }
                } else {
                    logger()->warning("[LLM] API HTTP " . $response->status() . " in {$elapsed}ms, body=" . $response->body());
                }

                // Rate limited? Wait and retry
                if ($response->status() === 429) {
                    $attempt++;
                    if ($attempt < $maxRetries) {
                        usleep($delay * 1000);
                        $delay *= 2;
                    }
                    continue;
                }

                $this->consecutiveFailures++;
                if ($this->consecutiveFailures >= self::CIRCUIT_BREAKER_THRESHOLD) {
                    $this->circuitOpenedAt = time();
                    logger()->warning('[LLM] Circuit breaker OPENED after ' . $this->consecutiveFailures . ' consecutive failures');
                }
                return null;
            } catch (\Throwable $e) {
                logger()->warning("[LLM] API exception attempt=" . ($attempt + 1) . ": " . $e->getMessage());
                $attempt++;
                if ($attempt < $maxRetries) {
                    usleep($delay * 1000);
                    $delay *= 2;
                }
            }
        }

        $this->consecutiveFailures++;
        if ($this->consecutiveFailures >= self::CIRCUIT_BREAKER_THRESHOLD) {
            $this->circuitOpenedAt = time();
            logger()->warning('[LLM] Circuit breaker OPENED after ' . $this->consecutiveFailures . ' consecutive failures');
        }
        logger()->error("[LLM] API call gagal setelah {$maxRetries} percobaan");
        return null;
    }

    private function buildPrompt(string $fieldType, array $context): ?string
    {
        return match ($fieldType) {
            'ia01_umpan_balik' => $this->buildIa01UmpanBalik($context),
            'ia03_tanggapan' => $this->buildIa03Tanggapan($context),
            'ia03_umpan_balik' => $this->buildIa03UmpanBalik($context),
            'ia04a_jawaban' => $this->buildIa04aJawaban($context),
            'ia04a_persiapan_kegiatan' => $this->buildIa04aPersiapanKegiatan($context),
            'ia04a_demonstrasi' => $this->buildIa04aDemonstrasi($context),
            'ia04a_umpan_balik' => $this->buildIa04aUmpanBalik($context),
            'ia05_umpan_balik' => $this->buildIa05UmpanBalik($context),
            'ak02_komentar' => $this->buildAk02Komentar($context),
            'ak03_catatan' => $this->buildAk03Catatan($context),
            'ak03_catatan_umum' => $this->buildAk03CatatanUmum($context),
            'ak04_alasan' => $this->buildAk04Alasan($context),
            'ak05_keterangan' => $this->buildAk05Keterangan($context),
            'ak05_aspek' => $this->buildAk05Aspek($context),
            'ak05_saran' => $this->buildAk05Saran($context),
            'ak05_catatan' => $this->buildAk05Catatan($context),
            'ak06_rekomendasi1' => $this->buildAk06Rekomendasi1($context),
            'ak06_rekomendasi2' => $this->buildAk06Rekomendasi2($context),
            'ak06_catatan_asesor1' => $this->buildAk06CatatanAsesor1($context),
            'ak06_catatan_asesor2' => $this->buildAk06CatatanAsesor2($context),
            'ak07_text' => $this->buildAk07Text($context),
            'kan_esai_benar' => $this->buildKanEsaiBenar($context),
            'kan_esai_salah' => $this->buildKanEsaiSalah($context),
            'kan_lisan_benar' => $this->buildKanLisanBenar($context),
            'kan_lisan_salah' => $this->buildKanLisanSalah($context),
            default => null,
        };
    }

    private function context(array $ctx): string
    {
        return "- Nama Asesi: " . ($ctx['nama_asesi'] ?? '-') . "\n"
             . "- Jabatan Kerja: " . ($ctx['jabatan_kerja'] ?? '-') . "\n"
             . "- Skema: " . ($ctx['nomor_skema'] ?? '-') . "\n"
             . "- Asesor: " . ($ctx['asesor'] ?? '-') . "\n";
    }

    private function buildIa01UmpanBalik(array $ctx): string
    {
        return "Berikut adalah konteks asesmen:\n"
             . $this->context($ctx)
             . "\nTulis umpan balik profesional untuk hasil observasi asesi (maksimal 2 kalimat, maks 255 huruf)."
             . "\nSebutkan kekuatan utama dan saran perbaikan.";
    }

    private function buildIa03Tanggapan(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "- Soal: {$ctx['soal_soal']}\n\n"
             . "Tulis tanggapan asesor profesional (maksimal 2 kalimat, maks 255 huruf) untuk jawaban asesi pada soal di atas.";
    }

    private function buildIa03UmpanBalik(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nTulis umpan balik keseluruhan untuk hasil ujian lisan asesi (maksimal 2 kalimat, maks 255 huruf)."
             . "\nBerikan apresiasi atas pencapaian kompeten.";
    }

    private function buildIa04aJawaban(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "- Soal: {$ctx['soal_soal']}\n\n"
             . "Tulis jawaban profesional untuk soal proyek kerja di atas (maksimal 2 kalimat, maks 255 huruf)."
             . "\nGunakan bahasa teknis sesuai bidang konstruksi.";
    }

    private function buildIa04aPersiapanKegiatan(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nJelaskan hal-hal yang harus dipersiapkan, dilakukan, atau dihasilkan asesi untuk suatu kegiatan terstruktur "
             . "berkaitan dengan skema sertifikasi (maksimal 3 kalimat, maks 255 huruf)."
             . "\nFokus pada persiapan teknis dan dokumen yang relevan dengan jabatan.";
    }

    private function buildIa04aDemonstrasi(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nJelaskan hal-hal yang perlu didemonstrasikan oleh asesi untuk menunjukkan kompetensinya "
             . "pada kegiatan terstruktur ini (maksimal 3 kalimat, maks 255 huruf)."
             . "\nFokus pada kemampuan teknis dan prosedural yang harus diperagakan.";
    }

    private function buildIa04aUmpanBalik(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nTulis umpan balik profesional untuk asesi terkait hasil kegiatan terstruktur yang telah dilaksanakan "
             . "(maksimal 3 kalimat, maks 255 huruf)."
             . "\nSebutkan apresiasi atas pencapaian dan saran pengembangan.";
    }

    private function buildIa05UmpanBalik(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nBeri umpan balik tentang performa asesi dalam ujian tertulis (maksimal 2 kalimat, maks 255 huruf)."
             . "\nBerikan apresiasi dan saran pengembangan.";
    }

    private function buildAk02Komentar(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nTulis komentar/observasi asesor tentang metode asesmen yang dipilih (maksimal 2 kalimat, maks 255 huruf)."
             . "\nJelaskan kesesuaian metode dengan unit kompetensi yang diujikan.";
    }

    private function buildAk03Catatan(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "- Pertanyaan: {$ctx['soal_soal']}\n\n"
             . "Tulis catatan asesi tentang pertanyaan ini (1-2 kalimat, maks 255 huruf)."
             . "\nTulis dari sudut pandang asesi yang merasa puas dengan proses asesmen.";
    }

    private function buildAk03CatatanUmum(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nTulis catatan umum asesi tentang keseluruhan proses asesmen (maksimal 2 kalimat, maks 255 huruf)."
             . "\nMencakup pengalaman positif dan apresiasi terhadap asesor.";
    }

    private function buildAk04Alasan(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nTulis pernyataan bahwa asesi tidak mengajukan banding karena semua item telah dinilai sesuai."
             . "\nTulis 1-2 kalimat dari sudut pandang asesi yang menyatakan kesetujuan dengan hasil asesmen (maks 255 huruf).";
    }

    private function buildAk05Keterangan(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nTulis keterangan bahwa asesi dinyatakan Kompeten untuk semua unit (maksimal 2 kalimat, maks 255 huruf).";
    }

    private function buildAk05Aspek(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nTulis aspek positif dan negatif pelaksanaan asesmen ini (maksimal 2 kalimat, maks 255 huruf)."
             . "\nFokus pada aspek positif karena asesi dinyatakan kompeten.";
    }

    private function buildAk05Saran(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nTulis saran perbaikan untuk asesor dan personil terkait (maksimal 2 kalimat, maks 255 huruf)."
             . "\nBerikan saran konstruktif untuk peningkatan mutu asesmen.";
    }

    private function buildAk05Catatan(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nTulis catatan hasil asesmen untuk asesi yang dinyatakan Kompeten (maksimal 2 kalimat, maks 255 huruf)."
             . "\nCatat bahwa semua unit kompetensi telah terpenuhi dengan baik.";
    }

    private function buildAk06Rekomendasi1(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nTulis rekomendasi untuk peningkatan prinsip asesmen (maksimal 2 kalimat, maks 255 huruf)."
             . "\nFokus pada validitas, reliabilitas, fleksibilitas, dan keadilan asesmen.";
    }

    private function buildAk06Rekomendasi2(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nTulis rekomendasi untuk peningkatan dimensi kompetensi asesi (maksimal 2 kalimat, maks 255 huruf)."
             . "\nBerikan saran pengembangan kompetensi lanjutan.";
    }

    private function buildAk06CatatanAsesor1(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nTulis catatan Asesor 1 tentang proses asesmen yang telah dilaksanakan (maksimal 2 kalimat, maks 255 huruf)."
             . "\nCatat bahwa semua tahapan asesmen telah berjalan sesuai prosedur.";
    }

    private function buildAk06CatatanAsesor2(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "\nTulis catatan Asesor 2 tentang proses asesmen yang telah dilaksanakan (maksimal 2 kalimat, maks 255 huruf)."
             . "\nBerikan perspektif sebagai asesor pendamping yang memverifikasi seluruh proses.";
    }

    private function buildAk07Text(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "- Referensi: {$ctx['referensi_nama']}\n\n"
             . "Tulis catatan verifikasi untuk item asesmen di atas (maksimal 2 kalimat, maks 255 huruf)."
             . "\nJelaskan bahwa semua persyaratan telah terpenuhi dan dokumen dinyatakan valid.";
    }

    // ──────────────────────────────────────────────
    //  KAN PROMPT BUILDERS
    // ──────────────────────────────────────────────

    private function buildKanEsaiBenar(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "- Soal Esai: {$ctx['soal']}\n\n"
             . "Kamu adalah asesi (peserta uji kompetensi) yang sangat kompeten di bidang konstruksi.\n"
             . "Tulis jawaban esai yang BENAR, profesional, dan teknis untuk soal di atas (maks 255 huruf).\n"
             . "Gunakan bahasa Indonesia profesional bidang konstruksi. Jawab sebagai asesi yang kompeten.\n"
             . "PENTING: Jawabanmu harus UNIK dan TIDAK BOLEH SAMA dengan jawaban praktisi/asesi lain untuk soal yang sama.\n"
             . "Variasikan sudut pandang, contoh, istilah teknis, dan urutan penjelasan.\n"
             . "Jawab HANYA teks jawaban esai, tanpa pembuka atau penutup.";
    }

    private function buildKanEsaiSalah(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "- Soal Esai: {$ctx['soal']}\n\n"
             . "Kamu adalah asesi (peserta uji kompetensi) yang masih dalam tahap pengembangan kompetensi di bidang konstruksi.\n"
             . "Tulis jawaban esai yang secara umum masih relevan dengan topik, namun mengandung beberapa kelemahan: "
             . "konsep yang kurang tepat, urutan kerja yang kurang sistematis, penerapan standar yang belum konsisten, "
             . "atau pemahaman yang masih dangkal (maks 255 huruf).\n"
             . "Gunakan bahasa Indonesia yang cukup baik, jangan terlihat seperti orang yang sama sekali tidak tahu.\n"
             . "Jawaban harus terlihat seperti dikerjakan oleh orang yang tahu sedikit namun belum menguasai sepenuhnya.\n"
             . "JANGAN membuat jawaban yang terlihat bodoh, sangat ceroboh, atau asal-asalan.\n"
             . "PENTING: Jenis kelemahan dan cara penulisan harus UNIK dan BERBEDA untuk setiap asesi.\n"
             . "Jangan gunakan pola kalimat atau argumen yang sama dengan jawaban lain.\n"
             . "Jawab HANYA teks jawaban esai, tanpa pembuka atau penutup.";
    }

    private function buildKanLisanBenar(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "- Instruksi Kegiatan Terstruktur: {$ctx['soal']}\n\n"
             . "Kamu adalah asesi (peserta uji kompetensi) yang sangat kompeten di bidang konstruksi.\n"
             . "Tulis jawaban/uraian kegiatan yang BENAR dan profesional untuk instruksi di atas (maks 255 huruf).\n"
             . "Jelaskan langkah-langkah teknis yang sesuai dengan standar prosedur konstruksi.\n"
             . "Gunakan bahasa Indonesia profesional. Jawab sebagai asesi yang kompeten.\n"
             . "PENTING: Jawabanmu harus UNIK dan TIDAK BOLEH SAMA dengan jawaban praktisi/asesi lain untuk instruksi yang sama.\n"
             . "Variasikan urutan langkah, penekanan aspek teknis, dan terminologi yang digunakan.\n"
             . "Jawab HANYA teks jawaban, tanpa pembuka atau penutup.";
    }

    private function buildKanLisanSalah(array $ctx): string
    {
        return "Konteks:\n"
             . $this->context($ctx)
             . "- Instruksi Kegiatan Terstruktur: {$ctx['soal']}\n\n"
             . "Kamu adalah asesi (peserta uji kompetensi) yang masih dalam tahap pengembangan kompetensi di bidang konstruksi.\n"
             . "Tulis jawaban/uraian kegiatan yang secara umum masih relevan dengan instruksi, namun mengandung beberapa kelemahan: "
             . "langkah kerja yang kurang sistematis, pertimbangan teknis yang kurang matang, "
             . "atau penerapan prosedur yang belum sepenuhnya sesuai standar (maks 255 huruf).\n"
             . "Gunakan bahasa Indonesia yang cukup baik, jangan terlihat seperti orang yang sama sekali tidak tahu.\n"
             . "Jawaban harus terlihat seperti dikerjakan oleh orang yang tahu sedikit namun belum menguasai sepenuhnya.\n"
             . "JANGAN membuat jawaban yang terlihat bodoh, sangat ceroboh, atau asal-asalan.\n"
             . "PENTING: Jenis kelemahan dan cara penulisan harus UNIK dan BERBEDA untuk setiap asesi.\n"
             . "Jangan gunakan pola langkah atau argumen yang sama dengan jawaban lain.\n"
             . "Jawab HANYA teks jawaban, tanpa pembuka atau penutup.";
    }

    private function fallbackText(string $fieldType): string
    {
        $fallbacks = [
            'ia01_umpan_balik' => 'Asesi telah menunjukkan kompetensi yang memadai pada seluruh aspek penilaian. Kinerja yang diperlihatkan sesuai dengan kriteria unjuk kerja yang ditetapkan.',
            'ia03_tanggapan' => 'Jawaban asesi menunjukkan pemahaman yang baik terhadap materi. Penjelasan yang diberikan relevan dan sesuai dengan konteks pekerjaan.',
            'ia03_umpan_balik' => 'Asesi berhasil menunjukkan penguasaan kompetensi secara keseluruhan. Kemampuan komunikasi dan pemahaman teknis sudah sangat baik.',
            'ia04a_jawaban' => 'Proyek kerja yang dilaksanakan menunjukkan kemampuan teknis yang memadai. Metodologi yang digunakan sesuai dengan standar operasional prosedur yang berlaku.',
            'ia05_umpan_balik' => 'Asesi menunjukkan penguasaan materi yang baik pada ujian tertulis. Jawaban yang diberikan tepat dan sesuai dengan kompetensi yang diujikan.',
            'ak02_komentar' => 'Metode asesmen yang digunakan sesuai dengan karakteristik unit kompetensi yang diujikan. Observasi dan pertanyaan tertulis memberikan gambaran komprehensif tentang kompetensi asesi.',
            'ak03_catatan' => 'Proses asesmen berjalan dengan baik dan profesional. Saya puas dengan pelaksanaan asesmen yang transparan dan objektif.',
            'ak03_catatan_umum' => 'Secara keseluruhan, proses asesmen berjalan dengan sangat baik. Asesor bersikap profesional dan memberikan arahan yang jelas. Saya merasa puas dengan hasil asesmen dan berterima kasih atas kesempatan yang diberikan.',
            'ak04_alasan' => 'Saya tidak mengajukan banding karena seluruh hasil penilaian telah sesuai dengan kompetensi yang saya miliki. Proses asesmen berjalan transparan dan objektif.',
            'ak05_keterangan' => 'Asesi dinyatakan Kompeten karena telah memenuhi seluruh kriteria unjuk kerja yang dipersyaratkan pada setiap unit kompetensi. Hasil observasi dan verifikasi menunjukkan penguasaan yang memadai.',
            'ak05_aspek' => 'Aspek positif: Asesi menunjukkan kesiapan dan penguasaan materi yang baik. Proses asesmen berjalan lancar sesuai jadwal. Aspek negatif: Beberapa jawaban masih perlu pendalaman, namun secara keseluruhan sudah memenuhi standar.',
            'ak05_saran' => 'Disarankan agar asesi terus mengembangkan kompetensi melalui pelatihan dan pengalaman lapangan. Evaluasi berkala perlu dilakukan untuk memastikan pemeliharaan kompetensi.',
            'ak05_catatan' => 'Asesi dinyatakan Kompeten pada seluruh unit kompetensi. Proses asesmen berjalan sesuai prosedur dan memenuhi prinsip asesmen yang berlaku.',
            'ak06_rekomendasi1' => 'Prinsip asesmen telah dilaksanakan dengan baik. Validitas dan reliabilitas instrumen asesmen terjaga. Fleksibilitas dalam pelaksanaan tetap memperhatikan keadilan bagi seluruh asesi.',
            'ak06_rekomendasi2' => 'Disarankan untuk mengikuti program pengembangan kompetensi lanjutan sesuai dengan jenjang jabatan. Peningkatan kemampuan teknis dan manajerial akan mendukung karir profesional.',
            'ak06_catatan_asesor1' => 'Proses asesmen dilaksanakan sesuai dengan pedoman asesmen yang berlaku. Seluruh tahapan terdokumentasi dengan baik dan hasilnya dapat dipertanggungjawabkan.',
            'ak06_catatan_asesor2' => 'Sebagai asesor pendamping, saya memverifikasi bahwa seluruh proses asesmen telah dilaksanakan sesuai prosedur. Dokumen asesmen lengkap dan valid.',
            'ak07_text' => 'Berdasarkan verifikasi yang dilakukan, dokumen dan bukti yang disampaikan telah memenuhi persyaratan yang ditetapkan. Semua item telah terverifikasi dengan baik.',
            'ia04a_persiapan_kegiatan' => 'Asesi perlu mempersiapkan dokumen teknis terkait proyek pekerjaan yang sesuai dengan skema sertifikasi, meliputi gambar kerja, spesifikasi teknis, dan rencana pelaksanaan pekerjaan. Kelengkapan dokumen menjadi prasyarat utama sebelum pelaksanaan kegiatan terstruktur.',
            'ia04a_demonstrasi' => 'Asesi harus mendemonstrasikan penguasaan kompetensi teknis sesuai dengan unit kompetensi yang diujikan, termasuk kemampuan membaca gambar teknis, penerapan Keselamatan dan Kesehatan Kerja (K3), serta penggunaan peralatan kerja yang sesuai dengan prosedur operasional standar.',
            'ia04a_umpan_balik' => 'Asesi berhasil menunjukkan penguasaan kompetensi yang memadai pada kegiatan terstruktur yang dilaksanakan. Kinerja yang diperlihatkan sesuai dengan kriteria unjuk kerja yang ditetapkan. Disarankan untuk terus meningkatkan kompetensi melalui pengalaman lapangan dan pelatihan lanjutan.',

            // KAN fallbacks — NOT used when LLM is available (throw exception instead)
            'kan_esai_benar' => 'Berdasarkan analisis teknis dan standar operasional prosedur yang berlaku, pelaksanaan pekerjaan konstruksi harus mengikuti tahapan perencanaan, pelaksanaan, pengawasan, dan serah terima sesuai dokumen kontrak. Setiap tahapan wajib didukung oleh dokumen teknis yang lengkap dan tenaga kerja yang kompeten di bidangnya. Penerapan K3 serta pengendalian mutu menjadi prasyarat utama keberhasilan proyek konstruksi.',
            'kan_esai_salah' => 'Pelaksanaan pekerjaan konstruksi pada dasarnya mengikuti alur yang sudah biasa dilakukan di lapangan. Perencanaan bisa disesuaikan nanti jika ada perubahan, dan dokumen teknis cukup dibuat seperlunya saja. Pengawasan dilakukan oleh tim yang ditunjuk, namun tidak semua aspek perlu diperiksa secara ketat karena tenaga kerja sudah cukup berpengalaman.',
            'kan_lisan_benar' => 'Langkah-langkah kegiatan terstruktur yang harus dilakukan meliputi: pertama, identifikasi kebutuhan sumber daya dan peralatan sesuai spesifikasi teknis. Kedua, penyusunan jadwal pelaksanaan dan metode kerja. Ketiga, pelaksanaan pekerjaan dengan pengawasan ketat terhadap mutu dan K3. Keempat, dokumentasi setiap tahapan sebagai bukti pelaksanaan.',
            'kan_lisan_salah' => 'Kegiatan ini secara umum mengikuti tahapan yang lazim diterapkan di proyek. Sumber daya dan peralatan disesuaikan dengan ketersediaan di lapangan. Penjadwalan dilakukan secara garis besar, dan detailnya bisa disesuaikan sambil berjalan. Dokumentasi dibuat setelah pekerjaan selesai sebagai laporan akhir.',
        ];

        return $fallbacks[$fieldType] ?? 'Telah diverifikasi dan dinyatakan sesuai.';
    }
}
