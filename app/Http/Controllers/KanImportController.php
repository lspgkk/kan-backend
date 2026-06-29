<?php

namespace App\Http\Controllers;

use App\Models\JabatanDokumen;
use App\Models\JabatanKerja;
use App\Models\KanSoal;
use App\Models\Kuk;
use App\Models\SubunitKompetensi;
use App\Models\UnitKompetensi;
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
            'ia06.*.soal' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // ── 1. Resolve jabatan_kerja ──
        $jabatan = JabatanKerja::where('jabatan_kerja', $request->nama)->first();
        if (!$jabatan) {
            return response()->json([
                'message' => "Skema '{$request->nama}' tidak ditemukan di tabel jabatan_kerja",
            ], 404);
        }
        $idJabatan = $jabatan->id_jabatan_kerja;

        // ── 2. Pre-cache dokumen ──
        $dokumenCache = [];
        $dokumenKodes = ['FR.IA.04.B', 'FR.IA.05', 'FR.IA.06'];

        foreach ($dokumenKodes as $kode) {
            $dok = JabatanDokumen::where('id_jabatan', $idJabatan)
                ->where('kode', $kode)
                ->where('is_active', true)
                ->first();
            if (!$dok) {
                return response()->json([
                    'message' => "Dokumen {$kode} untuk skema ini tidak ditemukan atau tidak aktif",
                ], 404);
            }
            $dokumenCache[$kode] = $dok->id;
        }

        // ── 3. Pre-cache unit & kuk ──
        $unitCache = UnitKompetensi::where('id_jabatan', $idJabatan)
            ->pluck('id', 'kode');

        $kukCache = Kuk::pluck('id', 'kode');

        // ── 4. Import helper ──
        $total = 0;
        $errors = [];

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

                    if (!empty($item['kode_unit']) && isset($unitCache[$item['kode_unit']])) {
                        $data['id_unitkompetensi'] = $unitCache[$item['kode_unit']];
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
                'errors' => $errors,
            ],
        ]);
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
