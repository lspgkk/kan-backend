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
use Barryvdh\DomPDF\Facade\Pdf;

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

    public function getApl01($id)
    {
        $personal = Personal::select(['nama', 'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'kodepos', 'telepon', 'email'])->where('id_izin', $id)->first();
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

    public function generateApl01($id)
    {
        /* =========================
        * FETCH DATA
        * ========================= */

        $personal = Personal::select([
            'nama', 'nik', 'tempat_lahir', 'tanggal_lahir',
            'jenis_kelamin', 'alamat', 'kodepos', 'telepon', 'email'
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
        $kualifikasi = JenjangJabatanKerja::where('jenjang_id', $klasifikasi->jenjang)
            ->value('jabatan_kerja');

        $unitKompetensi = UnitKompetensi::select(['kode', 'nama'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->get();

        $jenjang = Pendidikan::where('id_izin', $id)
            ->latest('created_at')
            ->value('jenjang');

        /* =========================
        * BUKTI
        * ========================= */

        $persyaratan = ImpBukti::select(['no', 'bukti'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->whereNotLike('bukti', 'Syarat Administrasi%')
            ->whereNotLike('bukti', 'Bukti Keanggotaan%')
            ->orderBy('no')
            ->get();

        $administratif = ImpBukti::select(['no', 'bukti'])
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
                $formattedAdministratif->push([
                    'no'      => $no++,
                    'bukti'   => $text,
                    'checked' => true
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
                'url'  => $this->imageUrlToBase64($barcode->url_image), // URL → Base64
            ];
        });

        $nomorSkema = JabatanConfigA::where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->value('no_skema');

        /* =========================
        * DATA FOR BLADE
        * ========================= */

        $data = [
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
                'perusahaan' => $apl1Answer?->perusahaan ?? $pekerjaan?->pemberi_kerja,
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

        $pdf = Pdf::loadView('pdf.apl01', $data)
            ->setPaper('A4', 'portrait');

        $filename = 'FR-APL-01-' . Str::slug($id) . '.pdf';

        return $pdf->download($filename);
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
                return [
                    'id' => (string) $unit->id,
                    'kode' => $unit->kode,
                    'judul_kompetensi' => $unit->nama,

                    'subunits' => $unit->subunits->map(function ($subunit) use (
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
                                    'path' => $baseUrl . ltrim($file->path, '/'),
                                ])->values()
                                : [],

                            'kuk_list' => $subunit->kuks->map(fn ($kuk) => [
                                'no_kuk' => $kuk->kode,
                                'judul_kuk' => $kuk->nama,
                            ])->values(),
                        ];
                    })->values(),
                ];
            })->values(),
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

    public function generateApl02($id)
    {
        $data = $this->buildApl02Data($id, true);
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

        return Pdf::loadView('pdf.apl02', $viewData)
            ->setPaper('A4', 'portrait')
            ->download('FR-APL-02-' . Str::slug($id) . '.pdf');
    }

    private function buildMapa01Data(
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

        $isPengalaman = Proyek::where('id_izin', $id)->exists();

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
                                                                ? $item->id == 84
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

                                                    return [
                                                        'id' => $item->id,
                                                        'nama' => $item->nama,
                                                        'value' => $checked,
                                                    ];
                                                })->values(),
                                            ];
                                        })->values(),
                                ];
                            })->values(),
                    ],
                ];
            });

        return [
            'kelompok_kerja' => $kelompokKerja,
            'referensi_form' => $dataReferensi,
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

    public function generateMapa01($id)
    {
        $data = $this->buildMapa01Data($id);
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

        return Pdf::loadView('pdf.mapa01', $viewData)
            ->setPaper('A4', 'portrait')
            ->download('MAPA-01-' . Str::slug($id) . '.pdf');
    }

    private function buildMapa02Data(
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

        return [
            'kelompok_kerja' => $kelompokKerja,
            'referensi_form' => $dataReferensi,
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

        return Pdf::loadView('pdf.mapa02', $viewData)
            ->setPaper('A4', 'portrait')
            ->download('MAPA-02-' . Str::slug($id) . '.pdf');
    }

    private function buildAk07Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        $barcodes = $this->resolveDocumentBarcodes($id, 'AK07', $forPdf);

        $isPengalaman = Proyek::where('id_izin', $id)->exists();

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

                                            if ($isPengalaman && $item->id == 117) {
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
                                                'jawaban' => false,
                                            ];
                                        }

                                        /** =========================
                                         * OTHER KELOMPOK (DB wins)
                                         * ========================= */
                                        if ($answer) {
                                            return [
                                                'id' => $item->id,
                                                'nama' => $item->nama,
                                                'jawaban' => $item->id_kelompok === 6
                                                    ? [
                                                        'bool' => $answer->answer_bool,
                                                        'text' => $answer->answer_text,
                                                    ]
                                                    : ($answer->answer_text ?? $answer->answer_bool),
                                            ];
                                        }

                                        return [
                                            'id' => $item->id,
                                            'nama' => $item->nama,
                                            'jawaban' => false,
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

    public function generateAk07($id)
    {
        $data = $this->buildAk07Data($id, true);
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

        return Pdf::loadView('pdf.ak07', $viewData)
            ->setPaper('A4', 'portrait')
            ->download('AK-07-' . Str::slug($id) . '.pdf');
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
                ========================= */
                else {
                    $answerBool = (bool) $item['value'];
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

    public function generateAk04($id)
    {
        $data = $this->buildAk04Data($id);
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

        return Pdf::loadView('pdf.ak04', $viewData)
            ->setPaper('A4', 'portrait')
            ->download('AK-04-' . Str::slug($id) . '.pdf');
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

    private function buildAk01Data(
        int|string $id,
        bool $forPdf = false
    ): ?array
    {
        /** ✅ Shared barcode resolver */
        $barcodes = $this->resolveDocumentBarcodes($id, 'AK01', $forPdf);

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

        return [
            'barcodes' => $barcodes,
            'items'    => $ak01,
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

    public function generateAk01($id)
    {
        $data = $this->buildAk01Data($id, true);
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

        return Pdf::loadView('pdf.ak01', $viewData)
            ->setPaper('A4', 'portrait')
            ->download('AK-01-' . Str::slug($id) . '.pdf');
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
}
