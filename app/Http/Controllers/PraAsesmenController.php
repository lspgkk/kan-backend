<?php

namespace App\Http\Controllers;

use App\Models\Ak1Answer;
use App\Models\Ak2Answer;
use App\Models\Ak2Feedback;
use App\Models\Ak3Answer;
use App\Models\Ak3Note;
use App\Models\Ak4Answer;
use App\Models\Ak4Reason;
use App\Models\Ak5Answer;
use App\Models\Ak6Answer;
use App\Models\Ak6Feedback;
use App\Models\Ak7Answer;
use App\Models\Apl1Answer;
use App\Models\Apl2Answer;
use App\Models\Apl2File;
use App\Models\Apl2Submission;
use App\Models\AsesiJadwal;
use App\Events\Apl02Updated;
use App\Events\Apl02AsesorUpdated;
use App\Models\AsesorBnsp;
use App\Models\Barcode;
use App\Models\BuktiAsesmen;
use App\Models\Ia1Answer;
use App\Models\Ia1Feedback;
use App\Models\Ia1Recommendation;
use App\Models\Ia3Answer;
use App\Models\Ia3Feedback;
use App\Models\Ia4aAnswer;
use App\Models\Ia4bAnswer;
use App\Models\Ia5Answer;
use App\Models\Ia5Feedback;
use App\Models\ImpBuktiAdministrasi;
use App\Models\ImpBuktiPersyaratan;
use App\Models\JabatanConfigA;
use App\Models\JabatanDokumen;
use App\Models\JabatanKerja;
use App\Models\Jadwal;
use App\Models\JenjangJabatanKerja;
use App\Models\Jenjang;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PraAsesmenController extends Controller
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
                ["url_absen_asesi_pra_{$type}" => $imageUrl]
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
                ["url_absen_{$roleAsesor}_pra_{$type}" => $imageUrl]
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
        $jenisKelasId = Personal::where('id_izin', $id)->value('jenis_kelas_id');
        $jadwalId = AsesiJadwal::where('id_izin', $id)->value('jadwal_id');
        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])
            ->where('id_izin', $id)
            ->first();
        $jabatanKerja = JabatanKerja::where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)->value('jabatan_kerja');
        $jabatanConfig = JabatanConfigA::select(['no_skema'])->where('id_jabatan', $klasifikasi->jabatan_kerja)->first();

        $penyusun_validator = JabatanConfigA::with([
            'penyusun:id,name,noreg',
            'validator:id,name,noreg',
            'manajer:id,name,noreg',
        ])->select(['penyusun_id', 'penyusun_tgl', 'penyusun_img', 'penyusun_url', 'validator_id', 'validator_tgl', 'validator_img', 'validator_url', 'manajer_id', 'manajer_img', 'manajer_url'])->where('id_jabatan', $klasifikasi->jabatan_kerja)->first();

        $apl2Submission = \App\Models\Apl2Submission::where('id_izin', $id)->first();

        $namaManajer = User::where('role_id', 7)->where('is_deleted', false)->first()?->name;

        // Jadwal-dependent fields — may be null when jadwal hasn't been created yet
        if ($jadwalId) {
            $jadwal = Jadwal::select([
                'jadwal_id',
                'tuk_id',
                'asesor_id',
                'asesor2_id',
                'tanggal_uji',
                'tanggal_selesai',
                'jenis_kelas',
                'tahap',
                'pengajuan_tuk_ref',
            ])->with([
                'tuk:id,nama',
                'asesor:id,nama,noreg',
                'asesor2:id,nama,noreg',
            ])->where('jadwal_id', $jadwalId)->first();

            $idAsesor1 = $jadwal ? User::where('noreg', $jadwal->asesor?->noreg)->value('id') : null;
            $idAsesor2 = $jadwal ? User::where('noreg', $jadwal->asesor2?->noreg)->value('id') : null;
        } else {
            $jadwal = null;
            $idAsesor1 = null;
            $idAsesor2 = null;
        }

        return response()->json([
            'message' => 'Success',
            'data' => [
                'jabatan_kerja' => $jabatanKerja,
                'nomor_skema' => $jabatanConfig->no_skema,
                'tuk' => $jadwal?->resolveNamaTuk($jenisKelasId),
                'id_asesor_1' => $idAsesor1,
                'id_asesor_2' => $idAsesor2,
                'nama_asesi' => $namaAsesi,
                'jenjang' => $klasifikasi->jenjang,
                'asesor_1' => $jadwal?->asesor?->nama,
                'asesor_2' => $jadwal?->asesor2?->nama,
                'noreg_asesor_1' => $jadwal?->asesor?->noreg,
                'noreg_asesor_2' => $jadwal?->asesor2?->noreg,
                'tanggal_uji' => $jadwal?->tanggal_uji,
                'tanggal_selesai' => $jadwal?->tanggal_selesai,
                'jenis_kelas' => $jadwal?->jenis_kelas,
                'nama_penyusun' => $penyusun_validator->penyusun?->name,
                'nama_validator' => $penyusun_validator->validator?->name,
                'tanggal_penyusun' => $penyusun_validator->penyusun_tgl,
                'tanggal_validator' => $penyusun_validator->validator_tgl,
                'barcode_penyusun' => $penyusun_validator->penyusun_img,
                'barcode_validator' => $penyusun_validator->validator_img,
                'noreg_penyusun' => $penyusun_validator->penyusun?->noreg,
                'noreg_validator' => $penyusun_validator->validator?->noreg,
                'metode' => $apl2Submission?->metode,
                'tahap' => $jadwal?->tahap,
                'jadwal_id' => $jadwalId,
                'nama_manajer' => $namaManajer,
                'tanggal_manajer' => $penyusun_validator->penyusun_tgl,
                'barcode_manajer' => $penyusun_validator->manajer_img,
            ]
        ]);
    }

    private function buildDokumenHeader($id): ?array
    {
        $namaAsesi = Personal::where('id_izin', $id)->value('nama');
        $jenisKelasId = Personal::where('id_izin', $id)->value('jenis_kelas_id');
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
                'pengajuan_tuk_ref',
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
            'manajer:id,name,noreg',
        ])->select(['penyusun_id', 'penyusun_tgl', 'penyusun_img', 'penyusun_url', 'validator_id', 'validator_tgl', 'validator_img', 'validator_url', 'manajer_id', 'manajer_img', 'manajer_url'])->where('id_jabatan', $klasifikasi->jabatan_kerja)->first();

        $apl2Submission = Apl2Submission::where('id_izin', $id)->first();

        return [
            'jabatan_kerja'   => $jabatanKerja,
            'nomor_skema'     => $jabatanConfig?->no_skema,
            'tuk'             => $jadwal->resolveNamaTuk($jenisKelasId),

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
            'nama_manajer' => $penyusun_validator->manajer?->name,
            'tanggal_penyusun' => $penyusun_validator->penyusun_tgl,
            'tanggal_validator' => $penyusun_validator->validator_tgl,
            'tanggal_manajer' => $penyusun_validator->penyusun_tgl,
            'barcode_penyusun' => $this->imageUrlToBase64($penyusun_validator->penyusun_img),
            'barcode_validator' => $this->imageUrlToBase64($penyusun_validator->validator_img),
            'barcode_manajer' => $this->imageUrlToBase64($penyusun_validator->manajer_img),
            'noreg_penyusun' => $penyusun_validator->penyusun?->noreg,
            'noreg_validator' => $penyusun_validator->validator?->noreg,
            'metode' => $apl2Submission?->metode,
            'jenjang' => $klasifikasi->jenjang,
            'waktu'   => $jadwal->tanggal_uji
                ? \Carbon\Carbon::parse($jadwal->tanggal_uji)->locale('id')->translatedFormat('H:i')
                : null,
        ];
    }

    public function getApl01($id)
    {
        $personal = Personal::select(['nama', 'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'kodepos', 'telepon', 'email', 'pas_foto', 'ktp'])->where('id_izin', $id)->first();
        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])->where('id_izin', $id)->first();
        if (!$klasifikasi) {
            return response()->json([
                'message' => 'Klasifikasi tidak ditemukan',
                'data' => null
            ], 404);
        }
        $jabatanKerja = JabatanKerja::select(['jabatan_kerja', 'acuan'])->where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)->first();
        $pekerjaan = Proyek::where('id_izin', $id)->latest('created_at')->first();
        $apl1Answer = Apl1Answer::where('id_izin', $id)->first();
        $kualifikasi = null;
        $pendidikan = Pendidikan::where('id_izin', $id)->orderByDesc('tahun_lulus')->first();
        if ($pendidikan?->jenjang) {
            $kualifikasi = Jenjang::where('id_siki', (string) $pendidikan->jenjang)->value('nama')
                ?? Jenjang::where('id_bnsp', (string) $pendidikan->jenjang)->value('nama');
        }
        if (!$kualifikasi) {
            $kualifikasi = JenjangJabatanKerja::where('jenjang_id', $klasifikasi->jenjang)
                ->where('jabatan_kerja', 'not like', '%fresh%')
                ->orderBy('id')->value('jabatan_kerja')
                ?? JenjangJabatanKerja::where('jenjang_id', $klasifikasi->jenjang)->orderBy('id')->value('jabatan_kerja');
        }
        $unitKompetensi = UnitKompetensi::select(['kode', 'nama'])->where('id_jabatan', $klasifikasi->jabatan_kerja)->get();
        $jenjang = Pendidikan::where('id_izin', $id)->orderBy('created_at', 'desc')->value('jenjang');

        // Get latest pendidikan data for ijazah URL
        $pendidikanTerakhir = Pendidikan::where('id_izin', $id)->orderBy('created_at', 'desc')->first();

        // Map jenjang to education level keywords
        $educationMapping = [
            'SD' => ['1', '2'],
            'SMP' => ['2'],
            'SMA' => ['3'],
            'D1' => ['4', '16'],
            'D2' => ['5'],
            'D3' => ['6'],
            'D4' => ['7', '12'],
            'S1' => ['8', '13'],
            'S2' => ['9', '14', '18'],
            'S3' => ['10', '15', '19'],
            'profesi' => ['11'],
            'Non' => ['99']
        ];

        // Education hierarchy from lowest to highest (for fallback matching)
        $educationOrder = ['Non', 'SD', 'SMP', 'SMA', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3', 'profesi'];

        // Load persyaratan records
        $persyaratanRecords = ImpBuktiPersyaratan::select(['no', 'bukti'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->orderBy('no', 'asc')
            ->get();

        // Get all persyaratan bukti text to detect which education levels are mentioned
        $allPersyaratanText = $persyaratanRecords->pluck('bukti')->implode(' ');

        // Determine the user's education level name from jenjang
        $userEducationLevel = null;
        foreach ($educationMapping as $level => $jenjangs) {
            if (in_array($jenjang, $jenjangs)) {
                $userEducationLevel = $level;
                break;
            }
        }

        // Build matched levels: try exact match first, then fall back to the nearest lower level
        $matchedEducationLevels = [];
        if ($userEducationLevel) {
            if (stripos($allPersyaratanText, $userEducationLevel) !== false) {
                // Exact level exists in persyaratan — use it
                $matchedEducationLevels = [$userEducationLevel, ucfirst($userEducationLevel)];
            } else {
                // Fall back to the highest nearest lower level that exists
                $userIndex = array_search($userEducationLevel, $educationOrder);
                if ($userIndex !== false) {
                    for ($i = $userIndex - 1; $i >= 0; $i--) {
                        $lowerLevel = $educationOrder[$i];
                        if (stripos($allPersyaratanText, $lowerLevel) !== false) {
                            $matchedEducationLevels = [$lowerLevel, ucfirst($lowerLevel)];
                            break;
                        }
                    }
                }
            }
        }

        $persyaratan = $persyaratanRecords->map(function ($item) use ($matchedEducationLevels, $pendidikanTerakhir) {
                $checked = false;
                foreach ($matchedEducationLevels as $level) {
                    if (stripos($item->bukti, $level) !== false) {
                        $checked = true;
                        break;
                    }
                }

                // Add ijazah URL if checked and bukti contains 'ijazah'
                $ijazahUrl = null;
                if ($checked && $pendidikanTerakhir && stripos($item->bukti, 'ijazah') !== false) {
                    $ijazahUrl = $pendidikanTerakhir->scan_ijazah_legalisir;
                }

                return [
                    'no' => $item->no,
                    'bukti' => $item->bukti,
                    'checked' => $checked,
                    'ijazah_url' => $ijazahUrl
                ];
            });
        $administratif = ImpBuktiAdministrasi::select(['no', 'bukti'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->orderBy('no', 'asc')
            ->get();
        $barcodeByRole = Barcode::with('user:id,name')
            ->where('id_izin', $id)
            ->where('jenis_dokumen', 'APL01')
            ->get()
            ->keyBy('id_role');

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
                // Add foto_url for "pas foto" and ktp_url for "ktp"
                $fotoUrl = null;
                $ktpUrl = null;

                if ($personal && stripos($text, 'pas foto') !== false) {
                    $fotoUrl = $personal->pas_foto;
                } elseif ($personal && stripos($text, 'ktp') !== false) {
                    $ktpUrl = $personal->ktp;
                }

                $formattedAdministratif->push([
                    'no'    => (string) $no++,
                    'bukti' => $text,
                    'checked' => true,
                    'foto_url' => $fotoUrl,
                    'ktp_url' => $ktpUrl
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
                    'perusahaan' => $pekerjaan
                        ? $pekerjaan->pemberi_kerja
                        : ($apl1Answer ? $apl1Answer->perusahaan : null),

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
                'is_diterima' => $apl1Answer?->is_diterima,
                'skkni' => $jabatanKerja->acuan,
                'data_unit_kompetensi' => $unitKompetensi,
                'bukti_persyaratan' => $persyaratan,
                'bukti_administratif' => $formattedAdministratif->values(),
                'catatan' => null,
                'barcodes' => [
                    'asesi' => [
                        'url' => optional($barcodeByRole->get(8))->url_image,
                        'tanggal' => optional($barcodeByRole->get(8))->created_at,
                        'nama' => optional(optional($barcodeByRole->get(8))->user)->name,
                    ],
                    'admin' => [
                        'url' => optional($barcodeByRole->get(2))->url_image,
                        'tanggal' => optional($barcodeByRole->get(2))->created_at,
                        'nama' => optional(optional($barcodeByRole->get(2))->user)->name,
                    ],
                ]
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
            'is_diterima'     => 'nullable|boolean',
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

    public function ttdApl01(Request $request, $id)
    {
        $data = $this->generateTtdBarcodeWithoutJadwal(
            'APL01',
            'ttd_apl01',
            $id
        );

        $personal = Personal::select([
            'nama', 'nik', 'tempat_lahir', 'tanggal_lahir',
            'jenis_kelamin', 'alamat', 'kodepos', 'telepon', 'email', 'pas_foto', 'ktp'
        ])->where('id_izin', $id)->firstOrFail();

        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            abort(404, 'Klasifikasi tidak ditemukan');
        }

        $jabatanKerja = JabatanKerja::select(['jabatan_kerja', 'acuan'])
            ->where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)
            ->firstOrFail();

        $pekerjaan   = Proyek::where('id_izin', $id)->latest('created_at')->first();
        $apl1Answer  = Apl1Answer::where('id_izin', $id)->first();
        $kualifikasi = null;
        $pendidikan = Pendidikan::where('id_izin', $id)->orderByDesc('tahun_lulus')->first();
        if ($pendidikan?->jenjang) {
            $kualifikasi = Jenjang::where('id_siki', (string) $pendidikan->jenjang)->value('nama')
                ?? Jenjang::where('id_bnsp', (string) $pendidikan->jenjang)->value('nama');
        }
        if (!$kualifikasi) {
            $kualifikasi = JenjangJabatanKerja::where('jenjang_id', $klasifikasi->jenjang)
                ->where('jabatan_kerja', 'not like', '%fresh%')
                ->orderBy('id')->value('jabatan_kerja')
                ?? JenjangJabatanKerja::where('jenjang_id', $klasifikasi->jenjang)->orderBy('id')->value('jabatan_kerja');
        }

        $unitKompetensi = UnitKompetensi::select(['kode', 'nama'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->get();

        $jenjang = Pendidikan::where('id_izin', $id)
            ->latest('created_at')
            ->value('jenjang');

        // Get latest pendidikan data for ijazah URL
        $pendidikanTerakhir = Pendidikan::where('id_izin', $id)
            ->latest('created_at')
            ->first();

        /* =========================
        * BUKTI
        * ========================= */

        // Map jenjang to education level keywords
        $educationMapping = [
            'SD' => ['1', '2'],
            'SMP' => ['2'],
            'SMA' => ['3'],
            'D1' => ['4', '16'],
            'D2' => ['5'],
            'D3' => ['6'],
            'D4' => ['7', '12'],
            'S1' => ['8', '13'],
            'S2' => ['9', '14', '18'],
            'S3' => ['10', '15', '19'],
            'profesi' => ['11'],
            'Non' => ['99']
        ];

        // Education hierarchy from lowest to highest (for fallback matching)
        $educationOrder = ['Non', 'SD', 'SMP', 'SMA', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3', 'profesi'];

        // Load persyaratan records
        $persyaratanRecords = ImpBuktiPersyaratan::select(['no', 'bukti'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->whereNotLike('bukti', 'Syarat Administrasi%')
            ->whereNotLike('bukti', 'Bukti Keanggotaan%')
            ->orderBy('no')
            ->get();

        // Get all persyaratan bukti text to detect which education levels are mentioned
        $allPersyaratanText = $persyaratanRecords->pluck('bukti')->implode(' ');

        // Determine the user's education level name from jenjang
        $userEducationLevel = null;
        foreach ($educationMapping as $level => $jenjangs) {
            if (in_array($jenjang, $jenjangs)) {
                $userEducationLevel = $level;
                break;
            }
        }

        // Build matched levels: try exact match first, then fall back to the nearest lower level
        $matchedEducationLevels = [];
        if ($userEducationLevel) {
            if (stripos($allPersyaratanText, $userEducationLevel) !== false) {
                // Exact level exists in persyaratan — use it
                $matchedEducationLevels = [$userEducationLevel, ucfirst($userEducationLevel)];
            } else {
                // Fall back to the highest nearest lower level that exists
                $userIndex = array_search($userEducationLevel, $educationOrder);
                if ($userIndex !== false) {
                    for ($i = $userIndex - 1; $i >= 0; $i--) {
                        $lowerLevel = $educationOrder[$i];
                        if (stripos($allPersyaratanText, $lowerLevel) !== false) {
                            $matchedEducationLevels = [$lowerLevel, ucfirst($lowerLevel)];
                            break;
                        }
                    }
                }
            }
        }

        $persyaratan = $persyaratanRecords->map(function ($item) use ($matchedEducationLevels, $pendidikanTerakhir) {
                $checked = false;
                foreach ($matchedEducationLevels as $level) {
                    if (stripos($item->bukti, $level) !== false) {
                        $checked = true;
                        break;
                    }
                }

                // Add ijazah URL if checked and bukti contains 'ijazah'
                $ijazahUrl = null;
                if ($checked && $pendidikanTerakhir && stripos($item->bukti, 'ijazah') !== false) {
                    $ijazahUrl = $pendidikanTerakhir->scan_ijazah_legalisir;
                }

                return [
                    'no' => $item->no,
                    'bukti' => $item->bukti,
                    'checked' => $checked,
                    'ijazah_url' => $ijazahUrl
                ];
            });

        $administratif = ImpBuktiAdministrasi::select(['no', 'bukti'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where(function ($q) {
                $q->whereLike('bukti', 'Syarat Administrasi%')
                ->orWhereLike('bukti', 'Bukti Keanggotaan%');
            })
            ->orderBy('no')
            ->get();

        /* =========================
        * FORMAT ADMINISTRATIF
        * ========================= */

        $formattedAdministratif = collect();
        $no = 1;

        foreach ($administratif as $item) {

            $clean = preg_replace(
                '/^(Syarat Administrasi|Bukti Keanggotaan)\s*:\s*/i',
                '',
                $item->bukti
            );

            $parts = array_map('trim', explode(',', $clean));

            $foto = $ktp = $other = [];

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

            foreach (array_merge($foto, $ktp, $other) as $text) {
                // Add foto_url for "pas foto" and ktp_url for "ktp"
                $fotoUrl = null;
                $ktpUrl = null;

                if ($personal && stripos($text, 'pas foto') !== false) {
                    $fotoUrl = $personal->pas_foto;
                } elseif ($personal && stripos($text, 'ktp') !== false) {
                    $ktpUrl = $personal->ktp;
                }

                $formattedAdministratif->push([
                    'no'      => $no++,
                    'bukti'   => $text,
                    'checked' => true,
                    'foto_url' => $fotoUrl,
                    'ktp_url' => $ktpUrl
                ]);
            }
        }

        /* =========================
        * BARCODE
        * ========================= */

        $barcodeByRole = Barcode::with('user:id,name')
        ->where('id_izin', $id)
        ->where('jenis_dokumen', 'APL01')
        ->get()
        ->keyBy('id_role')   // 👈 key FIRST
        ->map(function ($barcode) {
            return [
                'user'    => $barcode->user,
                'tanggal' => $barcode->created_at,
                'base64'  => $this->imageUrlToBase64($barcode->url_image), // URL → Base64
            ];
        });

        $nomorSkema = JabatanConfigA::where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->value('no_skema');

        /* =========================
        * DATA FOR BLADE
        * ========================= */

        $dataPdf = [
            'data_pribadi' => [
                'nama'           => $personal->nama,
                'nik'            => $personal->nik,
                'tempat_lahir'   => $personal->tempat_lahir,
                'tanggal_lahir'  => $personal->tanggal_lahir,
                'jenis_kelamin'  => $personal->jenis_kelamin,
                'kebangsaan'     => 'ID',
                'alamat'         => $personal->alamat,
                'telepon_hp'     => $personal->telepon,
                'kode_pos'       => $personal->kodepos,
                'email'          => $personal->email,
                'kualifikasi'    => $kualifikasi,
            ],

            'data_pekerjaan' => [
                'perusahaan' => $pekerjaan?->pemberi_kerja ?? $apl1Answer?->perusahaan,
                'jabatan'    => $apl1Answer?->jabatan ?? $pekerjaan?->jabatan,
                'alamat'     => $apl1Answer?->alamat_kantor ?? $pekerjaan?->alamat,
                'kode_pos'   => $apl1Answer?->kode_pos ?? $pekerjaan?->kode_pos,
                'telepon'    => $apl1Answer?->telepon_kantor ?? $pekerjaan?->telepon,
                'fax'        => $apl1Answer?->fax ?? $pekerjaan?->fax,
                'email'      => $apl1Answer?->email_kantor ?? $pekerjaan?->email,
            ],

            'sertifikasi' => [
                'judul' => $jabatanKerja->jabatan_kerja,
                'nomor' => $nomorSkema,
                'skkni' => $jabatanKerja->acuan,
            ],

            'unit_kompetensi'     => $unitKompetensi,
            'bukti_persyaratan'   => $persyaratan,
            'bukti_administratif' => $formattedAdministratif,

            'barcodes' => [
                'asesi' => $barcodeByRole[8] ?? null,
                'admin' => $barcodeByRole[2] ?? null,
            ],
        ];

        /* =========================
        * GENERATE PDF
        * ========================= */

        $pdf = Pdf::loadView('pdf.apl01', $dataPdf)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/apl01';

        // unique + traceable filename
        $filename = 'FR-APL-01-' . $id . '.pdf';
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
            ['url_apl_01' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    private function buildApl02Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        $baseUrl = 'https://moon.lspgatensi.id/files/new_gatensi/';

        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return null;
        }

        $units = UnitKompetensi::with(['subunits.kuks'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->get();

        $submission = Apl2Submission::where('id_izin', $id)->first();

        $answers = Apl2Answer::with('files')
            ->where('id_izin', $id)
            ->get()
            ->keyBy('subunit_id');

        /** ✅ Shared barcode resolver */
        $barcodes = $this->resolveDocumentBarcodes($id, 'APL02', $forPdf);

        return [
            'id_izin' => $id,
            'jabatan_kerja' => $klasifikasi->jabatan_kerja,
            'jenjang' => $klasifikasi->jenjang,

            'metode' => $submission?->metode,
            'is_dilanjutkan' => $submission?->is_dilanjutkan,

            'units' => $units->map(function ($unit) use ($answers, $baseUrl, $barcodes) {
                $subunits = $unit->subunits->filter(function ($subunit) {
                    return $subunit->kuks->isNotEmpty();
                })->map(function ($subunit) use (
                    $answers,
                    $baseUrl,
                    $barcodes
                ) {
                    $answer = $answers->get($subunit->id);

                    return [
                        'id' => (string) $subunit->id,
                        'no_elemen' => $subunit->kode,
                        'judul_elemen' => $subunit->nama,
                        'kompeten' => (bool) ($answer->kompeten ?? false),

                        'files' => $answer
                            ? $answer->files->map(fn ($file) => [
                                'id'   => $file->id,
                                'name' => $file->original_name,
                                'path' => $file->path && (str_starts_with($file->path, 'http://') || str_starts_with($file->path, 'https://'))
                                    ? $file->path
                                    : $baseUrl . ltrim($file->path, '/'),
                                'filetype' => $file->filetype,
                            ])->values()
                            : [],

                        'kuk_list' => $subunit->kuks->map(fn ($kuk) => [
                            'no_kuk' => $kuk->kode,
                            'judul_kuk' => $kuk->nama,
                        ])->values(),
                    ];
                })->values();

                return [
                    'id' => (string) $unit->id,
                    'kode' => $unit->kode,
                    'judul_kompetensi' => $unit->nama,
                    'subunits' => $subunits,
                ];
            })->filter(fn ($unit) => $unit['subunits']->isNotEmpty())->values(),
            'barcodes' => $barcodes,
        ];
    }

    public function getApl02($id)
    {
        $data = $this->buildApl02Data($id);

        if (!$data) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $data
        ]);
    }

    public function submitApl02(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'metode' => 'nullable|string',
            'is_dilanjutkan' => 'required|boolean',
            'answers' => 'required|array',

            'answers.*.subunit_id' => 'required|integer',
            'answers.*.kompeten' => 'required|boolean',
            'answers.*.file_ids' => 'nullable|array',
            'answers.*.file_ids.*' => 'exists:apl2_files,id',
            'answers.*.file_urls' => 'nullable|array',
            // Accept both: array of objects {url, name?} OR flat array of URL strings
            'answers.*.file_urls.*' => 'nullable',
        ]);

        $validator->after(function ($validator) use ($request) {
            foreach ($request->input('answers', []) as $index => $answer) {
                $fileIds = $answer['file_ids'] ?? [];
                $rawFileUrls = $answer['file_urls'] ?? [];

                // Normalize file_urls: extract valid URLs from mixed formats
                $normalizedUrls = collect($rawFileUrls)
                    ->filter(function ($item) {
                        if (is_string($item)) {
                            // Flat array of URL strings
                            return filter_var($item, FILTER_VALIDATE_URL) !== false;
                        }
                        if (is_array($item)) {
                            // Object format {url: ..., name?: ...}
                            return !empty($item['url']) && filter_var($item['url'], FILTER_VALIDATE_URL) !== false;
                        }
                        return false;
                    })
                    ->values()
                    ->toArray();

                $hasFileUrls = !empty($normalizedUrls);

                if (empty($fileIds) && !$hasFileUrls) {
                    $validator->errors()->add(
                        "answers.{$index}.file_ids",
                        'Setiap jawaban harus memiliki minimal satu file (unggahan atau URL).'
                    );
                }
            }
        });

        $validated = $validator->validate();

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

                $fileIds = $answer['file_ids'] ?? [];

                // Normalize file_urls again for processing
                $rawFileUrls = $answer['file_urls'] ?? [];
                $normalizedFileUrls = collect($rawFileUrls)
                    ->filter(function ($item) {
                        if (is_string($item)) {
                            return filter_var($item, FILTER_VALIDATE_URL) !== false;
                        }
                        if (is_array($item)) {
                            return !empty($item['url']) && filter_var($item['url'], FILTER_VALIDATE_URL) !== false;
                        }
                        return false;
                    })
                    ->map(function ($item) {
                        if (is_string($item)) {
                            return ['url' => $item, 'name' => basename($item)];
                        }
                        return ['url' => $item['url'], 'name' => $item['name'] ?? basename($item['url'])];
                    })
                    ->values()
                    ->toArray();

                // Create Apl2File records for external URLs and collect their IDs
                if (!empty($normalizedFileUrls)) {
                    foreach ($normalizedFileUrls as $item) {
                        $fileUrl = $item['url'];
                        $fileName = $item['name'];

                        // Cek apakah URL sudah pernah disimpan untuk id_izin ini
                        $existingFile = Apl2File::where('id_izin', $id)
                            ->where('path', $fileUrl)
                            ->first();

                        if ($existingFile) {
                            // Gunakan file yang sudah ada
                            $fileIds[] = $existingFile->id;
                        } else {
                            $file = Apl2File::create([
                                'id_izin'       => $id,
                                'original_name' => $fileName,
                                'path'          => $fileUrl,
                                'mime'          => 'application/octet-stream',
                                'size'          => 0,
                                'filetype'      => 'referensi_external',
                            ]);

                            $fileIds[] = $file->id;
                        }
                    }
                }

                // Hindari duplikasi ID dalam array (misal URL yg sama ada di file_ids dan file_urls)
                $fileIds = array_unique($fileIds);

                if (!empty($fileIds)) {
                    $aplAnswer->files()->sync($fileIds);
                }
            }
        });

        // Get asesor IDs from the relationship
        $asesiJadwal = AsesiJadwal::where('id_izin', $id)->first();
        $asesorIds = [];

        if ($asesiJadwal && $asesiJadwal->jadwal) {
            $jadwal = $asesiJadwal->jadwal;
            if ($jadwal->asesor_id) {
                $asesorIds[] = $jadwal->asesor_id;
            }
            if ($jadwal->asesor2_id) {
                $asesorIds[] = $jadwal->asesor2_id;
            }
        }

        // Get the updated data for broadcasting
        $submission = Apl2Submission::where('id_izin', $id)->first();
        $answers = Apl2Answer::where('id_izin', $id)->with('files')->get();

        // Broadcast disabled — keep for later
        // broadcast(new Apl02Updated($id, $submission, $answers, $asesorIds));

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
            'filetypes' => 'nullable|array',
            'filetypes.*' => 'nullable|string',
        ]);

        $uploaded = [];
        $files = $request->file('files');
        $filetypes = $request->filetypes ?? [];

        foreach ($files as $index => $file) {

            // 🔐 Optional: avoid name collision on FTP
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

            // 📁 FTP path
            $path = "apl02/{$id}/{$filename}";

            // 🚀 Upload to FTP
            Storage::disk('str_moon')->put(
                $path,
                file_get_contents($file->getRealPath())
            );

            // 💾 Save DB record with individual filetype
            $uploaded[] = Apl2File::create([
                'id_izin'        => $id,
                'original_name'  => $file->getClientOriginalName(),
                'path'           => $path,          // FTP path
                'mime'           => $file->getMimeType(),
                'size'           => $file->getSize(),
                'filetype'       => $filetypes[$index] ?? null, // Individual filetype per file
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
                'path' => $file->path && (str_starts_with($file->path, 'http://') || str_starts_with($file->path, 'https://'))
                    ? $file->path
                    : $baseUrl . ltrim($file->path, '/'),
                'filetype' => $file->filetype,
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

    public function ttdApl02(Request $request, $id)
    {
        $data = $this->generateTtdBarcodeWithoutJadwal(
            'APL02',
            'ttd_apl02',
            $id
        );

        $dataApl = $this->buildApl02Data($id, true);
        if (!$dataApl) {
            abort(404, 'Data not found');
        }

        $dokumenHeader = $this->buildDokumenHeader($id);
        if (!$dokumenHeader) {
            abort(404, 'Dokumen header not found');
        }

        $viewData = array_merge($dataApl, [
            'dokumenHeader' => $dokumenHeader
        ]);

        /* =========================
        * GENERATE PDF
        * ========================= */

        $pdf = Pdf::loadView('pdf.apl02', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/apl02';

        // unique + traceable filename
        $filename = 'FR-APL-02-' . $id . '.pdf';
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
            ['url_apl_02' => $fullUrl]
        );

        /* =========================
        * BROADCAST REAL-TIME UPDATE
        * ========================= */

        // Get the authenticated asesor ID
        $asesorId = auth()->id();

        // Broadcast disabled — keep for later
        // broadcast(new Apl02AsesorUpdated(
        //     $id,
        //     'signature',
        //     [
        //         'document_type' => 'APL02',
        //         'pdf_url' => $fullUrl,
        //         'filename' => $filename,
        //         'barcode_data' => $data,
        //         'signed_by' => $asesorId,
        //         'signed_at' => now()->toISOString(),
        //     ],
        //     $asesorId
        // ));

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    private function buildMapa01Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return null;
        }

        $apl2Submission = Apl2Submission::where('id_izin', $id)->first();

        // Pengalaman dihitung dari proyek YANG PUNYA referensi kerja (surat_referensi).
        // Jika APL-02 tidak punya url referensi kerja → asesi TIDAK dicentang
        // sebagai "pekerja berpengalaman" di MAPA-01 (per instruksi user).
        $isPengalaman = Proyek::where('id_izin', $id)
            ->whereNotNull('surat_referensi')
            ->where('surat_referensi', '!=', '')
            ->exists();

        $jabatanKerja = JabatanKerja::select(['jabatan_kerja', 'acuan'])
            ->where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)
            ->first();

        $dokumen = JabatanDokumen::with(['kelompoks.units.unit'])
            ->select(['id', 'kode', 'nama_dokumen'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'MAPA01')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return [
                'kelompok_kerja' => [],
                'referensi_form' => [],
            ];
        }

        $barcodes = $this->resolveDocumentBarcodes($id, 'MAPA01', $forPdf);

        /* =========================
        * KELOMPOK KERJA
        * ========================= */
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
                    })->values(),
                ];
            })->values(),
        ];

        /* =========================
        * REFERENSI
        * ========================= */
        $referensis = Referensi::with([
                'kategoriRel',
                'kelompokRel',
                'subkategoriRel',
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
                        'id' => $kelompok->id,
                        'nama' => $kelompok->nama,
                        'urut' => $kelompok->urut,

                        'kategoris' => $kelompokItems
                            ->groupBy(fn ($i) => $i->id_kategori ?? 'tanpa_kategori')
                            ->values()
                            ->map(function ($kategoriItems) use ($isPengalaman, $kelompok) {

                                $kategori = $kategoriItems->first()->kategoriRel;

                                return [
                                    'id' => $kategori?->id,
                                    'kategori' => $kategori?->kategori,
                                    'nama' => $kategori?->nama,
                                    'urut' => $kategori?->urut,
                                    'id_kelompok' => $kategori?->id_kelompok,

                                    'subkategoris' => $kategoriItems
                                        ->groupBy(fn ($i) => $i->id_subkategori ?? 'tanpa_subkategori')
                                        ->values()
                                        ->map(function ($subItems) use ($isPengalaman, $kelompok, $kategori) {

                                            $sub = $subItems->first()->subkategoriRel;

                                            return [
                                                'id' => $sub?->id,
                                                'nama' => $sub?->nama ?? 'Tanpa Subkategori',
                                                'urut' => $sub?->urut,

                                                'referensis' => $subItems->map(function ($item) use ($isPengalaman, $kelompok, $kategori) {

                                                    $checked = false;

                                                    if ($kelompok->id == 1) {
                                                        if ($kategori?->id == 1) {
                                                            $checked = $isPengalaman
                                                                ? $item->id == 85
                                                                : $item->id == 82;
                                                        } elseif ($kategori?->id == 2) {
                                                            $checked = $item->id == 87;
                                                        } elseif ($kategori?->id == 3) {
                                                            $checked = in_array($item->id, [92, 94, 95, 98], true);
                                                        } elseif ($kategori?->id == 4) {
                                                            $checked = $item->id == 101;
                                                        }
                                                    }

                                                    if ($kelompok->id == 2 && $kategori?->id == 5) {
                                                        $checked = $item->id == 105;
                                                    }

                                                    if ($kelompok->id == 3) {
                                                        $checked = $item->id == 114;
                                                    }

                                                    return [
                                                        'id' => $item->id,
                                                        'nama' => $item->nama,
                                                        'value' => $checked,
                                                        'alasan' => '',
                                                    ];
                                                })->values(),
                                            ];
                                        })->values(),
                                ];
                            })->values(),
                    ],
                ];
            });

        // ── Inject alasan (kode unit) ke item yang checked di kelompok 3 ──
        $unitText = '';
        foreach ($kelompokKerja['kelompok_kerja'] as $kg) {
            $unitText .= '<b>' . e($kg['nama']) . ":</b><br>\n";
            foreach ($kg['units'] as $unit) {
                $unitText .= '&nbsp;&nbsp;- ' . e($unit['kode_unit'] ?? $unit['kode'] ?? '') . "<br>\n";
            }
        }
        $dataReferensi = collect($dataReferensi)->map(function ($kelRef) use ($unitText) {
            if (isset($kelRef['kelompok']['id']) && $kelRef['kelompok']['id'] == 3) {
                $kelRef['kelompok']['kategoris'] = collect($kelRef['kelompok']['kategoris'])->map(function ($kat) use ($unitText) {
                    $kat['subkategoris'] = collect($kat['subkategoris'])->map(function ($sub) use ($unitText) {
                        $sub['referensis'] = collect($sub['referensis'])->map(function ($ref) use ($unitText) {
                            if (!empty($ref['value'])) {
                                $ref['alasan'] = $unitText;
                            }
                            return $ref;
                        })->values()->all();
                        return $sub;
                    })->values()->all();
                    return $kat;
                })->values()->all();
            }
            return $kelRef;
        })->values()->all();

        return [
            'kelompok_kerja' => $kelompokKerja,
            'referensi_form' => $dataReferensi,
            'skkni' => $jabatanKerja->acuan,
            'jenjang' => $klasifikasi->jenjang,
            'metode' => $apl2Submission?->metode,
            'barcodes' => $barcodes,
        ];
    }

    public function getMapa01($id)
    {
        $data = $this->buildMapa01Data($id);

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

    public function ttdMapa01(Request $request, $id)
    {
        $barcode = $this->generateTtdBarcode(
            $request,
            'MAPA01',
            'ttd_mapa01',
            $id
        );

        $data = $this->buildMapa01Data($id, true);
        if (!$data) {
            abort(404, 'Data not found');
        }

        $dokumenHeader = $this->buildDokumenHeader($id);
        if (!$dokumenHeader) {
            abort(404, 'Dokumen header not found');
        }

        $viewData = array_merge($data, [
            'dokumenHeader' => $dokumenHeader
        ]);

        /* =========================
        * GENERATE PDF
        * ========================= */

        $pdf = Pdf::loadView('pdf.mapa01', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/mapa01';

        // unique + traceable filename
        $filename = 'FR-MAPA-01-' . $id . '.pdf';
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
            ['url_mapa01' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $barcode,
            'url'     => $fullUrl,
            'broadcasted' => true,
        ]);
    }

    private function buildMapa02Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])
            ->where('id_izin', $id)
            ->first();

        if (!$klasifikasi) {
            return null;
        }

        $dokumen = JabatanDokumen::with([
                'kelompoks.units.unit'
            ])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->where('kode', 'MAPA02')
            ->where('is_active', true)
            ->first();

        if (!$dokumen) {
            return [
                'kelompok_kerja' => [],
                'referensi_form' => [],
            ];
        }

        $barcodes = $this->resolveDocumentBarcodes($id, 'MAPA02', $forPdf);

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

        // Get MAPA01 data to determine potensi asesi
        $mapa01Data = $this->buildMapa01Data($id);
        $potensiAsesiIndex = 1; // Default to 1

        if ($mapa01Data && isset($mapa01Data['referensi_form'])) {
            foreach ($mapa01Data['referensi_form'] as $kelompok) {
                if ($kelompok['kelompok']['id'] == 1) {
                    foreach ($kelompok['kelompok']['kategoris'] as $kategori) {
                        if ($kategori['id'] == 1) {
                            foreach ($kategori['subkategoris'][0]['referensis'] as $index => $referensi) {
                                if ($referensi['value'] === true) {
                                    $potensiAsesiIndex = $index + 1;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Get metode & jenjang for MAPA02_1 isdefault logic
        $apl2Submission = Apl2Submission::where('id_izin', $id)->first();
        $metode = $apl2Submission?->metode;
        $jenjang = $klasifikasi->jenjang;

        // Determine which FR codes should be default based on jenjang and metode
        $defaultFrCodes = [];
        if ($jenjang >= 1 && $jenjang <= 3) {
            $defaultFrCodes = ['FR.IA.01', 'FR.IA.02', 'FR.IA.03', 'FR.IA.05'];
        } elseif ($jenjang >= 4 && $jenjang <= 9) {
            if ($metode === 'observasi') {
                $defaultFrCodes = ['FR.IA.04A', 'FR.IA.04B', 'FR.IA.05'];
            } elseif ($metode === 'portofolio') {
                $defaultFrCodes = ['FR.IA.08', 'FR.IA.09', 'FR.IA.10'];
            }
        }

        $referensis = Referensi::select(['id', 'nama', 'kategori', 'isdefault'])
            ->whereIn('kategori', ['MAPA02-1', 'MAPA02_1'])
            ->get();

        $dataReferensi = $referensis
            ->groupBy('kategori')
            ->map(function ($items, $kategori) use ($potensiAsesiIndex, $defaultFrCodes) {
                return [
                    'kategori'   => $kategori,
                    'referensis' => $items->map(function ($item) use ($potensiAsesiIndex, $kategori, $defaultFrCodes) {
                        $isdefault = $item->isdefault;

                        // Override isdefault for MAPA02_1 based on jenjang/metode logic
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
                    })->values(),
                ];
            })
            ->values();

        return [
            'kelompok_kerja' => $kelompokKerja,
            'referensi_form' => $dataReferensi,
            'barcodes' => $barcodes,
        ];
    }

    public function getMapa02($id)
    {
        $data = $this->buildMapa02Data($id);

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

    public function generateMapa02($id)
    {
        $data = $this->buildMapa02Data($id);
        if (!$data) {
            abort(404, 'Data not found');
        }

        $dokumenHeader = $this->buildDokumenHeader($id);
        if (!$dokumenHeader) {
            abort(404, 'Dokumen header not found');
        }

        $viewData = array_merge($data, [
            'dokumenHeader' => $dokumenHeader
        ]);

        /* =========================
        * GENERATE PDF
        * ========================= */

        $pdf = Pdf::loadView('pdf.mapa02', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/mapa02';

        // unique + traceable filename
        $filename = 'FR-MAPA-02-' . $id . '.pdf';
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
            ['url_mapa02' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $fullUrl,
        ]);
    }

    public function ttdMapa02(Request $request, $id)
    {
        $barcode = $this->generateTtdBarcode(
            $request,
            'MAPA02',
            'ttd_mapa02',
            $id
        );

        $data = $this->buildMapa02Data($id, true);
        if (!$data) {
            abort(404, 'Data not found');
        }

        $dokumenHeader = $this->buildDokumenHeader($id);
        if (!$dokumenHeader) {
            abort(404, 'Dokumen header not found');
        }

        $viewData = array_merge($data, [
            'dokumenHeader' => $dokumenHeader
        ]);

        /* =========================
        * GENERATE PDF
        * ========================= */

        $pdf = Pdf::loadView('pdf.mapa02', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/mapa02';

        // unique + traceable filename
        $filename = 'FR-MAPA-02-' . $id . '.pdf';
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
            ['url_mapa02' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $barcode,
            'url'     => $fullUrl,
            'broadcasted' => true,
        ]);
    }

    private function buildAk07Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        $barcodes = $this->resolveDocumentBarcodes($id, 'AK07', $forPdf);

        // Berpengalaman = punya FILE REFERENSI KERJA (surat_referensi) ATAU nama proyek terisi.
        // Baris placeholder (semua kolom null) TIDAK dihitung.
        $isPengalaman = Proyek::where('id_izin', $id)
            ->where(function ($q) {
                $q->whereNotNull('surat_referensi')->where('surat_referensi', '!=', '')
                  ->orWhereNotNull('nama_proyek')->where('nama_proyek', '!=', '');
            })
            ->exists();

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

        return [
            'barcodes' => $barcodes,

            'kelompoks' => $referensis
                ->groupBy('id_kelompok')
                ->values()
                ->map(function ($kelompokItems) use ($isPengalaman) {

                    $kelompok = $kelompokItems->first()->kelompokRel;

                    return [
                        'id'   => $kelompok->id,
                        'nama' => $kelompok->nama,
                        'urut' => $kelompok->urut,

                        'kategoris' => $kelompokItems
                            ->groupBy('id_kategori')
                            ->values()
                            ->map(function ($kategoriItems) use ($isPengalaman, $kelompok) {

                                $kategori = $kategoriItems->first()->kategoriRel;

                                return [
                                    'id'          => $kategori?->id,
                                    'kategori'    => $kategori?->kategori,
                                    'nama'        => $kategori?->nama,
                                    'urut'        => $kategori?->urut,
                                    'id_kelompok' => $kategori?->id_kelompok,

                                    'referensis' => $kategoriItems->map(function ($item) use ($isPengalaman, $kelompok) {

                                        $answer = $item->ak07Answer;

                                        // Untuk kelompok 5, jika nama asli null dan ada custom_name
                                        // dari jawaban tersimpan, gunakan answer_text sebagai nama
                                        $nama = $item->nama;
                                        if ($kelompok->id == 5 && is_null($nama) && $answer && $answer->answer_text) {
                                            $nama = $answer->answer_text;
                                        }

                                        /** =========================
                                         * KELOMPOK 4 STRICT LOGIC
                                         * ========================= */
                                        if ($kelompok->id == 4) {

                                            // Default rules
                                            if (!$isPengalaman && $item->id == 115) {
                                                return [
                                                    'id' => $item->id,
                                                    'nama' => $item->nama,
                                                    'jawaban' => true,
                                                ];
                                            }

                                            if ($isPengalaman && $item->id == 118) {
                                                return [
                                                    'id' => $item->id,
                                                    'nama' => $item->nama,
                                                    'jawaban' => true,
                                                ];
                                            }

                                            // Everything else in kelompok 4 → FALSE
                                            return [
                                                'id' => $item->id,
                                                'nama' => $item->nama,
                                                'jawaban' => null,
                                            ];
                                        }

                                        /** =========================
                                         * OTHER KELOMPOK (DB wins)
                                         * ========================= */
                                        if ($answer) {
                                            return [
                                                'id' => $item->id,
                                                'nama' => $nama,
                                                'jawaban' => $item->id_kelompok === 6
                                                    ? [
                                                        'bool' => $answer->answer_bool,
                                                        'text' => $answer->answer_text,
                                                    ]
                                                    : ($item->id_kelompok === 7 ? $answer->answer_text : $answer->answer_bool),
                                            ];
                                        }

                                        return [
                                            'id' => $item->id,
                                            'nama' => $nama,
                                            'jawaban' => null,
                                        ];
                                    })->values(),
                                ];
                            })->values(),
                    ];
                })->values(),
        ];
    }

    public function getAk07($id)
    {
        $data = $this->buildAk07Data($id);

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

    public function ttdAk07(Request $request, $id)
    {
        $data = $this->generateTtdBarcode(
            $request,
            'AK07',
            'ttd_ak07',
            $id
        );

        $dataDokumen = $this->buildAk07Data($id, true);
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

        $pdf = Pdf::loadView('pdf.ak07', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/ak07';

        // unique + traceable filename
        $filename = 'FR-AK-07-' . $id . '.pdf';
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
            ['url_ak07' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    public function submitAk07(Request $request, $id)
    {
        $request->validate([
            'answers' => 'nullable|array',
            'answers.*.referensi_id' => 'nullable|integer|exists:referensis,id',
            'answers.*.kelompok_id' => 'nullable|integer|exists:referensi_kelompoks,id',
            'answers.*.value' => 'nullable',
            'answers.*.custom_name' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $id) {

            foreach ($request->answers as $item) {

                $kelompokId = (int) $item['kelompok_id'];

                $answerBool = null;
                $answerText = null;

                /* =========================
                KELOMPOK 6 (BOOLEAN + TEXT)
                ========================= */
                if ($kelompokId === 6) {
                    $answerBool = $item['value']['bool'] ?? null;
                    $answerText = $item['value']['text'] ?? null;
                }

                /* =========================
                KELOMPOK 7 (TEXT ONLY)
                ========================= */
                elseif ($kelompokId === 7) {
                    $answerText = (string) $item['value'];
                }

                /* =========================
                OTHER KELOMPOK (BOOLEAN ONLY)
                — KELOMPOK 5: juga simpan custom_name di answer_text
                  untuk referensi dengan nama null (opsi lainnya)
                ========================= */
                else {
                    $answerBool = (bool) $item['value'];

                    // Kelompok 5 — simpan custom_name jika ada
                    if ($kelompokId === 5 && !empty($item['custom_name'])) {
                        $answerText = $item['custom_name'];
                    }
                }

                Ak7Answer::updateOrCreate(
                    [
                        'id_izin'      => $id,
                        'referensi_id' => $item['referensi_id'],
                    ],
                    [
                        'kelompok_id' => $kelompokId,
                        'answer_bool' => $answerBool,
                        'answer_text' => $answerText,
                    ]
                );
            }
        });

        return response()->json([
            'message' => 'AK07 successfully submitted'
        ]);
    }

    private function buildAk04Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
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

        if ($forPdf) {
            $barcodeByRole = Barcode::with('user:id,name')
            ->where('id_izin', $id)
            ->where('jenis_dokumen', 'AK04')
            ->get()
            ->keyBy('id_role')   // 👈 key FIRST
            ->map(function ($barcode) {
                return [
                    'user'    => $barcode->user,
                    'tanggal' => $barcode->created_at,
                    'url'  => $this->imageUrlToBase64($barcode->url_image), // URL → Base64
                ];
            });

            return [
                'kelompoks' => $kelompokData,
                'alasan'    => $reason,
                'barcodes' => [
                    'asesi' => $barcodeByRole[8] ?? null,
                ]
            ];
        }
        
        $barcodeByRole = Barcode::with('user:id,name')
        ->where('id_izin', $id)
        ->where('jenis_dokumen', 'AK04')
        ->get()
        ->keyBy('id_role');

        return [
                'kelompoks' => $kelompokData,
                'alasan'    => $reason,
                'barcodes' => [
                    'asesi' => [
                        'url' => optional($barcodeByRole->get(8))->url_image,
                        'tanggal' => optional($barcodeByRole->get(8))->created_at,
                        'nama' => optional(optional($barcodeByRole->get(8))->user)->name,
                    ]
                ]
            ];
    }

    public function getAk04($id)
    {
        $data = $this->buildAk04Data($id);

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

    public function submitAk04(Request $request, $id)
    {
        $validated = $request->validate([
            'answers' => 'nullable|array',
            'answers.*.referensi_id' => 'nullable|integer|exists:referensis,id',
            'answers.*.kelompok_id'  => 'nullable|integer',
            'answers.*.jawaban'     => 'nullable|boolean',
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

    public function ttdAk04(Request $request, $id)
    {
        $data = $this->generateTtdBarcode(
            $request,
            'AK04',
            'ttd_ak04',
            $id
        );

        $dataDokumen = $this->buildAk04Data($id, true);
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

        $pdf = Pdf::loadView('pdf.ak04', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/ak04';

        // unique + traceable filename
        $filename = 'FR-AK-04-' . $id . '.pdf';
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
            ['url_ak04' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    public function getK3Asesmen($id)
    {
        $fileUrl = env('APP_URL') . '/files/k3_asesmen.pdf';

        // Resolve barcodes — same pattern as other endpoints with TTD
        $barcodes = $this->resolveDocumentBarcodes($id, 'K3', false);

        return response()->json([
            'message' => 'Success',
            'data'    => [
                'file'      => $fileUrl,
                'barcodes'  => $barcodes,
            ]
        ]);
    }

    private function buildAk01Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        /** ✅ Shared barcode resolver */
        $barcodes = $this->resolveDocumentBarcodes($id, 'AK01', $forPdf);

        $klasifikasi = KlasifikasiKualifikasi::where('id_izin', $id)->first();
        $autoTrueIds = [];

        if ($klasifikasi) {
            $jenjang = (int) $klasifikasi->jenjang;
            $apl2Submission = \App\Models\Apl2Submission::where('id_izin', $id)->first();

            if ($jenjang >= 1 && $jenjang <= 3) {
                $autoTrueIds = [62, 227]; // Daftar Pertanyaan Tertulis, Hasil Observasi Langsung
            } elseif ($jenjang >= 4 && $jenjang <= 9) {
                if ($apl2Submission?->metode === 'portofolio') {
                    $autoTrueIds = [64, 226]; // Daftar Pertanyaan Wawancara, Hasil Verifikasi Portfolio
                } else {
                    $autoTrueIds = [60, 62]; // Hasil Kegiatan Terstruktur, Hasil Observasi Langsung
                }
            }
        }

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
            ->map(function ($row) use ($autoTrueIds) {
                // Hanya item dalam autoTrueIds yang bernilai true,
                // sisanya false (abaikan nilai dari DB)
                $row->jawaban = in_array($row->id, $autoTrueIds);
                return $row;
            });

        $waktu = Ak1Answer::where('id_izin', $id)->value('waktu');

        return [
            'barcodes' => $barcodes,
            'items'    => $ak01,
            'waktu'    => $waktu,
        ];
    }

    public function getAk01($id)
    {
        $data = $this->buildAk01Data($id);

        if ($data === null) {
            return response()->json([
                'message' => 'Data not found',
                'data' => null,
            ], 41);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    public function submitAk01(Request $request, $id)
    {
        $validated = $request->validate([
            'answers' => 'required|array|min:1',
            'answers.*.id_referensi' => 'required|exists:referensis,id',
            'answers.*.jawaban' => 'required|boolean',
            'waktu' => 'nullable|string|max:255',
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

            // Store waktu in the first ak1_answer record for this asesi
            if (isset($validated['waktu']) && !empty($validated['answers'])) {
                $firstAnswerId = $validated['answers'][0]['id_referensi'];
                Ak1Answer::where('id_izin', $id)
                    ->where('id_referensi', $firstAnswerId)
                    ->update(['waktu' => $validated['waktu']]);
            }
        });

        return response()->json([
            'message' => 'AK01 successfully submitted'
        ]);
    }

    public function ttdAk01(Request $request, $id)
    {
        $data = $this->generateTtdBarcode(
            $request,
            'AK01',
            'ttd_ak01',
            $id
        );

        $dataDokumen = $this->buildAk01Data($id, true);
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

        $pdf = Pdf::loadView('pdf.ak01', $viewData)
            ->setPaper('A4', 'portrait');

        $disk = 'str_moon';
        $directory = 'dok_asesmen/ak01';

        // unique + traceable filename
        $filename = 'FR-AK-01-' . $id . '.pdf';
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
            ['url_ak01' => $fullUrl]
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ]);
    }

    public function ttdK3Asesmen(Request $request, $id)
    {
        $data = $this->generateTtdBarcode(
            $request,
            'K3',
            'ttd_k3',
            $id
        );

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
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

        // ── DEBUG ──
        Log::info("[resolveDocumentBarcodes] START — idIzin={$idIzin} jenisDokumen={$jenisDokumen} forPdf=" . ($forPdf ? 'true' : 'false'));
        $raw = Barcode::where('id_izin', $idIzin)
            ->where('jenis_dokumen', $jenisDokumen)
            ->get(['id', 'id_user', 'id_role', 'id_jadwal', 'url_image', 'jenis_dokumen']);
        Log::info("[resolveDocumentBarcodes] raw barcodes:", $raw->toArray());

        if ($barcodes->isEmpty()) {
            Log::warning("[resolveDocumentBarcodes] barcodes IS EMPTY → returning all null");
            return [
                'asesi' => null,
                'asesor1' => null,
                'asesor2' => null,
            ];
        }

        Log::info("[resolveDocumentBarcodes] barcodes keyed by id_user — keys: " . json_encode($barcodes->keys()->toArray()));

        /** 📌 Resolve jadwal from AsesiJadwal (authoritative source — NOT from barcodes,
         *  because barcodes snapshot id_jadwal at sign-time and may be stale/inconsistent) */
        $jadwalId = AsesiJadwal::where('id_izin', $idIzin)->value('jadwal_id');
        Log::info("[resolveDocumentBarcodes] jadwalId from AsesiJadwal: " . var_export($jadwalId, true));

        $jadwal = null;
        if ($jadwalId) {
            $jadwal = Jadwal::select(['asesor_id', 'asesor2_id'])
                ->with([
                    'asesor:id,nama,noreg',
                    'asesor2:id,nama,noreg',
                ])
                ->where('jadwal_id', $jadwalId)
                ->first();
        }

        if (!$jadwal) {
            Log::warning("[resolveDocumentBarcodes] JADWAL NOT FOUND for jadwal_id=" . var_export($jadwalId, true) . " → returning asesi only");
            return [
                'asesi' => $map($barcodes->firstWhere('id_role', 8)),
                'asesor1' => null,
                'asesor2' => null,
            ];
        }

        Log::info("[resolveDocumentBarcodes] jadwal found — asesor_id={$jadwal->asesor_id} asesor2_id={$jadwal->asesor2_id}");
        Log::info("[resolveDocumentBarcodes] eager-loaded asesor: " . ($jadwal->asesor ? "id={$jadwal->asesor->id} noreg={$jadwal->asesor->noreg}" : 'NULL'));
        Log::info("[resolveDocumentBarcodes] eager-loaded asesor2: " . ($jadwal->asesor2 ? "id={$jadwal->asesor2->id} noreg={$jadwal->asesor2->noreg}" : 'NULL'));

        /** 📌 Resolve asesor → user via eager‑loaded noreg
         *  (same pattern as buildDokumenHeader / getDokumenHeader) */
        $asesor1User = $jadwal->asesor
            ? User::where('noreg', $jadwal->asesor->noreg)->first()
            : null;

        $asesor2User = $jadwal->asesor2
            ? User::where('noreg', $jadwal->asesor2->noreg)->first()
            : null;

        Log::info("[resolveDocumentBarcodes] asesor1User: " . ($asesor1User ? "id={$asesor1User->id} name={$asesor1User->name}" : 'NULL'));
        Log::info("[resolveDocumentBarcodes] asesor2User: " . ($asesor2User ? "id={$asesor2User->id} name={$asesor2User->name}" : 'NULL'));
        Log::info("[resolveDocumentBarcodes] asesor1 barcode in collection? " . ($asesor1User && isset($barcodes[$asesor1User->id]) ? 'YES' : 'NO'));
        Log::info("[resolveDocumentBarcodes] asesor2 barcode in collection? " . ($asesor2User && isset($barcodes[$asesor2User->id]) ? 'YES' : 'NO'));

        $result = [
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

        Log::info("[resolveDocumentBarcodes] RESULT: asesi=" . ($result['asesi'] ? 'PRESENT' : 'NULL') . " asesor1=" . ($result['asesor1'] ? 'PRESENT' : 'NULL') . " asesor2=" . ($result['asesor2'] ? 'PRESENT' : 'NULL'));

        return $result;
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

    private function generateTtdBarcodeWithoutJadwal(
        string $docType,
        string $filePrefix,
        string $izinId
    ) {
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

        // 7️⃣ Save / update DB (resolve id_jadwal from AsesiJadwal so barcode
        //    can be linked back to the jadwal for asesor resolution)
        $idJadwal = AsesiJadwal::where('id_izin', $izinId)->value('jadwal_id');

        Barcode::updateOrCreate(
            [
                'id_user'       => auth()->user()->id,
                'id_izin'       => $izinId,
                'jenis_dokumen' => $docType,
            ],
            [
                'id_jadwal' => $idJadwal,
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

    public function deleteAsesmenData($id_izin)
    {
        DB::transaction(function () use ($id_izin) {
            // Delete AK (Asesmen Kompetensi) related data
            Ak1Answer::where('id_izin', $id_izin)->delete();
            Ak2Answer::where('id_izin', $id_izin)->delete();
            Ak2Feedback::where('id_izin', $id_izin)->delete();
            Ak3Answer::where('id_izin', $id_izin)->delete();
            Ak3Note::where('id_izin', $id_izin)->delete();
            Ak4Answer::where('id_izin', $id_izin)->delete();
            Ak4Reason::where('id_izin', $id_izin)->delete();
            Ak5Answer::where('id_izin', $id_izin)->delete();
            Ak6Answer::where('id_izin', $id_izin)->delete();
            Ak6Feedback::where('id_izin', $id_izin)->delete();
            Ak7Answer::where('id_izin', $id_izin)->delete();

            // Delete APL (Application) related data
            Apl1Answer::where('id_izin', $id_izin)->delete();
            Apl2Answer::where('id_izin', $id_izin)->delete();

            // Delete pivot table for Apl2 files
            DB::table('apl2_answer_files')
                ->whereIn('apl2_answer_id', function ($query) use ($id_izin) {
                    $query->select('id')
                        ->from('apl2_answers')
                        ->where('id_izin', $id_izin);
                })
                ->delete();

            Apl2File::where('id_izin', $id_izin)->delete();
            Apl2Submission::where('id_izin', $id_izin)->delete();

            // Delete IA (Instrument Asesmen) related data
            Ia1Answer::where('id_izin', $id_izin)->delete();
            Ia1Feedback::where('id_izin', $id_izin)->delete();
            Ia1Recommendation::where('id_izin', $id_izin)->delete();
            Ia3Answer::where('id_izin', $id_izin)->delete();
            Ia3Feedback::where('id_izin', $id_izin)->delete();
            Ia4aAnswer::where('id_izin', $id_izin)->delete();
            Ia4bAnswer::where('id_izin', $id_izin)->delete();
            Ia5Answer::where('id_izin', $id_izin)->delete();
            Ia5Feedback::where('id_izin', $id_izin)->delete();

            // Delete other related data
            Barcode::where('id_izin', $id_izin)->delete();
            BuktiAsesmen::where('id_izin', $id_izin)->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'All asesmen data successfully deleted',
        ]);
    }

    /**
     * Test WebSocket Broadcasting
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testBroadcast(Request $request)
    {
        $validated = $request->validate([
            'id_izin' => 'required|string',
        ]);

        // Create a test APL-02 update
        $testSubmission = new Apl2Submission([
            'metode' => 'test',
            'is_dilanjutkan' => true,
            'submitted_at' => now(),
        ]);

        $testAnswers = collect([
            (object) [
                'subunit_id' => 999,
                'kompeten' => true,
                'files' => collect([]),
            ]
        ]);

        // Get asesor IDs for this asesi
        $asesiJadwal = AsesiJadwal::where('id_izin', $validated['id_izin'])->first();
        $asesorIds = [];

        if ($asesiJadwal && $asesiJadwal->jadwal) {
            $jadwal = $asesiJadwal->jadwal;
            if ($jadwal->asesor_id) {
                $asesorIds[] = $jadwal->asesor_id;
            }
            if ($jadwal->asesor2_id) {
                $asesorIds[] = $jadwal->asesor2_id;
            }
        }

        // Broadcast test event — disabled, keep for later
        // broadcast(new Apl02Updated(
        //     $validated['id_izin'],
        //     $testSubmission,
        //     $testAnswers,
        //     $asesorIds
        // ));

        return response()->json([
            'success' => true,
            'message' => 'Test broadcast skipped (disabled)',
            'data' => [
                'id_izin' => $validated['id_izin'],
                'asesor_ids' => $asesorIds,
                'channels' => array_merge(
                    array_map(fn($id) => "private:asesor.{$id}", $asesorIds),
                    ["private:apl02.{$validated['id_izin']}"]
                ),
            ],
        ]);
    }

    /**
     * Resolve UUID magic link to id_izin for the frontend.
     *
     * Looks up from Personal (set during verification) — no Jadwal/AsesiJadwal dependency.
     */
    public function resolveUuid(string $uuid)
    {
        $personal = Personal::where('wa_uuid', $uuid)->first();

        if (!$personal) {
            return response()->json([
                'success' => false,
                'message' => 'Link tidak valid atau sudah kadaluwarsa.',
            ], 404);
        }

        $account = $personal->account;
        $password = $personal->password_plain;

        $token = null;
        if ($account && $password) {
            $user = User::where('email', $account)
                ->where('is_deleted', false)
                ->first();

            if ($user) {
                $token = auth()->attempt([
                    'email'    => $account,
                    'password' => $password,
                ]);
            }
        }

        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja'])->where('id_izin', $personal->id_izin)->first();

        $jadwalId = AsesiJadwal::where('id_izin', $personal->id_izin)->value('jadwal_id');

        return response()->json([
            'success' => true,
            'data' => [
                'id_izin'          => $personal->id_izin,
                'jadwal_id'        => $jadwalId,
                'jabatan_kerja'    => $klasifikasi?->jabatan_kerja,
                'jenis_kelas_id'   => $personal->jenis_kelas_id,
                'access_token'   => $token,
                'token_type'     => $token ? 'bearer' : null,
                'expires_in'     => $token ? auth()->factory()->getTTL() * 60 : null,
            ],
        ]);
    }
}
