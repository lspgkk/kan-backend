<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
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
            
        }
    }

    public function getDokumenHeader($id)
    {
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
                    'perusahaan' => $pekerjaan->pemberi_kerja,
                    'jabatan' => $pekerjaan->jabatan,
                    'alamat_kantor' => $pekerjaan ? $pekerjaan->alamat : null,
                    'kode_pos' => $pekerjaan ? $pekerjaan->kode_pos : null,
                    'telepon_kantor' => $pekerjaan ? $pekerjaan->telepon : null,
                    'fax' => $pekerjaan ? $pekerjaan->fax : null,
                    'email_kantor' => $pekerjaan ? $pekerjaan->email : null,
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
                'is_memenuhi_syarat' => true,
                'skkni' => $jabatanKerja->acuan,
                'data_unit_kompetensi' => $unitKompetensi,
                'bukti_persyaratan' => $persyaratan,
                'bukti_administratif' => $formattedAdministratif->values(),
            ]
        ]);
    }

    public function getApl02($id)
    {
        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])
            ->where('id_izin', $id)
            ->first();
        
        // Load units → subunits → kuks
        $units = UnitKompetensi::with(['subunits.kuks'])
            ->where('id_jabatan', $klasifikasi->jabatan_kerja)
            ->get();

        if ($units->isEmpty()) {
            return response()->json([
                'message' => 'Success',
                'data' => null
            ]);
        }

        $data = [
            'units' => $units->map(function ($unit) {

                return [
                    'id' => (string) $unit->id,
                    'kode' => $unit->kode,
                    'judul_kompetensi' => $unit->nama,
                    'subunits' => $unit->subunits->map(function ($subunit) {

                        return [
                            'id' => (string) $subunit->id,
                            'no_elemen' => $subunit->kode,
                            'judul_elemen' => $subunit->nama,
                            'kuk_list' => $subunit->kuks->map(function ($kuk) {

                                return [
                                    'no_kuk' => $kuk->kode,
                                    'judul_kuk' => $kuk->nama,
                                ];

                            })->values()
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
        ->map(function ($kelompokItems) {

            $kelompok = $kelompokItems->first()->kelompokRel;

            return [
                'kelompok' => [
                    'id'   => $kelompok->id,
                    'nama' => $kelompok->nama,
                    'urut' => $kelompok->urut,

                    'kategoris' => $kelompokItems
                        ->groupBy(fn ($item) => $item->id_kategori ?? 'tanpa_kategori')
                        ->values()
                        ->map(function ($kategoriItems) {

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
                                    ->map(function ($subItems) {

                                        $firstSub = $subItems->first();
                                        $sub = $firstSub->subkategoriRel;

                                        return [
                                            'id'   => $sub?->id,
                                            'nama' => $sub?->nama ?? 'Tanpa Subkategori',
                                            'urut' => $sub?->urut,

                                            'referensis' => $subItems->map(fn ($item) => [
                                                'id'   => $item->id,
                                                'nama' => $item->nama,
                                            ])->values(),
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

    public function getAk07($id)
    {
        $referensis = Referensi::with([
            'kategoriRel',
            'kelompokRel'
        ])
        ->select(['id', 'nama', 'id_kelompok', 'id_kategori'])
        ->where('kategori', 'AK07')
        ->whereNotNull('id_kelompok')   // keep this
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
                            ->groupBy(fn ($item) => $item->id_kategori ?? null)
                            ->values()
                            ->map(function ($kategoriItems) {

                                $first = $kategoriItems->first();
                                $kategori = $first->kategoriRel;

                                return [
                                    'id'          => $kategori?->id,
                                    'kategori'    => $kategori?->kategori,
                                    'nama'        => $kategori?->nama ?? null,
                                    'urut'        => $kategori?->urut,
                                    'id_kelompok' => $kategori?->id_kelompok,

                                    'referensis' => $kategoriItems->map(fn ($item) => [
                                        'id'   => $item->id,
                                        'nama' => $item->nama,
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

    public function getAk04($id)
    {
        $referensis = Referensi::with('kelompokRel')->select(['id', 'nama', 'id_kelompok'])
            ->where('kategori', 'AK04')
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

                        'referensis' => $kelompokItems->map(fn ($item) => [
                            'id'   => $item->id,
                            'nama' => $item->nama,
                        ])->values(),
                    ]
                ];
            });

        return response()->json([
            'message' => 'Success',
            'data'    => $data
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
        $ak01 = Referensi::select(['id', 'nama'])
            ->where('kategori', 'AK01_1')
            ->get();
        
        return response()->json([
            'message' => 'Success',
            'data'    => $ak01
        ]);
    }
}
