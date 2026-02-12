<?php

namespace App\Http\Controllers;

use App\Models\Ak1Answer;
use App\Models\Ak4Answer;
use App\Models\Ak4Reason;
use App\Models\Ak7Answer;
use App\Models\Apl1Answer;
use App\Models\Apl2Answer;
use App\Models\Apl2File;
use App\Models\Apl2Submission;
use App\Models\AsesiJadwal;
use App\Models\AsesorBnsp;
use App\Models\Barcode;
use App\Models\BuktiAsesmen;
use App\Models\ImpBukti;
use App\Models\JabatanConfigA;
use App\Models\JabatanDokumen;
use App\Models\JabatanKerja;
use App\Models\Jadwal;
use App\Models\JenjangJabatanKerja;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Pendidikan;
use App\Models\Personal;
use App\Models\Proyek;
use App\Models\Referensi;
use App\Models\Role;
use App\Models\UnitKompetensi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PraAsesmenController extends Controller
{
    public function absenAwal(Request $request, $id)
    {
        $user = auth()->user();

        $role = Role::findOrFail($user->role_id);

        if ($role->name === 'Asesi') {
            $bukti = BuktiAsesmen::updateOrCreate(['id_izin' => $id],[
                'id_izin'    => $id,
                'url_absen_asesi_awal' => $request->url_absen,
            ]);
        }
        
        if ($role->name === 'Asesor') {
            $roleAsesor = null;
            $jadwal = Jadwal::where('jadwal_id', AsesiJadwal::where('id_izin', $id)->value('jadwal_id'))->with(['asesor:id,noreg'])->first();
            if ($user->noreg === $jadwal->asesor->noreg) {
                $roleAsesor = 'asesor1';
            } elseif ($user->noreg === $jadwal->asesor2->noreg) {
                $roleAsesor = 'asesor2';
            }
            if ($roleAsesor) {
                $bukti = BuktiAsesmen::updateOrCreate(['id_izin' => $id],[
                    'id_izin'    => $id,
                    "url_absen_{$roleAsesor}_awal" => $request->url_absen,
                ]);
            }
        }
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

    public function getApl01($id)
    {
        $personal = Personal::select(['nama', 'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'kodepos', 'telepon', 'email'])->where('id_izin', $id)->first();
        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])->where('id_izin', $id)->first();
        $jabatanKerja = JabatanKerja::select(['jabatan_kerja', 'acuan'])->where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)->first();
        $pekerjaan = Proyek::where('id_izin', $id)->latest('created_at')->first();
        $apl1Answer = Apl1Answer::where('id_izin', $id)->first();
        $kualifikasi = JenjangJabatanKerja::where('jenjang_id', $klasifikasi->jenjang)->value('jabatan_kerja');
        $unitKompetensi = UnitKompetensi::select(['kode', 'nama'])->where('id_jabatan', $klasifikasi->jabatan_kerja)->get();
        $jenjang = Pendidikan::where('id_izin', $id)->orderBy('created_at', 'desc')->value('jenjang');
        $persyaratan = ImpBukti::select(['no', 'bukti'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->whereNotLike('bukti', 'Syarat Administrasi%')
            ->whereNotLike('bukti', 'Bukti Keanggotaan%')
            ->orderBy('no', 'asc')
            ->get();
        $administratif = ImpBukti::select(['no', 'bukti'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where(function ($q) {
                $q->whereLike('bukti', 'Syarat Administrasi%')
                ->orWhereLike('bukti', 'Bukti Keanggotaan%');
            })
            ->orderBy('no', 'asc')
            ->get();

        $formattedAdministratif = collect();
        $no = 1;

        foreach ($administratif as $item) {

            $clean = preg_replace(
                '/^(Syarat Administrasi|Bukti Keanggotaan)\s*:\s*/i',
                '',
                $item->bukti
            );

            $parts = array_map('trim', explode(',', $clean));

            // Buckets
            $foto = [];
            $ktp  = [];
            $other = [];

            foreach ($parts as $text) {
                if ($text === '' || strtolower($text) === 'dan lain-lain.') {
                    continue;
                }

                if (stripos($text, 'pas foto') !== false) {
                    $foto[] = $text;
                } elseif (stripos($text, 'ktp') !== false) {
                    $ktp[] = $text;
                } else {
                    $other[] = $text;
                }
            }

            // Merge in desired order
            foreach (array_merge($foto, $ktp, $other) as $text) {
                $formattedAdministratif->push([
                    'no'    => (string) $no++,
                    'bukti' => $text,
                    'checked' => true
                ]);
            }
        }

        $nomorSkema = JabatanConfigA::where('id_jabatan', $klasifikasi->jabatan_kerja)->value('no_skema');
        return response()->json([
            'message' => 'Success',
            'data' => [
                'data_pribadi' => [
                    'nama' => $personal->nama,
                    'nik' => $personal->nik,
                    'tempat_lahir' => $personal->tempat_lahir,
                    'tanggal_lahir' => $personal->tanggal_lahir,
                    'jenis_kelamin' => $personal->jenis_kelamin,
                    'kebangsaan' => 'ID',
                    'alamat' => $personal->alamat,
                    'telepon_rumah' => null,
                    'telepon_hp' => $personal->telepon,
                    'kode_pos' => $personal->kode_pos,
                    'email' => $personal->email,
                    'kualifikasi' => $kualifikasi,
                ],
                'data_pekerjaan' => [
                    'perusahaan' => $apl1Answer
                        ? $apl1Answer->perusahaan
                        : ($pekerjaan ? $pekerjaan->pemberi_kerja : null),

                    'jabatan' => $apl1Answer
                        ? $apl1Answer->jabatan
                        : ($pekerjaan ? $pekerjaan->jabatan : null),

                    'alamat_kantor' => $apl1Answer
                        ? $apl1Answer->alamat_kantor
                        : ($pekerjaan ? $pekerjaan->alamat : null),

                    'kode_pos' => $apl1Answer
                        ? $apl1Answer->kode_pos
                        : ($pekerjaan ? $pekerjaan->kode_pos : null),

                    'telepon_kantor' => $apl1Answer
                        ? $apl1Answer->telepon_kantor
                        : ($pekerjaan ? $pekerjaan->telepon : null),

                    'fax' => $apl1Answer
                        ? $apl1Answer->fax
                        : ($pekerjaan ? $pekerjaan->fax : null),

                    'email_kantor' => $apl1Answer
                        ? $apl1Answer->email_kantor
                        : ($pekerjaan ? $pekerjaan->email : null),
                ],
                'data_sertifikasi' => [
                    'judul' => $jabatanKerja->jabatan_kerja,
                    'nomor' => $nomorSkema,
                    'options' => [
                        ['id' => '1', 'label' => 'Sertifikasi', 'checked' => true],
                        ['id' => '2', 'label' => 'Sertifikasi Ulang', 'checked' => false],
                        ['id' => '3', 'label' => 'Pengakuan Kompetensi Terkini (PKT)', 'checked' => false],
                        ['id' => '4', 'label' => 'Rekognisi Pembelajaran Lampau', 'checked' => false],
                        ['id' => '5', 'label' => 'Lainnya:', 'checked' => false]
                    ]
                ],
                'is_diterima' => true,
                'skkni' => $jabatanKerja->acuan,
                'data_unit_kompetensi' => $unitKompetensi,
                'bukti_persyaratan' => $persyaratan,
                'bukti_administratif' => $formattedAdministratif->values(),
                'catatan' => null
            ]
        ]);
    }

    public function submitApl01(Request $request, $id)
    {
        $validated = $request->validate([
            'perusahaan'      => 'nullable|string',
            'jabatan'         => 'nullable|string',
            'alamat_kantor'   => 'nullable|string',
            'kode_pos'        => 'nullable|integer',
            'telepon_kantor'  => 'nullable|string',
            'fax'             => 'nullable|string',
            'email_kantor'    => 'nullable|email',
        ]);

        $apl01 = Apl1Answer::updateOrCreate(
            ['id_izin' => $id],
            $validated
        );

        return response()->json([
            'message' => 'APL-01 successfully submitted',
            'data' => $apl01
        ]);
    }

    public function getTtdApl01($id)
    {
        $barcodes = Barcode::where('id_izin', $id)
            ->where('jenis_dokumen', 'APL01')
            ->get()
            ->keyBy('id_user'); // 🔑 important

        if ($barcodes->isEmpty()) {
            return response()->json([
                'message' => 'Barcode belum tersedia',
                'data' => null
            ], 404);
        }

        // Get jadwal
        $jadwal = Jadwal::select(['asesor_id', 'asesor2_id'])
            ->where('jadwal_id', $barcodes->first()->id_jadwal)
            ->first();

        // Resolve asesor → user
        $asesors = AsesorBnsp::select(['id', 'noreg'])
            ->whereIn('id', [$jadwal->asesor_id, $jadwal->asesor2_id])
            ->get()
            ->keyBy('id');

        $asesorUsers = User::whereIn('noreg', $asesors->pluck('noreg'))
            ->get()
            ->keyBy('noreg');

        // 4️⃣ Map results
        $asesor1User = $asesorUsers[$asesors[$jadwal->asesor_id]->noreg] ?? null;
        $asesor2User = $asesorUsers[$asesors[$jadwal->asesor2_id]->noreg] ?? null;

        return response()->json([
            'message' => 'Success',
            'data' => [
                'url_barcode_asesi' =>
                    $barcodes->firstWhere('id_role', 8)?->url_image,

                'url_barcode_asesor1' =>
                    $asesor1User
                        ? ($barcodes[$asesor1User->id]->url_image ?? null)
                        : null,

                'url_barcode_asesor2' =>
                    $asesor2User
                        ? ($barcodes[$asesor2User->id]->url_image ?? null)
                        : null,
            ]
        ]);
    }

    public function ttdApl01(Request $request, $id)
    {
        $request->validate([
            'id_jadwal' => 'required'
        ]);

        $uuid = (string) Uuid::uuid4();

        // 1️⃣ Generate URL TTD (QR destination)
        $urlTtd = "https://barcode.lspgatensi.id/ttd/$uuid";

        // 2️⃣ Generate filename
        $filename = 'ttd_apl01_' . Str::uuid() . '.png';

        // 3️⃣ Generate QR image (PNG binary)
        $qrImage = QrCode::format('png')
            ->size(300)
            ->margin(1)
            ->generate($urlTtd);

        // 4️⃣ FTP path
        $path = "barcodes/{$filename}";

        // 5️⃣ Upload QR image to FTP
        Storage::disk('str_moon')->put($path, $qrImage);

        // 6️⃣ Public image URL
        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        // 7️⃣ Save to DB
        Barcode::updateOrCreate(
            [
                'id_izin'   => $id,
                'id_jadwal' => $request->id_jadwal,
            ],
            [
                'id_user'   => auth()->user()->id,
                'url_image' => $urlImage,
                'url_ttd'   => $urlTtd,
                'id_role'   => auth()->user()->role_id,
                'jenis_dokumen' => 'APL01'
            ]
        );

        return response()->json([
            'message'   => 'Success',
            'data' => [
                'url_ttd'   => $urlTtd,
                'url_image' => $urlImage
            ]
        ]);
    }

    public function getApl02($id)
    {
        $baseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';

        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null
            ], 404);
        }

        $units = UnitKompetensi::with(['subunits.kuks'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->get();

        $submission = Apl2Submission::where('id_izin', $id)->first();

        $answers = Apl2Answer::with('files')
            ->where('id_izin', $id)
            ->get()
            ->keyBy('subunit_id');

        $data = [
            'metode' => $submission?->metode,
            'is_dilanjutkan' => $submission?->is_dilanjutkan,

            'units' => $units->map(function ($unit) use ($answers, $baseUrl) {

                return [
                    'id' => (string) $unit->id,
                    'kode' => $unit->kode,
                    'judul_kompetensi' => $unit->nama,

                    'subunits' => $unit->subunits->map(function ($subunit) use ($answers, $baseUrl) {

                        $answer = $answers->get($subunit->id);

                        return [
                            'id' => (string) $subunit->id,
                            'no_elemen' => $subunit->kode,
                            'judul_elemen' => $subunit->nama,
                            'kompeten' => (bool) ($answer->kompeten ?? false),

                            // 📎 SIMPLIFIED FILES
                            'files' => $answer
                                ? $answer->files->map(fn ($file) => [
                                    'id'   => $file->id,
                                    'name' => $file->original_name,
                                    'path' => $baseUrl . ltrim($file->path, '/'),
                                ])->values()
                                : [],

                            'kuk_list' => $subunit->kuks->map(fn ($kuk) => [
                                'no_kuk' => $kuk->kode,
                                'judul_kuk' => $kuk->nama,
                            ])->values()
                        ];
                    })->values()
                ];
            })->values()
        ];

        return response()->json([
            'message' => 'Success',
            'data' => $data
        ]);
    }

    public function submitApl02(Request $request, $id)
    {
        $validated = $request->validate([
            'metode' => 'required|string',
            'is_dilanjutkan' => 'required|boolean',
            'answers' => 'required|array',

            'answers.*.subunit_id' => 'required|integer',
            'answers.*.kompeten' => 'required|boolean',
            'answers.*.file_ids' => 'nullable|array',
            'answers.*.file_ids.*' => 'exists:apl2_files,id',
        ]);

        DB::transaction(function () use ($validated, $id) {

            Apl2Submission::updateOrCreate(
                ['id_izin' => $id],
                [
                    'metode' => $validated['metode'],
                    'is_dilanjutkan' => $validated['is_dilanjutkan'],
                    'submitted_at' => now(),
                ]
            );

            foreach ($validated['answers'] as $answer) {
                $aplAnswer = Apl2Answer::updateOrCreate(
                    [
                        'id_izin' => $id,
                        'subunit_id' => $answer['subunit_id'],
                    ],
                    [
                        'kompeten' => $answer['kompeten'],
                    ]
                );

                if (isset($answer['file_ids'])) {
                    $aplAnswer->files()->sync($answer['file_ids']);
                }
            }
        });

        return response()->json([
            'message' => 'APL-02 successfully submitted'
        ]);
    }

    public function uploadApl02Files(Request $request, $id)
    {
        $request->validate([
            'files'   => 'required|array',
            'files.*' => [
                'required',
                'file',
                'max:20480', // 20MB
                'mimetypes:'
                    . 'application/pdf,'
                    . 'application/msword,'
                    . 'application/vnd.openxmlformats-officedocument.wordprocessingml.document,'
                    . 'application/vnd.ms-excel,'
                    . 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'
                    . 'application/vnd.ms-powerpoint,'
                    . 'application/vnd.openxmlformats-officedocument.presentationml.presentation,'
                    . 'text/plain,'
                    . 'image/jpeg,'
                    . 'image/png,'
                    . 'image/gif,'
                    . 'image/webp',
            ],
        ]);

        $uploaded = [];

        foreach ($request->file('files') as $file) {

            // 🔐 Optional: avoid name collision on FTP
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

            // 📁 FTP path
            $path = "apl02/{$id}/{$filename}";

            // 🚀 Upload to FTP
            Storage::disk('str_moon')->put(
                $path,
                file_get_contents($file->getRealPath())
            );

            // 💾 Save DB record
            $uploaded[] = Apl2File::create([
                'id_izin'        => $id,
                'original_name'  => $file->getClientOriginalName(),
                'path'           => $path,          // FTP path
                'mime'           => $file->getMimeType(),
                'size'           => $file->getSize(),
            ]);
        }

        return response()->json([
            'message' => 'Files uploaded',
            'files'   => $uploaded
        ]);
    }

    public function getApl02Files($id)
    {
        $baseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';

        $files = Apl2File::where('id_izin', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($file) => [
                'id'   => $file->id,
                'name' => $file->original_name,
                'path' => $baseUrl . ltrim($file->path, '/'),
            ]);

        return response()->json([
            'message' => 'Success',
            'data'    => $files
        ]);
    }

    public function deleteApl02File($fileId)
    {
        $file = Apl2File::findOrFail($fileId);

        DB::transaction(function () use ($file) {

            // 🧹 Remove pivot relations (apl2_answer_files)
            $file->answers()->detach();

            // 🗑 Delete file from FTP
            Storage::disk('str_moon')->delete($file->path);

            // 🗑 Delete DB record
            $file->delete();
        });

        return response()->json([
            'message' => 'File deleted successfully'
        ]);
    }

    public function getMapa01($id)
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

        $isPengalaman = Proyek::where('id_izin', $id)->exists();

        $dokumen = JabatanDokumen::with([
                'kelompoks.units.unit'
            ])
            ->select(['id', 'kode', 'nama_dokumen'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'MAPA01')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return response()->json([
                'message' => 'Success',
                'data' => []
            ]);
        }

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

        $referensis = Referensi::with([
            'kategoriRel',
            'kelompokRel',
            'subkategoriRel'
        ])
        ->select(['id', 'nama', 'id_kelompok', 'id_kategori', 'id_subkategori'])
        ->where('kategori', 'MAPA01')
        ->whereNotNull('id_kelompok')
        ->get()
        ->filter(fn ($r) => $r->kelompokRel !== null);

        $dataReferensi = $referensis
        ->groupBy('id_kelompok')
        ->values()
        ->map(function ($kelompokItems) use ($isPengalaman) {

            $kelompok = $kelompokItems->first()->kelompokRel;

            return [
                'kelompok' => [
                    'id'   => $kelompok->id,
                    'nama' => $kelompok->nama,
                    'urut' => $kelompok->urut,

                    'kategoris' => $kelompokItems
                        ->groupBy(fn ($item) => $item->id_kategori ?? 'tanpa_kategori')
                        ->values()
                        ->map(function ($kategoriItems) use ($isPengalaman, $kelompok) {
                            // ✅ pass $kelompok

                            $firstKategori = $kategoriItems->first();
                            $kategori = $firstKategori->kategoriRel;

                            return [
                                'id'          => $kategori?->id,
                                'kategori'    => $kategori?->kategori,
                                'nama'        => $kategori?->nama,
                                'urut'        => $kategori?->urut,
                                'id_kelompok' => $kategori?->id_kelompok,

                                'subkategoris' => $kategoriItems
                                    ->groupBy(fn ($item) => $item->id_subkategori ?? 'tanpa_subkategori')
                                    ->values()
                                    ->map(function ($subItems) use ($isPengalaman, $kelompok, $kategori) {
                                        // ✅ pass BOTH

                                        $firstSub = $subItems->first();
                                        $sub = $firstSub->subkategoriRel;

                                        return [
                                            'id'   => $sub?->id,
                                            'nama' => $sub?->nama ?? 'Tanpa Subkategori',
                                            'urut' => $sub?->urut,

                                            'referensis' => $subItems->map(function ($item) use ($isPengalaman, $kelompok, $kategori) {

                                                $checked = false;

                                                if ($kelompok->id == 1) {
                                                    // (Asesi)
                                                    if ($kategori?->id == 1) {
                                                        if ($isPengalaman) {
                                                            $checked = $item->id == 84;
                                                        } else {
                                                            $checked = $item->id == 82;
                                                        }
                                                    }

                                                    // Tujuan Asesmen
                                                    elseif ($kategori?->id == 2) {
                                                        $checked = $item->id == 87;
                                                    }
                                                    
                                                    // Konteks Asesmen
                                                    elseif ($kategori?->id == 3) {
                                                        $checked = in_array($item->id, [92, 94, 95, 98], true);
                                                    }

                                                    // Orang yang relevan
                                                    elseif ($kategori?->id == 4) {
                                                        $checked = $item->id == 101;
                                                    }
                                                }

                                                if ($kelompok->id == 2) {
                                                    // Tolak ukur asesmen
                                                    if ($kategori?->id == 5) {
                                                        $checked = $item->id == 105;
                                                    }
                                                }

                                                if ($kelompok->id == 2) {
                                                    // Tolak ukur asesmen
                                                    if ($kategori?->id == 5) {
                                                        $checked = $item->id == 105;
                                                    }
                                                }

                                                return [
                                                    'id'      => $item->id,
                                                    'nama'    => $item->nama,
                                                    'value' => $checked,
                                                ];
                                            })->values(),
                                        ];
                                    })->values(),
                            ];
                        })->values(),
                ]
            ];
        });

        return response()->json([
            'message' => 'Success',
            'data' => [
                'kelompok_kerja' => $kelompokKerja,
                'referensi_form' => $dataReferensi,
            ]
        ]);
    }

    public function submitMapa01(Request $request, $id)
    {
        
    }

    public function getMapa02($id)
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

        $dokumen = JabatanDokumen::with([
                'kelompoks.units.unit'
            ])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'MAPA02')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return response()->json([
                'message' => 'Success',
                'data' => []
            ]);
        }

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

        $referensis = Referensi::select(['id', 'nama', 'kategori', 'isdefault'])
            ->whereIn('kategori', ['MAPA02-1', 'MAPA02_1'])
            ->get();

        $dataReferensi = $referensis
            ->groupBy('kategori')
            ->map(function ($items, $kategori) {
                return [
                    'kategori'   => $kategori,
                    'referensis' => $items->map(fn ($item) => [
                        'id'         => $item->id,
                        'nama'       => $item->nama,
                        'isdefault' => $item->isdefault,
                    ])->values(),
                ];
            })
            ->values();

        return response()->json([
            'message' => 'Success',
            'data' => [
                'kelompok_kerja' => $kelompokKerja,
                'referensi_form' => $dataReferensi,
            ]
        ]);
    }

    public function submitMapa02(Request $request, $id)
    {

    }

    public function getAk07($id)
    {
        $referensis = Referensi::with([
                'kategoriRel',
                'kelompokRel',
                'ak07Answer' => fn ($q) => $q->where('id_izin', $id)
            ])
            ->select(['id', 'nama', 'id_kelompok', 'id_kategori'])
            ->where('kategori', 'AK07')
            ->whereNotNull('id_kelompok')
            ->get()
            ->filter(fn ($r) => $r->kelompokRel !== null);

        $data = $referensis
            ->groupBy('id_kelompok')
            ->values()
            ->map(function ($kelompokItems) {

                $kelompok = $kelompokItems->first()->kelompokRel;

                return [
                    'kelompok' => [
                        'id'   => $kelompok->id,
                        'nama' => $kelompok->nama,
                        'urut' => $kelompok->urut,

                        'kategoris' => $kelompokItems
                            ->groupBy('id_kategori')
                            ->values()
                            ->map(function ($kategoriItems) {

                                $kategori = $kategoriItems->first()->kategoriRel;

                                return [
                                    'id'          => $kategori?->id,
                                    'kategori'    => $kategori?->kategori,
                                    'nama'        => $kategori?->nama,
                                    'urut'        => $kategori?->urut,
                                    'id_kelompok' => $kategori?->id_kelompok,

                                    'referensis' => $kategoriItems->map(fn ($item) => [
                                        'id'   => $item->id,
                                        'nama' => $item->nama,
                                        'jawaban' => $item->ak07Answer
                                            ? ($item->ak07Answer->answer_text
                                                ?? $item->ak07Answer->answer_bool)
                                            : null,
                                    ])->values(),
                                ];
                            })->values(),
                    ]
                ];
            });

        return response()->json([
            'message' => 'Success',
            'data'    => $data
        ]);
    }

    public function submitAk07(Request $request, $id)
    {
        $request->validate([
            'answers' => 'required|array',
            'answers.*.referensi_id' => 'required|integer|exists:referensis,id',
            'answers.*.kelompok_id' => 'required|integer|exists:referensi_kelompoks,id',
            'answers.*.value' => 'required'
        ]);

        DB::transaction(function () use ($request, $id) {

            foreach ($request->answers as $item) {

                $isTextKelompok = in_array($item['kelompok_id'], [6, 7]);

                Ak7Answer::updateOrCreate(
                    [
                        'id_izin' => $id,
                        'referensi_id' => $item['referensi_id'],
                    ],
                    [
                        'kelompok_id' => $item['kelompok_id'],
                        'answer_bool' => $isTextKelompok ? null : (bool) $item['value'],
                        'answer_text' => $isTextKelompok ? (string) $item['value'] : null,
                    ]
                );
            }

        });

        return response()->json([
            'message' => 'AK07 successfully submitted'
        ]);
    }

    public function getAk04($id)
    {
        // --- Fetch referensi + answers ---
        $referensis = Referensi::with([
                'kelompokRel',
                'ak04Answer' => fn ($q) => $q->where('id_izin', $id)
            ])
            ->select(['id', 'nama', 'id_kelompok'])
            ->where('kategori', 'AK04')
            ->whereNotNull('id_kelompok')
            ->get()
            ->filter(fn ($r) => $r->kelompokRel !== null);

        $kelompokData = $referensis
            ->groupBy('id_kelompok')
            ->values()
            ->map(function ($kelompokItems) {

                $kelompok = $kelompokItems->first()->kelompokRel;

                return [
                    'id'   => $kelompok->id,
                    'nama' => $kelompok->nama,
                    'urut' => $kelompok->urut,
                    'referensis' => $kelompokItems->map(fn ($item) => [
                        'id'      => $item->id,
                        'nama'    => $item->nama,
                        'jawaban' => is_null($item->ak04Answer?->jawaban)
                            ? null
                            : (bool) $item->ak04Answer->jawaban,
                    ])->values(),
                ];
            });

        // --- Fetch AK04 reason ---
        $reason = Ak4Reason::where('id_izin', $id)->value('alasan');

        return response()->json([
            'message' => 'Success',
            'data'    => [
                'kelompoks' => $kelompokData,
                'alasan'    => $reason
            ]
        ]);
    }

    public function submitAk04(Request $request, $id)
    {
        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.referensi_id' => 'required|integer|exists:referensis,id',
            'answers.*.kelompok_id'  => 'required|integer',
            'answers.*.jawaban'     => 'required|boolean',
            'alasan'                => 'nullable|string'
        ]);

        DB::transaction(function () use ($validated, $request, $id) {

            foreach ($validated['answers'] as $answer) {
                Ak4Answer::updateOrCreate(
                    [
                        'id_izin'      => $id,
                        'referensi_id' => $answer['referensi_id'],
                    ],
                    [
                        'kelompok_id' => $answer['kelompok_id'],
                        'jawaban'     => $answer['jawaban'],
                    ]
                );
            }

            // Save alasan only if provided
            if (!empty($validated['alasan'])) {
                Ak4Reason::updateOrCreate(
                    ['id_izin' => $id],
                    ['alasan'  => $validated['alasan']]
                );
            }
        });

        return response()->json([
            'message' => 'AK04 successfully submitted'
        ]);
    }

    public function getK3Asesmen()
    {
        return response()->json([
            'message' => 'Success',
            'data'    => [
                'file' => env('APP_URL') . '/files/k3_asesmen.pdf',
            ]
        ]);
    }

    public function getAk01($id)
    {
        $ak01 = Referensi::where('kategori', 'AK01_1')
            ->leftJoin('ak1_answers as a', function ($join) use ($id) {
                $join->on('a.id_referensi', '=', 'referensis.id')
                    ->where('a.id_izin', '=', $id);
            })
            ->orderBy('referensis.id')
            ->get([
                'referensis.id',
                'referensis.nama',
                DB::raw('IFNULL(a.jawaban, 0) as jawaban')
            ])
            ->map(function ($row) {
                $row->jawaban = (bool) $row->jawaban;
                return $row;
            });

        return response()->json([
            'message' => 'Success',
            'data'    => $ak01
        ]);
    }

    public function submitAk01(Request $request, $id)
    {
        $validated = $request->validate([
            'answers' => 'required|array|min:1',
            'answers.*.id_referensi' => 'required|exists:referensis,id',
            'answers.*.jawaban' => 'required|boolean',
        ]);

        DB::transaction(function () use ($validated, $id) {

            foreach ($validated['answers'] as $answer) {
                Ak1Answer::updateOrCreate(
                    [
                        'id_izin'      => $id,
                        'id_referensi' => $answer['id_referensi'],
                    ],
                    [
                        'jawaban' => $answer['jawaban'],
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'AK01 successfully submitted'
        ]);
    }
}
