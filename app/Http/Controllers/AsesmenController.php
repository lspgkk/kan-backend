<?php

namespace App\Http\Controllers;

use App\Models\Ak3Answer;
use App\Models\AsesiJadwal;
use App\Models\BuktiAsesmen;
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

    public function uploadTugas(Request $request, $id)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480' // max 20MB
        ]);

        DB::transaction(function () use ($request, $id) {

            // 1️⃣ Generate unique filename (NO extension)
            $file_paparan_url_name = (string) Uuid::uuid4();

            // 2️⃣ Full FTP path
            $ftpPath = "/dok_asesmen/paparan/{$file_paparan_url_name}.pdf";

            // 3️⃣ Upload to FTP
            Storage::disk('str_moon')->put(
                $ftpPath,
                file_get_contents($request->file('file')->getRealPath())
            );

            // 4️⃣ Public URL
            $fullUrlPaparan = "https://moon.lspgatensi.id/files{$ftpPath}";

            // 5️⃣ Save / update DB record
            BuktiAsesmen::updateOrCreate(
                ['id_izin' => $id],
                [
                    'url_paparan'  => $fullUrlPaparan,
                    'updated_at' => now(),
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

        $unitKompetensi = UnitKompetensi::select(['kode', 'nama'])->where('id_jabatan', $klasifikasi->jabatan_kerja)->get();

        return response()->json([
            'message' => 'Success',
            'data' => [
                'data_unit_kompetensi' => $unitKompetensi,
            ]
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
                    'soal' => []
                ]
            ]);
        }

        /**
         * QUESTIONS + ANSWERS
         * NOTE: dokumen_id is used ONLY for filtering questions
         *       not stored in answers table
         */
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

        return response()->json([
            'message' => 'Success',
            'data' => [
                'soal' => $questions
            ]
        ]);
    }

    public function submitAk03(Request $request, $id)
    {
        $request->validate([
            'answers' => 'required|array',
            'answers.*.soal_id' => 'required|integer',
            'answers.*.is_kompeten' => 'nullable|boolean',
            'answers.*.catatan' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $id) {
            foreach ($request->answers as $answer) {
                Ak3Answer::updateOrCreate(
                    [
                        'id_izin'    => $id,
                        'soal_id'    => $answer['soal_id'],
                    ],
                    [
                        'is_kompeten' => $answer['is_kompeten'] ?? null,
                        'catatan'     => $answer['catatan'] ?? null,
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'Success'
        ]);
    }
}
