<?php

namespace App\Http\Controllers;

use App\Models\JabatanDokumen;
use App\Models\JabatanKerja;
use App\Models\KanSoal;
use App\Models\Kuk;
use App\Models\SubunitKompetensi;
use App\Models\UnitKompetensi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class KanImportController extends Controller
{
    /**
     * POST /api/kan/import
     *
     * Import semua soal MUK KAN dari 1 file JSON frontend.
     * Struktur JSON: { nama, folder?, ia04b: [...], ia05: [...], ia06: [...] }
     * Backend auto-resolve semua relasi dari string kode.
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string',
            'ia04b' => 'nullable|array',
            'ia04b.*.no' => 'required|integer',
            'ia04b.*.soal' => 'required|string',
            'ia05' => 'nullable|array',
            'ia05.*.no' => 'required|integer',
            'ia05.*.soal' => 'required|string',
            'ia06' => 'nullable|array',
            'ia06.*.no' => 'required|integer',
            'ia06.*.soal' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // ── 1. Resolve jabatan_kerja ──
        $jabatan = JabatanKerja::where('jabatan_kerja', $request->nama)
            ->orderBy('id_jabatan_kerja', 'desc')
            ->first();
        if (!$jabatan) {
            return response()->json([
                'message' => "Skema '{$request->nama}' tidak ditemukan di tabel jabatan_kerja",
            ], 404);
        }
        $idJabatan = $jabatan->id_jabatan_kerja;

        // ── 2. Pre-cache & auto-create dokumen jika belum ada ──
        $dokumenKodes = ['FR.IA.04.B', 'FR.IA.05', 'FR.IA.06'];
        $dokumenNama = [
            'FR.IA.04.B' => 'LEMBAR PERIKSA KEGIATAN TERSTRUKTUR',
            'FR.IA.05' => 'PERTANYAAN TERTULIS PILIHAN GANDA',
            'FR.IA.06' => 'PERTANYAAN TERTULIS ESAI',
        ];

        $dokumenCache = [];
        foreach ($dokumenKodes as $kode) {
            $dok = JabatanDokumen::where('id_jabatan', $idJabatan)
                ->where('kode', $kode)
                ->where('is_active', true)
                ->first();

            if (!$dok) {
                $dok = JabatanDokumen::create([
                    'id_jabatan' => $idJabatan,
                    'kode' => $kode,
                    'nama_dokumen' => $dokumenNama[$kode],
                    'is_active' => true,
                ]);
            }
            $dokumenCache[$kode] = $dok->id;
        }

        // ── 3. Pre-cache unit & kuk ──
        $unitCache = UnitKompetensi::where('id_jabatan', $idJabatan)
            ->pluck('id', 'kode');

        $kukCache = Kuk::pluck('id', 'kode');

        // ── 4. Import helper ──
        $total = 0;

        DB::beginTransaction();
        try {
            // ── IA04B ──
            if ($request->has('ia04b')) {
                $dokId = $dokumenCache['FR.IA.04.B'];
                foreach ($request->ia04b as $item) {
                    $data = [
                        'id_dokumen' => $dokId,
                        'tipe' => 8,
                        'no' => $item['no'],
                        'urut' => $item['no'],
                        'soal' => $item['soal'],
                        'soal1' => $item['lingkup'] ?? null,
                    ];

                    $unitId = $this->resolveUnitKode($item['kode_unit'] ?? '', $unitCache);
                    if ($unitId) {
                        $data['id_unitkompetensi'] = $unitId;
                    }

                    KanSoal::updateOrCreate(
                        ['id_dokumen' => $dokId, 'no' => $item['no']],
                        $data
                    );
                    $total++;
                }
            }

            // ── IA05 ──
            if ($request->has('ia05')) {
                $dokId = $dokumenCache['FR.IA.05'];
                foreach ($request->ia05 as $item) {
                    $data = [
                        'id_dokumen' => $dokId,
                        'tipe' => 1,
                        'no' => $item['no'],
                        'urut' => $item['no'],
                        'soal' => $item['soal'],
                        'jawab_a' => $item['jawab_a'] ?? null,
                        'jawab_b' => $item['jawab_b'] ?? null,
                        'jawab_c' => $item['jawab_c'] ?? null,
                        'jawab_d' => $item['jawab_d'] ?? null,
                        'jawaban' => $item['jawaban'] ?? null,
                    ];

                    $data = $this->resolveKuk($item, $data, $unitCache, $kukCache);

                    KanSoal::updateOrCreate(
                        ['id_dokumen' => $dokId, 'no' => $item['no']],
                        $data
                    );
                    $total++;
                }
            }

            // ── IA06 ──
            if ($request->has('ia06')) {
                $dokId = $dokumenCache['FR.IA.06'];
                foreach ($request->ia06 as $item) {
                    // Skip baris dengan soal kosong (template placeholder)
                    if (empty(trim($item['soal'] ?? ''))) {
                        continue;
                    }

                    $data = [
                        'id_dokumen' => $dokId,
                        'tipe' => 8,
                        'no' => $item['no'],
                        'urut' => $item['no'],
                        'soal' => $item['soal'],
                    ];

                    $data = $this->resolveKuk($item, $data, $unitCache, $kukCache);

                    KanSoal::updateOrCreate(
                        ['id_dokumen' => $dokId, 'no' => $item['no']],
                        $data
                    );
                    $total++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import gagal: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => "{$total} soal berhasil diimport untuk skema '{$request->nama}'",
            'data' => [
                'nama_skema' => $request->nama,
                'total' => $total,
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  IMPORT PER TIPE FILE
    // ──────────────────────────────────────────────

    /**
     * POST /api/kan/import/ia04b
     * Submit master soal IA04B (DIT) untuk suatu skema.
     * Body: { nama, soal: [{ no, soal, lingkup?, kode_unit? }] }
     */
    public function importIa04b(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nama'       => 'required|string',
            'soal'       => 'required|array|min:1',
            'soal.*.no'  => 'required|integer',
            'soal.*.soal'=> 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $jabatan   = $this->resolveJabatan($request->nama);
        $refs      = $this->preCacheSoalRefs($jabatan->id_jabatan_kerja);
        $dokumenId = $this->resolveDokumen($jabatan->id_jabatan_kerja, 'FR.IA.04.B', 'LEMBAR PERIKSA KEGIATAN TERSTRUKTUR');

        $total = 0;
        DB::beginTransaction();
        try {
            foreach ($request->soal as $item) {
                $data = [
                    'id_dokumen'        => $dokumenId,
                    'tipe'              => 8,
                    'no'                => $item['no'],
                    'urut'              => $item['no'],
                    'soal'              => $item['soal'],
                    'soal1'             => $item['lingkup'] ?? null,
                ];

                $unitId = $this->resolveUnitKode($item['kode_unit'] ?? '', $refs['units']);
                if ($unitId) {
                    $data['id_unitkompetensi'] = $unitId;
                }

                KanSoal::updateOrCreate(
                    ['id_dokumen' => $dokumenId, 'no' => $item['no']],
                    $data
                );
                $total++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import IA04B gagal: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => "{$total} soal IA04B berhasil diimport untuk skema '{$request->nama}'",
            'data'    => [
                'nama_skema' => $request->nama,
                'total'      => $total,
            ],
        ]);
    }

    /**
     * POST /api/kan/import/ia05
     * Submit master soal IA05 (Pilihan Ganda) untuk suatu skema.
     * Body: { nama, soal: [{ no, soal, jawab_a?, jawab_b?, jawab_c?, jawab_d?, jawaban?, kode_kuk? }] }
     */
    public function importIa05(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nama'       => 'required|string',
            'soal'       => 'required|array|min:1',
            'soal.*.no'  => 'required|integer',
            'soal.*.soal'=> 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $jabatan   = $this->resolveJabatan($request->nama);
        $refs      = $this->preCacheSoalRefs($jabatan->id_jabatan_kerja);
        $dokumenId = $this->resolveDokumen($jabatan->id_jabatan_kerja, 'FR.IA.05', 'PERTANYAAN TERTULIS PILIHAN GANDA');

        $total = 0;
        DB::beginTransaction();
        try {
            foreach ($request->soal as $item) {
                $data = [
                    'id_dokumen' => $dokumenId,
                    'tipe'       => 1,
                    'no'         => $item['no'],
                    'urut'       => $item['no'],
                    'soal'       => $item['soal'],
                    'jawab_a'    => $item['jawab_a'] ?? null,
                    'jawab_b'    => $item['jawab_b'] ?? null,
                    'jawab_c'    => $item['jawab_c'] ?? null,
                    'jawab_d'    => $item['jawab_d'] ?? null,
                    'jawaban'    => $item['jawaban'] ?? null,
                ];

                $data = $this->resolveKuk($item, $data, $refs['units'], $refs['kuks']);

                KanSoal::updateOrCreate(
                    ['id_dokumen' => $dokumenId, 'no' => $item['no']],
                    $data
                );
                $total++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import IA05 gagal: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => "{$total} soal IA05 berhasil diimport untuk skema '{$request->nama}'",
            'data'    => [
                'nama_skema' => $request->nama,
                'total'      => $total,
            ],
        ]);
    }

    /**
     * POST /api/kan/import/ia06
     * Submit master soal IA06 (Esai) untuk suatu skema.
     * Body: { nama, soal: [{ no, soal, kode_kuk? }] }
     */
    public function importIa06(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nama'       => 'required|string',
            'soal'       => 'required|array|min:1',
            'soal.*.no'  => 'required|integer',
            'soal.*.soal'=> 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $jabatan   = $this->resolveJabatan($request->nama);
        $refs      = $this->preCacheSoalRefs($jabatan->id_jabatan_kerja);
        $dokumenId = $this->resolveDokumen($jabatan->id_jabatan_kerja, 'FR.IA.06', 'PERTANYAAN TERTULIS ESAI');

        $total = 0;
        DB::beginTransaction();
        try {
            foreach ($request->soal as $item) {
                if (empty(trim($item['soal'] ?? ''))) {
                    continue;
                }

                $data = [
                    'id_dokumen' => $dokumenId,
                    'tipe'       => 8,
                    'no'         => $item['no'],
                    'urut'       => $item['no'],
                    'soal'       => $item['soal'],
                ];

                $data = $this->resolveKuk($item, $data, $refs['units'], $refs['kuks']);

                KanSoal::updateOrCreate(
                    ['id_dokumen' => $dokumenId, 'no' => $item['no']],
                    $data
                );
                $total++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import IA06 gagal: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'message' => "{$total} soal IA06 berhasil diimport untuk skema '{$request->nama}'",
            'data'    => [
                'nama_skema' => $request->nama,
                'total'      => $total,
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  SHARED HELPERS
    // ──────────────────────────────────────────────

    /**
     * Resolve jabatan kerja by name.
     */
    private function resolveJabatan(string $nama): JabatanKerja
    {
        $jabatan = JabatanKerja::where('jabatan_kerja', $nama)
            ->orderBy('id_jabatan_kerja', 'desc')
            ->first();

        if (!$jabatan) {
            abort(404, "Skema '{$nama}' tidak ditemukan di tabel jabatan_kerja");
        }

        return $jabatan;
    }

    /**
     * Find or create a JabatanDokumen record, return its id.
     */
    private function resolveDokumen($idJabatan, string $kode, string $namaDokumen): int
    {
        $dok = JabatanDokumen::where('id_jabatan', $idJabatan)
            ->where('kode', $kode)
            ->where('is_active', true)
            ->first();

        if (!$dok) {
            $dok = JabatanDokumen::create([
                'id_jabatan'   => $idJabatan,
                'kode'         => $kode,
                'nama_dokumen' => $namaDokumen,
                'is_active'    => true,
            ]);
        }

        return $dok->id;
    }

    /**
     * Pre-cache unit kompetensi & KUK untuk suatu jabatan.
     */
    private function preCacheSoalRefs($idJabatan): array
    {
        return [
            'units' => UnitKompetensi::where('id_jabatan', $idJabatan)
                ->pluck('id', 'kode'),
            'kuks'  => Kuk::pluck('id', 'kode'),
        ];
    }

    /**
     * Resolve kode_unit ke id_unitkompetensi.
     * Handles: multi-kode by \n (ambil kode pertama yg valid), trailing space.
     */
    private function resolveUnitKode(string $kodeUnitRaw, $unitCache): ?int
    {
        if (empty(trim($kodeUnitRaw))) {
            return null;
        }

        // Split by newline — try each code until one is found
        $codes = explode("\n", trim($kodeUnitRaw));
        foreach ($codes as $code) {
            $code = trim($code);
            if ($code === '') continue;
            if (isset($unitCache[$code])) {
                return $unitCache[$code];
            }
        }

        return null;
    }

    /**
     * Resolve kode_kuk (dengan format "{kode_unit}\n{nomor_kuk}") ke id_unitkompetensi + id_kuk
     */
    private function resolveKuk(array $item, array $data, $unitCache, $kukCache): array
    {
        if (empty($item['kode_kuk'])) {
            return $data;
        }

        $raw = $item['kode_kuk'];

        // Cari langsung di cache kuk
        if (isset($kukCache[$raw])) {
            $data['id_kuk'] = $kukCache[$raw];
            return $data;
        }

        // Split berdasarkan newline: "kode_unit\nnomor_kuk"
        $parts = explode("\n", $raw);
        $kodeUnit = trim($parts[0] ?? '');
        $nomorKuk = trim($parts[1] ?? '');

        // Resolve unit dari bagian pertama
        if ($kodeUnit && isset($unitCache[$kodeUnit])) {
            $data['id_unitkompetensi'] = $unitCache[$kodeUnit];
        }

        // Cari KUK dengan kombinasi kode_unit + nomor_kuk
        // Coba berbagai format yang mungkin
        if ($kodeUnit && $nomorKuk) {
            $possibleKodes = [
                $kodeUnit . "\n" . $nomorKuk,   // exact match with newline
                $kodeUnit . ' ' . $nomorKuk,     // space separator
                $kodeUnit . '_' . $nomorKuk,     // underscore separator
                $nomorKuk,                       // just the number
            ];

            foreach ($possibleKodes as $kode) {
                if (isset($kukCache[$kode])) {
                    $data['id_kuk'] = $kukCache[$kode];
                    break;
                }
            }
        }

        return $data;
    }
}
