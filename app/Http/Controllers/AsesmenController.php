<?php

namespace App\Http\Controllers;

use App\Models\Ak2Answer;
use App\Models\Ak2Feedback;
use App\Models\Ak3Answer;
use App\Models\Ak3Note;
use App\Models\Ak5Answer;
use App\Models\Ak6Answer;
use App\Models\Ak6Feedback;
use App\Models\Ak6DimensiKompetensi;
use App\Models\AsesiJadwal;
use App\Models\AsesorBnsp;
use App\Models\Barcode;
use App\Models\BuktiAsesmen;
use App\Models\DimensiKompetensi;
use App\Models\Ia1Answer;
use App\Models\Ia1Feedback;
use App\Models\Ia1Recommendation;
use App\Models\Ia3Answer;
use App\Models\Ia3Feedback;
use App\Models\Ia4aAnswer;
use App\Models\Ia4bAnswer;
use App\Models\Ia5Answer;
use App\Models\Ia5Feedback;
use App\Models\JabatanConfigA;
use App\Models\JabatanDokumen;
use App\Models\JabatanKerja;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Personal;
use App\Models\Referensi;
use App\Models\Role;
use App\Models\Soal;
use App\Models\UnitKompetensi;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AsesmenController extends Controller
{
    public function absenAwal(Request $request, $id)
    {
        return $this->handleAbsen($request, $id, 'awal');
    }

    public function absenAkhir(Request $request, $id)
    {
        return $this->handleAbsen($request, $id, 'akhir');
    }

    private function handleAbsen(Request $request, $id, string $type)
    {
        // 🔹 Validate input
        $request->validate([
            'image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ]);

        $user = auth()->user();
        $role = Role::find($user->role_id);

        if (!$role) {
            return response()->json([
                'message' => 'Role tidak ditemukan',
            ], 403);
        }

        // 🔹 Upload image
        $image = $request->file('image');
        $filename = (string) Str::uuid() . '.' . $image->getClientOriginalExtension();
        $relativePath = "dok_asesmen/absen/{$filename}";

        $uploaded = Storage::disk('str_moon')->putFileAs(
            'dok_asesmen/absen',
            $image,
            $filename
        );

        if (!$uploaded) {
            return response()->json([
                'message' => 'Gagal mengupload gambar',
            ], 500);
        }

        $imageUrl = 'https://moon.lspgatensi.id/files/new_gatensi/' . $relativePath;

        // 🔹 ASSESI
        if ($role->name === 'Asesi') {
            BuktiAsesmen::updateOrCreate(
                ['id_izin' => $id],
                ["url_absen_asesi_{$type}" => $imageUrl]
            );

            return response()->json([
                'message' => "Absen {$type} asesi berhasil disimpan",
                'url' => $imageUrl,
            ]);
        }

        // 🔹 ASESOR
        if ($role->name === 'Asesor') {

            $jadwalId = AsesiJadwal::where('id_izin', $id)->value('jadwal_id');
            $jadwal = Jadwal::with(['asesor:id,noreg', 'asesor2:id,noreg'])
                ->where('jadwal_id', $jadwalId)
                ->first();

            if (!$jadwal) {
                return response()->json([
                    'message' => 'Jadwal tidak ditemukan',
                ], 404);
            }

            $roleAsesor = null;

            if ($jadwal->asesor && $user->noreg === $jadwal->asesor->noreg) {
                $roleAsesor = 'asesor1';
            } elseif ($jadwal->asesor2 && $user->noreg === $jadwal->asesor2->noreg) {
                $roleAsesor = 'asesor2';
            }

            if (!$roleAsesor) {
                return response()->json([
                    'message' => 'Anda tidak berhak mengisi absen pada jadwal ini',
                ], 403);
            }

            BuktiAsesmen::updateOrCreate(
                ['id_izin' => $id],
                ["url_absen_{$roleAsesor}_{$type}" => $imageUrl]
            );

            return response()->json([
                'message' => "Absen {$type} {$roleAsesor} berhasil disimpan",
                'url' => $imageUrl,
            ]);
        }

        // 🔹 ROLE NOT ALLOWED
        return response()->json([
            'message' => 'Role tidak diizinkan melakukan absen',
        ], 403);
    }

    public function getDokumenHeader($id)
    {
        $namaAsesi = Personal::where('id_izin', $id)->value('nama');
        $jadwalId = AsesiJadwal::where('id_izin', $id)->value('jadwal_id');
        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])
            ->where('id_izin', $id)
            ->first();
        $jabatanKerja = JabatanKerja::where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)->value('jabatan_kerja');
        $jabatanConfig = JabatanConfigA::select(['no_skema'])->where('id_jabatan', $klasifikasi->jabatan_kerja)->first();
        $jadwal = Jadwal::select([
            'jadwal_id',
            'tuk_id',
            'asesor_id',
            'asesor2_id',
            'tanggal_uji',
            'tanggal_selesai',
            'jenis_kelas',
        ])->with([
            'tuk:id,nama',
            'asesor:id,nama,noreg',
            'asesor2:id,nama,noreg',
        ])->where('jadwal_id', $jadwalId)->first();

        $idAsesor1 = User::where('noreg', $jadwal->asesor ? $jadwal->asesor->noreg : null)->value('id');
        $idAsesor2 = User::where('noreg', $jadwal->asesor2 ? $jadwal->asesor2->noreg : null)->value('id');

        $penyusun_validator = JabatanConfigA::with([
            'penyusun:id,name,noreg',
            'validator:id,name,noreg',
        ])->select(['penyusun_id', 'penyusun_tgl', 'penyusun_img', 'validator_id', 'validator_tgl', 'validator_img'])->where('id_jabatan', $klasifikasi->jabatan_kerja)->first();

        return response()->json([
            'message' => 'Success',
            'data' => [
                'jabatan_kerja' => $jabatanKerja,
                'nomor_skema' => $jabatanConfig->no_skema,
                'tuk' => $jadwal->tuk ? $jadwal->tuk->nama : null,
                'id_asesor_1' => $idAsesor1,
                'id_asesor_2' => $idAsesor2,
                'nama_asesi' => $namaAsesi,
                'jenjang' => $klasifikasi->jenjang,
                'asesor_1' => $jadwal->asesor ? $jadwal->asesor->nama : null,
                'asesor_2' => $jadwal->asesor2 ? $jadwal->asesor2->nama : null,
                'noreg_asesor_1' => $jadwal->asesor ? $jadwal->asesor->noreg : null,
                'noreg_asesor_2' => $jadwal->asesor2 ? $jadwal->asesor2->noreg : null,
                'tanggal_uji' => $jadwal->tanggal_uji,
                'tanggal_selesai' => $jadwal->tanggal_selesai,
                'jenis_kelas' => $jadwal->jenis_kelas,
                'nama_penyusun' => $penyusun_validator->penyusun?->name,
                'nama_validator' => $penyusun_validator->validator?->name,
                'tanggal_penyusun' => $penyusun_validator->penyusun_tgl,
                'tanggal_validator' => $penyusun_validator->validator_tgl,
                'barcode_penyusun' => $penyusun_validator->penyusun_img,
                'barcode_validator' => $penyusun_validator->validator_img,
                'noreg_penyusun' => $penyusun_validator->penyusun?->noreg,
                'noreg_validator' => $penyusun_validator->validator?->noreg,
            ]
        ]);
    }

    private function buildDokumenHeader($id): ?array
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

        $jabatanKerja = JabatanKerja::where(
            'id_jabatan_kerja',
            $klasifikasi->jabatan_kerja
        )->value('jabatan_kerja');

        $jabatanConfig = JabatanConfigA::select('no_skema')
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->first();

        $jadwal = Jadwal::select([
                'jadwal_id',
                'tuk_id',
                'asesor_id',
                'asesor2_id',
                'tanggal_uji',
                'tanggal_selesai',
                'jenis_kelas',
            ])
            ->with([
                'tuk:id,nama',
                'asesor:id,nama,noreg',
                'asesor2:id,nama,noreg',
            ])
            ->where('jadwal_id', $jadwalId)
            ->first();

        if (!$jadwal) {
            return null;
        }

        $idAsesor1 = User::where('noreg', $jadwal->asesor?->noreg)->value('id');
        $idAsesor2 = User::where('noreg', $jadwal->asesor2?->noreg)->value('id');

        $penyusun_validator = JabatanConfigA::with([
            'penyusun:id,name,noreg',
            'validator:id,name,noreg',
        ])->select(['penyusun_id', 'penyusun_tgl', 'penyusun_img', 'validator_id', 'validator_tgl', 'validator_img'])->where('id_jabatan', $klasifikasi->jabatan_kerja)->first();

        return [
            'jabatan_kerja'   => $jabatanKerja,
            'nomor_skema'     => $jabatanConfig?->no_skema,
            'tuk'             => $jadwal->tuk?->nama,

            'id_asesor_1'     => $idAsesor1,
            'id_asesor_2'     => $idAsesor2,

            'nama_asesi'      => $namaAsesi,
            'asesor_1'        => $jadwal->asesor?->nama,
            'asesor_2'        => $jadwal->asesor2?->nama,

            'noreg_asesor_1'  => $jadwal->asesor?->noreg,
            'noreg_asesor_2'  => $jadwal->asesor2?->noreg,

            'tanggal_uji'     => $jadwal->tanggal_uji,
            'tanggal_selesai' => $jadwal->tanggal_selesai,
            'jenis_kelas'     => $jadwal->jenis_kelas,

            'nama_penyusun' => $penyusun_validator->penyusun?->name,
            'nama_validator' => $penyusun_validator->validator?->name,
            'tanggal_penyusun' => $penyusun_validator->penyusun_tgl,
            'tanggal_validator' => $penyusun_validator->validator_tgl,
            'barcode_penyusun' => $this->imageUrlToBase64($penyusun_validator->penyusun_img),
            'barcode_validator' => $this->imageUrlToBase64($penyusun_validator->validator_img),
            'noreg_penyusun' => $penyusun_validator->penyusun?->noreg,
            'noreg_validator' => $penyusun_validator->validator?->noreg,
        ];
    }

    private function buildIa01Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return null;
        }

        /** ✅ Shared barcode resolver */
        $barcodes = $this->resolveDocumentBarcodes($id, 'IA01', $forPdf);

        $referensis = Referensi::select(['id', 'nama'])
            ->where('kategori', 'IA01')
            ->get();

        $dokumen = JabatanDokumen::with(['kelompoks.units.unit'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.IA.01')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return [
                'barcodes' => $barcodes,
                'kelompok_kerja' => [],
                'referensi_form' => $referensis,
            ];
        }

        /** 🔹 Load saved feedback answers */
        $ia01Answers = Ia1Answer::where('id_izin', $id)
            ->get()
            ->keyBy('soal_id');

        /** 🔹 Load saved feedback for each kelompok */
        $ia01Feedbacks = Ia1Feedback::where('id_izin', $id)
            ->get()
            ->keyBy('id_kelompok');

        /** 🔹 Load saved recommendation */
        $ia01Recommendation = Ia1Recommendation::where('id_izin', $id)->first();

        /** 🔹 Load questions + inject feedback answer */
        $questions = Soal::with(['unit', 'kuk.subunit'])
            ->where('id_dokumen', $dokumen->id)
            ->get()
            ->map(function ($q) use ($ia01Answers) {

                $answer = $ia01Answers->get($q->id);

                $questionData = [
                    'id'          => $q->id,
                    'no'          => $q->no,
                    'jenis'       => $q->jenis,
                    'id_kelompok' => $q->id_kelompok,
                    'penilaian_lanjut'   => $answer?->penilaian_lanjut,
                    'pencapaian'  => $answer?->pencapaian,
                ];

                // Add unitkompetensi ID if exists (needed for filtering)
                if ($q->id_unitkompetensi) {
                    $questionData['id_unitkompetensi'] = $q->id_unitkompetensi;
                }

                // Add kuk data if exists
                if ($q->id_kuk) {
                    $questionData['id_kuk'] = $q->id_kuk;
                    $questionData['kuk'] = $q->kuk ? [
                        'id' => $q->kuk->id,
                        'nama' => $q->kuk->nama,
                    ] : null;

                    // Add subunitkompetensi data if exists (through kuk)
                    if ($q->kuk && $q->kuk->subunit) {
                        $questionData['id_subunitkompetensi'] = $q->kuk->id_subunitkompetensi;
                        $questionData['subunitkompetensi'] = [
                            'id' => $q->kuk->subunit->id,
                            'nama' => $q->kuk->subunit->nama,
                        ];
                    }
                }

                return $questionData;
            });

        $kelompokKerja = [
            'id' => $dokumen->id,
            'kode' => $dokumen->kode,
            'nama_dokumen' => $dokumen->nama_dokumen,
            'kelompok_kerja' => $dokumen->kelompoks->map(function ($kelompok) use ($questions, $ia01Feedbacks) {
                $kelompokData = [
                    'id' => $kelompok->id,
                    'nama' => $kelompok->nama,
                    'urut' => $kelompok->urut,
                    'umpan_balik' => $ia01Feedbacks->get($kelompok->id)?->umpan_balik,
                    'units' => $kelompok->units->map(function ($unit) use ($questions, $kelompok) {
                        $unitData = [
                            'id_unit'   => $unit->id_unit,
                            'nama_unit' => $unit->unit?->nama,
                            'kode_unit' => $unit->unit?->kode,
                        ];

                        // Filter soal by id_kelompok AND id_unitkompetensi
                        $unitQuestions = $questions->filter(function ($soal) use ($kelompok, $unit) {
                            return $soal['id_kelompok'] == $kelompok->id
                                && isset($soal['id_unitkompetensi'])
                                && $soal['id_unitkompetensi'] == $unit->id_unit;
                        })->values();

                        // Group soal by subunits
                        $subunitsGrouped = [];
                        foreach ($unitQuestions as $soal) {
                            if (isset($soal['id_subunitkompetensi']) && isset($soal['subunitkompetensi'])) {
                                $subunitId = $soal['id_subunitkompetensi'];

                                // Initialize subunit if not exists
                                if (!isset($subunitsGrouped[$subunitId])) {
                                    $subunitsGrouped[$subunitId] = [
                                        'id' => $soal['subunitkompetensi']['id'],
                                        'nama' => $soal['subunitkompetensi']['nama'],
                                        'soal' => []
                                    ];
                                }

                                // Add soal to subunit (remove subunitkompetensi data from individual soal)
                                $cleanSoal = $soal;
                                unset($cleanSoal['subunitkompetensi']);
                                $subunitsGrouped[$subunitId]['soal'][] = $cleanSoal;
                            }
                        }

                        $unitData['subunits'] = array_values($subunitsGrouped);

                        return $unitData;
                    })->values()
                ];

                return $kelompokData;
            })->values()
        ];

        $feedback = Ia1Feedback::where('id_izin', $id)->value('umpan_balik');

        return [
            'barcodes'        => $barcodes,
            'kelompok_kerja'  => $kelompokKerja,
            'referensi_form'  => $referensis,
            'is_kompeten'     => $ia01Recommendation?->is_kompeten,
            'umpan_balik'     => $feedback
        ];
    }

    public function getIa01($id)
    {
        $data = $this->buildIa01Data($id);

        if ($data === null) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    public function submitIa01(Request $request, $idIzin)
    {
        $validated = $request->validate([
            'dokumen_id' => 'required|integer',
            'answers' => 'required|array',
            'answers.*.soal_id' => 'required|integer',
            'answers.*.penilaian_lanjut' => 'nullable|string|max:255',
            'answers.*.pencapaian' => 'required|boolean',
            'feedback' => 'nullable|array',
            'feedback.*.kelompok_id' => 'nullable|integer',
            'feedback.*.umpan_balik' => 'nullable|string',
            'is_kompeten' => 'required|boolean',
        ]);

        DB::transaction(function () use ($validated, $idIzin) {
            foreach ($validated['answers'] as $answer) {
                Ia1Answer::updateOrCreate(
                    [
                        'id_izin' => $idIzin,
                        'soal_id' => $answer['soal_id'],
                    ],
                    [
                        'dokumen_id' => $validated['dokumen_id'],
                        'penilaian_lanjut' => $answer['penilaian_lanjut'],
                        'pencapaian' => $answer['pencapaian'],
                    ]
                );
            }

            // Loop through feedbacks and insert each one
            if (!empty($validated['feedback'])) {
                foreach ($validated['feedback'] as $feedback) {
                    if (!empty($feedback['umpan_balik'])) {
                        Ia1Feedback::updateOrCreate(
                            [
                                'id_izin' => $idIzin,
                                'id_kelompok' => $feedback['kelompok_id'],
                            ],
                            [
                                'umpan_balik' => $feedback['umpan_balik'],
                            ]
                        );
                    }
                }
            }

            // Save or update the recommendation with is_kompeten value
            Ia1Recommendation::updateOrCreate(
                [
                    'id_izin' => $idIzin,
                ],
                [
                    'is_kompeten' => $validated['is_kompeten'],
                ]
            );
        });

        return response()->json([
            'message' => 'IA01 answers saved successfully'
        ]);
    }

    public function ttdIa01(Request $request, $id)
    {
        $data = $this->generateTtdBarcode(
            $request,
            'IA01',
            'ttd_ia01',
            $id
        );

        $dataDokumen = $this->buildIa01Data($id, true);
        if (!$dataDokumen) {
            abort(404, 'Data not found');
        }

        $dokumenHeader = $this->buildDokumenHeader($id);
        if (!$dokumenHeader) {
            abort(404, 'Dokumen header not found');
        }

        $viewData = array_merge($dataDokumen, [
            'dokumenHeader' => $dokumenHeader,
        ]);

        /* =========================
        * GENERATE PDF
        * ========================= */

        $pdf = Pdf::loadView('pdf.ia01', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/ia01';

        // unique + traceable filename
        $filename = 'FR-IA-01-' . $id . '-' . Str::uuid() . '.pdf';
        $path = $directory . '/' . $filename;

        // save PDF bytes to FTP
        Storage::disk($disk)->put($path, $pdf->output());

        /* =========================
        * BUILD PUBLIC URL
        * ========================= */

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $fullUrl = $publicBaseUrl . $path;

        /* =========================
        * UPDATE DATABASE
        * ========================= */

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_ia_01' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    private function buildIa02Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return null;
        }

        /** ✅ Shared barcode resolver */
        $barcodes = $this->resolveDocumentBarcodes($id, 'IA02', $forPdf);

        $referensis = Referensi::select(['id', 'nama'])
            ->where('kategori', 'IA02')
            ->get();

        $dokumen = JabatanDokumen::where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.IA.02')
            ->where('is_active', true)
            ->orderBy('id', 'desc')
            ->first();

        if (!$dokumen) {
            return [
                'barcodes' => $barcodes,
                'kelompok_kerja' => [],
                'referensi_form' => $referensis,
            ];
        }

        /** 🔹 Load questions + inject feedback answer */
        $questions = Soal::select(['id', 'isi_nonsoal'])
            ->where('id_dokumen', $dokumen->id)
            ->first();

        return [
            'barcodes'        => $barcodes,
            'questions'  => $questions,
        ];
    }

    public function getIa02($id)
    {
        $data = $this->buildIa02Data($id);

        if ($data === null) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    public function ttdIa02(Request $request, $id)
    {
        $data = $this->generateTtdBarcode(
            $request,
            'IA02',
            'ttd_ia02',
            $id
        );

        $dataDokumen = $this->buildIa02Data($id, true);
        if (!$dataDokumen) {
            abort(404, 'Data not found');
        }

        $dokumenHeader = $this->buildDokumenHeader($id);
        if (!$dokumenHeader) {
            abort(404, 'Dokumen header not found');
        }

        $viewData = array_merge($dataDokumen, [
            'dokumenHeader' => $dokumenHeader,
        ]);

        /* =========================
        * GENERATE PDF
        * ========================= */

        $pdf = Pdf::loadView('pdf.ia02', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/ia02';

        // unique + traceable filename
        $filename = 'FR-IA-02-' . $id . '-' . Str::uuid() . '.pdf';
        $path = $directory . '/' . $filename;

        // save PDF bytes to FTP
        Storage::disk($disk)->put($path, $pdf->output());

        /* =========================
        * BUILD PUBLIC URL
        * ========================= */

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $fullUrl = $publicBaseUrl . $path;

        /* =========================
        * UPDATE DATABASE
        * ========================= */

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_ia_02' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    private function buildIa03Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return null;
        }

        /** ✅ Shared barcode resolver */
        $barcodes = $this->resolveDocumentBarcodes($id, 'IA03', $forPdf);

        $referensis = Referensi::select(['id', 'nama'])
            ->where('kategori', 'IA03')
            ->get();

        $dokumen = JabatanDokumen::with(['kelompoks.units.unit'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.IA.03')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return [
                'barcodes' => $barcodes,
                'kelompok_kerja' => [],
                'referensi_form' => $referensis,
            ];
        }

        /** 🔹 Load saved feedback answers */
        $ia03Answers = Ia3Answer::where('id_izin', $id)
            ->get()
            ->keyBy('soal_id');

        /** 🔹 Load questions + inject feedback answer */
        $questions = Soal::with(['unit', 'kuk.subunit'])
            ->where('id_dokumen', $dokumen->id)
            ->get()
            ->map(function ($q) use ($ia03Answers) {

                $answer = $ia03Answers->get($q->id);

                $questionData = [
                    'id'          => $q->id,
                    'no'          => $q->no,
                    'jenis'       => $q->jenis,
                    'id_kelompok' => $q->id_kelompok,
                    'soal'        => $q->soal,
                    'tanggapan'   => $answer?->tanggapan,
                    'pencapaian'  => $answer?->pencapaian,
                ];

                // Add unitkompetensi data if exists
                if ($q->id_unitkompetensi) {
                    $questionData['id_unitkompetensi'] = $q->id_unitkompetensi;
                    $questionData['unitkompetensi'] = $q->unit ? [
                        'id' => $q->unit->id,
                        'kode' => $q->unit->kode,
                    ] : null;
                }

                // Add kuk data if exists
                if ($q->id_kuk) {
                    $questionData['id_kuk'] = $q->id_kuk;
                    $questionData['kuk'] = $q->kuk ? [
                        'id' => $q->kuk->id,
                        'kode' => $q->kuk->kode,
                    ] : null;

                    // Add subunitkompetensi data if exists (through kuk)
                    if ($q->kuk && $q->kuk->subunit) {
                        $questionData['id_subunitkompetensi'] = $q->kuk->id_subunitkompetensi;
                        $questionData['subunitkompetensi'] = [
                            'id' => $q->kuk->subunit->id,
                            'kode' => $q->kuk->subunit->kode,
                        ];
                    }
                }

                return $questionData;
            });

        $kelompokKerja = [
            'id' => $dokumen->id,
            'kode' => $dokumen->kode,
            'nama_dokumen' => $dokumen->nama_dokumen,
            'kelompok_kerja' => $dokumen->kelompoks->map(function ($kelompok) use ($questions) {
                $kelompokData = [
                    'id' => $kelompok->id,
                    'nama' => $kelompok->nama,
                    'urut' => $kelompok->urut,
                    'units' => $kelompok->units->map(function ($unit) {
                        return [
                            'id_unit'   => $unit->id_unit,
                            'nama_unit' => $unit->unit?->nama,
                            'kode_unit' => $unit->unit?->kode,
                        ];
                    })->values()
                ];

                // Group soal by id_kelompok
                $kelompokData['soal'] = $questions->filter(function ($soal) use ($kelompok) {
                    return $soal['id_kelompok'] == $kelompok->id;
                })->values();

                return $kelompokData;
            })->values()
        ];

        $feedback = Ia3Feedback::where('id_izin', $id)->value('umpan_balik');

        return [
            'barcodes'        => $barcodes,
            'kelompok_kerja'  => $kelompokKerja,
            'referensi_form'  => $referensis,
            'umpan_balik'     => $feedback
        ];
    }

    public function getIa03($id)
    {
        $data = $this->buildIa03Data($id);

        if ($data === null) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    public function submitIa03(Request $request, $idIzin)
    {
        $validated = $request->validate([
            'dokumen_id' => 'required|integer',
            'answers' => 'required|array',
            'answers.*.soal_id' => 'required|integer',
            'answers.*.tanggapan' => 'nullable|string|max:255',
            'answers.*.pencapaian' => 'required|boolean',
            'umpan_balik' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $idIzin) {
            foreach ($request->answers as $answer) {
                Ia3Answer::updateOrCreate(
                    [
                        'id_izin' => $idIzin,
                        'soal_id' => $answer['soal_id'],
                    ],
                    [
                        'dokumen_id' => $request->dokumen_id,
                        'tanggapan' => $answer['tanggapan'],
                        'pencapaian' => $answer['pencapaian'],
                    ]
                );
            }
        });

        if (!empty($validated['umpan_balik'])) {
            Ia3Feedback::updateOrCreate(
                ['id_izin' => $idIzin],
                [
                    'umpan_balik' => $validated['umpan_balik'],
                ]
            );
        }

        return response()->json([
            'message' => 'IA03 answers saved successfully'
        ]);
    }

    public function ttdIa03(Request $request, $id)
    {
        $data = $this->generateTtdBarcode(
            $request,
            'IA03',
            'ttd_ia03',
            $id
        );

        $dataDokumen = $this->buildIa03Data($id, true);
        if (!$dataDokumen) {
            abort(404, 'Data not found');
        }

        $dokumenHeader = $this->buildDokumenHeader($id);
        if (!$dokumenHeader) {
            abort(404, 'Dokumen header not found');
        }

        $viewData = array_merge($dataDokumen, [
            'dokumenHeader' => $dokumenHeader,
        ]);

        /* =========================
        * GENERATE PDF
        * ========================= */

        $pdf = Pdf::loadView('pdf.ia03', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/ia03';

        // unique + traceable filename
        $filename = 'FR-IA-03-' . $id . '-' . Str::uuid() . '.pdf';
        $path = $directory . '/' . $filename;

        // save PDF bytes to FTP
        Storage::disk($disk)->put($path, $pdf->output());

        /* =========================
        * BUILD PUBLIC URL
        * ========================= */

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $fullUrl = $publicBaseUrl . $path;

        /* =========================
        * UPDATE DATABASE
        * ========================= */

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_ia_03' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    private function buildIa04aData(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return null;
        }

        /** ✅ Shared barcode resolver */
        $barcodes = $this->resolveDocumentBarcodes($id, 'IA04A', $forPdf);

        $referensis = Referensi::select(['id', 'nama'])
            ->where('kategori', 'IA04')
            ->get();

        $dokumen = JabatanDokumen::with(['kelompoks.units.unit'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.IA.04.A')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return [
                'barcodes' => $barcodes,
                'kelompok_kerja' => [],
                'referensi_form' => $referensis,
                'soal' => [],
            ];
        }

        /** 🔹 Load saved feedback answers */
        $ia04aAnswers = Ia4aAnswer::where('id_izin', $id)
            ->get()
            ->keyBy('soal_id');

        /** 🔹 Load questions + inject feedback answer */
        $questions = Soal::where('id_dokumen', $dokumen->id)
            ->orderBy('urut', 'asc')
            ->get()
            ->map(function ($q) use ($ia04aAnswers) {

                $answer = $ia04aAnswers->get($q->id);

                return [
                    'id'          => $q->id,
                    'urut'        => $q->urut,
                    'jenis'       => $q->jenis,
                    'soal'        => $q->soal,
                    'is_komentar' => $q->is_komentar,
                    'jawaban'     => $q->is_komentar == 2
                        ? ($answer?->jawaban ?? '')
                        : $q->jawab_a,
                ];
            });

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
                    })->values()
                ];
            })->values()
        ];

        return [
            'barcodes'        => $barcodes,
            'kelompok_kerja'  => $kelompokKerja,
            'referensi_form'  => $referensis,
            'soal'            => $questions,
        ];
    }

    public function getIa04a($id)
    {
        $data = $this->buildIa04aData($id);

        if ($data === null) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    public function submitIa04a(Request $request, $id)
    {
        $validated = $request->validate([
            'soal_id' => 'required|integer|exists:soals,id',
            'jawaban' => 'nullable|string',
        ]);

        Ia4aAnswer::updateOrCreate(
            [
                'id_izin' => $id,
                'soal_id' => $validated['soal_id'],
            ],
            [
                'jawaban' => $validated['jawaban'],
            ]
        );

        return response()->json([
            'message' => 'IA04A berhasil disimpan'
        ]);
    }

    public function ttdIa04a(Request $request, $id)
    {
        $data = $this->generateTtdBarcode(
            $request,
            'IA04A',
            'ttd_ia04a',
            $id
        );

        $dataDokumen = $this->buildIa04aData($id, true);
        if (!$dataDokumen) {
            abort(404, 'Data not found');
        }

        $dokumenHeader = $this->buildDokumenHeader($id);
        if (!$dokumenHeader) {
            abort(404, 'Dokumen header not found');
        }

        $viewData = array_merge($dataDokumen, [
            'dokumenHeader' => $dokumenHeader
        ]);

        /* =========================
        * GENERATE PDF
        * ========================= */

        $pdf = Pdf::loadView('pdf.ia04a', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/ia04a';

        // unique + traceable filename
        $filename = 'FR-IA-04A-' . $id . '-' . Str::uuid() . '.pdf';
        $path = $directory . '/' . $filename;

        // save PDF bytes to FTP
        Storage::disk($disk)->put($path, $pdf->output());

        /* =========================
        * BUILD PUBLIC URL
        * ========================= */

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $fullUrl = $publicBaseUrl . $path;

        /* =========================
        * UPDATE DATABASE
        * ========================= */

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_ia_04a' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    public function getTugas($id)
    {
        $bukti = BuktiAsesmen::select(['url_paparan', 'filename_paparan'])
            ->where('id_izin', $id)
            ->first();

        if (!$bukti || !$bukti->url_paparan) {
            return response()->json([
                'message' => 'Tugas belum diupload',
                'data'    => null,
            ], 404);
        }

        // Extract file info
        $url      = $bukti->url_paparan;
        $path     = parse_url($url, PHP_URL_PATH);
        $ext      = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return response()->json([
            'message' => 'Success',
            'data' => [
                'url'       => $url,
                'filename'  => $bukti->filename_paparan,
                'extension' => $ext,
            ],
        ]);
    }

    public function uploadTugas(Request $request, $id)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:pdf,ppt,pptx',
                'max:20480', // 20MB
            ],
        ]);

        DB::transaction(function () use ($request, $id) {

            // 1️⃣ Original filename from user
            $originalFilename = $request->file('file')->getClientOriginalName();

            // 2️⃣ Generate unique filename for storage
            $uuid = (string) Uuid::uuid4();

            // 3️⃣ Extension
            $extension = $request->file('file')->getClientOriginalExtension();

            // 4️⃣ FTP path (stored file name)
            $ftpPath = "/paparan/{$uuid}.{$extension}";

            // 5️⃣ Upload to FTP
            Storage::disk('str_moon')->put(
                $ftpPath,
                file_get_contents($request->file('file')->getRealPath())
            );

            // 6️⃣ Public URL
            $fullUrlPaparan = "https://moon.lspgatensi.id/files/new_gatensi{$ftpPath}";

            // 7️⃣ Save DB
            BuktiAsesmen::updateOrCreate(
                ['id_izin' => $id],
                [
                    'url_paparan'      => $fullUrlPaparan,
                    'filename_paparan' => $originalFilename, // ✅ real filename
                    'updated_at'       => now(),
                ]
            );
        });

        return response()->json([
            'message' => 'Tugas berhasil diupload!'
        ]);
    }

    private function buildIa04bData(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return null;
        }

        /** ✅ Shared barcode resolver */
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
            ];
        }

        /**
         * QUESTIONS + ANSWERS + PENCAPAIAN
         */
        $questions = Soal::select([
                'soals.id',
                'soals.no',
                'soals.jenis',
                'soals.soal',
                'soals.soal1',
                'soals.soal2',
                'soals.is_komentar',
                'ia4b_answers.jawaban',
                'ia4b_answers.pencapaian'
            ])
            ->leftJoin('ia4b_answers', function ($join) use ($id) {
                $join->on('ia4b_answers.soal_id', '=', 'soals.id')
                    ->where('ia4b_answers.id_izin', $id);
            })
            ->where('soals.id_dokumen', $dokumen->id)
            ->where('soals.tipe', 8)
            ->orderBy('soals.no', 'asc')
            ->get()
            ->map(function ($q) {
                $q->pencapaian = $q->pencapaian !== null
                    ? (bool) $q->pencapaian
                    : null;

                return $q;
            });

        /**
         * REKOMENDASI + VALUE
         */
        $rekomendasi = Soal::select([
                'soals.id',
                'soals.no',
                'soals.jenis',
                'soals.soal',
                'soals.is_komentar',
                'ia4b_answers.rekomendasi'
            ])
            ->leftJoin('ia4b_answers', function ($join) use ($id) {
                $join->on('ia4b_answers.soal_id', '=', 'soals.id')
                    ->where('ia4b_answers.id_izin', $id)
                    ->where('ia4b_answers.is_rekomendasi', true);
            })
            ->where('soals.id_dokumen', $dokumen->id)
            ->where('soals.tipe', 3)
            ->first();

        return [
            'barcodes' => $barcodes,
            'dokumen' => $dokumen,
            'soal' => $questions,
            'rekomendasi' => $rekomendasi
                ? [
                    'id' => $rekomendasi->id,
                    'no' => $rekomendasi->no,
                    'jenis' => $rekomendasi->jenis,
                    'soal' => $rekomendasi->soal,
                    'is_komentar' => $rekomendasi->is_komentar,
                    'rekomendasi' => (bool) $rekomendasi->rekomendasi
                ]
                : null
        ];
    }

    public function getIa04b($id)
    {
        $data = $this->buildIa04bData($id);

        if ($data === null) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    public function submitIa04b(Request $request, $idIzin)
    {
        $request->validate([
            'dokumen_id' => 'required|integer',
            'answers' => 'array',
            'answers.*.soal_id' => 'required|integer',
            'answers.*.jawaban' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $idIzin) {

            // --- Save normal answers ---
            foreach ($request->answers as $answer) {
                Ia4bAnswer::updateOrCreate(
                    [
                        'id_izin' => $idIzin,
                        'soal_id' => $answer['soal_id'],
                    ],
                    [
                        'dokumen_id' => $request->dokumen_id,
                        'jawaban' => $answer['jawaban'],
                        'is_rekomendasi' => false,
                        'pencapaian' => null,
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'IA04B answers saved successfully'
        ]);
    }

    public function nilaiIa04b(Request $request, $idIzin)
    {
        $request->validate([
            'dokumen_id' => 'required|integer',
            'evaluations' => 'required|array',
            'evaluations.*.soal_id' => 'required|integer',
            'evaluations.*.pencapaian' => 'required|boolean',
            'rekomendasi.soal_id' => 'required|integer',
            'rekomendasi.value' => 'required|boolean',
        ]);

        foreach ($request->evaluations as $eval) {
            Ia4bAnswer::where('id_izin', $idIzin)
                ->where('soal_id', $eval['soal_id'])
                ->where('is_rekomendasi', false)
                ->update([
                    'pencapaian' => $eval['pencapaian']
                ]);
        }

        // Save rekomendasi
        Ia4bAnswer::updateOrCreate(
            [
                'id_izin' => $idIzin,
                'soal_id' => $request->rekomendasi['soal_id'],
            ],
            [
                'dokumen_id' => $request->dokumen_id,
                'is_rekomendasi' => true,
                'rekomendasi' => $request->rekomendasi['value'],
                'jawaban' => null,
                'pencapaian' => null,
            ]
        );

        return response()->json([
            'message' => 'Rekomendasi IA04B disimpan'
        ]);
    }

    public function ttdIa04b(Request $request, $id)
    {
        $data = $this->generateTtdBarcode(
            $request,
            'IA04B',
            'ttd_ia04b',
            $id
        );

        $dataDokumen = $this->buildIa04bData($id, true);
        if (!$dataDokumen) {
            abort(404, 'Data not found');
        }

        $dokumenHeader = $this->buildDokumenHeader($id);
        if (!$dokumenHeader) {
            abort(404, 'Dokumen header not found');
        }

        $viewData = array_merge($dataDokumen, [
            'dokumenHeader' => $dokumenHeader
        ]);

        /* =========================
        * GENERATE PDF
        * ========================= */

        $pdf = Pdf::loadView('pdf.ia04b', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/ia04b';

        // unique + traceable filename
        $filename = 'FR-IA-04B-' . $id . '-' . Str::uuid() . '.pdf';
        $path = $directory . '/' . $filename;

        // save PDF bytes to FTP
        Storage::disk($disk)->put($path, $pdf->output());

        /* =========================
        * BUILD PUBLIC URL
        * ========================= */

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $fullUrl = $publicBaseUrl . $path;

        /* =========================
        * UPDATE DATABASE
        * ========================= */

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_ia_04b' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    private function buildIa05Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return null;
        }

        /** ✅ Shared barcode resolver */
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
                'soal' => [],
            ];
        }

        $questions = Soal::query()
            ->leftJoin('ia5_answers as a', function ($join) use ($id, $dokumen) {
                $join->on('a.soal_id', '=', 'soals.id')
                    ->where('a.id_izin', $id)
                    ->where('a.dokumen_id', $dokumen->id);
            })
            ->with(['unit:id,kode', 'kuk:id,kode'])
            ->where('soals.id_dokumen', $dokumen->id)
            ->where('soals.tipe', 1)
            ->orderBy('soals.no', 'asc')
            ->select([
                'soals.id',
                'soals.no',
                'soals.id_unitkompetensi',
                'soals.id_kuk',
                'soals.jenis',
                'soals.soal',
                'soals.jawab_a',
                'soals.jawab_b',
                'soals.jawab_c',
                'soals.jawab_d',
                'soals.file_a',
                'soals.file_b',
                'soals.file_c',
                'soals.file_d',
                'soals.jawaban as kunci_jawaban', // correct answer
                'a.jawaban as jawaban_asesi'        // answer by id_izin
            ])
            ->get()
            ->values(); // Re-index collection to ensure consistent ordering

        $feedback = Ia5Feedback::where('id_izin', $id)->value('umpan_balik');

        return [
            'barcodes' => $barcodes,
            'dokumen' => $dokumen,
            'soal' => $questions,
            'umpan_balik' => $feedback
        ];
    }

    public function getIa05($id)
    {
        $data = $this->buildIa05Data($id);

        if ($data === null) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    public function submitIa05(Request $request, $idIzin)
    {
        $validated = $request->validate([
            'dokumen_id' => 'required|integer',
            'answers' => 'required|array',
            'answers.*.soal_id' => 'required|integer',
            'answers.*.jawaban' => 'required|in:A,B,C,D',
            'umpan_balik' => 'nullable'
        ]);

        DB::transaction(function () use ($request, $idIzin) {
            foreach ($request->answers as $answer) {
                Ia5Answer::updateOrCreate(
                    [
                        'id_izin' => $idIzin,
                        'soal_id' => $answer['soal_id'],
                    ],
                    [
                        'dokumen_id' => $request->dokumen_id,
                        'jawaban' => $answer['jawaban'],
                    ]
                );
            }
        });

        if (!empty($validated['umpan_balik'])) {
            Ia5Feedback::updateOrCreate(
                ['id_izin' => $idIzin],
                [
                    'umpan_balik' => $validated['umpan_balik'],
                ]
            );
        }

        return response()->json([
            'message' => 'IA05 answers saved successfully'
        ]);
    }

    public function ttdIa05(Request $request, $id)
    {
        $data = $this->generateTtdBarcode(
            $request,
            'IA05',
            'ttd_ia05',
            $id
        );

        $dataDokumen = $this->buildIa05Data($id, true);
        if (!$dataDokumen) {
            abort(404, 'Data not found');
        }

        $dokumenHeader = $this->buildDokumenHeader($id);
        if (!$dokumenHeader) {
            abort(404, 'Dokumen header not found');
        }

        $viewData = array_merge($dataDokumen, [
            'dokumenHeader' => $dokumenHeader,
            'imga_checked' => '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAGvSURBVHjaYvz//z8DNQAjI6M5kCoFCCAYfxoQfwfi/yRgkHobqH4NIH4AxCkAAcQIJDqAuByq6AUQ/yHCQcxA/ByIPYCYB4j3AfEFoG+DAAIIJPkNaogrGT6VAuJLQPwaiBVBwQcQQAxQl90gwzBRID4G1Z8IMgyEAQIIZuBFEg0ThHoTpHctzDAQBgggmIGXSTCMF4i3IYW5OrKBAAGEbGAOEGsRMIwTiNcjxXQm2AAkAwECCNnALmjkzAZiXRyGrUQybCsQM6EbCBBA6F7uh/J/APEKILaEirMC8TIkw94iW4psIEAAoRsISpdzkDSCXLwGiFehJepiJJfrIRsIEEDYIoUNiFfjySF7oWpAoBCIfyEbCBBAuGIZFJO7sBj2CYhNkNRdgPgYYSBAAOFLNqCEexzNwCo0NWfRDQQIIJiBl3AkE3kgvoLFqzBwGt1AgACCGXgNT9rTB+ITQGyBRe4SuoEAAcQATSKg2LQiMfuByr/fIP3IBgIEEAtQYAYQ5wPxYSB+BMR/iSy+ZKH0TGQJgACCoQVQl5JSwP4E4kXoCRsggBipVQXAAECAAQAl4g0mXRSaqQAAAABJRU5ErkJggg==" align=abmiddle>',
		    'imga_nochecked' => '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAYAAACNiR0NAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAAEGSURBVHjaYvz//z8DNQFAAMHQNCD+DsT/ScB/gbgCpBnkKBgGCCAQvwOq4B8QPwPiRzjwFyD+BsRfoZb/heoJRDYQIIAYoIr+ALErib4yB+IfQHwK2UCAAGKAuu4GmUG1CYh/IRsIEEBMUImfZBoI8jozsgBAADGh0RQDgABionZyAQggqhsIEEBUNxAggKhuIEAAUWqgGroZAAFEqYEK6AIAAUSpgRglC0AAMeGSINdAgACCGchCpoEY+gACiAGawUGFgxWJhsXCSinkvAwQQCAbZgBxPhAfhhZTf3EYIArEjEg+44aydyErAgggGFoAdSkpBSyoLDyIXsACBBAjtasAgAADADiOfQARGEFnAAAAAElFTkSuQmCC" align=abmiddle>',
        ]);

        /* =========================
        * GENERATE PDF
        * ========================= */

        $pdf = Pdf::loadView('pdf.ia05', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/ia05';

        // unique + traceable filename
        $filename = 'FR-IA-05-' . $id . '-' . Str::uuid() . '.pdf';
        $path = $directory . '/' . $filename;

        // save PDF bytes to FTP
        Storage::disk($disk)->put($path, $pdf->output());

        /* =========================
        * BUILD PUBLIC URL
        * ========================= */

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $fullUrl = $publicBaseUrl . $path;

        /* =========================
        * UPDATE DATABASE
        * ========================= */

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_ia_05' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    private function buildAk02Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return null;
        }

        $barcodes = $this->resolveDocumentBarcodes($id, 'AK02', $forPdf);

        $unitKompetensi = UnitKompetensi::from('unit_kompetensis')
            ->where('unit_kompetensis.id_jabatan', $klasifikasi->jabatan_kerja)
            ->leftJoin('ak2_answers as a', function ($join) use ($id) {
                $join->on('a.id_unit_kompetensi', '=', 'unit_kompetensis.id')
                    ->where('a.id_izin', '=', $id);
            })
            ->orderBy('unit_kompetensis.id')
            ->get([
                'unit_kompetensis.id',
                'unit_kompetensis.kode',
                'unit_kompetensis.nama',
                'a.observasi',
                'a.portofolio',
                'a.pertanyaan_wawancara',
                'a.pertanyaan_lisan',
                'a.pertanyaan_tertulis',
                'a.proyek_kerja',
            ])
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'kode' => $item->kode,
                    'nama' => $item->nama,
                    'observasi' => is_null($item->observasi) ? null : (bool) $item->observasi,
                    'portofolio' => is_null($item->portofolio) ? null : (bool) $item->portofolio,
                    'pertanyaan_wawancara' => is_null($item->pertanyaan_wawancara) ? null : (bool) $item->pertanyaan_wawancara,
                    'pertanyaan_lisan' => is_null($item->pertanyaan_lisan) ? null : (bool) $item->pertanyaan_lisan,
                    'pertanyaan_tertulis' => is_null($item->pertanyaan_tertulis) ? null : (bool) $item->pertanyaan_tertulis,
                    'proyek_kerja' => is_null($item->proyek_kerja) ? null : (bool) $item->proyek_kerja,
                ];
            });

        $feedback = Ak2Feedback::where('id_izin', $id)->first();

        return [
            'barcodes' => $barcodes,
            'data_unit_kompetensi' => $unitKompetensi,
            'is_kompeten' => is_null($feedback?->is_kompeten) ? null : (bool) $feedback->is_kompeten,
            'tindak_lanjut' => $feedback?->tindak_lanjut,
            'komentar' => $feedback?->komentar,
        ];
    }

    public function getAk02($id)
    {
        $data = $this->buildAk02Data($id);

        if ($data === null) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    public function submitAk02(Request $request, $id)
    {
        // Validate request
        $validated = $request->validate([
            'answers' => 'required|array|min:1',
            'answers.*.id_unit_kompetensi' => 'required|exists:unit_kompetensis,id',
            'answers.*.observasi' => 'required|boolean',
            'answers.*.portofolio' => 'required|boolean',
            'answers.*.pertanyaan_wawancara' => 'required|boolean',
            'answers.*.pertanyaan_lisan' => 'required|boolean',
            'answers.*.pertanyaan_tertulis' => 'required|boolean',
            'answers.*.proyek_kerja' => 'required|boolean',
            'is_kompeten' => 'required|boolean',
            'tindak_lanjut' => 'nullable|string',
            'komentar' => 'nullable|string'
        ]);

        foreach ($validated['answers'] as $answer) {
            Ak2Answer::updateOrCreate(
                [
                    'id_izin' => $id,
                    'id_unit_kompetensi' => $answer['id_unit_kompetensi'],
                ],
                [
                    'observasi' => $answer['observasi'],
                    'portofolio' => $answer['portofolio'],
                    'pertanyaan_wawancara' => $answer['pertanyaan_wawancara'],
                    'pertanyaan_lisan' => $answer['pertanyaan_lisan'],
                    'pertanyaan_tertulis' => $answer['pertanyaan_tertulis'],
                    'proyek_kerja' => $answer['proyek_kerja'],
                ]
            );
        }

        if (!empty($validated['tindak_lanjut']) && !empty($validated['komentar'])) {
            Ak2Feedback::updateOrCreate(
                ['id_izin' => $id],
                [
                    'is_kompeten' => $validated['is_kompeten'],
                    'tindak_lanjut'  => $validated['tindak_lanjut'],
                    'komentar' => $validated['komentar']
                ]
            );
        }

        return response()->json([
            'message' => 'AK02 submitted successfully'
        ]);
    }

    public function ttdAk02(Request $request, $id)
    {
        $data = $this->generateTtdBarcode(
            $request,
            'AK02',
            'ttd_ak02',
            $id
        );

        $dataDokumen = $this->buildAk02Data($id, true);
        if (!$dataDokumen) {
            abort(404, 'Data not found');
        }

        $dokumenHeader = $this->buildDokumenHeader($id);
        if (!$dokumenHeader) {
            abort(404, 'Dokumen header not found');
        }

        $viewData = array_merge($dataDokumen, [
            'dokumenHeader' => $dokumenHeader
        ]);

        /* =========================
        * GENERATE PDF
        * ========================= */

        $pdf = Pdf::loadView('pdf.ak02', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/ak02';

        // unique + traceable filename
        $filename = 'FR-AK-02-' . $id . '-' . Str::uuid() . '.pdf';
        $path = $directory . '/' . $filename;

        // save PDF bytes to FTP
        Storage::disk($disk)->put($path, $pdf->output());

        /* =========================
        * BUILD PUBLIC URL
        * ========================= */

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $fullUrl = $publicBaseUrl . $path;

        /* =========================
        * UPDATE DATABASE
        * ========================= */

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_ak02' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    private function buildAk03Data($id): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return null;
        }

        // Get active FR.AK.03 document for this jabatan
        $dokumen = JabatanDokumen::where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.AK.03')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return [
                'soal'     => [],
                'ak3_note' => null,
            ];
        }

        // ===== QUESTIONS + ANSWERS =====
        $questions = Soal::query()
            ->where('soals.id_dokumen', $dokumen->id)
            ->leftJoin('ak3_answers', function ($join) use ($id) {
                $join->on('ak3_answers.soal_id', '=', 'soals.id')
                    ->where('ak3_answers.id_izin', $id);
            })
            ->orderBy('soals.no', 'asc')
            ->get([
                'soals.id',
                'soals.no',
                'soals.jenis',
                'soals.soal',
                'ak3_answers.is_kompeten',
                'ak3_answers.catatan',
            ])
            ->map(function ($row) {
                return [
                    'id'          => $row->id,
                    'no'          => $row->no,
                    'jenis'       => $row->jenis,
                    'soal'        => $row->soal,
                    'is_kompeten' => is_null($row->is_kompeten)
                        ? null
                        : (bool) $row->is_kompeten,
                    'catatan'     => $row->catatan,
                ];
            });

        // ===== AK3 NOTE (GLOBAL NOTE) =====
        $ak3Note = Ak3Note::where('id_izin', $id)->value('catatan');

        return [
            'soal'     => $questions,
            'catatan' => $ak3Note,
        ];
    }

    public function getAk03($id)
    {
        $data = $this->buildAk03Data($id);

        if ($data === null) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    public function submitAk03(Request $request, $id)
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.soal_id' => 'required|integer',
            'answers.*.is_kompeten' => 'nullable|boolean',
            'answers.*.catatan' => 'nullable|string',
            'catatan' => 'nullable|string'
        ]);

        DB::transaction(function () use ($validated, $id) {

            foreach ($validated['answers'] as $answer) {
                Ak3Answer::updateOrCreate(
                    [
                        'id_izin' => $id,
                        'soal_id' => $answer['soal_id'],
                    ],
                    [
                        'is_kompeten' => $answer['is_kompeten'] ?? null,
                        'catatan'     => $answer['catatan'] ?? null,
                    ]
                );
            }

            if (!empty($validated['catatan'])) {
                Ak3Note::updateOrCreate(
                    ['id_izin' => $id],
                    ['catatan' => $validated['catatan']]
                );
            }

            // 🔥 Run AFTER transaction is committed
            DB::afterCommit(function () use ($id) {
                $this->generateAk03Internal($id);
            });
        });

        return response()->json([
            'message' => 'Success'
        ]);
    }

    private function generateAk03Internal($id): string
    {
        $data = $this->buildAk03Data($id);
        if (!$data) {
            throw new \Exception('AK03 data not found');
        }

        $dokumenHeader = $this->buildDokumenHeader($id, true);
        if (!$dokumenHeader) {
            throw new \Exception('Dokumen header not found');
        }

        $viewData = array_merge($data, [
            'dokumenHeader' => $dokumenHeader
        ]);

        $pdf = Pdf::loadView('pdf.ak03', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/ak03';

        $filename = 'FR-AK-03-' . $id . '-' . Str::uuid() . '.pdf';
        $path = $directory . '/' . $filename;

        Storage::disk($disk)->put($path, $pdf->output());

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $fullUrl = $publicBaseUrl . $path;

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_ak03' => $fullUrl]
        );

        return $fullUrl;
    }

    private function buildAk05Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return null;
        }

        $answer = Ak5Answer::select(['keterangan', 'aspek', 'pencatatan_penolakan', 'saran', 'catatan'])->where('id_izin', $id)->first();

        $barcodes = $this->resolveDocumentBarcodes($id, 'AK05', $forPdf);

        unset($barcodes['asesi']);

        $kompeten = AsesiJadwal::where('id_izin', $id)->value('kompeten');

        return [
            'kompeten' => $kompeten === 'K' ? true : false,
            'answers' => $answer,
            'barcodes' => $barcodes
        ];
    }

    public function getAk05($id)
    {
        $data = $this->buildAk05Data($id);

        if ($data === null) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    public function submitAk05(Request $request, $id)
    {
        // 🔹 Validate request
        $validated = $request->validate([
            'kompeten'              => 'required|boolean',
            'keterangan'            => 'nullable|string',
            'aspek'                 => 'nullable|string',
            'pencatatan_penolakan'  => 'nullable|string',
            'saran'                 => 'nullable|string',
            'catatan'               => 'nullable|string',
        ]);

        // 🔹 Ensure klasifikasi exists (same logic as getAk05)
        $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $id)->first();

        if (!$klasifikasi) {
            return response()->json([
                'message' => 'Data tidak ditemukan',
            ], 404);
        }

        // 🔹 Create or update AK05 answer
        Ak5Answer::updateOrCreate(
            ['id_izin' => $id],
            [
                'keterangan'           => $validated['keterangan'],
                'aspek'                => $validated['aspek'],
                'pencatatan_penolakan' => $validated['pencatatan_penolakan'] ?? null,
                'saran'                => $validated['saran'] ?? null,
                'catatan'              => $validated['catatan'] ?? null,
            ]
        );

        AsesiJadwal::where('id_izin', $id)->update([
            'kompeten' => $request->kompeten ? 'K' : 'BK'
        ]);

        return response()->json([
            'message' => 'AK05 berhasil disubmit'
        ]);
    }

    public function ttdAk05(Request $request, $id)
    {
        $data = $this->generateTtdBarcode(
            $request,
            'AK05',
            'ttd_ak05',
            $id
        );

        $dataDokumen = $this->buildAk05Data($id, true);
        if (!$dataDokumen) {
            abort(404, 'Data not found');
        }

        $dokumenHeader = $this->buildDokumenHeader($id);
        if (!$dokumenHeader) {
            abort(404, 'Dokumen header not found');
        }

        $viewData = array_merge($dataDokumen, [
            'dokumenHeader' => $dokumenHeader
        ]);

        /* =========================
        * GENERATE PDF
        * ========================= */

        $pdf = Pdf::loadView('pdf.ak05', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/ak05';

        // unique + traceable filename
        $filename = 'FR-AK-05-' . $id . '-' . Str::uuid() . '.pdf';
        $path = $directory . '/' . $filename;

        // save PDF bytes to FTP
        Storage::disk($disk)->put($path, $pdf->output());

        /* =========================
        * BUILD PUBLIC URL
        * ========================= */

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $fullUrl = $publicBaseUrl . $path;

        /* =========================
        * UPDATE DATABASE
        * ========================= */

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_ak05' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    private function buildAk06Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return null;
        }

        /** 🔹 Aspek + Answers */
        $aspek = Referensi::from('referensis')
            ->where('referensis.kategori', 'AK06')
            ->where('referensis.id', '!=', 26)
            ->leftJoin('ak6_answers as a', function ($join) use ($id) {
                $join->on('a.id_aspek', '=', 'referensis.id')
                    ->where('a.id_izin', '=', $id);
            })
            ->orderBy('referensis.id')
            ->get([
                'referensis.id as aspek_id',
                'referensis.nama',
                'a.validitas',
                'a.reliabel',
                'a.fleksibel',
                'a.adil',
            ])
            ->map(function ($item) {
                return [
                    'aspek_id'  => $item->aspek_id,
                    'nama'      => $item->nama,
                    'validitas' => is_null($item->validitas) ? null : (bool) $item->validitas,
                    'reliabel'  => is_null($item->reliabel)  ? null : (bool) $item->reliabel,
                    'fleksibel' => is_null($item->fleksibel) ? null : (bool) $item->fleksibel,
                    'adil'      => is_null($item->adil)      ? null : (bool) $item->adil,
                ];
            });

        /** 🔹 AK06 Feedback (single row per id_izin) */
        $feedback = Ak6Feedback::where('id_izin', $id)
            ->first([
                'rekomendasi1',
                'rekomendasi2',
                'catatan_asesor1',
                'catatan_asesor2',
            ]);

        /** 🔹 Dimensi Kompetensi */
        $dimensiKompetensi = Ak6DimensiKompetensi::getByIzinId($id);

        $barcodes = $this->resolveDocumentBarcodes($id, 'AK06', $forPdf);

        return [
            'aspek' => $aspek,
            'feedback' => [
                'rekomendasi1'     => $feedback->rekomendasi1 ?? null,
                'rekomendasi2'     => $feedback->rekomendasi2 ?? null,
                'catatan_asesor1' => $feedback->catatan_asesor1 ?? null,
                'catatan_asesor2' => $feedback->catatan_asesor2 ?? null,
            ],
            'dimensi_kompetensi' => [
                'task_skills' => $dimensiKompetensi->task_skills ?? 'L/DIT<br/> T/DPT',
                'task_management_skills' => $dimensiKompetensi->task_management_skills ?? 'L/DIT<br/> T/DPT',
                'contingency_management_skills' => $dimensiKompetensi->contingency_management_skills ?? 'L/DIT<br/> T/DPT',
                'job_role_environment_skills' => $dimensiKompetensi->job_role_environment_skills ?? 'L/DIT<br/> T/DPT',
                'transfer_skills' => $dimensiKompetensi->transfer_skills ?? 'L/DIT<br/> T/DPT',
            ],
            'barcodes' => $barcodes
        ];
    }

    public function getAk06($id)
    {
        $data = $this->buildAk06Data($id);

        if ($data === null) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    public function submitAk06(Request $request, $id)
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.aspek_id' => 'required|exists:referensis,id',
            'answers.*.validitas' => 'nullable|boolean',
            'answers.*.reliabel'  => 'nullable|boolean',
            'answers.*.fleksibel' => 'nullable|boolean',
            'answers.*.adil'      => 'nullable|boolean',
            'rekomendasi1' => 'nullable|string',
            'rekomendasi2' => 'nullable|string',
            'catatan_asesor1' => 'nullable|string',
            'catatan_asesor2' => 'nullable|string',
            'dimensi_kompetensi' => 'nullable|array',
            'dimensi_kompetensi.task_skills' => 'nullable|string',
            'dimensi_kompetensi.task_management_skills' => 'nullable|string',
            'dimensi_kompetensi.contingency_management_skills' => 'nullable|string',
            'dimensi_kompetensi.job_role_environment_skills' => 'nullable|string',
            'dimensi_kompetensi.transfer_skills' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $id) {

            /** 🔹 Save answers */
            foreach ($validated['answers'] as $answer) {
                Ak6Answer::updateOrCreate(
                    [
                        'id_izin'  => $id,
                        'id_aspek' => $answer['aspek_id'],
                    ],
                    [
                        'validitas' => isset($answer['validitas']) ? (bool) $answer['validitas'] : null,
                        'reliabel'  => isset($answer['reliabel'])  ? (bool) $answer['reliabel']  : null,
                        'fleksibel' => isset($answer['fleksibel']) ? (bool) $answer['fleksibel'] : null,
                        'adil'      => isset($answer['adil'])      ? (bool) $answer['adil']      : null,
                    ]
                );
            }

            /** 🔹 Save notes in ONE query */
            $noteData = array_filter([
                'rekomendasi1'     => $validated['rekomendasi1']     ?? null,
                'rekomendasi2'     => $validated['rekomendasi2']     ?? null,
                'catatan_asesor1' => $validated['catatan_asesor1'] ?? null,
                'catatan_asesor2' => $validated['catatan_asesor2'] ?? null,
            ], fn ($v) => !is_null($v));

            if (!empty($noteData)) {
                Ak6Feedback::updateOrCreate(
                    ['id_izin' => $id],
                    $noteData
                );
            }

            /** 🔹 Save dimensi kompetensi */
            if (!empty($validated['dimensi_kompetensi'])) {
                $dimensiData = array_filter([
                    'task_skills' => $validated['dimensi_kompetensi']['task_skills'] ?? null,
                    'task_management_skills' => $validated['dimensi_kompetensi']['task_management_skills'] ?? null,
                    'contingency_management_skills' => $validated['dimensi_kompetensi']['contingency_management_skills'] ?? null,
                    'job_role_environment_skills' => $validated['dimensi_kompetensi']['job_role_environment_skills'] ?? null,
                    'transfer_skills' => $validated['dimensi_kompetensi']['transfer_skills'] ?? null,
                ], fn ($v) => !is_null($v));

                if (!empty($dimensiData)) {
                    Ak6DimensiKompetensi::updateOrCreate(
                        ['id_izin' => $id],
                        $dimensiData
                    );
                }
            }
        });

        return response()->json([
            'message' => 'AK06 berhasil disubmit'
        ]);
    }

    public function ttdAk06(Request $request, $id)
    {
        $data = $this->generateTtdBarcode(
            $request,
            'AK06',
            'ttd_ak06',
            $id
        );

        $dataDokumen = $this->buildAk06Data($id, true);
        if (!$dataDokumen) {
            abort(404, 'Data not found');
        }

        $dokumenHeader = $this->buildDokumenHeader($id);
        if (!$dokumenHeader) {
            abort(404, 'Dokumen header not found');
        }

        $viewData = array_merge($dataDokumen, [
            'dokumenHeader' => $dokumenHeader
        ]);

        /* =========================
        * GENERATE PDF
        * ========================= */

        $pdf = Pdf::loadView('pdf.ak06', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/ak06';

        // unique + traceable filename
        $filename = 'FR-AK-06-' . $id . '-' . Str::uuid() . '.pdf';
        $path = $directory . '/' . $filename;

        // save PDF bytes to FTP
        Storage::disk($disk)->put($path, $pdf->output());

        /* =========================
        * BUILD PUBLIC URL
        * ========================= */

        $publicBaseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';
        $fullUrl = $publicBaseUrl . $path;

        /* =========================
        * UPDATE DATABASE
        * ========================= */

        BuktiAsesmen::updateOrCreate(
            ['id_izin' => $id],
            ['url_ak06' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    public function dokumenAsesi($id)
    {
        $fields = [
            'url_apl_01',
            'url_apl_02',
            'url_mapa01',
            'url_mapa02',
            'url_ak07',
            'url_ak04',
            'url_ak01',
            'url_ia_01',
            'url_ia_02',
            'url_ia_03',
            'url_ia_04a',
            'url_ia_04b',
            'url_ia_05',
            'url_ak02',
            'url_ak03',
            'url_ak05',
            'url_ak06',
            'url_paparan',
            'url_docs_asesmen',
        ];

        $dokumen = BuktiAsesmen::select($fields)
            ->where('id_izin', $id)
            ->first();

        if (!$dokumen) {
            return response()->json([
                'message' => 'Dokumen asesmen not found',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => [
                'apl01' => $dokumen->url_apl_01,
                'apl02' => $dokumen->url_apl_02,
                'mapa01' => $dokumen->url_mapa01,
                'mapa02' => $dokumen->url_mapa02,
                'ak07' => $dokumen->url_ak07,
                'ak04' => $dokumen->url_ak04,
                'ak01' => $dokumen->url_ak01,
                'ia01' => $dokumen->url_ia_01,
                'ia02' => $dokumen->url_ia_02,
                'ia03' => $dokumen->url_ia_03,
                'ia04a' => $dokumen->url_ia_04a,
                'ia04b' => $dokumen->url_ia_04b,
                'ia05' => $dokumen->url_ia_05,
                'ak02' => $dokumen->url_ak02,
                'ak03' => $dokumen->url_ak03,
                'ak05' => $dokumen->url_ak05,
                'ak06' => $dokumen->url_ak06,
                'tugas' => $dokumen->url_paparan,
                'foto_kegiatan' => $dokumen->url_docs_asesmen,
            ]
        ]);
    }

    public function absensi($id)
    {
        $fields = [
            'url_absen_asesi_awal',
            'url_absen_asesi_akhir',
            'url_absen_asesor1_awal',
            'url_absen_asesor1_akhir',
            'url_absen_asesor2_awal',
            'url_absen_asesor2_akhir',
            'url_absen_asesi_pra_awal',
            'url_absen_asesi_pra_akhir',
            'url_absen_asesor1_pra_awal',
            'url_absen_asesor1_pra_akhir',
            'url_absen_asesor2_pra_awal',
            'url_absen_asesor2_pra_akhir',
            'url_docs_asesmen as foto_kegiatan'
        ];

        $dokumen = BuktiAsesmen::select($fields)
            ->where('id_izin', $id)
            ->firstOrFail();

        return response()->json([
            'message' => 'Success',
            'data' => $dokumen
        ]);
    }

    private function mapBarcode(?Barcode $barcode): ?array
    {
        if (!$barcode) {
            return null;
        }

        return [
            'url' => $barcode->url_image,
            'tanggal' => $barcode->created_at,
            'nama' => $barcode->user?->name,
        ];
    }

    private function mapBarcodeForPdf(?Barcode $barcode): ?array
    {
        if (!$barcode) {
            return null;
        }

        return [
            'url'     => $this->imageUrlToBase64($barcode->url_image),
            'tanggal' => $barcode->created_at,
            'nama'    => $barcode->user?->name,
        ];
    }

    private function resolveDocumentBarcodes(
        int|string $idIzin,
        string $jenisDokumen,
        bool $forPdf = false
    ): array
    {
        $map = $forPdf
            ? fn (?Barcode $b) => $this->mapBarcodeForPdf($b)
            : fn (?Barcode $b) => $this->mapBarcode($b);
        /** 📌 Load barcodes & key by user */
        $barcodes = Barcode::with('user')
            ->where('id_izin', $idIzin)
            ->where('jenis_dokumen', $jenisDokumen)
            ->get()
            ->keyBy('id_user');

        if ($barcodes->isEmpty()) {
            return [
                'asesi' => null,
                'asesor1' => null,
                'asesor2' => null,
            ];
        }

        /** 📌 Resolve jadwal */
        $jadwal = Jadwal::select(['asesor_id', 'asesor2_id'])
            ->where('jadwal_id', $barcodes->first()->id_jadwal)
            ->first();

        if (!$jadwal) {
            return [
                'asesi' => $map($barcodes->firstWhere('id_role', 8)),
                'asesor1' => null,
                'asesor2' => null,
            ];
        }

        /** 📌 Resolve asesor → user */
        $asesors = AsesorBnsp::select(['id', 'noreg'])
            ->whereIn('id', array_filter([
                $jadwal->asesor_id,
                $jadwal->asesor2_id
            ]))
            ->get()
            ->keyBy('id');

        $asesorUsers = User::whereIn('noreg', $asesors->pluck('noreg'))
            ->get()
            ->keyBy('noreg');

        $asesor1User = isset($asesors[$jadwal->asesor_id])
            ? ($asesorUsers[$asesors[$jadwal->asesor_id]->noreg] ?? null)
            : null;

        $asesor2User = isset($asesors[$jadwal->asesor2_id])
            ? ($asesorUsers[$asesors[$jadwal->asesor2_id]->noreg] ?? null)
            : null;

        return [
            'asesi' =>
                $map($barcodes->firstWhere('id_role', 8)),
            'asesor1' =>
                $asesor1User
                    ? $map($barcodes[$asesor1User->id] ?? null)
                    : null,

            'asesor2' =>
                $asesor2User
                    ? $map($barcodes[$asesor2User->id] ?? null)
                    : null,
        ];
    }

    private function imageUrlToBase64(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        try {
            $contents = file_get_contents($url);

            if ($contents === false) {
                return null;
            }

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

    private function generateTtdBarcode(
        Request $request,
        string $docType,
        string $filePrefix,
        string $izinId
    ) {
        $request->validate([
            'id_jadwal' => 'required'
        ]);

        // 1️⃣ Generate UUID & URL TTD
        $uuid = (string) Uuid::uuid4();
        $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";

        // 2️⃣ Generate filename
        $filename = "{$filePrefix}_" . Str::uuid() . '.png';

        // 3️⃣ Generate QR image
        $qrImage = QrCode::format('png')
            ->size(300)
            ->margin(1)
            ->generate($urlTtd);

        // 4️⃣ FTP path
        $path = "barcodes/{$filename}";

        // 5️⃣ Upload to FTP
        Storage::disk('str_moon')->put($path, $qrImage);

        // 6️⃣ Public image URL
        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        // 7️⃣ Save / update DB
        Barcode::updateOrCreate(
            [
                'id_user'       => auth()->user()->id,
                'id_izin'       => $izinId,
                'jenis_dokumen' => $docType,
            ],
            [
                'id_jadwal' => $request->id_jadwal,
                'url_image' => $urlImage,
                'url_ttd'   => $urlTtd,
                'id_role'   => auth()->user()->role_id,
            ]
        );

        return [
            'url_image' => $urlImage,
            'url_ttd'   => $urlTtd,
        ];
    }
}
