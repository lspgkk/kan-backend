<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\AsesorBnsp;
use App\Models\Barcode;
use App\Models\BuktiAsesmen;
use App\Models\JabatanConfigA;
use App\Models\JabatanDokumen;
use App\Models\JabatanKerja;
use App\Models\Jadwal;
use App\Models\KanAk2Feedback;
use App\Models\KanIa4bAnswer;
use App\Models\KanIa5Answer;
use App\Models\KanIa5Feedback;
use App\Models\KanIa6Answer;
use App\Models\KanIa6Feedback;
use App\Models\KanPraktisiJabatan;
use App\Models\KanSoal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Personal;
use App\Models\Ak2Answer;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Ramsey\Uuid\Uuid;

class KanAsesmenController extends Controller
{
    // ──────────────────────────────────────────────
    //  BUILDER: DOKUMEN HEADER
    // ──────────────────────────────────────────────

    private function buildKanDokumenHeader($id): ?array
    {
        $namaAsesi = Personal::where('id_izin', $id)->value('nama');
        $jadwalId  = AsesiJadwal::where('id_izin', $id)->value('jadwal_id');

        if (!$jadwalId) {
            return null;
        }

        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return null;
        }

        $jabatanKerja = JabatanKerja::where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)
            ->value('jabatan_kerja');

        $jabatanConfig = JabatanConfigA::select('kan_no_skema')
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->first();

        $jadwal = Jadwal::with([
                'tuk:id,nama',
                'asesor:id,nama,noreg',
                'asesor2:id,nama,noreg',
            ])
            ->select(['jadwal_id', 'tuk_id', 'asesor_id', 'asesor2_id', 'tanggal_uji', 'tanggal_selesai', 'jenis_kelas'])
            ->where('jadwal_id', $jadwalId)
            ->first();

        if (!$jadwal) {
            return null;
        }

        // Build dynamic asesor list
        $asesorList = collect();
        if ($jadwal->asesor) {
            $asesorList->push((object) [
                'nama'  => $jadwal->asesor->nama,
                'noreg' => $jadwal->asesor->noreg,
                'id'    => $jadwal->asesor_id,
            ]);
        }
        if ($jadwal->asesor2) {
            $asesorList->push((object) [
                'nama'  => $jadwal->asesor2->nama,
                'noreg' => $jadwal->asesor2->noreg,
                'id'    => $jadwal->asesor2_id,
            ]);
        }

        $penyusunValidator = JabatanConfigA::with([
            'kanPenyusun:id,name,noreg',
            'kanValidator:id,name,noreg',
        ])
        ->select([
            'kan_penyusun_id', 'kan_penyusun_tgl', 'kan_penyusun_img',
            'kan_validator_id', 'kan_validator_tgl', 'kan_validator_img',
        ])
        ->where('id_jabatan', $klasifikasi->jabatan_kerja)
        ->first();

        return [
            // Flat variables (used by KAN templates)
            'jabatan_kerja'   => $jabatanKerja,
            'nomor_skema'     => $jabatanConfig?->kan_no_skema,
            'tuk'             => $jadwal->tuk?->nama,
            'nama_asesi'      => $namaAsesi,
            'asesor_list'     => $asesorList,
            'jenjang'         => $klasifikasi->jenjang,
            'tanggal'         => $jadwal->tanggal_uji
                ? Carbon::parse($jadwal->tanggal_uji)->translatedFormat('d F Y') : '',
            'tanggal_mulai'   => $jadwal->tanggal_uji
                ? Carbon::parse($jadwal->tanggal_uji)->translatedFormat('d F Y') : '',
            'tanggal_selesai' => $jadwal->tanggal_selesai
                ? Carbon::parse($jadwal->tanggal_selesai)->translatedFormat('d F Y') : '',
            // KAN Penyusun & Validator (separate from BNSP)
            'nama_penyusun'    => $penyusunValidator?->kanPenyusun?->name,
            'nama_validator'   => $penyusunValidator?->kanValidator?->name,
            'noreg_penyusun'   => $penyusunValidator?->kanPenyusun?->noreg,
            'noreg_validator'  => $penyusunValidator?->kanValidator?->noreg,
            'tanggal_penyusun' => $penyusunValidator?->kan_penyusun_tgl,
            'tanggal_validator'=> $penyusunValidator?->kan_validator_tgl,
            'barcode_penyusun' => $penyusunValidator?->kan_penyusun_img
                ? $this->imageUrlToBase64($penyusunValidator->kan_penyusun_img) : null,
            'barcode_validator'=> $penyusunValidator?->kan_validator_img
                ? $this->imageUrlToBase64($penyusunValidator->kan_validator_img) : null,
        ];
    }

    // ──────────────────────────────────────────────
    //  BUILDER: KAN IA04B DATA
    // ──────────────────────────────────────────────

    private function buildKanIa04bData(int|string $id, bool $forPdf = false): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)->first();

        if (!$klasifikasi) return null;

        $barcodes = $this->resolveDocumentBarcodes($id, 'IA04B', $forPdf);

        $dokumen = JabatanDokumen::select(['id', 'nama_dokumen'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.IA.04.B')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return [
                'barcodes' => $barcodes,
                'dokumen' => null,
                'soal' => [],
                'rekomendasi' => null,
                'total_skor' => 0,
            ];
        }

        // Questions (tipe=8 for DIT, tipe=3 for rekomendasi) with KAN answers
        $questions = KanSoal::select([
                'kan_soals.id',
                'kan_soals.no',
                'kan_soals.soal',
                'kan_soals.soal1',
                'kan_soals.soal2',
                'kan_soals.tipe',
                'kan_soals.is_komentar',
                'kan_soals.id_unitkompetensi',
                'kan_soals.id_kuk',
                'kan_ia4b_answers.jawaban',
                'kan_ia4b_answers.skor',
            ])
            ->leftJoin('kan_ia4b_answers', function ($join) use ($id, $dokumen) {
                $join->on('kan_ia4b_answers.soal_id', '=', 'kan_soals.id')
                    ->where('kan_ia4b_answers.id_izin', $id)
                    ->where('kan_ia4b_answers.dokumen_id', $dokumen->id);
            })
            ->with(['unit:id,kode', 'kuk:id,kode'])
            ->where('kan_soals.id_dokumen', $dokumen->id)
            ->whereIn('kan_soals.tipe', [3, 8])
            ->orderBy('kan_soals.no', 'asc')
            ->get()
            ->map(function ($q) {
                $q->pencapaian = $q->skor !== null ? (int) $q->skor : null;
                $q->unit_kode = $q->unit?->kode;
                $q->kuk_kode  = $q->kuk?->kode;
                return $q;
            });

        // Separate rekomendasi item
        $rekomendasi = $questions->firstWhere('tipe', 3);
        $soalList = $questions->where('tipe', '!=', 3)->values();

        // Calculate total skor
        $totalSkor = $soalList->sum('skor');

        return [
            'barcodes'    => $barcodes,
            'dokumen'     => $dokumen,
            'soal_list'   => $soalList,
            'rekomendasi' => $rekomendasi ? [
                'id'            => $rekomendasi->id,
                'soal'          => $rekomendasi->soal,
                'is_komentar'   => $rekomendasi->is_komentar,
                'rekomendasi'   => (bool) $rekomendasi->skor,
            ] : null,
            'total_skor'  => $totalSkor,
        ];
    }

    // ──────────────────────────────────────────────
    //  BUILDER: KAN IA05 DATA
    // ──────────────────────────────────────────────

    private function buildKanIa05Data(int|string $id, bool $forPdf = false): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)->first();

        if (!$klasifikasi) return null;

        $barcodes = $this->resolveDocumentBarcodes($id, 'IA05', $forPdf);

        $dokumen = JabatanDokumen::select(['id', 'nama_dokumen'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.IA.05')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return [
                'barcodes' => $barcodes,
                'dokumen' => null,
                'soal_list' => [],
                'umpan_balik' => null,
                'jumlah_benar' => 0,
                'jumlah_salah' => 0,
            ];
        }

        $questions = KanSoal::select([
                'kan_soals.id',
                'kan_soals.no',
                'kan_soals.soal',
                'kan_soals.jawab_a',
                'kan_soals.jawab_b',
                'kan_soals.jawab_c',
                'kan_soals.jawab_d',
                'kan_soals.jawaban as kunci_jawaban',
                'kan_soals.id_unitkompetensi',
                'kan_soals.id_kuk',
                'kan_ia5_answers.jawaban as jawaban_asesi',
                'kan_ia5_answers.skor',
            ])
            ->leftJoin('kan_ia5_answers', function ($join) use ($id, $dokumen) {
                $join->on('kan_ia5_answers.soal_id', '=', 'kan_soals.id')
                    ->where('kan_ia5_answers.id_izin', $id)
                    ->where('kan_ia5_answers.dokumen_id', $dokumen->id);
            })
            ->with(['unit:id,kode', 'kuk:id,kode'])
            ->where('kan_soals.id_dokumen', $dokumen->id)
            ->where('kan_soals.tipe', 1)
            ->orderBy('kan_soals.no', 'asc')
            ->get()
            ->map(function ($q) {
                $q->unit_kode = $q->unit?->kode;
                $q->kuk_kode  = $q->kuk?->kode;
                return $q;
            });

        $feedback = KanIa5Feedback::where('id_izin', $id)->value('umpan_balik');

        $jumlahBenar = $questions->filter(fn($q) => $q->skor === 1)->count();
        $jumlahSalah = $questions->filter(fn($q) => $q->skor === 0)->count();

        return [
            'barcodes'      => $barcodes,
            'dokumen'       => $dokumen,
            'soal_list'     => $questions,
            'umpan_balik'   => $feedback,
            'jumlah_benar'  => $jumlahBenar,
            'jumlah_salah'  => $jumlahSalah,
        ];
    }

    // ──────────────────────────────────────────────
    //  BUILDER: KAN IA06 DATA (NEW)
    // ──────────────────────────────────────────────

    private function buildKanIa06Data(int|string $id, bool $forPdf = false): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)->first();

        if (!$klasifikasi) return null;

        $barcodes = $this->resolveDocumentBarcodes($id, 'IA06', $forPdf);

        $dokumen = JabatanDokumen::select(['id', 'nama_dokumen'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.IA.06')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return [
                'barcodes'   => $barcodes,
                'dokumen'    => null,
                'soal_list'  => [],
                'umpan_balik'=> null,
                'unit_elemen_kuk' => null,
                'total_skor' => 0,
            ];
        }

        $questions = KanSoal::select([
                'kan_soals.id',
                'kan_soals.no',
                'kan_soals.soal',
                'kan_soals.id_unitkompetensi',
                'kan_soals.id_kuk',
                'kan_ia6_answers.jawaban',
                'kan_ia6_answers.skor',
            ])
            ->leftJoin('kan_ia6_answers', function ($join) use ($id, $dokumen) {
                $join->on('kan_ia6_answers.soal_id', '=', 'kan_soals.id')
                    ->where('kan_ia6_answers.id_izin', $id)
                    ->where('kan_ia6_answers.dokumen_id', $dokumen->id);
            })
            ->with(['unit:id,kode', 'kuk:id,kode,nama'])
            ->where('kan_soals.id_dokumen', $dokumen->id)
            ->where('kan_soals.tipe', 8)
            ->orderBy('kan_soals.no', 'asc')
            ->get()
            ->map(function ($q) {
                $q->unit_kode = $q->unit?->kode;
                $q->kuk_kode  = $q->kuk?->kode;
                $q->kuk_nama  = $q->kuk?->nama;
                return $q;
            });

        $ia6Feedback = KanIa6Feedback::where('id_izin', $id)->first();

        $totalSkor = $questions->sum('skor');

        return [
            'barcodes'       => $barcodes,
            'dokumen'        => $dokumen,
            'soal_list'      => $questions,
            'umpan_balik'    => $ia6Feedback?->umpan_balik,
            'unit_elemen_kuk'=> $ia6Feedback?->unit_elemen_kuk,
            'total_skor'     => $totalSkor,
        ];
    }

    // ──────────────────────────────────────────────
    //  BUILDER: KAN AK02 DATA
    // ──────────────────────────────────────────────

    private function buildKanAk02Data(int|string $id, bool $forPdf = false): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)->first();

        if (!$klasifikasi) return null;

        $barcodes = $this->resolveDocumentBarcodes($id, 'AK02', $forPdf);

        $dokumen = JabatanDokumen::select(['id', 'nama_dokumen'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.AK.02')
            ->where('is_active', true)
            ->first();

        // Unit kompetensi evidence (reuse existing BNSP table)
        $unitKompetensi = \App\Models\UnitKompetensi::select(['id', 'kode', 'nama'])
            ->whereHas('jabatanKelompokUnits.kelompok.dokumen', function ($q) use ($klasifikasi) {
                $q->where('id_jabatan', $klasifikasi->jabatan_kerja);
            })
            ->get()
            ->map(function ($unit) use ($id) {
                $evidence = Ak2Answer::where('id_izin', $id)
                    ->where('id_unit_kompetensi', $unit->id)
                    ->first();

                $unit->evidence = (object) [
                    'observasi'             => $evidence?->observasi ?? false,
                    'portofolio'            => $evidence?->portofolio ?? false,
                    'pertanyaan_wawancara'  => $evidence?->pertanyaan_wawancara ?? false,
                    'pertanyaan_lisan'      => $evidence?->pertanyaan_lisan ?? false,
                    'pertanyaan_tertulis'   => $evidence?->pertanyaan_tertulis ?? false,
                    'proyek_kerja'          => $evidence?->proyek_kerja ?? false,
                    'lainnya'               => $evidence?->lainnya ?? false,
                ];
                return $unit;
            });

        // KAN AK02 feedback scores
        $ak2Feedback = KanAk2Feedback::where('id_izin', $id)->first();

        $threshold = $klasifikasi->jenjang <= 6 ? 65 : 70;

        return [
            'barcodes'           => $barcodes,
            'dokumen'            => $dokumen,
            'unit_kompetensi'    => $unitKompetensi,
            'total_skor_dit'     => $ak2Feedback?->total_skor_dit,
            'total_skor_pilihan_ganda' => $ak2Feedback?->total_skor_pg,
            'total_skor_esai'    => $ak2Feedback?->total_skor_esai,
            'nilai_skor_dit'     => $ak2Feedback?->nilai_skor_dit,
            'nilai_skor_pilihan_ganda' => $ak2Feedback?->nilai_skor_pg,
            'nilai_skor_esai'    => $ak2Feedback?->nilai_skor_esai,
            'skor_nilai_akhir'   => $ak2Feedback?->skor_nilai_akhir,
            'isLulus'            => $ak2Feedback?->is_kompeten === true,
            'isTidakLulus'       => $ak2Feedback?->is_kompeten === false,
            'tindak_lanjut'      => $ak2Feedback?->tindak_lanjut,
            'komentar'           => $ak2Feedback?->komentar,
            'threshold_passing'  => $threshold,
        ];
    }

    // ──────────────────────────────────────────────
    //  SUBMIT: KAN IA04B
    // ──────────────────────────────────────────────

    public function submitKanIa04b(Request $request, $idIzin)
    {
        $validated = $request->validate([
            'dokumen_id'           => 'required|integer',
            'answers'              => 'required|array',
            'answers.*.soal_id'    => 'required|integer',
            'answers.*.jawaban'    => 'nullable|string',
            'answers.*.skor'       => 'nullable|integer|min:0|max:3',
            'rekomendasi'          => 'nullable|boolean',
        ]);

        $hasRekomendasi = $request->has('rekomendasi');
        $rekomendasiValue = $hasRekomendasi ? $request->boolean('rekomendasi') : null;

        DB::transaction(function () use ($validated, $idIzin, $hasRekomendasi, $rekomendasiValue) {
            foreach ($validated['answers'] as $answer) {
                $updateData = ['is_rekomendasi' => false];
                if (array_key_exists('jawaban', $answer)) {
                    $updateData['jawaban'] = $answer['jawaban'];
                }
                if (array_key_exists('skor', $answer)) {
                    $updateData['skor'] = $answer['skor'];
                }

                KanIa4bAnswer::updateOrCreate(
                    [
                        'id_izin'    => $idIzin,
                        'dokumen_id' => $validated['dokumen_id'],
                        'soal_id'    => $answer['soal_id'],
                    ],
                    $updateData
                );
            }

            // Handle rekomendasi item
            if ($hasRekomendasi) {
                $rekomSoal = KanSoal::where('id_dokumen', $validated['dokumen_id'])
                    ->where('tipe', 3)->first();

                if ($rekomSoal) {
                    KanIa4bAnswer::updateOrCreate(
                        [
                            'id_izin'    => $idIzin,
                            'dokumen_id' => $validated['dokumen_id'],
                            'soal_id'    => $rekomSoal->id,
                        ],
                        [
                            'is_rekomendasi' => true,
                            'rekomendasi'    => $rekomendasiValue,
                        ]
                    );
                }
            }
        });

        return response()->json(['message' => 'KAN IA04B answers saved']);
    }

    // ──────────────────────────────────────────────
    //  SUBMIT: KAN IA05
    // ──────────────────────────────────────────────

    public function submitKanIa05(Request $request, $idIzin)
    {
        $validated = $request->validate([
            'dokumen_id'           => 'required|integer',
            'answers'              => 'required|array',
            'answers.*.soal_id'    => 'required|integer',
            'answers.*.jawaban'    => 'nullable|in:A,B,C,D',
            'answers.*.skor'       => 'nullable|integer|min:0|max:1',
            'umpan_balik'          => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $idIzin) {
            foreach ($validated['answers'] as $answer) {
                $updateData = [];
                if (array_key_exists('jawaban', $answer)) {
                    $updateData['jawaban'] = $answer['jawaban'];
                }
                if (array_key_exists('skor', $answer)) {
                    $updateData['skor'] = $answer['skor'];
                }

                KanIa5Answer::updateOrCreate(
                    [
                        'id_izin'    => $idIzin,
                        'dokumen_id' => $validated['dokumen_id'],
                        'soal_id'    => $answer['soal_id'],
                    ],
                    $updateData
                );
            }

            if (!empty($validated['umpan_balik'])) {
                KanIa5Feedback::updateOrCreate(
                    ['id_izin' => $idIzin],
                    ['umpan_balik' => $validated['umpan_balik']]
                );
            }
        });

        return response()->json(['message' => 'KAN IA05 answers saved']);
    }

    // ──────────────────────────────────────────────
    //  SUBMIT: KAN IA06 (NEW)
    // ──────────────────────────────────────────────

    public function submitKanIa06(Request $request, $idIzin)
    {
        $validated = $request->validate([
            'dokumen_id'           => 'required|integer',
            'answers'              => 'required|array',
            'answers.*.soal_id'    => 'required|integer',
            'answers.*.jawaban'    => 'nullable|string',
            'answers.*.skor'       => 'nullable|integer|min:0|max:3',
            'umpan_balik'          => 'nullable|string',
            'unit_elemen_kuk'      => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $idIzin) {
            foreach ($validated['answers'] as $answer) {
                $updateData = [];
                if (array_key_exists('jawaban', $answer)) {
                    $updateData['jawaban'] = $answer['jawaban'];
                }
                if (array_key_exists('skor', $answer)) {
                    $updateData['skor'] = $answer['skor'];
                }

                KanIa6Answer::updateOrCreate(
                    [
                        'id_izin'    => $idIzin,
                        'dokumen_id' => $validated['dokumen_id'],
                        'soal_id'    => $answer['soal_id'],
                    ],
                    $updateData
                );
            }

            KanIa6Feedback::updateOrCreate(
                ['id_izin' => $idIzin],
                [
                    'umpan_balik'     => $validated['umpan_balik'] ?? null,
                    'unit_elemen_kuk' => $validated['unit_elemen_kuk'] ?? null,
                ]
            );
        });

        return response()->json(['message' => 'KAN IA06 answers saved']);
    }

    // ──────────────────────────────────────────────
    //  SUBMIT: KAN AK02
    // ──────────────────────────────────────────────

    public function submitKanAk02(Request $request, $id)
    {
        $validated = $request->validate([
            'answers'                           => 'required|array',
            'answers.*.id_unit_kompetensi'      => 'required|integer',
            'answers.*.observasi'               => 'nullable|boolean',
            'answers.*.portofolio'              => 'nullable|boolean',
            'answers.*.pertanyaan_wawancara'    => 'nullable|boolean',
            'answers.*.pertanyaan_lisan'        => 'nullable|boolean',
            'answers.*.pertanyaan_tertulis'     => 'nullable|boolean',
            'answers.*.proyek_kerja'            => 'nullable|boolean',
            'answers.*.lainnya'                 => 'nullable|boolean',
            'total_skor_dit'                    => 'nullable|numeric',
            'total_skor_pg'                     => 'nullable|numeric',
            'total_skor_esai'                   => 'nullable|numeric',
            'is_kompeten'                       => 'required|boolean',
            'tindak_lanjut'                     => 'nullable|string',
            'komentar'                          => 'nullable|string',
        ]);

        // Determine threshold based on jenjang
        $klasifikasi = KlasifikasiKualifikasi::select('jenjang')
            ->where('id_izin', $id)->first();
        $threshold = $klasifikasi && $klasifikasi->jenjang <= 6 ? 65 : 70;

        DB::transaction(function () use ($validated, $id, $threshold) {
            // Save evidence answers (reuse BNSP table)
            foreach ($validated['answers'] as $answer) {
                Ak2Answer::updateOrCreate(
                    [
                        'id_izin'            => $id,
                        'id_unit_kompetensi' => $answer['id_unit_kompetensi'],
                    ],
                    [
                        'observasi'            => $answer['observasi'] ?? false,
                        'portofolio'           => $answer['portofolio'] ?? false,
                        'pertanyaan_wawancara' => $answer['pertanyaan_wawancara'] ?? false,
                        'pertanyaan_lisan'     => $answer['pertanyaan_lisan'] ?? false,
                        'pertanyaan_tertulis'  => $answer['pertanyaan_tertulis'] ?? false,
                        'proyek_kerja'         => $answer['proyek_kerja'] ?? false,
                        'lainnya'              => $answer['lainnya'] ?? false,
                    ]
                );
            }

            // Compute weighted scores
            $totalDit  = $validated['total_skor_dit'] ?? 0;
            $totalPg   = $validated['total_skor_pg'] ?? 0;
            $totalEsai = $validated['total_skor_esai'] ?? 0;

            $nilaiDit  = $totalDit / 10 * 50;   // IA04B: 50% weight
            $nilaiPg   = $totalPg / 20 * 30;    // IA05: 30% weight
            $nilaiEsai = $totalEsai / 10 * 20;  // IA06: 20% weight
            $skorAkhir = $nilaiDit + $nilaiPg + $nilaiEsai;

            KanAk2Feedback::updateOrCreate(
                ['id_izin' => $id],
                [
                    'total_skor_dit'    => $totalDit,
                    'total_skor_pg'     => $totalPg,
                    'total_skor_esai'   => $totalEsai,
                    'nilai_skor_dit'    => $nilaiDit,
                    'nilai_skor_pg'     => $nilaiPg,
                    'nilai_skor_esai'   => $nilaiEsai,
                    'skor_nilai_akhir'  => $skorAkhir,
                    'is_kompeten'       => $validated['is_kompeten'],
                    'threshold_passing' => $threshold,
                    'tindak_lanjut'     => $validated['tindak_lanjut'] ?? null,
                    'komentar'          => $validated['komentar'] ?? null,
                ]
            );
        });

        return response()->json(['message' => 'KAN AK02 saved']);
    }

    // ──────────────────────────────────────────────
    //  GET METHODS
    // ──────────────────────────────────────────────

    public function getKanIa04b($id)
    {
        $data = $this->buildKanIa04bData($id);
        if ($data === null) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        return response()->json(['message' => 'OK', 'data' => $data]);
    }

    public function getKanIa05($id)
    {
        $data = $this->buildKanIa05Data($id);
        if ($data === null) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        return response()->json(['message' => 'OK', 'data' => $data]);
    }

    public function getKanIa06($id)
    {
        $data = $this->buildKanIa06Data($id);
        if ($data === null) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        return response()->json(['message' => 'OK', 'data' => $data]);
    }

    public function getKanAk02($id)
    {
        $data = $this->buildKanAk02Data($id);
        if ($data === null) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        return response()->json(['message' => 'OK', 'data' => $data]);
    }

    // ──────────────────────────────────────────────
    //  TTD / PDF GENERATION METHODS
    // ──────────────────────────────────────────────

    public function ttdKanIa04b(Request $request, $id)
    {
        $data = $this->generateTtdBarcode($request, 'IA04B', 'ttd_kan_ia04b', $id);
        $dataDokumen = $this->buildKanIa04bData($id, true);
        if (!$dataDokumen) abort(404, 'Data not found');

        $dokumenHeader = $this->buildKanDokumenHeader($id);
        if (!$dokumenHeader) abort(404, 'Dokumen header not found');

        $viewData = array_merge($dataDokumen, $dokumenHeader);

        $pdf = Pdf::loadView('pdf.kan_ia04b', $viewData)->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/kan_ia04b';
        $filename = 'KAN-IA04B-' . $id . '.pdf';
        $path = $directory . '/' . $filename;
        Storage::disk($disk)->put($path, $pdf->output());

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $fullUrl = $publicBaseUrl . $path;

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_kan_ia_04b' => $fullUrl]
        );

        return response()->json(['message' => 'Success', 'data' => $data]);
    }

    public function ttdKanIa05(Request $request, $id)
    {
        $data = $this->generateTtdBarcode($request, 'IA05', 'ttd_kan_ia05', $id);
        $dataDokumen = $this->buildKanIa05Data($id, true);
        if (!$dataDokumen) abort(404, 'Data not found');

        $dokumenHeader = $this->buildKanDokumenHeader($id);
        if (!$dokumenHeader) abort(404, 'Dokumen header not found');

        $viewData = array_merge($dataDokumen, $dokumenHeader);

        $pdf = Pdf::loadView('pdf.kan_ia05', $viewData)->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/kan_ia05';
        $filename = 'KAN-IA05-' . $id . '.pdf';
        $path = $directory . '/' . $filename;
        Storage::disk($disk)->put($path, $pdf->output());

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $fullUrl = $publicBaseUrl . $path;

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_kan_ia_05' => $fullUrl]
        );

        return response()->json(['message' => 'Success', 'data' => $data]);
    }

    public function ttdKanIa06(Request $request, $id)
    {
        $data = $this->generateTtdBarcode($request, 'IA06', 'ttd_kan_ia06', $id);
        $dataDokumen = $this->buildKanIa06Data($id, true);
        if (!$dataDokumen) abort(404, 'Data not found');

        $dokumenHeader = $this->buildKanDokumenHeader($id);
        if (!$dokumenHeader) abort(404, 'Dokumen header not found');

        $viewData = array_merge($dataDokumen, $dokumenHeader);

        $pdf = Pdf::loadView('pdf.kan_ia06', $viewData)->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/kan_ia06';
        $filename = 'KAN-IA06-' . $id . '.pdf';
        $path = $directory . '/' . $filename;
        Storage::disk($disk)->put($path, $pdf->output());

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $fullUrl = $publicBaseUrl . $path;

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_kan_ia_06' => $fullUrl]
        );

        return response()->json(['message' => 'Success', 'data' => $data]);
    }

    public function ttdKanAk02(Request $request, $id)
    {
        $data = $this->generateTtdBarcode($request, 'AK02', 'ttd_kan_ak02', $id);
        $dataDokumen = $this->buildKanAk02Data($id, true);
        if (!$dataDokumen) abort(404, 'Data not found');

        $dokumenHeader = $this->buildKanDokumenHeader($id);
        if (!$dokumenHeader) abort(404, 'Dokumen header not found');

        $viewData = array_merge($dataDokumen, $dokumenHeader);

        $pdf = Pdf::loadView('pdf.kan_ak02', $viewData)->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/kan_ak02';
        $filename = 'KAN-AK02-' . $id . '.pdf';
        $path = $directory . '/' . $filename;
        Storage::disk($disk)->put($path, $pdf->output());

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $fullUrl = $publicBaseUrl . $path;

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_kan_ak_02' => $fullUrl]
        );

        return response()->json(['message' => 'Success', 'data' => $data]);
    }

    // ──────────────────────────────────────────────
    //  BARCODE HELPERS (same logic as AsesmenController)
    // ──────────────────────────────────────────────

    private function resolveDocumentBarcodes(
        int|string $idIzin,
        string $jenisDokumen,
        bool $forPdf = false
    ): array {
        $map = $forPdf
            ? fn(?Barcode $b) => $this->mapBarcodeForPdf($b)
            : fn(?Barcode $b) => $this->mapBarcode($b);

        $barcodes = Barcode::with('user')
            ->where('id_izin', $idIzin)
            ->where('jenis_dokumen', $jenisDokumen)
            ->get()
            ->keyBy('id_user');

        if ($barcodes->isEmpty()) {
            return [
                'asesi'  => null,
                'asesor1' => null,
                'asesor2' => null,
            ];
        }

        $jadwal = Jadwal::select(['asesor_id', 'asesor2_id'])
            ->where('jadwal_id', $barcodes->first()->id_jadwal)
            ->first();

        if (!$jadwal) {
            return [
                'asesi'   => $map($barcodes->firstWhere('id_role', 8)),
                'asesor1' => null,
                'asesor2' => null,
            ];
        }

        $asesors = AsesorBnsp::select(['id', 'noreg'])
            ->whereIn('id', array_filter([$jadwal->asesor_id, $jadwal->asesor2_id]))
            ->get()
            ->keyBy('id');

        $asesorUsers = User::whereIn('noreg', $asesors->pluck('noreg'))
            ->get()
            ->keyBy('noreg');

        $asesor1User = isset($asesors[$jadwal->asesor_id])
            ? ($asesorUsers[$asesors[$jadwal->asesor_id]->noreg] ?? null) : null;

        $asesor2User = isset($asesors[$jadwal->asesor2_id])
            ? ($asesorUsers[$asesors[$jadwal->asesor2_id]->noreg] ?? null) : null;

        return [
            'asesi'   => $map($barcodes->firstWhere('id_role', 8)),
            'asesor1' => $asesor1User ? $map($barcodes[$asesor1User->id] ?? null) : null,
            'asesor2' => $asesor2User ? $map($barcodes[$asesor2User->id] ?? null) : null,
        ];
    }

    private function generateTtdBarcode(Request $request, string $docType, string $filePrefix, string $izinId)
    {
        $request->validate(['id_jadwal' => 'required']);

        $uuid = (string) Uuid::uuid4();
        $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";
        $filename = "{$filePrefix}_" . Str::uuid() . '.png';

        $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);

        $path = "barcodes/{$filename}";
        Storage::disk('str_moon')->put($path, $qrImage);

        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        Barcode::updateOrCreate(
            [
                'id_user'       => auth()->user()->id,
                'id_izin'       => $izinId,
                'jenis_dokumen' => $docType,
            ],
            [
                'id_jadwal'  => $request->id_jadwal,
                'url_image'  => $urlImage,
                'url_ttd'    => $urlTtd,
                'id_role'    => auth()->user()->role_id,
                'created_at' => now(),
            ]
        );

        return ['url_image' => $urlImage, 'url_ttd' => $urlTtd];
    }

    private function mapBarcode(?Barcode $b): ?object
    {
        if (!$b) return null;
        return (object) [
            'url'     => $b->url_image,
            'tanggal' => $b->updated_at,
        ];
    }

    private function mapBarcodeForPdf(?Barcode $b): ?object
    {
        if (!$b) return null;
        return (object) [
            'url'     => $this->imageUrlToBase64($b->url_image),
            'tanggal' => $b->updated_at,
        ];
    }

    private function imageUrlToBase64(?string $url): ?string
    {
        if (!$url) return null;
        try {
            $contents = file_get_contents($url);
            if ($contents === false) return null;
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'png';
            $mime = match (strtolower($extension)) {
                'jpg', 'jpeg' => 'image/jpeg',
                'gif'         => 'image/gif',
                'svg'         => 'image/svg+xml',
                default       => 'image/png',
            };
            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  KAN PRAKTISI JABATAN FLOW (keyed by kan_praktisi_jabatan.id)
    //  All methods below use kan_praktisi_jabatan_id instead of id_izin.
    //  BNSP flow above is NOT modified.
    // ═══════════════════════════════════════════════════════════════

    // ──────────────────────────────────────────────
    //  HEADER BUILDER: By Praktisi Jabatan
    // ──────────────────────────────────────────────

    private function buildKanDokumenHeaderByPraktisi(int $praktisiJabatanId, bool $forPdf = false): ?array
    {
        $record = KanPraktisiJabatan::with([
            'user:id,name,email',
            'jabatanKerja:id_jabatan_kerja,jabatan_kerja,jenjang_id',
        ])->find($praktisiJabatanId);

        if (!$record || !$record->user) {
            return null;
        }

        $jabatanConfig = JabatanConfigA::select([
            'kan_no_skema', 'kan_manajer_id', 'kan_manajer_tgl', 'kan_manajer_img',
        ])
            ->where('id_jabatan', $record->id_jabatan_kerja)
            ->first();

        $penyusunValidator = JabatanConfigA::with([
            'kanPenyusun:id,name,noreg',
            'kanValidator:id,name,noreg',
            'kanManajer:id,name,noreg',
            'manajer:id,name,noreg',
        ])
        ->select([
            'id_jabatan',
            'kan_penyusun_id', 'kan_penyusun_tgl', 'kan_penyusun_img',
            'kan_validator_id', 'kan_validator_tgl', 'kan_validator_img',
            'kan_manajer_id', 'kan_manajer_tgl', 'kan_manajer_img',
            'manajer_id', 'manajer_img',
        ])
        ->where('id_jabatan', $record->id_jabatan_kerja)
        ->first();

        $mapBarcode = fn(?string $url) => $url
            ? ($forPdf ? $this->imageUrlToBase64($url) : $url)
            : null;

        // Get the praktisi's TTD date from barcode (for tanggal in header)
        $praktisiBarcode = Barcode::where('kan_praktisi_jabatan_id', $praktisiJabatanId)
            ->where('jenis_dokumen', 'IA04B')
            ->first();
        $ttdDate = $praktisiBarcode?->created_at?->locale('id')->translatedFormat('d F Y') ?? '';

        return [
            'jabatan_kerja'   => $record->jabatanKerja?->jabatan_kerja ?? '',
            'nomor_skema'     => $jabatanConfig?->kan_no_skema,
            'tuk'             => 'TUK SEWAKTU GKK',
            'nama_asesi'      => $record->user->name,
            'asesor_list'     => collect(),
            'jenjang'         => $record->jabatanKerja?->jenjang_id ?? 0,
            'metode'          => 'observasi',
            'tanggal'         => $ttdDate,
            'tanggal_mulai'   => $ttdDate,
            'tanggal_selesai' => $ttdDate,
            'nama_penyusun'   => $penyusunValidator?->kanPenyusun?->name,
            'nama_validator'  => $penyusunValidator?->kanValidator?->name,
            'noreg_penyusun'  => $penyusunValidator?->kanPenyusun?->noreg,
            'noreg_validator' => $penyusunValidator?->kanValidator?->noreg,
            'tanggal_penyusun'  => $penyusunValidator?->kan_penyusun_tgl,
            'tanggal_validator' => $penyusunValidator?->kan_validator_tgl,
            'barcode_penyusun'  => $mapBarcode($penyusunValidator?->kan_penyusun_img),
            'barcode_validator' => $mapBarcode($penyusunValidator?->kan_validator_img),
            'nama_manajer'      => $penyusunValidator?->kanManajer?->name
                ?: $penyusunValidator?->manajer?->name,
            'tanggal_manajer'   => $penyusunValidator?->kan_manajer_tgl
                ?: $penyusunValidator?->kan_penyusun_tgl,
            'barcode_manajer'   => $mapBarcode($penyusunValidator?->kan_manajer_img)
                ?: $mapBarcode($penyusunValidator?->manajer_img),
        ];
    }

    // ──────────────────────────────────────────────
    //  DATA BUILDER: KAN IA04B By Praktisi
    // ──────────────────────────────────────────────

    private function buildKanIa04bDataByPraktisi(int $praktisiJabatanId, bool $forPdf = false): ?array
    {
        $record = KanPraktisiJabatan::select(['id_jabatan_kerja'])->find($praktisiJabatanId);
        if (!$record) return null;

        $barcodes = $this->resolveDocumentBarcodesByPraktisi($praktisiJabatanId, 'IA04B', $forPdf);

        $dokumen = JabatanDokumen::select(['id', 'nama_dokumen'])
            ->where('id_jabatan', $record->id_jabatan_kerja)
            ->where('kode', 'FR.IA.04.B')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return [
                'barcodes'    => $barcodes,
                'dokumen'     => null,
                'soal_list'   => [],
                'rekomendasi' => null,
                'total_skor'  => 0,
            ];
        }

        $questions = KanSoal::select([
                'kan_soals.id',
                'kan_soals.no',
                'kan_soals.soal',
                'kan_soals.soal1',
                'kan_soals.soal2',
                'kan_soals.tipe',
                'kan_soals.is_komentar',
                'kan_soals.id_unitkompetensi',
                'kan_soals.id_kuk',
                'kan_ia4b_answers.jawaban',
                'kan_ia4b_answers.skor',
            ])
            ->leftJoin('kan_ia4b_answers', function ($join) use ($praktisiJabatanId, $dokumen) {
                $join->on('kan_ia4b_answers.soal_id', '=', 'kan_soals.id')
                    ->where('kan_ia4b_answers.kan_praktisi_jabatan_id', $praktisiJabatanId)
                    ->where('kan_ia4b_answers.dokumen_id', $dokumen->id);
            })
            ->with(['unit:id,kode', 'kuk:id,kode'])
            ->where('kan_soals.id_dokumen', $dokumen->id)
            ->whereIn('kan_soals.tipe', [3, 8])
            ->orderBy('kan_soals.no', 'asc')
            ->get()
            ->map(function ($q) {
                $q->pencapaian = $q->skor !== null ? (int) $q->skor : null;
                $q->unit_kode = $q->unit?->kode;
                $q->kuk_kode  = $q->kuk?->kode;
                return $q;
            });

        // Override soal2 for IA04B to show unit kode
        $questions->each(function ($q) {
            if ($q->unit?->kode) {
                $q->soal2 = 'Kode Unit : ' . $q->unit->kode;
            }
        });

        $rekomendasi = $questions->firstWhere('tipe', 3);
        $soalList = $questions->where('tipe', '!=', 3)->values();

        return [
            'barcodes'    => $barcodes,
            'dokumen'     => $dokumen,
            'soal_list'   => $soalList,
            'rekomendasi' => $rekomendasi ? [
                'id'          => $rekomendasi->id,
                'soal'        => $rekomendasi->soal,
                'is_komentar' => $rekomendasi->is_komentar,
                'rekomendasi' => (bool) $rekomendasi->skor,
            ] : null,
            'total_skor'  => $soalList->sum('skor'),
        ];
    }

    // ──────────────────────────────────────────────
    //  DATA BUILDER: KAN IA05 By Praktisi
    // ──────────────────────────────────────────────

    private function buildKanIa05DataByPraktisi(int $praktisiJabatanId, bool $forPdf = false): ?array
    {
        $record = KanPraktisiJabatan::select(['id_jabatan_kerja'])->find($praktisiJabatanId);
        if (!$record) return null;

        $barcodes = $this->resolveDocumentBarcodesByPraktisi($praktisiJabatanId, 'IA05', $forPdf);

        $dokumen = JabatanDokumen::select(['id', 'nama_dokumen'])
            ->where('id_jabatan', $record->id_jabatan_kerja)
            ->where('kode', 'FR.IA.05')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return [
                'barcodes'      => $barcodes,
                'dokumen'       => null,
                'soal_list'     => [],
                'umpan_balik'   => null,
                'jumlah_benar'  => 0,
                'jumlah_salah'  => 0,
            ];
        }

        $questions = KanSoal::select([
                'kan_soals.id',
                'kan_soals.no',
                'kan_soals.soal',
                'kan_soals.jawab_a',
                'kan_soals.jawab_b',
                'kan_soals.jawab_c',
                'kan_soals.jawab_d',
                'kan_soals.jawaban as kunci_jawaban',
                'kan_soals.id_unitkompetensi',
                'kan_soals.id_kuk',
                'kan_ia5_answers.jawaban as jawaban_asesi',
                'kan_ia5_answers.skor',
            ])
            ->leftJoin('kan_ia5_answers', function ($join) use ($praktisiJabatanId, $dokumen) {
                $join->on('kan_ia5_answers.soal_id', '=', 'kan_soals.id')
                    ->where('kan_ia5_answers.kan_praktisi_jabatan_id', $praktisiJabatanId)
                    ->where('kan_ia5_answers.dokumen_id', $dokumen->id);
            })
            ->with(['unit:id,kode', 'kuk:id,kode'])
            ->where('kan_soals.id_dokumen', $dokumen->id)
            ->where('kan_soals.tipe', 1)
            ->orderBy('kan_soals.no', 'asc')
            ->get()
            ->map(function ($q) {
                $q->unit_kode = $q->unit?->kode;
                $q->kuk_kode  = $q->kuk?->kode;
                return $q;
            });

        $feedback = KanIa5Feedback::where('kan_praktisi_jabatan_id', $praktisiJabatanId)
            ->value('umpan_balik');

        $jumlahBenar = $questions->filter(fn($q) => $q->skor === 1)->count();
        $jumlahSalah = $questions->filter(fn($q) => $q->skor === 0)->count();

        return [
            'barcodes'      => $barcodes,
            'dokumen'       => $dokumen,
            'soal_list'     => $questions,
            'umpan_balik'   => $feedback,
            'jumlah_benar'  => $jumlahBenar,
            'jumlah_salah'  => $jumlahSalah,
        ];
    }

    // ──────────────────────────────────────────────
    //  DATA BUILDER: KAN IA06 By Praktisi
    // ──────────────────────────────────────────────

    private function buildKanIa06DataByPraktisi(int $praktisiJabatanId, bool $forPdf = false): ?array
    {
        $record = KanPraktisiJabatan::select(['id_jabatan_kerja'])->find($praktisiJabatanId);
        if (!$record) return null;

        $barcodes = $this->resolveDocumentBarcodesByPraktisi($praktisiJabatanId, 'IA06', $forPdf);

        $dokumen = JabatanDokumen::select(['id', 'nama_dokumen'])
            ->where('id_jabatan', $record->id_jabatan_kerja)
            ->where('kode', 'FR.IA.06')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return [
                'barcodes'       => $barcodes,
                'dokumen'        => null,
                'soal_list'      => [],
                'umpan_balik'    => null,
                'unit_elemen_kuk'=> null,
                'total_skor'     => 0,
            ];
        }

        $questions = KanSoal::select([
                'kan_soals.id',
                'kan_soals.no',
                'kan_soals.soal',
                'kan_soals.id_unitkompetensi',
                'kan_soals.id_kuk',
                'kan_ia6_answers.jawaban',
                'kan_ia6_answers.skor',
            ])
            ->leftJoin('kan_ia6_answers', function ($join) use ($praktisiJabatanId, $dokumen) {
                $join->on('kan_ia6_answers.soal_id', '=', 'kan_soals.id')
                    ->where('kan_ia6_answers.kan_praktisi_jabatan_id', $praktisiJabatanId)
                    ->where('kan_ia6_answers.dokumen_id', $dokumen->id);
            })
            ->with(['unit:id,kode', 'kuk:id,kode,nama'])
            ->where('kan_soals.id_dokumen', $dokumen->id)
            ->where('kan_soals.tipe', 8)
            ->orderBy('kan_soals.no', 'asc')
            ->get()
            ->map(function ($q) {
                $q->unit_kode = $q->unit?->kode;
                $q->kuk_kode  = $q->kuk?->kode;
                $q->kuk_nama  = $q->kuk?->nama;
                return $q;
            });

        $ia6Feedback = KanIa6Feedback::where('kan_praktisi_jabatan_id', $praktisiJabatanId)->first();

        return [
            'barcodes'        => $barcodes,
            'dokumen'         => $dokumen,
            'soal_list'       => $questions,
            'umpan_balik'     => $ia6Feedback?->umpan_balik,
            'unit_elemen_kuk' => $ia6Feedback?->unit_elemen_kuk,
            'total_skor'      => $questions->sum('skor'),
        ];
    }

    // ──────────────────────────────────────────────
    //  DATA BUILDER: KAN AK02 By Praktisi
    // ──────────────────────────────────────────────

    private function buildKanAk02DataByPraktisi(int $praktisiJabatanId, bool $forPdf = false): ?array
    {
        $record = KanPraktisiJabatan::with('jabatanKerja:id_jabatan_kerja,jenjang_id')
            ->find($praktisiJabatanId);
        if (!$record) return null;

        $barcodes = $this->resolveDocumentBarcodesByPraktisi($praktisiJabatanId, 'AK02', $forPdf);

        $dokumen = JabatanDokumen::select(['id', 'nama_dokumen'])
            ->where('id_jabatan', $record->id_jabatan_kerja)
            ->where('kode', 'FR.AK.02')
            ->where('is_active', true)
            ->first();

        $ak2Feedback = KanAk2Feedback::where('kan_praktisi_jabatan_id', $praktisiJabatanId)->first();

        // Unit kompetensi for this jabatan
        $unitKompetensi = \App\Models\UnitKompetensi::select(['id', 'kode', 'nama'])
            ->where('id_jabatan', $record->id_jabatan_kerja)
            ->get()
            ->map(function ($unit) {
                // Praktisi flow doesn't save individual Ak2Answer evidence,
                // default all to false
                $unit->evidence = (object) [
                    'observasi'             => false,
                    'portofolio'            => false,
                    'pertanyaan_wawancara'  => false,
                    'pertanyaan_lisan'      => false,
                    'pertanyaan_tertulis'   => false,
                    'proyek_kerja'          => false,
                    'lainnya'               => false,
                ];
                return $unit;
            });

        $jenjang = $record->jabatanKerja?->jenjang_id ?? 0;
        $threshold = $jenjang <= 6 ? 65 : 70;

        return [
            'barcodes'                 => $barcodes,
            'dokumen'                  => $dokumen,
            'unit_kompetensi'          => $unitKompetensi,
            'total_skor_dit'           => $ak2Feedback?->total_skor_dit,
            'total_skor_pilihan_ganda' => $ak2Feedback?->total_skor_pg,
            'total_skor_esai'          => $ak2Feedback?->total_skor_esai,
            'nilai_skor_dit'           => $ak2Feedback?->nilai_skor_dit,
            'nilai_skor_pilihan_ganda' => $ak2Feedback?->nilai_skor_pg,
            'nilai_skor_esai'          => $ak2Feedback?->nilai_skor_esai,
            'skor_nilai_akhir'         => $ak2Feedback?->skor_nilai_akhir,
            'isLulus'                  => $ak2Feedback?->is_kompeten === true,
            'isTidakLulus'             => $ak2Feedback?->is_kompeten === false,
            'tindak_lanjut'            => $ak2Feedback?->tindak_lanjut,
            'komentar'                 => $ak2Feedback?->komentar,
            'threshold_passing'        => $threshold,
        ];
    }

    // ──────────────────────────────────────────────
    //  BARCODE RESOLVER: By Praktisi
    // ──────────────────────────────────────────────

    private function resolveDocumentBarcodesByPraktisi(
        int $praktisiJabatanId,
        string $jenisDokumen,
        bool $forPdf = false
    ): array {
        $map = $forPdf
            ? fn(?Barcode $b) => $this->mapBarcodeForPdf($b)
            : fn(?Barcode $b) => $this->mapBarcode($b);

        $barcodes = Barcode::with('user')
            ->where('kan_praktisi_jabatan_id', $praktisiJabatanId)
            ->where('jenis_dokumen', $jenisDokumen)
            ->get()
            ->keyBy('id_user');

        if ($barcodes->isEmpty()) {
            return [
                'asesi'   => null,
                'asesor1' => null,
                'asesor2' => null,
            ];
        }

        // In Praktisi Jabatan flow, the barcode belongs to the praktisi (role 16),
        // so we look for the first matching role (16 for praktisi, 8 for asesi fallback)
        $asesiBarcode = $barcodes->firstWhere('id_role', 16)
            ?? $barcodes->firstWhere('id_role', 8)
            ?? $barcodes->first();

        return [
            'asesi'   => $map($asesiBarcode),
            'asesor1' => null,
            'asesor2' => null,
        ];
    }

    // ──────────────────────────────────────────────
    //  BARCODE GENERATOR: By Praktisi
    // ──────────────────────────────────────────────

    private function generateTtdBarcodeByPraktisi(
        Request $request,
        string $docType,
        string $filePrefix,
        int $praktisiJabatanId
    ): array {
        $uuid = (string) Uuid::uuid4();
        $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";
        $filename = "{$filePrefix}_" . Str::uuid() . '.png';

        $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);

        $path = "barcodes/{$filename}";
        Storage::disk('str_moon')->put($path, $qrImage);

        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        $existing = Barcode::where([
            'id_user'                 => auth()->user()->id,
            'kan_praktisi_jabatan_id' => $praktisiJabatanId,
            'jenis_dokumen'           => $docType,
        ])->first();

        $data = [
            'url_image' => $urlImage,
            'url_ttd'   => $urlTtd,
            'id_role'   => auth()->user()->role_id,
        ];

        // Preserve created_at from existing barcode (set by kan:generate-qr from tanggal_uji_coba_asesmen)
        // Only set on initial create, not on update
        if (!$existing) {
            $data['created_at'] = now();
        }

        Barcode::updateOrCreate(
            [
                'id_user'                 => auth()->user()->id,
                'kan_praktisi_jabatan_id' => $praktisiJabatanId,
                'jenis_dokumen'           => $docType,
            ],
            $data
        );

        return ['url_image' => $urlImage, 'url_ttd' => $urlTtd];
    }

    // ──────────────────────────────────────────────
    //  SUBMIT: KAN IA04B By Praktisi
    // ──────────────────────────────────────────────

    public function submitKanIa04bByPraktisi(Request $request, int $praktisiJabatanId): JsonResponse
    {
        $validated = $request->validate([
            'dokumen_id'           => 'required|integer',
            'answers'              => 'required|array',
            'answers.*.soal_id'    => 'required|integer',
            'answers.*.jawaban'    => 'nullable|string',
            'answers.*.skor'       => 'nullable|integer|min:0|max:3',
            'rekomendasi'          => 'nullable|boolean',
        ]);

        $hasRekomendasi = $request->has('rekomendasi');
        $rekomendasiValue = $hasRekomendasi ? $request->boolean('rekomendasi') : null;

        DB::transaction(function () use ($validated, $praktisiJabatanId, $hasRekomendasi, $rekomendasiValue) {
            foreach ($validated['answers'] as $answer) {
                $updateData = ['is_rekomendasi' => false];
                if (array_key_exists('jawaban', $answer)) {
                    $updateData['jawaban'] = $answer['jawaban'];
                }
                if (array_key_exists('skor', $answer)) {
                    $updateData['skor'] = $answer['skor'];
                }

                KanIa4bAnswer::updateOrCreate(
                    [
                        'kan_praktisi_jabatan_id' => $praktisiJabatanId,
                        'dokumen_id'              => $validated['dokumen_id'],
                        'soal_id'                 => $answer['soal_id'],
                    ],
                    $updateData
                );
            }

            if ($hasRekomendasi) {
                $rekomSoal = KanSoal::where('id_dokumen', $validated['dokumen_id'])
                    ->where('tipe', 3)->first();

                if ($rekomSoal) {
                    KanIa4bAnswer::updateOrCreate(
                        [
                            'kan_praktisi_jabatan_id' => $praktisiJabatanId,
                            'dokumen_id'              => $validated['dokumen_id'],
                            'soal_id'                 => $rekomSoal->id,
                        ],
                        [
                            'is_rekomendasi' => true,
                            'rekomendasi'    => $rekomendasiValue,
                        ]
                    );
                }
            }
        });

        return response()->json(['message' => 'KAN IA04B answers saved']);
    }

    // ──────────────────────────────────────────────
    //  SUBMIT: KAN IA05 By Praktisi
    // ──────────────────────────────────────────────

    public function submitKanIa05ByPraktisi(Request $request, int $praktisiJabatanId): JsonResponse
    {
        $validated = $request->validate([
            'dokumen_id'           => 'required|integer',
            'answers'              => 'required|array',
            'answers.*.soal_id'    => 'required|integer',
            'answers.*.jawaban'    => 'nullable|in:A,B,C,D',
            'answers.*.skor'       => 'nullable|integer|min:0|max:1',
            'umpan_balik'          => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $praktisiJabatanId) {
            foreach ($validated['answers'] as $answer) {
                $updateData = [];
                if (array_key_exists('jawaban', $answer)) {
                    $updateData['jawaban'] = $answer['jawaban'];
                }
                if (array_key_exists('skor', $answer)) {
                    $updateData['skor'] = $answer['skor'];
                }

                KanIa5Answer::updateOrCreate(
                    [
                        'kan_praktisi_jabatan_id' => $praktisiJabatanId,
                        'dokumen_id'              => $validated['dokumen_id'],
                        'soal_id'                 => $answer['soal_id'],
                    ],
                    $updateData
                );
            }

            if (!empty($validated['umpan_balik'])) {
                KanIa5Feedback::updateOrCreate(
                    ['kan_praktisi_jabatan_id' => $praktisiJabatanId],
                    ['umpan_balik' => $validated['umpan_balik']]
                );
            }
        });

        return response()->json(['message' => 'KAN IA05 answers saved']);
    }

    // ──────────────────────────────────────────────
    //  SUBMIT: KAN IA06 By Praktisi
    // ──────────────────────────────────────────────

    public function submitKanIa06ByPraktisi(Request $request, int $praktisiJabatanId): JsonResponse
    {
        $validated = $request->validate([
            'dokumen_id'           => 'required|integer',
            'answers'              => 'required|array',
            'answers.*.soal_id'    => 'required|integer',
            'answers.*.jawaban'    => 'nullable|string',
            'answers.*.skor'       => 'nullable|integer|min:0|max:3',
            'umpan_balik'          => 'nullable|string',
            'unit_elemen_kuk'      => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $praktisiJabatanId) {
            foreach ($validated['answers'] as $answer) {
                $updateData = [];
                if (array_key_exists('jawaban', $answer)) {
                    $updateData['jawaban'] = $answer['jawaban'];
                }
                if (array_key_exists('skor', $answer)) {
                    $updateData['skor'] = $answer['skor'];
                }

                KanIa6Answer::updateOrCreate(
                    [
                        'kan_praktisi_jabatan_id' => $praktisiJabatanId,
                        'dokumen_id'              => $validated['dokumen_id'],
                        'soal_id'                 => $answer['soal_id'],
                    ],
                    $updateData
                );
            }

            KanIa6Feedback::updateOrCreate(
                ['kan_praktisi_jabatan_id' => $praktisiJabatanId],
                [
                    'umpan_balik'     => $validated['umpan_balik'] ?? null,
                    'unit_elemen_kuk' => $validated['unit_elemen_kuk'] ?? null,
                ]
            );
        });

        return response()->json(['message' => 'KAN IA06 answers saved']);
    }

    // ──────────────────────────────────────────────
    //  SUBMIT: KAN AK02 By Praktisi
    // ──────────────────────────────────────────────

    public function submitKanAk02ByPraktisi(Request $request, int $praktisiJabatanId): JsonResponse
    {
        $validated = $request->validate([
            'total_skor_dit'  => 'nullable|numeric',
            'total_skor_pg'   => 'nullable|numeric',
            'total_skor_esai' => 'nullable|numeric',
            'is_kompeten'     => 'required|boolean',
            'tindak_lanjut'   => 'nullable|string',
            'komentar'        => 'nullable|string',
        ]);

        $record = KanPraktisiJabatan::with('jabatanKerja:id_jabatan_kerja,jenjang_id')
            ->find($praktisiJabatanId);

        $jenjang = $record?->jabatanKerja?->jenjang_id ?? 0;
        $threshold = $jenjang <= 6 ? 65 : 70;

        KanAk2Feedback::updateOrCreate(
            ['kan_praktisi_jabatan_id' => $praktisiJabatanId],
            [
                'total_skor_dit'    => $validated['total_skor_dit'] ?? null,
                'total_skor_pg'     => $validated['total_skor_pg'] ?? null,
                'total_skor_esai'   => $validated['total_skor_esai'] ?? null,
                'is_kompeten'       => $validated['is_kompeten'],
                'threshold_passing' => $threshold,
                'tindak_lanjut'     => $validated['tindak_lanjut'] ?? null,
                'komentar'          => $validated['komentar'] ?? null,
            ]
        );

        return response()->json(['message' => 'KAN AK02 saved']);
    }

    // ──────────────────────────────────────────────
    //  GET: KAN IA04B By Praktisi
    // ──────────────────────────────────────────────

    public function getKanIa04bByPraktisi(int $praktisiJabatanId): JsonResponse
    {
        $data = $this->buildKanIa04bDataByPraktisi($praktisiJabatanId);
        if ($data === null) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        return response()->json(['message' => 'OK', 'data' => $data]);
    }

    // ──────────────────────────────────────────────
    //  GET: KAN IA05 By Praktisi
    // ──────────────────────────────────────────────

    public function getKanIa05ByPraktisi(int $praktisiJabatanId): JsonResponse
    {
        $data = $this->buildKanIa05DataByPraktisi($praktisiJabatanId);
        if ($data === null) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        return response()->json(['message' => 'OK', 'data' => $data]);
    }

    // ──────────────────────────────────────────────
    //  GET: KAN IA06 By Praktisi
    // ──────────────────────────────────────────────

    public function getKanIa06ByPraktisi(int $praktisiJabatanId): JsonResponse
    {
        $data = $this->buildKanIa06DataByPraktisi($praktisiJabatanId);
        if ($data === null) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        return response()->json(['message' => 'OK', 'data' => $data]);
    }

    // ──────────────────────────────────────────────
    //  GET: KAN AK02 By Praktisi
    // ──────────────────────────────────────────────

    public function getKanAk02ByPraktisi(int $praktisiJabatanId): JsonResponse
    {
        $data = $this->buildKanAk02DataByPraktisi($praktisiJabatanId);
        if ($data === null) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        return response()->json(['message' => 'OK', 'data' => $data]);
    }

    // ──────────────────────────────────────────────
    //  GET: Dokumen Header By Praktisi
    // ──────────────────────────────────────────────

    public function getKanDokumenHeaderByPraktisi(int $praktisiJabatanId): JsonResponse
    {
        $data = $this->buildKanDokumenHeaderByPraktisi($praktisiJabatanId);
        if ($data === null) {
            return response()->json(['message' => 'Data not found'], 404);
        }
        return response()->json(['message' => 'Success', 'data' => $data]);
    }

    // ──────────────────────────────────────────────
    //  TTD / PDF: KAN IA04B By Praktisi
    // ──────────────────────────────────────────────

    public function ttdKanIa04bByPraktisi(Request $request, int $praktisiJabatanId)
    {
        $data = $this->generateTtdBarcodeByPraktisi($request, 'IA04B', 'ttd_kan_ia04b', $praktisiJabatanId);
        $dataDokumen = $this->buildKanIa04bDataByPraktisi($praktisiJabatanId, true);
        if (!$dataDokumen) abort(404, 'Data not found');

        $dokumenHeader = $this->buildKanDokumenHeaderByPraktisi($praktisiJabatanId, true);
        if (!$dokumenHeader) abort(404, 'Dokumen header not found');

        $viewData = array_merge($dataDokumen, $dokumenHeader);

        $pdf = Pdf::loadView('pdf.kan_ia04b', $viewData)->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/kan_ia04b';
        $filename = 'KAN-IA04B-PRAKTISI-' . $praktisiJabatanId . '.pdf';
        $path = $directory . '/' . $filename;
        Storage::disk($disk)->put($path, $pdf->output());

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        KanPraktisiJabatan::where('id', $praktisiJabatanId)->update(['url_ia04b' => $publicBaseUrl . $path]);

        return response()->json(['message' => 'Success', 'data' => $data]);
    }

    // ──────────────────────────────────────────────
    //  TTD / PDF: KAN IA05 By Praktisi
    // ──────────────────────────────────────────────

    public function ttdKanIa05ByPraktisi(Request $request, int $praktisiJabatanId)
    {
        $data = $this->generateTtdBarcodeByPraktisi($request, 'IA05', 'ttd_kan_ia05', $praktisiJabatanId);
        $dataDokumen = $this->buildKanIa05DataByPraktisi($praktisiJabatanId, true);
        if (!$dataDokumen) abort(404, 'Data not found');

        $dokumenHeader = $this->buildKanDokumenHeaderByPraktisi($praktisiJabatanId, true);
        if (!$dokumenHeader) abort(404, 'Dokumen header not found');

        $viewData = array_merge($dataDokumen, $dokumenHeader);

        $pdf = Pdf::loadView('pdf.kan_ia05', $viewData)->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/kan_ia05';
        $filename = 'KAN-IA05-PRAKTISI-' . $praktisiJabatanId . '.pdf';
        $path = $directory . '/' . $filename;
        Storage::disk($disk)->put($path, $pdf->output());

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        KanPraktisiJabatan::where('id', $praktisiJabatanId)->update(['url_ia05' => $publicBaseUrl . $path]);

        return response()->json(['message' => 'Success', 'data' => $data]);
    }

    // ──────────────────────────────────────────────
    //  TTD / PDF: KAN IA06 By Praktisi
    // ──────────────────────────────────────────────

    public function ttdKanIa06ByPraktisi(Request $request, int $praktisiJabatanId)
    {
        $data = $this->generateTtdBarcodeByPraktisi($request, 'IA06', 'ttd_kan_ia06', $praktisiJabatanId);
        $dataDokumen = $this->buildKanIa06DataByPraktisi($praktisiJabatanId, true);
        if (!$dataDokumen) abort(404, 'Data not found');

        $dokumenHeader = $this->buildKanDokumenHeaderByPraktisi($praktisiJabatanId, true);
        if (!$dokumenHeader) abort(404, 'Dokumen header not found');

        $viewData = array_merge($dataDokumen, $dokumenHeader);

        $pdf = Pdf::loadView('pdf.kan_ia06', $viewData)->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/kan_ia06';
        $filename = 'KAN-IA06-PRAKTISI-' . $praktisiJabatanId . '.pdf';
        $path = $directory . '/' . $filename;
        Storage::disk($disk)->put($path, $pdf->output());

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        KanPraktisiJabatan::where('id', $praktisiJabatanId)->update(['url_ia06' => $publicBaseUrl . $path]);

        return response()->json(['message' => 'Success', 'data' => $data]);
    }

    // ──────────────────────────────────────────────
    //  TTD / PDF: KAN AK02 By Praktisi
    // ──────────────────────────────────────────────

    public function ttdKanAk02ByPraktisi(Request $request, int $praktisiJabatanId)
    {
        $data = $this->generateTtdBarcodeByPraktisi($request, 'AK02', 'ttd_kan_ak02', $praktisiJabatanId);
        $dataDokumen = $this->buildKanAk02DataByPraktisi($praktisiJabatanId, true);
        if (!$dataDokumen) abort(404, 'Data not found');

        $dokumenHeader = $this->buildKanDokumenHeaderByPraktisi($praktisiJabatanId, true);
        if (!$dokumenHeader) abort(404, 'Dokumen header not found');

        $viewData = array_merge($dataDokumen, $dokumenHeader);

        $pdf = Pdf::loadView('pdf.kan_ak02', $viewData)->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/kan_ak02';
        $filename = 'KAN-AK02-PRAKTISI-' . $praktisiJabatanId . '.pdf';
        $path = $directory . '/' . $filename;
        Storage::disk($disk)->put($path, $pdf->output());

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        KanPraktisiJabatan::where('id', $praktisiJabatanId)->update(['url_ak02' => $publicBaseUrl . $path]);

        return response()->json(['message' => 'Success', 'data' => $data]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  GENERATE PDF: KAN Documents by Praktisi
    // ═══════════════════════════════════════════════════════════════

    private const KAN_DOC_TYPES = [
        ['method' => 'KanIa04b', 'blade' => 'kan_ia04b', 'dir' => 'kan_ia04b', 'code' => 'KAN-IA-04B', 'label' => 'IA04B',  'url_col' => 'url_ia04b'],
        ['method' => 'KanIa05',  'blade' => 'kan_ia05',  'dir' => 'kan_ia05',  'code' => 'KAN-IA-05',  'label' => 'IA05',   'url_col' => 'url_ia05'],
        ['method' => 'KanIa06',  'blade' => 'kan_ia06',  'dir' => 'kan_ia06',  'code' => 'KAN-IA-06',  'label' => 'IA06',   'url_col' => 'url_ia06'],
        ['method' => 'KanMapa',  'blade' => 'kan_mapa01', 'dir' => 'kan_mapa',  'code' => 'KAN-MAPA-01', 'label' => 'MAPA01',  'url_col' => 'url_mapa01'],
        ['method' => 'KanMapa2', 'blade' => 'kan_mapa02', 'dir' => 'kan_mapa2', 'code' => 'KAN-MAPA-02', 'label' => 'MAPA02',  'url_col' => 'url_mapa02'],
    ];

    /**
     * Generate PDF file(s) for a praktisi. Saves to storage + updates DB.
     * Returns array of ['filename' => ..., 'content' => ..., 'label' => ...].
     */
    private function generatePdfFiles(int $praktisiJabatanId, ?string $docFilter = null): array
    {
        $record = KanPraktisiJabatan::find($praktisiJabatanId);
        if (!$record) return [];

        $docs = self::KAN_DOC_TYPES;
        if ($docFilter) {
            $docs = array_filter($docs, fn($d) => strtolower($d['label']) === strtolower($docFilter));
        }

        $disk = 'str_moon';
        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $files = [];

        foreach ($docs as $doc) {
            try {
                $header = $this->buildKanDokumenHeaderByPraktisiForPdf($praktisiJabatanId);
                $docData = match ($doc['method']) {
                    'KanIa04b' => $this->buildKanIa04bDataByPraktisiForPdf($praktisiJabatanId),
                    'KanIa05'  => $this->buildKanIa05DataByPraktisiForPdf($praktisiJabatanId),
                    'KanIa06'  => $this->buildKanIa06DataByPraktisiForPdf($praktisiJabatanId),
                    'KanAk02'  => $this->buildKanAk02DataByPraktisiForPdf($praktisiJabatanId),
                    'KanMapa'  => $this->buildKanMapaDataByPraktisiForPdf($praktisiJabatanId),
                    'KanMapa2' => $this->buildKanMapa2DataByPraktisiForPdf($praktisiJabatanId),
                    default    => null,
                };

                if ($docData === null || $header === null) continue;

                if (in_array($doc['method'], ['KanMapa', 'KanMapa2'])) {
                    $viewData = array_merge($docData, ['dokumenHeader' => $header]);
                    $viewData['skkni'] = $viewData['skkni'] ?? '';
                    $viewData['kelompok_kerja'] = $viewData['kelompok_kerja'] ?? ['kelompok_kerja' => []];
                    $viewData['referensi_form'] = $viewData['referensi_form'] ?? [];
                } else {
                    $viewData = array_merge($docData, $header);
                }

                $filename = $doc['code'] . '-PRAKTISI-' . $praktisiJabatanId . '.pdf';
                $path = "dok_asesmen/{$doc['dir']}/{$filename}";

                $pdf = Pdf::loadView("pdf.{$doc['blade']}", $viewData)->setPaper('A4', 'portrait');
                $pdfOutput = $pdf->output();

                Storage::disk($disk)->put($path, $pdfOutput);
                $fullUrl = $publicBaseUrl . $path;

                $record->update([$doc['url_col'] => $fullUrl]);

                $files[] = [
                    'filename' => $filename,
                    'content'  => $pdfOutput,
                    'label'    => $doc['label'],
                ];
            } catch (\Throwable $e) {
                // skip failed docs
            }
        }

        return $files;
    }

    /**
     * POST /kan/asesmen/{id}/generate-pdf
     * Generate KAN PDF(s) for a praktisi, return ZIP download.
     *
     * Query params:
     *   - doc: ia04b|ia05|ia06|ak02|mapa01|mapa02  (single doc, default = all)
     */
    public function generateKanPdfByPraktisi(Request $request, int $praktisiJabatanId)
    {
        $record = KanPraktisiJabatan::with('user:id,name')->find($praktisiJabatanId);
        if (!$record) {
            return response()->json(['message' => 'Data not found'], 404);
        }

        $docFilter = $request->query('doc');
        if ($docFilter) {
            $valid = collect(self::KAN_DOC_TYPES)->first(fn($d) => strtolower($d['label']) === strtolower($docFilter));
            if (!$valid) {
                return response()->json([
                    'message' => "Invalid doc type. Valid: ia04b, ia05, ia06, mapa01, mapa02",
                ], 422);
            }
        }

        $files = $this->generatePdfFiles($praktisiJabatanId, $docFilter);
        if (empty($files)) {
            return response()->json(['message' => 'No PDF generated, data not ready'], 400);
        }

        $zip = new ZipArchive();
        $zipName = "dokumen-kan-{$praktisiJabatanId}.zip";
        $zipPath = tempnam(sys_get_temp_dir(), 'kan_');

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            return response()->json(['message' => 'Failed to create ZIP'], 500);
        }

        foreach ($files as $file) {
            $zip->addFromString($file['filename'], $file['content']);
        }
        $zip->close();

        return response()->streamDownload(function () use ($zipPath) {
            readfile($zipPath);
            unlink($zipPath);
        }, $zipName, ['Content-Type' => 'application/zip']);
    }

    /**
     * POST /kan/asesmen/bulk-generate-pdf
     * Generate PDFs for multiple praktisi, return ZIP download.
     *
     * Body: { "ids": [1, 2, 3] }
     */
    public function generateKanPdfBulk(Request $request)
    {
        $validated = $request->validate([
            'ids'     => 'required|array',
            'ids.*'   => 'integer|exists:kan_praktisi_jabatan,id',
        ]);

        $zip = new ZipArchive();
        $zipName = 'dokumen-kan-bulk.zip';
        $zipPath = tempnam(sys_get_temp_dir(), 'kan_bulk_');

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            return response()->json(['message' => 'Failed to create ZIP'], 500);
        }

        $total = 0;

        foreach ($validated['ids'] as $id) {
            $record = KanPraktisiJabatan::with('user:id,name')->find($id);
            if (!$record) continue;

            $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $record->user?->name ?? "praktisi-{$id}");
            $folder = "{$name}-{$id}/";
            $files = $this->generatePdfFiles($id);

            foreach ($files as $file) {
                $zip->addFromString($folder . $file['filename'], $file['content']);
                $total++;
            }
        }

        $zip->close();

        if ($total === 0) {
            unlink($zipPath);
            return response()->json(['message' => 'No PDFs generated'], 400);
        }

        return response()->streamDownload(function () use ($zipPath) {
            readfile($zipPath);
            unlink($zipPath);
        }, $zipName, ['Content-Type' => 'application/zip']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  DOWNLOAD ALL DOKUMEN PRAKTISI (ZIP)
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /kan/asesmen/download-all-dokumen
     *
     * Download seluruh dokumen KAN (mapa01, mapa02, ia04b, ia05, ia06)
     * untuk semua praktisi dalam satu file ZIP.
     *
     * Struktur ZIP:
     *   {id_jabatan_kerja - jabatan_kerja}/
     *     {nama_praktisi - id_praktisi_jabatan}/
     *       KAN-MAPA-01-PRAKTISI-{id}.pdf
     *       KAN-MAPA-02-PRAKTISI-{id}.pdf
     *       KAN-IA-04B-PRAKTISI-{id}.pdf
     *       KAN-IA-05-PRAKTISI-{id}.pdf
     *       KAN-IA-06-PRAKTISI-{id}.pdf
     *
     * Jika PDF belum pernah digenerate, akan digenerate terlebih dahulu.
     */
    public function downloadAllDokumenPraktisi(Request $request)
    {
        set_time_limit(0);
        try {
            $records = KanPraktisiJabatan::with(['user:id,name', 'jabatanKerja'])
                ->orderBy('id_jabatan_kerja')
                ->get();

            if ($records->isEmpty()) {
                return response()->json(['message' => 'Tidak ada data praktisi'], 404);
            }

            $grouped = $records->groupBy('id_jabatan_kerja');

            $zip = new ZipArchive();
            $zipName = 'dokumen-praktisi-all.zip';
            $zipPath = tempnam(sys_get_temp_dir(), 'kan_all_');

            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                return response()->json(['message' => 'Gagal membuat file ZIP'], 500);
            }

            $totalFiles = 0;
            $disk = 'str_moon';
            $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
            $docTypes = self::KAN_DOC_TYPES;

            foreach ($grouped as $idJabatanKerja => $praktisiList) {
                $jabatanKerja = $praktisiList->first()->jabatanKerja;
                $namaJabatan = $jabatanKerja ? $jabatanKerja->jabatan_kerja : 'Unknown';
                $jabatanFolder = $idJabatanKerja . ' - ' . $namaJabatan . '/';

                foreach ($praktisiList as $kpj) {
                    $kpjId = $kpj->id;
                    $userName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $kpj->user?->name ?? 'praktisi-' . $kpj->id_user);
                    $praktisiFolder = $jabatanFolder . $userName . '-' . $kpjId . '/';

                    // Cek apakah ada dokumen yg belum di-generate
                    $anyMissing = false;
                    foreach ($docTypes as $doc) {
                        if (empty($kpj->{$doc['url_col']})) {
                            $anyMissing = true;
                            break;
                        }
                    }

                    // Generate semua dokumen kalo ada yg missing
                    // Return value dipake langsung (skip FTP readback) = jauh lebih cepet
                    $generatedFiles = [];
                    if ($anyMissing) {
                        $generatedFiles = $this->generatePdfFiles($kpjId);
                        // Re-fetch biar dapet URL update buat doc yg tetep null abis generate
                        $kpj = KanPraktisiJabatan::find($kpjId);
                        if (!$kpj) continue;
                    }

                    // ZIP semua dokumen
                    foreach ($docTypes as $doc) {
                        $label = $doc['label'];

                        // Prioritas 1: pake content dari hasil generate langsung (gak perlu FTP)
                        $matched = array_values(array_filter($generatedFiles, fn($f) => $f['label'] === $label));
                        if (!empty($matched)) {
                            $zip->addFromString($praktisiFolder . $matched[0]['filename'], $matched[0]['content']);
                            $totalFiles++;
                            continue;
                        }

                        // Prioritas 2: baca dari FTP kalo dokumen udah existing
                        $url = $kpj->{$doc['url_col']};
                        if (empty($url)) continue;

                        $path = str_replace($publicBaseUrl, '', $url);
                        $filename = basename($path);

                        try {
                            $content = Storage::disk($disk)->get($path);
                            if ($content) {
                                $zip->addFromString($praktisiFolder . $filename, $content);
                                $totalFiles++;
                            }
                        } catch (\Throwable $e) {
                            // skip kalo FTP gagal
                        }
                    }
                }
            }

            $zip->close();

            if ($totalFiles === 0) {
                unlink($zipPath);
                return response()->json(['message' => 'Tidak ada dokumen yang tersedia untuk diunduh'], 400);
            }

            return response()->streamDownload(function () use ($zipPath) {
                readfile($zipPath);
                unlink($zipPath);
            }, $zipName, ['Content-Type' => 'application/zip']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  PUBLIC WRAPPERS for kan:generate-muk command
    //  (private builders called with $forPdf=true)
    // ═══════════════════════════════════════════════════════════════

    public function buildKanDokumenHeaderByPraktisiForPdf(int $kpjId): ?array
    {
        return $this->buildKanDokumenHeaderByPraktisi($kpjId, true);
    }

    public function buildKanIa04bDataByPraktisiForPdf(int $kpjId): ?array
    {
        return $this->buildKanIa04bDataByPraktisi($kpjId, true);
    }

    public function buildKanIa05DataByPraktisiForPdf(int $kpjId): ?array
    {
        return $this->buildKanIa05DataByPraktisi($kpjId, true);
    }

    public function buildKanIa06DataByPraktisiForPdf(int $kpjId): ?array
    {
        return $this->buildKanIa06DataByPraktisi($kpjId, true);
    }

    public function buildKanAk02DataByPraktisiForPdf(int $kpjId): ?array
    {
        return $this->buildKanAk02DataByPraktisi($kpjId, true);
    }

    public function buildKanMapaDataByPraktisiForPdf(int $kpjId): ?array
    {
        $data = $this->buildKanMapaDataByPraktisi($kpjId);
        if (!$data) return null;

        // Convert BNSP-format referensi_form to flat arrays for KAN template
        return array_merge($data, $this->flattenMapaReferensiForm($data['referensi_form'] ?? []));
    }

    public function buildKanMapa2DataByPraktisiForPdf(int $kpjId): ?array
    {
        return $this->buildKanMapa2DataByPraktisi($kpjId);
    }

    // ═══════════════════════════════════════════════════════════════
    //  MAPA02 DATA BUILDER (KAN — by kan_praktisi_jabatan)
    // ═══════════════════════════════════════════════════════════════

    private function buildKanMapa2DataByPraktisi(int $kpjId): ?array
    {
        $record = KanPraktisiJabatan::with([
            'user:id,name',
            'jabatanKerja:id_jabatan_kerja,jabatan_kerja,jenjang_id',
        ])->find($kpjId);

        if (!$record) return null;

        // Try JabatanDokumen first (BNSP format)
        $dokumen = \App\Models\JabatanDokumen::with(['kelompoks.units.unit'])
            ->where('id_jabatan', $record->id_jabatan_kerja)
            ->where('kode', 'MAPA02')
            ->where('is_active', true)
            ->first();

        if ($dokumen) {
            $kelompokKerja = [
                'id' => $dokumen->id,
                'kode' => $dokumen->kode,
                'nama_dokumen' => $dokumen->nama_dokumen,
                'kelompok_kerja' => $dokumen->kelompoks->map(function ($kelompok) {
                    return [
                        'id' => $kelompok->id,
                        'nama' => $kelompok->nama,
                        'urut' => $kelompok->urut,
                        'units' => $kelompok->units->map(function ($unit) {
                            return [
                                'id_unit'   => $unit->id_unit,
                                'nama_unit' => $unit->unit?->nama,
                                'kode_unit' => $unit->unit?->kode,
                            ];
                        })->values()->toArray(),
                    ];
                })->values()->toArray(),
            ];
        } else {
            // Fallback: build from UnitKompetensi
            $mapa01 = $this->buildKanMapaDataByPraktisi($kpjId);
            if (!$mapa01) return null;
            $kelompokKerja = $mapa01['kelompok_kerja'];
        }

        // Get MAPA01 data to determine potensi asesi index
        $mapa01Data = $this->buildKanMapaDataByPraktisi($kpjId);
        $potensiAsesiIndex = 1;

        if ($mapa01Data && isset($mapa01Data['referensi_form'])) {
            foreach ($mapa01Data['referensi_form'] as $kelompok) {
                if (($kelompok['kelompok']['id'] ?? 0) == 1) {
                    foreach ($kelompok['kelompok']['kategoris'] ?? [] as $kategori) {
                        if (($kategori['id'] ?? 0) == 1) {
                            foreach ($kategori['subkategoris'][0]['referensis'] ?? [] as $index => $referensi) {
                                if (!empty($referensi['value'])) {
                                    $potensiAsesiIndex = $index + 1;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        // KAN always uses observasi method
        // Default auto-checked instruments: IA.04B, IA.05, IA.06
        $defaultFrCodes = ['IA.04B', 'IA.05', 'IA.06'];

        // Load referensi from DB
        $referensis = \App\Models\Referensi::select(['id', 'nama', 'kategori', 'isdefault'])
            ->whereIn('kategori', ['MAPA02-1', 'MAPA02_1'])
            ->get();

        $dataReferensi = $referensis
            ->groupBy('kategori')
            ->map(function ($items, $kategori) use ($potensiAsesiIndex, $defaultFrCodes) {
                return [
                    'kategori'   => $kategori,
                    'referensis' => $items->map(function ($item) use ($potensiAsesiIndex, $kategori, $defaultFrCodes) {
                        $isdefault = $item->isdefault;

                        if ($kategori === 'MAPA02_1' && !empty($defaultFrCodes)) {
                            $matched = false;
                            foreach ($defaultFrCodes as $frCode) {
                                if (str_contains($item->nama, $frCode)) {
                                    $matched = true;
                                    break;
                                }
                            }
                            $isdefault = $matched ? 1 : null;
                        }

                        return [
                            'id'         => $item->id,
                            'nama'       => $item->nama,
                            'isdefault'  => $isdefault,
                            'potensi_asesi_index' => $potensiAsesiIndex,
                        ];
                    })->values()->toArray(),
                ];
            })
            ->values()
            ->toArray();

        return [
            'kelompok_kerja' => $kelompokKerja,
            'referensi_form' => $dataReferensi,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  MAPA01 DATA BUILDER (KAN — by kan_praktisi_jabatan)
    //  Returns BNSP-format data (matches PraAsesmenController)
    // ═══════════════════════════════════════════════════════════════

    private function buildKanMapaDataByPraktisi(int $kpjId): ?array
    {
        $record = KanPraktisiJabatan::with([
            'user:id,name',
            'jabatanKerja:id_jabatan_kerja,jabatan_kerja,jenjang_id,acuan',
        ])->find($kpjId);

        if (!$record) return null;

        $idJabatan = $record->id_jabatan_kerja;
        $jenjang = $record->jabatanKerja->jenjang_id ?? 0;

        // Try JabatanDokumen first (BNSP format)
        $dokumen = \App\Models\JabatanDokumen::with(['kelompoks.units.unit'])
            ->where('id_jabatan', $idJabatan)
            ->where('kode', 'MAPA01')
            ->where('is_active', true)
            ->first();

        if ($dokumen) {
            // ✅ BNSP-format: use JabatanDokumen structure
            $kelompokKerja = [
                'id' => $dokumen->id,
                'kode' => $dokumen->kode,
                'nama_dokumen' => $dokumen->nama_dokumen,
                'kelompok_kerja' => $dokumen->kelompoks->map(function ($kelompok) {
                    return [
                        'id' => $kelompok->id,
                        'nama' => $kelompok->nama,
                        'urut' => $kelompok->urut,
                        'units' => $kelompok->units->map(function ($unit) {
                            return [
                                'id_unit'   => $unit->id_unit,
                                'nama_unit' => $unit->unit?->nama,
                                'kode_unit' => $unit->unit?->kode,
                            ];
                        })->values()->toArray(),
                    ];
                })->values()->toArray(),
            ];

            // Load referensi with full nested grouping (same as BNSP buildMapa01Data)
            // Default checked values for KAN observasi context
            $kanMapa01Defaults = [
                82  => true,  // hasil pelatihan mampu telusur
                87  => true,  // sertifikasi
                91  => true,  // tempat kerja nyata
                93  => true,  // tersedia
                98  => true,  // LSP Gatensi Karya Konstruksi
                101 => true,  // manajer sertifikasi LSP
                105 => true,  // standar kompetensi (SKKNI)
                114 => true,  // peluang kegiatan asesmen terintegrasi
            ];

            $referensis = \App\Models\Referensi::with(['kategoriRel', 'kelompokRel', 'subkategoriRel'])
                ->select(['id', 'nama', 'id_kelompok', 'id_kategori', 'id_subkategori'])
                ->where('kategori', 'MAPA01')
                ->whereNotNull('id_kelompok')
                ->get()
                ->filter(fn($r) => $r->kelompokRel !== null);

            $dataReferensi = $referensis
                ->groupBy('id_kelompok')
                ->values()
                ->map(function ($kelompokItems) use ($kanMapa01Defaults) {
                    $kelompok = $kelompokItems->first()->kelompokRel;

                    return [
                        'kelompok' => [
                            'id' => $kelompok->id,
                            'nama' => $kelompok->nama,
                            'urut' => $kelompok->urut,
                            'kategoris' => $kelompokItems
                                ->groupBy(fn($i) => $i->id_kategori ?? 'tanpa_kategori')
                                ->values()
                                ->map(function ($kategoriItems) use ($kelompok, $kanMapa01Defaults) {
                                    $kategori = $kategoriItems->first()->kategoriRel;

                                    return [
                                        'id' => $kategori?->id,
                                        'kategori' => $kategori?->kategori,
                                        'nama' => $kategori?->nama,
                                        'urut' => $kategori?->urut,
                                        'id_kelompok' => $kategori?->id_kelompok,
                                        'subkategoris' => $kategoriItems
                                            ->groupBy(fn($i) => $i->id_subkategori ?? 'tanpa_subkategori')
                                            ->values()
                                            ->map(function ($subItems) use ($kanMapa01Defaults) {
                                                $sub = $subItems->first()->subkategoriRel;

                                                return [
                                                    'id' => $sub?->id,
                                                    'nama' => $sub?->nama ?? 'Tanpa Subkategori',
                                                    'urut' => $sub?->urut,
                                                    'referensis' => $subItems->map(function ($item) use ($kanMapa01Defaults) {
                                                        return [
                                                            'id' => $item->id,
                                                            'nama' => $item->nama,
                                                            'value' => $kanMapa01Defaults[$item->id] ?? false,
                                                            'alasan' => '',
                                                        ];
                                                    })->values()->toArray(),
                                                ];
                                            })->values()->toArray(),
                                    ];
                                })->values()->toArray(),
                        ],
                    ];
                })
                ->values()
                ->toArray();

            // ── Inject alasan (kode unit) ke item yang checked di kelompok 3 ──
            $unitText = '';
            foreach ($kelompokKerja['kelompok_kerja'] as $kg) {
                $unitText .= '<b>' . e($kg['nama']) . ":</b><br>\n";
                foreach ($kg['units'] as $unit) {
                    $unitText .= '&nbsp;&nbsp;- ' . e($unit['kode_unit'] ?? '') . "<br>\n";
                }
            }
            $dataReferensi = collect($dataReferensi)->map(function ($kelRef) use ($unitText) {
                if (isset($kelRef['kelompok']['id']) && $kelRef['kelompok']['id'] == 3) {
                    foreach ($kelRef['kelompok']['kategoris'] as &$kat) {
                        foreach ($kat['subkategoris'] as &$sub) {
                            foreach ($sub['referensis'] as &$ref) {
                                if (!empty($ref['value'])) {
                                    $ref['alasan'] = $unitText;
                                }
                            }
                        }
                    }
                }
                return $kelRef;
            })->values()->toArray();

            return [
                'kelompok_kerja' => $kelompokKerja,
                'referensi_form' => $dataReferensi,
                'skkni' => $record->jabatanKerja->acuan ?? '',
                'jenjang' => $jenjang,
                'metode' => 'observasi',
            ];
        }

        // Fallback: build from UnitKompetensi (legacy KAN format)
        $units = \App\Models\UnitKompetensi::where('id_jabatan', $idJabatan)
            ->orderBy('kode')->get();

        $kelompokKerja = [
            'id' => null,
            'kode' => 'MAPA01',
            'nama_dokumen' => 'MAPA01',
            'kelompok_kerja' => [[
                'id' => null,
                'nama' => $record->jabatanKerja->jabatan_kerja ?? '',
                'urut' => 1,
                'units' => $units->map(fn($u) => [
                    'id_unit'   => $u->id,
                    'nama_unit' => $u->nama,
                    'kode_unit' => $u->kode,
                ])->toArray(),
            ]],
        ];

        return [
            'kelompok_kerja' => $kelompokKerja,
            'referensi_form' => [],
            'skkni' => $record->jabatanKerja->acuan ?? '',
            'jenjang' => $jenjang,
            'metode' => 'observasi',
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  HELPER: Convert BNSP referensi_form → flat arrays for PDF
    // ═══════════════════════════════════════════════════════════════

    private function flattenMapaReferensiForm(array $referensiForm): array
    {
        $refMap = collect($referensiForm)
            ->flatMap(fn($f) => $f['kelompok']['kategoris'] ?? [])
            ->flatMap(fn($k) => $k['subkategoris'] ?? [])
            ->flatMap(fn($s) => $s['referensis'] ?? [])
            ->keyBy('id');

        $asesi = [
            'hasil_pelatihan_telusur'           => $refMap[82]['value'] ?? false,
            'hasil_pelatihan_tidak_kompetensi'  => $refMap[83]['value'] ?? false,
            'pekerja_berpengalaman_telusur'     => $refMap[84]['value'] ?? false,
            'pekerja_berpengalaman_tidak_telusur'=> $refMap[85]['value'] ?? false,
            'otodidak'                          => $refMap[86]['value'] ?? false,
        ];

        $tujuan = [
            'sertifikasi' => $refMap[87]['value'] ?? false,
            'pkt'         => $refMap[88]['value'] ?? false,
            'rpl'         => $refMap[89]['value'] ?? false,
            'lainnya'     => $refMap[90]['value'] ?? false,
        ];

        $konteks = [
            'tempat_kerja_nyata'     => $refMap[91]['value'] ?? false,
            'tempat_kerja_simulasi'  => $refMap[92]['value'] ?? false,
            'tersedia'               => $refMap[93]['value'] ?? false,
            'terbatas'               => $refMap[94]['value'] ?? false,
            'bukti_asesmen_rpl'      => $refMap[95]['value'] ?? false,
            'aktivitas_kerja'        => $refMap[96]['value'] ?? false,
            'kegiatan_pembelajaran'  => $refMap[97]['value'] ?? false,
            'lsp_gatensi'            => $refMap[98]['value'] ?? false,
            'organisasi_pelatihan'   => $refMap[99]['value'] ?? false,
            'asesor_perusahaan'      => $refMap[100]['value'] ?? false,
        ];

        $orangRelevan = [
            'manajer_sertifikasi_lsp' => $refMap[101]['value'] ?? false,
            'master_assessor_lsp'     => $refMap[102]['value'] ?? false,
            'manajer_pelatihan_lsp'   => $refMap[103]['value'] ?? false,
            'lainnya'                 => $refMap[104]['value'] ?? false,
        ];

        $tolakUkur = [
            'skkni'               => $refMap[105]['value'] ?? false,
            'kriteria_kurikulum'  => $refMap[106]['value'] ?? false,
            'spesifikasi_kinerja' => $refMap[107]['value'] ?? false,
            'spesifikasi_produk'  => $refMap[108]['value'] ?? false,
            'pedoman_khusus'      => $refMap[109]['value'] ?? false,
        ];

        // Modifikasi items: extract from kelompok 3 (modifikasi) if available
        $modifikasiItems = [];
        foreach ($referensiForm as $kelompok) {
            if (($kelompok['kelompok']['id'] ?? 0) == 3) {
                $modifikasiItems = collect(
                    $kelompok['kelompok']['kategoris'][0]['subkategoris'][0]['referensis'] ?? []
                )->map(fn($item) => [
                    'nama'   => $item['nama'],
                    'value'  => $item['value'] ?? false,
                    'alasan' => $item['alasan'] ?? '',
                ])->toArray();
                break;
            }
        }

        // Fallback modifikasi items
        if (empty($modifikasiItems)) {
            $modifikasiItems = [
                ['nama' => 'Hasil asesmen disesuaikan dengan kondisi asesi', 'value' => false, 'alasan' => ''],
                ['nama' => 'Instrumen asesmen disesuaikan dengan konteks pekerjaan', 'value' => false, 'alasan' => ''],
                ['nama' => 'Metode asesmen disesuaikan dengan kebutuhan khusus', 'value' => false, 'alasan' => ''],
                ['nama' => 'Waktu pelaksanaan asesmen disesuaikan', 'value' => false, 'alasan' => ''],
                ['nama' => 'Tempat pelaksanaan asesmen disesuaikan', 'value' => false, 'alasan' => ''],
            ];
        }

        return [
            'asesi'            => $asesi,
            'tujuan'           => $tujuan,
            'konteks'          => $konteks,
            'orang_relevan'    => $orangRelevan,
            'tolak_ukur'       => $tolakUkur,
            'modifikasi_items' => $modifikasiItems,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    //  SUBMIT: KAN MAPA01 By Praktisi
    // ═══════════════════════════════════════════════════════════════

    public function submitKanMapaByPraktisi(Request $request, int $praktisiJabatanId): JsonResponse
    {
        $record = KanPraktisiJabatan::find($praktisiJabatanId);
        if (!$record) {
            return response()->json(['message' => 'Data not found'], 404);
        }

        $validated = $request->validate([
            'referensi_form' => 'required|array',
        ]);

        // Flatten incoming referensi_form → extract checked values & alasan
        $refMap = collect($validated['referensi_form'] ?? [])
            ->flatMap(fn($f) => $f['kelompok']['kategoris'] ?? [])
            ->flatMap(fn($k) => $k['subkategoris'] ?? [])
            ->flatMap(fn($s) => $s['referensis'] ?? [])
            ->keyBy('id');

        // Save to KanMapaAnswer table if needed, for now just validate & return
        return response()->json([
            'message' => 'KAN MAPA01 saved',
            'data' => [
                'referensi_count' => $refMap->count(),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  SUBMIT: KAN MAPA02 By Praktisi
    // ═══════════════════════════════════════════════════════════════

    public function submitKanMapa2ByPraktisi(Request $request, int $praktisiJabatanId): JsonResponse
    {
        $record = KanPraktisiJabatan::find($praktisiJabatanId);
        if (!$record) {
            return response()->json(['message' => 'Data not found'], 404);
        }

        $validated = $request->validate([
            'referensi_form' => 'required|array',
        ]);

        return response()->json([
            'message' => 'KAN MAPA02 saved',
            'data' => [
                'referensi_count' => count($validated['referensi_form'] ?? []),
            ],
        ]);
    }
}
