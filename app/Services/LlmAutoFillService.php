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
        // Build prompts
        $mapped = [];
        $promptParts = [];
        $fieldTypes = [];
        foreach ($fields as $i => [$fieldType, $context]) {
            $fieldTypes[] = $fieldType;
            $prompt = $this->buildPrompt($fieldType, $context);
            if (!$prompt) {
                logger()->warning("[LLM] generateBatchTexts: no prompt for field[{$i}]={$fieldType}, using fallback");
                $mapped["f_{$i}"] = $this->fallbackText($fieldType);
            } else {
                $mapped["f_{$i}"] = null;
                $promptParts[] = "[Field {$i}]: {$prompt}";
            }
        }

        $pending = array_filter($mapped, fn ($v) => $v === null);
        logger()->debug("[LLM] generateBatchTexts: total={$i} pending=" . count($pending) . " types=" . json_encode($fieldTypes));
        if (empty($pending)) {
            logger()->debug("[LLM] generateBatchTexts: semua fields pakai fallback (no prompt)");
            return $this->rekeyResults($mapped, $fields);
        }

        // One batch request
        $batchPrompt = "Generate ALL of the following assessment texts in Indonesian. "
                     . "Return ONLY a valid JSON object with the numeric field ID as key, e.g. {\"0\": \"...\", \"1\": \"...\"}.\n\n"
                     . implode("\n---\n", $promptParts);

        $response = $this->callLlm($batchPrompt);

        if ($response) {
            logger()->debug("[LLM] generateBatchTexts: API response len=" . strlen($response) . " preview=" . substr($response, 0, 150));
            // Try to parse JSON response
            $parsed = $this->tryParseJson($response);
            if ($parsed) {
                $matchedKeys = 0;
                foreach ($parsed as $key => $text) {
                    // LLM might return keys as "0", "1" (numeric) or "f_0", "f_1"
                    // Normalize: if it's a pure number, prepend "f_"
                    $normalizedKey = is_numeric($key) ? "f_{$key}" : $key;

                    // Value might be nested object (LLM sometimes wraps in {"text": "..."})
                    $value = $text;
                    if (is_array($value)) {
                        $value = $value['text'] ?? $value['content'] ?? current($value) ?? '';
                    }
                    if (!is_string($value)) {
                        $value = (string) $value;
                    }

                    if (array_key_exists($normalizedKey, $mapped) && strlen(trim($value)) > 5) {
                        $mapped[$normalizedKey] = trim($value);
                        $matchedKeys++;
                    }
                }
                logger()->debug("[LLM] generateBatchTexts: parsed JSON matched {$matchedKeys}/" . count($parsed) . " keys");
                if ($matchedKeys === 0 && count($parsed) > 0) {
                    $sampleKey = array_keys($parsed)[0] ?? 'none';
                    $sampleVal = $parsed[$sampleKey] ?? 'none';
                    logger()->warning("[LLM] generateBatchTexts: 0 matched! sample parsed key=" . json_encode($sampleKey) . " val_type=" . gettype($sampleVal) . " mapped_first_key=" . json_encode(array_keys($mapped)[0] ?? 'none'));
                }
            } else {
                logger()->warning("[LLM] generateBatchTexts: GAGAL parse JSON dari response API");
                logger()->warning("[LLM] generateBatchTexts: response (first 1000 chars): " . substr($response, 0, 1000));
            }
        } else {
            logger()->warning("[LLM] generateBatchTexts: API call returned null, semua fields pakai fallback");
        }

        // Fill any remaining nulls with fallback
        foreach ($mapped as $key => $val) {
            if ($val === null) {
                $idx = (int) str_replace('f_', '', $key);
                $mapped[$key] = $this->fallbackText($fields[$idx][0] ?? '');
            }
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
        // Use JSON_INVALID_UTF8_IGNORE to handle any encoding issues
        $data = json_decode($text, true, 512, JSON_INVALID_UTF8_IGNORE);
        if (is_array($data)) return $data;

        // Log why it failed
        logger()->warning("[LLM] tryParseJson: json_decode error=" . json_last_error_msg() . " at position=" . json_last_error());

        // If that fails, try extracting the first top-level JSON object
        // with a state machine that properly handles strings and escape sequences
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

        logger()->warning("[LLM] tryParseJson: state machine candidate also failed: " . json_last_error_msg());

        // Last resort: regex extraction of all "key": "value" pairs
        preg_match_all('/"(\d+)"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/s', $text, $matches);
        if (!empty($matches[1])) {
            $result = [];
            foreach ($matches[1] as $idx => $key) {
                $result[$key] = $matches[2][$idx];
            }
            if (count($result) > 0) {
                logger()->debug("[LLM] tryParseJson: regex fallback extracted " . count($result) . " keys");
                return $result;
            }
        }

        logger()->warning("[LLM] tryParseJson: semua metode gagal");
        return null;
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

        $maxRetries = 3;
        $attempt = 0;
        $delay = 2000; // ms

        while ($attempt < $maxRetries) {
            try {
                logger()->debug("[LLM] API call attempt=" . ($attempt + 1) . " url=" . self::API_URL . " prompt_len=" . strlen($userPrompt));
                $start = microtime(true);
                $response = Http::timeout(30)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . self::API_TOKEN,
                    ])
                    ->post(self::API_URL, [
                        'model' => self::MODEL,
                        'max_tokens' => 8192,
                        'temperature' => 0.7,
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
        ];

        return $fallbacks[$fieldType] ?? 'Telah diverifikasi dan dinyatakan sesuai.';
    }
}
