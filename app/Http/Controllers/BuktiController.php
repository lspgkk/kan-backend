<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\ImpBukti;
use App\Models\JabatanDokumen;
use App\Models\JabatanKerja;
use App\Models\JenjangJabatanKerja;
use App\Models\KlasifikasiKualifikasi;
use App\Models\MyTuk;
use App\Models\Pendidikan;
use App\Models\Personal;
use App\Models\Proyek;
use App\Models\SPTAsesor;
use App\Models\UnitKompetensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuktiController extends Controller
{
    public function getKebenaranData($id)
    {
        $personal = Personal::select(['nama', 'nik', 'tempat_lahir', 'tanggal_lahir', 'alamat', 'telepon', 'email', 'file_npwp', 'ktp', 'pas_foto'])->where('id_izin', $id)->first();
        $ijazah = Pendidikan::where('id_izin', $id)->value('scan_ijazah_legalisir');
        $referensiKerja = Proyek::where('id_izin', $id)->value('surat_referensi');
        $kebenaranData = [
            'personal' => $personal,
            'ijazah' => $ijazah,
            'referensi_kerja' => $referensiKerja
        ];
        return response()->json($kebenaranData);
    }

    public function dokumenAsesi($id)
    {
        $personal = Personal::select(['file_npwp', 'ktp'])->where('id_izin', $id)->first();
        $ijazah = Pendidikan::where('id_izin', $id)->latest('created_at')->value('scan_ijazah_legalisir');
        $referensiKerja = Proyek::where('id_izin', $id)->latest('created_at')->value('surat_referensi');
        $jadwalId = AsesiJadwal::where('id_izin', $id)->value('jadwal_id');
        $sptAsesor = SPTAsesor::where('jadwal_id', $jadwalId)->first();
        $verifikasiTuk = null;
        $kebenaranData = [
            'ktp' => $personal->ktp,
            'npwp' => $personal->file_npwp,
            'ijazah' => $ijazah,
            'referensi_kerja' => $referensiKerja,
            'spt_asesor' => $sptAsesor ? $sptAsesor->file_spt : null,
            'verifikasi_tuk' => $verifikasiTuk,
            
        ];
        return response()->json([
            'message' => 'Success',
            'data' => $kebenaranData
        ]);
    }

    public function getApl01($id)
    {
        $personal = Personal::select(['nama', 'nik', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'kodepos', 'telepon', 'email'])->where('id_izin', $id)->first();
        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])->where('id_izin', $id)->first();
        $jabatanKerja = JabatanKerja::select(['jabatan_kerja'])->where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)->first();
        $pekerjaan = Proyek::where('id_izin', $id)->latest('created_at')->first();
        $kualifikasi = JenjangJabatanKerja::where('jenjang_id', $klasifikasi->jenjang)->value('jabatan_kerja');
        $unitKompetensi = UnitKompetensi::select(['kode', 'nama'])->where('id_jabatan', $klasifikasi->jabatan_kerja)->get();
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

        $nomorSkema = ImpBukti::where('id_jabatan', $klasifikasi->jabatan_kerja)->value('skema');
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
                ],
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
        $jabatanKerja = JabatanKerja::where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)->value('jabatan_kerja');

        $nomorSkema = ImpBukti::where('id_jabatan', $klasifikasi->jabatan_kerja)->value('skema');
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
            'jabatan_kerja' => $jabatanKerja,
            'no_skema' => $nomorSkema,
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

        return response()->json([
            'message' => 'Success',
            'data' => [
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
                                'urut'      => $unit->urut,
                            ];
                        })->values()
                    ];
                })->values()
            ]
        ]);
    }

    public function getMapa02($id)
    {
        
    }

    
}
