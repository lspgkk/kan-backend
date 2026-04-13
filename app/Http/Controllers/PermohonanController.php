<?php

namespace App\Http\Controllers;

use App\Models\JabatanKerja;
use Illuminate\Http\Request;
use App\Models\Personal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\Pendidikan;
use App\Models\Proyek;
use App\Models\Pelatihan;
use App\Models\SertifikatSuket;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Kualifikasi;
use App\Models\Subklasifikasi;
use App\Models\MyTuk;
use App\Models\Province;
use App\Models\City;

ini_set('max_execution_time', 12000);

class PermohonanController extends Controller
{
    private const SKK_TYPES = [
        'reguler' => '/',
        'balai'   => '-balai/',
        'fg'      => '-fg/',
    ];

    public function detailPermohonan(string $id)
    {
        $personals = Personal::with(['province', 'city'])->where('id_izin', $id)->get();

        if ($personals->isEmpty()) {
            return response()->json([
                'message' => 'ID Izin tidak ditemukan.',
            ], 404);
        }

        $personal = $personals->first();

        /**
         * ===============================
         * Status description
         * ===============================
         */
        $pathStatus = resource_path('json/status.json');

        if (file_exists($pathStatus)) {
            $allStatus = json_decode(file_get_contents($pathStatus), true)['data'] ?? [];
            foreach ($allStatus as $status) {
                if ($status['kd_status'] == $personal->status) {
                    $current_status_desc = "{$status['deskripsi']} ({$status['kd_status']})";
                    break;
                }
            }
        }

        /**
         * ===============================
         * Related data
         * ===============================
         */
        $pendidikans = Pendidikan::with(['province', 'city'])->where('id_izin', $id)->get();
        $proyeks = Proyek::where('id_izin', $id)->get();
        $pelatihans = Pelatihan::where('id_izin', $id)->get();

        /**
         * ===============================
         * Transform personals to include province and city names
         * ===============================
         */
        $personals->transform(function ($item) {
            $item->propinsi_nama = $item->province ? $item->province->nama : null;
            $item->kabupaten_nama = $item->city ? $item->city->nama_kabupaten_dagri : null;
            unset($item->province, $item->city);
            return $item;
        });

        /**
         * ===============================
         * Transform pendidikans to include province and city names
         * ===============================
         */
        $pendidikans->transform(function ($item) {
            $item->propinsi_nama = $item->province ? $item->province->nama : null;
            $item->kabupaten_nama = $item->city ? $item->city->nama_kabupaten_dagri : null;
            unset($item->province, $item->city);
            return $item;
        });
        $sertifikatsukets = SertifikatSuket::where('id_izin', $id)->get();
        $klasifikasikualifikasis = KlasifikasiKualifikasi::with(['tuk', 'asosiasiRel'])->where('id_izin', $id)->get();

        /**
         * ===============================
         * Jabatan kerja
         * ===============================
         */
        $klasifikasikualifikasis[0]->nama_jabker = JabatanKerja::where('id_jabatan_kerja', $klasifikasikualifikasis[0]->jabatan_kerja)
            ->value('jabatan_kerja');

        $klasifikasikualifikasis->transform(function ($item) {

            /**
             * Extract last number from kualifikasi (e.g. SI112 → 2)
             */
            if ($item->kualifikasi) {
                $lastNumber = (int) substr($item->kualifikasi, -1);
            }

            /**
             * Replace kualifikasi with value from kualifikasis table
             */
            $kualifikasi = Kualifikasi::where('kualifikasi_id', $lastNumber)->value('kualifikasi');
            $item->kualifikasi = $kualifikasi;

            $subklasifikasi = Subklasifikasi::where('kode_subklasifikasi', $item->subklasifikasi)->value('deskripsi_subklasifikasi');
            $item->subklasifikasi = $subklasifikasi;
            /**
             * Clean response
             */
            $item->asosiasi = $item->asosiasiRel->nama_lengkap ?? null;

            unset(
                $item->asosiasiRel,
                $item->kualifikasiRel
            );

            return $item;
        });

        /**
         * ===============================
         * Status SIKI
         * ===============================
         */

        /**
         * ===============================
         * Validator names
         * ===============================
         */
        $nama_validate = DB::table('users')
            ->where('id', $personal->verifby_id)
            ->value('name');

        $namapj_validate = DB::table('users')
            ->where('id', $personal->validpjby_id)
            ->value('name');

        /**
         * ===============================
         * Experience calculation
         * ===============================
         */
        $totalYears = $totalMonths = $totalDays = 0;

        foreach ($proyeks as $proyek) {
            $days = Carbon::parse($proyek->tanggal_awal)
                ->diffInDays(Carbon::parse($proyek->tanggal_akhir));

            $years = intdiv($days, 365);
            $days %= 365;
            $months = intdiv($days, 30);
            $days %= 30;

            if (($months >= 6 || ($months == 6 && $days > 0)) && $proyeks->count() > 1) {
                $years++;
                $months = $days = 0;
            }

            $totalYears += $years;
            $totalMonths += $months;
            $totalDays += $days;
        }

        if ($totalDays >= 30) {
            $totalMonths += intdiv($totalDays, 30);
            $totalDays %= 30;
        }
        if ($totalMonths >= 12) {
            $totalYears += intdiv($totalMonths, 12);
            $totalMonths %= 12;
        }

        /**
         * ===============================
         * TUK
         * ===============================
         */
        $allTuk = MyTuk::select(['id', 'nama_tuk'])
            ->where('id_lsp', 16)
            ->get();

        $asesiTuk = null;
        if ($klasifikasikualifikasis->isNotEmpty()) {
            $asesiTuk = $allTuk
                ->firstWhere('id', $klasifikasikualifikasis->first()->tuk)
                ->nama_tuk ?? null;
        }

        /**
         * ===============================
         * FINAL JSON RESPONSE
         * ===============================
         */
        return response()->json([
            'id_izin' => $id,
            'personals' => $personals,
            'pendidikans' => $pendidikans,
            'proyeks' => $proyeks,
            'pelatihans' => $pelatihans,
            'sertifikatsukets' => $sertifikatsukets,
            'klasifikasi_kualifikasis' => $klasifikasikualifikasis,
            'validator' => [
                'verifikator' => $nama_validate,
                'validasi_pj' => $namapj_validate,
            ],
            'pengalaman' => [
                'tahun' => $totalYears,
                'bulan' => $totalMonths,
                'hari' => $totalDays,
            ],
            'tuk' => [
                'selected' => $asesiTuk,
                'list' => $allTuk,
            ],
            'is_validated' => $personal->status === '10',
        ]);
    }

    public function permohonanList(Request $request)
    {
        return $this->basePermohonanList($request);
    }

    private function permohonanBase(Request $request, string $type)
    {
        $request->merge(['type' => $type]);
        return $this->permohonanList($request);
    }

    public function permohonanRegulerList(Request $request)
    {
        return $this->permohonanBase($request, 'reguler');
    }

    public function permohonanBalaiList(Request $request)
    {
        return $this->permohonanBase($request, 'balai');
    }

    public function permohonanFgList(Request $request)
    {
        return $this->permohonanBase($request, 'fg');
    }

    public function skkBaruList(Request $request)
    {
        return $this->basePermohonanList($request, 'verifikasi');
    }

    private function skkBaruBase(Request $request, string $type)
    {
        $request->merge(['type' => $type]);
        return $this->skkBaruList($request);
    }

    public function skkBaruRegulerList(Request $request)
    {
        return $this->skkBaruBase($request, 'reguler');
    }

    public function skkBaruBalaiList(Request $request)
    {
        return $this->skkBaruBase($request, 'balai');
    }

    public function skkBaruFgList(Request $request)
    {
        return $this->skkBaruBase($request, 'fg');
    }

    public function skkValidasiList(Request $request)
    {
        return $this->basePermohonanList($request, 'validasi');
    }

    private function skkValidasiBase(Request $request, string $type)
    {
        $request->merge(['type' => $type]);
        return $this->skkValidasiList($request);
    }

    public function skkValidasiRegulerList(Request $request)
    {
        return $this->skkValidasiBase($request, 'reguler');
    }

    public function skkValidasiBalaiList(Request $request)
    {
        return $this->skkValidasiBase($request, 'balai');
    }

    public function skkValidasiFgList(Request $request)
    {
        return $this->skkValidasiBase($request, 'fg');
    }

    public function syncPermohonan(Request $request)
    {
        $type = $request->input('type'); // nullable

        if ($type) {
            if (!array_key_exists($type, self::SKK_TYPES)) {
                return response()->json([
                    'message' => 'Invalid type'
                ], 422);
            }

            return $this->syncPermohonanByType($type);
        }

        // sync ALL
        $success = 0;
        $errors = [];

        foreach (self::SKK_TYPES as $type => $endpoint) {
            $result = $this->syncPermohonanByType($type);
            $payload = $result->getData(true);

            $success += $payload['success_count'];
            $errors = array_merge($errors, $payload['errors']);
        }

        return response()->json([
            'message' => 'Sync completed (all types)',
            'success_count' => $success,
            'errors' => $errors,
        ]);
    }

    public function syncSinglePermohonan(Request $request)
    {
        $validated = $request->validate([
            'id_izin' => 'required|string',
            'type' => 'required|string|in:reguler,balai,fg',
            'base_url' => 'nullable|string',
        ]);

        $id_izin = $validated['id_izin'];
        $type = $validated['type'];
        $baseUrl = $validated['base_url'] ?? 'https://siki.pu.go.id/siki-api/v1';
        $endpoint = self::SKK_TYPES[$type];

        try {
            $this->syncSinglePermohonanData($id_izin, $type, $endpoint, $baseUrl);

            return response()->json([
                'message' => 'Single permohonan synced successfully',
                'id_izin' => $id_izin,
                'type' => $type,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to sync single permohonan',
                'id_izin' => $id_izin,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function syncPermohonanByType(string $type)
    {
        $endpoint = self::SKK_TYPES[$type];
        $baseUrl = 'https://siki.pu.go.id/siki-api/v1';

        $success_id_izin = 0;
        $error_id_izin = [];

        // 1️⃣ List permohonan
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'token' => env('SIKI_TOKEN'),
        ])->timeout(60)->connectTimeout(60)
        ->get("$baseUrl/permohonan-skk{$endpoint}");

        $items = $response->json('data', []);

        $now = now();

        $idIzins = collect($items)
            ->filter(fn ($i) =>
                !isset($i['status']) || is_null($i['status'])
            )
            ->filter(fn ($i) =>
                isset($i['created_at']) &&
                Carbon::parse($i['created_at'])->isSameMonth($now)
            )
            ->pluck('id_izin')
            ->values();

        foreach ($idIzins as $id_izin) {

            if (Personal::where('id_izin', $id_izin)->exists()) {
                continue;
            }

            try {
                $this->syncSinglePermohonanData($id_izin, $type, $endpoint, $baseUrl);
                $success_id_izin++;
            } catch (\Throwable $e) {
                $error_id_izin[] = [
                    'id_izin' => $id_izin,
                    'msg' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success_count' => $success_id_izin,
            'errors' => $error_id_izin,
        ]);
    }

    private function syncSinglePermohonanData(
        string $id_izin,
        string $type,
        string $endpoint,
        string $baseUrl
    ) {
        $headers = [
            'Content-Type' => 'application/json',
            'token' => env('SIKI_TOKEN'),
        ];

        $detail = Http::withHeaders($headers)
            ->timeout(60)->connectTimeout(60)
            ->get("$baseUrl/permohonan-skk{$endpoint}{$id_izin}")
            ->json();

        if (($detail['status'] ?? null) === 'errors') {
            throw new \Exception($detail['message'] ?? 'Unknown error');
        }

        DB::transaction(function () use ($detail, $id_izin, $type) {

            // Personal
            foreach ($detail['personal'] as $p) {
                if (!is_array($p)) {
                    continue;
                }
                Personal::updateOrInsert(
                    ['id_izin' => $id_izin],
                    [
                        'id_pemohon' => $p['id'],
                        'type' => $type,
                        'updated' => now(),
                        'created' => $p['created'],
                        'creator' => $p['creator'],
                        'data_id' => $p['data_id'],
                        'nik' => $p['nik'],
                        'nama' => $p['nama'],
                        'tempat_lahir' => $p['tempat_lahir'],
                        'tanggal_lahir' => $p['tanggal_lahir'],
                        'email' => str_replace(' ', '', $p['email'] ?? ''),
                        'telepon' => $p['telepon'],
                        'npwp' => $p['npwp'],
                        'jenis_kelamin' => $p['jenis_kelamin'],
                        'alamat' => $p['alamat'],
                        'negara' => $p['negara'],
                        'propinsi' => $p['propinsi'],
                        'kabupaten' => $p['kabupaten'],
                        'kodepos' => $p['kodepos'],
                        'ktp' => $p['ktp'],
                        'status' => null,
                        'surat_pernyataan_kebenaran_data' => $p['surat_pernyataan_kebenaran_data'],
                        'file_npwp' => $p['file_npwp'],
                        'pas_foto' => $p['pas_foto'],
                    ]
                );
            }

            // Pendidikan
            collect($detail['pendidikan'] ?? [])
            ->filter(fn ($d) => is_array($d))
            ->each(fn ($d) =>
                Pendidikan::create([
                    'id_izin' => $id_izin,
                    'id_pendidikan' => $d['id'],
                    'updated' => now(),
                    'created' => $d['created'],
                    'creator' => $d['creator'],
                    'data_id' => $d['data_id'],
                    'nama_sekolah_perguruan_tinggi' => $d['nama_sekolah_perguruan_tinggi'],
                    'program_studi' => $d['program_studi'],
                    'no_ijazah' => $d['no_ijazah'],
                    'tahun_lulus' => Carbon::now()->year,
                    'jenjang' => $d['jenjang'],
                    'alamat' => $d['alamat'],
                    'negara' => "ID",
                    'propinsi' => $d['propinsi'],
                    'kabupaten' => $d['kabupaten'],
                    'scan_ijazah_legalisir' => $d['scan_ijazah_legalisir'],
                    'scan_surat_keterangan' => $d['scan_surat_keterangan'],
                    'sertifikat_pemberian_kompetensi_tambahan' => $d['sertifikat_pemberian_kompetensi_tambahan'] ?? null,
                    'sertifikat_sibima' => $d['sertifikat_sibima'] ?? null,
                    'tanggal_lulus' => $this->formatDate($d['tanggal_lulus'] ?? null)
                ])
            );

            // Proyek
            collect($detail['proyek'] ?? [])
            ->filter(fn ($d) => is_array($d))
            ->each(fn ($d) =>
                Proyek::create([
                    'id_izin' => $id_izin,
                    'id_proyek' => $d['id'],
                    'updated' => now(),
                    'created' => $d['created'],
                    'creator' => $d['creator'],
                    'data_id' => $d['data_id'],
                    'nama_proyek' => $d['nama_proyek'],
                    'lokasi_proyek' => $d['lokasi_proyek'],
                    'tanggal_awal' => $this->formatDate($d['tanggal_awal'] ?? null),
                    'tanggal_akhir' => $this->formatDate($d['tanggal_akhir'] ?? null),
                    'jabatan' => $d['jabatan'],
                    'nilai_proyek' => $this->formatNumeric($d['nilai_proyek'] ?? null),
                    'surat_referensi' => $d['surat_referensi'],
                    'jenis_pengalaman' => $d['jenis_pengalaman'],
                    'pemberi_kerja' => $d['pemberi_kerja'],
                    'no_registrasi' => $d['no_registrasi'],
                ])
            );

            // Pelatihan
            collect($detail['pelatihan'] ?? [])
            ->filter(fn ($d) => is_array($d))
            ->each(fn ($d) =>
                Pelatihan::create([
                    'id_izin' => $id_izin,
                    'id_pelatihan' => $d['id'],
                    'updated' => now(),
                    'created' => $d['created'],
                    'creator' => $d['creator'],
                    'data_id' => $d['data_id'],
                    'nama_pelatihan' => $d['nama_pelatihan'],
                    'tanggal_awal' => $this->formatDate($d['tanggal_awal'] ?? null),
                    'tanggal_akhir' => $this->formatDate($d['tanggal_akhir'] ?? null),
                    'jumlah_jp' => $this->formatNumeric($d['jumlah_jp'] ?? null),
                    'jumlah_hari' => $this->formatNumeric($d['jumlah_hari'] ?? null),
                    'file_sertifikat' => $d['file_sertifikat']
                ])
            );

            // Sertifikat
            collect($detail['sertifikat_surat_keterangan'] ?? [])
            ->filter(fn ($d) => is_array($d))
            ->each(fn ($d) =>
                SertifikatSuket::create([
                    'id_izin' => $id_izin,
                    'id_sertifikat_suket' => $d['id'],
                    'updated' => $d['updated'],
                    'created' => $d['created'],
                    'creator' => $d['creator'],
                    'nama_sertifikat_surket' => $d['nama_sertifikat_surket'],
                    'penerbit' => $d['penerbit'],
                    'tanggal_mulai' => $this->formatDate($d['tanggal_mulai'] ?? null),
                    'tanggal_selesai' => $this->formatDate($d['tanggal_selesai'] ?? null),
                    'file_sertifikat' => $d['file_sertifikat'],
                ])
            );

            // Klasifikasi
            collect($detail['klasifikasi_kualifikasi'] ?? [])
            ->filter(fn ($d) => is_array($d))
            ->each(fn ($d) =>
                KlasifikasiKualifikasi::create([
                    'id_izin' => $id_izin,
                        'updated' => $d['updated'],
                        'id_klasifikasi_kualifikasi' => $d['id'],
                        'created' => $d['created'],
                        'creator' => $d['creator'],
                        'data_id' => $d['data_id'],
                        'lsp' => $d['lsp'],
                        'subklasifikasi' => $d['subklasifikasi'],
                        'kualifikasi' => $d['kualifikasi'],
                        'jabatan_kerja' => $d['jabatan_kerja'],
                        'jenjang' => $d['jenjang'],
                        'asosiasi' => $d['asosiasi'],
                        'kta' => $d['no_registrasi_asosiasi'],
                        'tuk' => $d['tuk'],
                        'jenis_permohonan' => $d['jenis_permohonan'],
                        'berita_acara_vv' => $d['berita_acara_vv'],
                        'surat_permohonan' => $d['surat_permohonan'],
                        'surat_pengantar_pemohonan_asosiasi' => $d['surat_pengantar_pemohonan_asosiasi'],
                        'sertifikat_skk' => $d['sertifikat_skk'],
                        'self_asesmen_apl' => $d['self_asesmen_apl'],
                        'klasifikasi' => $d['klasifikasi'],
                        'metode_uji' => $d['metode_uji'] ?? null,
                ])
            );
        });
    }

    private function basePermohonanList(
        Request $request,
        string $status = null
    ) {
        $type = $request->type;

        if (!in_array($type, ['reguler', 'balai', 'fg'], true)) {
            return response()->json(['message' => 'Invalid type'], 422);
        }

        $query = Personal::with('klasifikasi_kualifikasi.tuk')
            ->where('type', $type)
            ->orderBy('created', 'desc');

        if ($status === 'verifikasi') {
            $query->whereNull('status');
        } else if ($status === 'validasi') {
            $query->where('status', 20);
        }

        if ($search = $request->input('search')) {
            $this->applyPermohonanSearch($query, $search);
        }

        return response()->json([
            'message' => 'Permohonan retrieved successfully',
            'data' => $query->paginate($request->input('per_page', 20)),
        ]);
    }

    private function applyPermohonanSearch($query, string $search)
    {
        $query->where(function ($q) use ($search) {

            if (str_starts_with($search, 'I-')) {
                $q->where('id_izin', 'like', "{$search}%");
                return;
            }

            if (ctype_digit($search)) {
                $q->where('nik', 'like', "{$search}%");
                return;
            }

            if (preg_match('/\d{8}/', $search)) {
                $q->where('id_izin', 'like', "%{$search}%");
                return;
            }

            if (preg_match('/\d{2}\s\w{3}\s\d{4}/', $search)) {
                $q->whereDate('created', 'like', "%{$search}%");
                return;
            }

            // Only hit this if none above matched
            $q->where('nama', 'like', "{$search}%") // 🔥 prefix search
            ->orWhereHas('klasifikasi_kualifikasi.tuk', function ($tuk) use ($search) {
                $tuk->where('nama_tuk', 'like', "{$search}%"); // 🔥 prefix search
            });
        });
    }

    private function formatDate($date)
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return null;
        }
        return $date;
    }

    private function formatNumeric($value)
    {
        if (empty($value) || $value === '' || $value === null) {
            return null;
        }
        return is_numeric($value) ? $value : null;
    }
}
