<?php

namespace App\Http\Controllers;

use App\Models\Ak2Answer;
use App\Models\Ak2Feedback;
use App\Models\Ak3Answer;
use App\Models\Ak3Note;
use App\Models\Ak6Answer;
use App\Models\Ak6Feedback;
use App\Models\AsesiJadwal;
use App\Models\BuktiAsesmen;
use App\Models\DimensiKompetensi;
use App\Models\Ia4aAnswer;
use App\Models\Ia4bAnswer;
use App\Models\Ia5Answer;
use App\Models\JabatanConfigA;
use App\Models\JabatanDokumen;
use App\Models\JabatanKerja;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Personal;
use App\Models\Referensi;
use App\Models\Soal;
use App\Models\UnitKompetensi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

class AsesmenController extends Controller
{
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

        return response()->json([
            'message' => 'Success',
            'data' => [
                'jabatan_kerja' => $jabatanKerja,
                'nomor_skema' => $jabatanConfig->no_skema,
                'tuk' => $jadwal->tuk ? $jadwal->tuk->nama : null,
                'id_asesor_1' => $idAsesor1,
                'id_asesor_2' => $idAsesor2,
                'nama_asesi' => $namaAsesi,
                'asesor_1' => $jadwal->asesor ? $jadwal->asesor->nama : null,
                'asesor_2' => $jadwal->asesor2 ? $jadwal->asesor2->nama : null,
                'noreg_asesor_1' => $jadwal->asesor ? $jadwal->asesor->noreg : null,
                'noreg_asesor_2' => $jadwal->asesor2 ? $jadwal->asesor2->noreg : null,
                'tanggal_uji' => $jadwal->tanggal_uji,
                'tanggal_selesai' => $jadwal->tanggal_selesai,
                'jenis_kelas' => $jadwal->jenis_kelas,
            ]
        ]);
    }

    public function getIa04a($id)
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null
            ], 404);
        }

        $referensis = Referensi::select(['id', 'nama'])
            ->where('kategori', 'IA04')
            ->get();

        $dokumen = JabatanDokumen::with(['kelompoks.units.unit'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.IA.04')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return response()->json([
                'message' => 'Success',
                'data' => []
            ]);
        }

        // 🔹 Load saved feedback answers
        $ia04aAnswers = Ia4aAnswer::where('id_izin', $id)
            ->get()
            ->keyBy('soal_id');

        // 🔹 Load questions + inject feedback answer
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

        return response()->json([
            'message' => 'Success',
            'data' => [
                'kelompok_kerja' => $kelompokKerja,
                'referensi_form' => $referensis,
                'soal' => $questions
            ]
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

    public function getTugas($id)
    {
        $bukti = BuktiAsesmen::select('url_paparan')
            ->where('id_izin', $id)
            ->first();

        if (!$bukti || !$bukti->url_paparan) {
            return response()->json([
                'message' => 'Tugas belum diupload',
                'data'    => null,
            ], 404);
        }

        // Extract file info
        $url  = $bukti->url_paparan;
        $path = parse_url($url, PHP_URL_PATH);
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return response()->json([
            'message' => 'Success',
            'data' => [
                'url'       => $url,
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

            // 1️⃣ Generate unique filename
            $uuid = (string) \Ramsey\Uuid\Uuid::uuid4();

            // 2️⃣ Get original extension (pdf / ppt / pptx)
            $extension = $request->file('file')->getClientOriginalExtension();

            // 3️⃣ Full FTP path
            $ftpPath = "/paparan/{$uuid}.{$extension}";

            // 4️⃣ Upload to FTP
            Storage::disk('str_moon')->put(
                $ftpPath,
                file_get_contents($request->file('file')->getRealPath())
            );

            // 5️⃣ Public URL
            $fullUrlPaparan = "https://moon.lspgatensi.id/files/new_gatensi{$ftpPath}";

            // 6️⃣ Save / update DB record
            BuktiAsesmen::updateOrCreate(
                ['id_izin' => $id],
                [
                    'url_paparan' => $fullUrlPaparan,
                    'updated_at'  => now(),
                ]
            );
        });

        return response()->json([
            'message' => 'Tugas berhasil diupload!'
        ]);
    }

    public function getIa04b($id)
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null
            ], 404);
        }

        $dokumen = JabatanDokumen::select(['id', 'nama_dokumen'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.IA.04.B')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return response()->json([
                'message' => 'Success',
                'data' => []
            ]);
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
            ->get();

        $questions = $questions->map(function ($q) {
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

        return response()->json([
            'message' => 'Success',
            'data' => [
                'dokumen' => $dokumen,
                'soal' => $questions,
                'rekomendasi' => [
                    'id' => $rekomendasi->id,
                    'no' => $rekomendasi->no,
                    'jenis' => $rekomendasi->jenis,
                    'soal' => $rekomendasi->soal,
                    'is_komentar' => $rekomendasi->is_komentar,
                    'rekomendasi' => (bool) $rekomendasi->rekomendasi
                ]
            ]
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

    public function getIa05($id)
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null
            ], 404);
        }

        $dokumen = JabatanDokumen::select(['id', 'nama_dokumen'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.IA.05')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return response()->json([
                'message' => 'Success',
                'data' => []
            ]);
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
            ->get();

        return response()->json([
            'message' => 'Success',
            'data' => [
                'dokumen' => $dokumen,
                'soal' => $questions
            ]
        ]);
    }

    public function submitIa05(Request $request, $idIzin)
    {
        $request->validate([
            'dokumen_id' => 'required|integer',
            'answers' => 'required|array',
            'answers.*.soal_id' => 'required|integer',
            'answers.*.jawaban' => 'required|in:A,B,C,D',
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

        return response()->json([
            'message' => 'IA05 answers saved successfully'
        ]);
    }

    public function getAk02($id)
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null
            ], 404);
        }

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

        return response()->json([
            'message' => 'Success',
            'data' => [
                'data_unit_kompetensi' => $unitKompetensi,
                'is_kompeten' => is_null($feedback?->is_kompeten) ? null : (bool) $feedback->is_kompeten,
                'tindak_lanjut' => $feedback?->tindak_lanjut,
                'komentar' => $feedback?->komentar,
            ]
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

    public function getAk03($id)
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null
            ], 404);
        }

        // Get active FR.AK.03 document for this jabatan
        $dokumen = JabatanDokumen::where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'FR.AK.03')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return response()->json([
                'message' => 'Success',
                'data' => [
                    'soal'     => [],
                    'ak3_note' => null,
                ]
            ]);
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

        return response()->json([
            'message' => 'Success',
            'data' => [
                'soal'     => $questions,
                'catatan' => $ak3Note,
            ]
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
                    [
                        'catatan' => $validated['catatan'],
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Success'
        ]);
    }

    public function getAk06($id)
    {
        $klasifikasi = KlasifikasiKualifikasi::select('jabatan_kerja')
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return response()->json([
                'message' => 'Data not found',
                'data'    => null
            ], 404);
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
        $dimensiKompetensi = DimensiKompetensi::get();

        return response()->json([
            'message' => 'Success',
            'data' => [
                'aspek' => $aspek,
                'feedback' => [
                    'rekomendasi1'     => $feedback->rekomendasi1 ?? null,
                    'rekomendasi2'     => $feedback->rekomendasi2 ?? null,
                    'catatan_asesor1' => $feedback->catatan_asesor1 ?? null,
                    'catatan_asesor2' => $feedback->catatan_asesor2 ?? null,
                ],
                'dimensi_kompetensi' => $dimensiKompetensi
            ]
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
        });

        return response()->json([
            'message' => 'AK06 berhasil disubmit'
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
            ->firstOrFail();

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
}
