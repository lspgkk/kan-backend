<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsesorBnsp extends Model
{
    public $timestamps = false;
    protected $table = 'asesor_bnsp';

    protected $fillable = [
        'id_asesor',
        'id_subklas',
        'id_klasifikasi',
        'id_profile',
        'jenjang',
        'id_lsp',
        'skk_asesor',
        'ska_asesor',
        'tgl_awal_skk',
        'tgl_skk',
        'is_active',
        'fskk',
        'noreg',
        'jumlah_lisensi',
        'jumlah_skema',
        'status',
        'status_lisensi',
        'status_skema',
        'nama',
        'nik',
        'Tempatlahir',
        'tgl_lahir',
        'kelamin',
        'id_provinsi_ktp',
        'id_kota_ktp',
        'alamat_ktp',
        'id_provinsi',
        'id_kota',
        'alamat',
        'status_tempat_tinggal',
        'status_warga_negara',
        'id_pendidikan',
        'id_pekerjaan',
        'no_hp',
        'nip',
        'id_photo',
        'jumlah_skema_asesor',
        'jumlah_asli',
        'id_jabatan',
        'pegawai',
        'status_asesor',
        'input_date',
        'input_by',
    ];

    /**
     * Fetch asesor data from BNSP API by noreg and save to asesor_bnsp table.
     * Returns the AsesorBnsp model instance, or null on failure.
     */
    public static function fetchFromBnspByNoreg(string $noreg): ?self
    {
        $authData = DB::table('b_n_s_p_auths')->value('x_authorization');
        if (!$authData) {
            Log::warning('[AsesorBnsp] x-authorization tidak ditemukan di tabel b_n_s_p_auths');
            return null;
        }

        $url = 'https://konstruksi.bnsp.go.id/api/v1/asesor?no_reg=' . $noreg;

        try {
            $response = Http::timeout(15)->retry(2, 100)->withHeaders([
                'Content-Type' => 'application/json',
                'x-authorization' => $authData,
            ])->get($url);

            if ($response->failed()) {
                Log::warning("[AsesorBnsp] Gagal fetch asesor noreg={$noreg} HTTP {$response->status()}");
                return null;
            }

            $body = $response->json();
            $data = $body['data'] ?? null;

            if (!$data) {
                Log::warning("[AsesorBnsp] Response tidak memiliki data untuk noreg={$noreg}");
                return null;
            }

            $asesorM = $data['asesor_m'] ?? [];
            $profile = $asesorM['profile'] ?? [];
            $now = now();

            $record = self::updateOrCreate(
                ['noreg' => $noreg],
                [
                    'id_asesor'            => $data['id_asesor'] ?? null,
                    'id_subklas'           => $data['id_sub_klas'] ?? null,
                    'id_klasifikasi'       => $data['id_klas'] ?? null,
                    'id_profile'           => $data['id_profile_a'] ?? null,
                    'jenjang'              => $data['i_jenjang'] ?? null,
                    'id_lsp'               => $data['id_lsp'] ?? null,
                    'skk_asesor'           => $data['skk_asesor'] ?? null,
                    'ska_asesor'           => $data['ska_asesor'] ?? 0,
                    'tgl_awal_skk'         => $data['tgl_awal_skk'] ?? null,
                    'tgl_skk'              => $data['tgl_skk'] ?? null,
                    'is_active'            => $data['is_actived'] ?? null,
                    'fskk'                 => $data['f_skk'] ?? null,
                    'jumlah_lisensi'       => $asesorM['jumlah_lisensi'] ?? null,
                    'jumlah_skema'         => $asesorM['jumlah_skema'] ?? null,
                    'status'               => $asesorM['status'] ?? null,
                    'status_lisensi'       => $asesorM['status_lisensi'] ?? null,
                    'status_skema'         => $asesorM['status_skema'] ?? null,
                    'nama'                 => $profile['nama'] ?? null,
                    'nik'                  => $profile['nik'] ?? null,
                    'Tempatlahir'          => $profile['tempat_lahir'] ?? null,
                    'tgl_lahir'            => $profile['tanggal_lahir'] ?? null,
                    'kelamin'              => $profile['idjeniskelamin'] ?? null,
                    'id_provinsi_ktp'      => $profile['kode_propinsi_ktp'] ?? null,
                    'id_kota_ktp'          => $profile['kode_kota_ktp'] ?? null,
                    'alamat_ktp'           => $profile['alamat_ktp'] ?? null,
                    'id_provinsi'          => $profile['kode_propinsi_tinggal'] ?? null,
                    'id_kota'              => $profile['kode_kota_tinggal'] ?? null,
                    'alamat'               => $profile['alamat_tinggal'] ?? null,
                    'status_tempat_tinggal' => $profile['statustempattinggal'] ?? null,
                    'status_warga_negara'  => 'Indonesia',
                    'id_pendidikan'        => $profile['idpendidikan'] ?? null,
                    'id_pekerjaan'         => $profile['idpekerjaan'] ?? null,
                    'no_hp'                => $profile['nohp'] ?? null,
                    'nip'                  => $profile['nip'] ?? 0,
                    'id_photo'             => $profile['idphoto'] ?? null,
                    'jumlah_skema_asesor'  => $profile['jumlah_skema'] ?? null,
                    'jumlah_asli'          => $profile['jumlah_asli'] ?? null,
                    'id_jabatan'           => $profile['idjabatan'] ?? null,
                    'pegawai'              => $profile['pegawai'] ?? null,
                    'status_asesor'        => $profile['status'] ?? null,
                    'input_date'           => $now,
                    'input_by'             => 'system',
                ]
            );

            Log::info("[AsesorBnsp] Berhasil menyimpan/fetch asesor noreg={$noreg} id={$record->id}");
            return $record;

        } catch (\Throwable $e) {
            Log::error("[AsesorBnsp] Exception fetch noreg={$noreg}: {$e->getMessage()}");
            return null;
        }
    }
}
