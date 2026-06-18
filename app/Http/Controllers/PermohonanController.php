<?php

namespace App\Http\Controllers;

use App\Models\JabatanKerja;
use App\Models\Jenjang;
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
use App\Services\PermohonanSyncService;

class PermohonanController extends Controller
{
    protected PermohonanSyncService $syncService;

    public function __construct(PermohonanSyncService $syncService)
    {
        set_time_limit(12000);
        $this->syncService = $syncService;
    }

    public function detailPermohonan(string $id)
    {
        $personals = Personal::forUserTuk()->with(['province', 'city'])->where('id_izin', $id)->get();

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
            $item->jenjang_nama = Jenjang::where('id_siki', $item->jenjang)->value('nama');
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
        return $this->basePermohonanList($request, 'verifikasi', lastSixMonths: true);
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
            if (!array_key_exists($type, PermohonanSyncService::SKK_TYPES)) {
                return response()->json([
                    'message' => 'Invalid type'
                ], 422);
            }

            return $this->syncPermohonanByType($type);
        }

        // sync ALL
        $success = 0;
        $errors = [];

        foreach (PermohonanSyncService::SKK_TYPES as $type => $endpoint) {
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
        $endpoint = PermohonanSyncService::SKK_TYPES[$type];

        try {
            $this->syncService->syncSinglePermohonanData($id_izin, $type, $endpoint, $baseUrl);

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

    /**
     * Refetch (overwrite) existing permohonan data from SIKI for a given id_izin.
     * Same as syncSinglePermohonan but deletes existing child records first
     * so the local data is a full refresh from SIKI.
     */
    public function refetchPermohonan(Request $request)
    {
        $validated = $request->validate([
            'id_izin' => 'required|string',
            'type' => 'required|string|in:reguler,balai,fg',
            'base_url' => 'nullable|string',
        ]);

        $id_izin = $validated['id_izin'];
        $type = $validated['type'];
        $baseUrl = $validated['base_url'] ?? 'https://siki.pu.go.id/siki-api/v1';
        $endpoint = PermohonanSyncService::SKK_TYPES[$type];

        if (!Personal::where('id_izin', $id_izin)->exists()) {
            return response()->json([
                'message' => 'Data not found locally. Use sync/single instead.',
                'id_izin' => $id_izin,
            ], 404);
        }

        try {
            $this->syncService->refetchSinglePermohonanData($id_izin, $type, $endpoint, $baseUrl);

            return response()->json([
                'message' => 'Permohonan data refetched and overwritten successfully',
                'id_izin' => $id_izin,
                'type' => $type,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to refetch permohonan data',
                'id_izin' => $id_izin,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function syncPermohonanByType(string $type)
    {
        $endpoint = PermohonanSyncService::SKK_TYPES[$type];
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
        $idIzins = collect($items)
            ->filter(fn ($i) =>
                !isset($i['status']) || is_null($i['status'])
            )
            ->filter(fn ($i) =>
                isset($i['created_at']) &&
                Carbon::parse($i['created_at'])->greaterThanOrEqualTo(now()->subMonths(2)->startOfMonth())
            )
            ->pluck('id_izin')
            ->values();

        foreach ($idIzins as $id_izin) {

            if (Personal::where('id_izin', $id_izin)->exists()) {
                continue;
            }

            try {
                $this->syncService->syncSinglePermohonanData($id_izin, $type, $endpoint, $baseUrl);
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

    private function basePermohonanList(
        Request $request,
        string $status = null,
        bool $lastSixMonths = false,
    ) {
        $type = $request->type;

        if (!in_array($type, ['reguler', 'balai', 'fg'], true)) {
            return response()->json(['message' => 'Invalid type'], 422);
        }

        $query = Personal::forUserTuk()->with('klasifikasi_kualifikasi.tuk')
            ->where('type', $type)
            ->orderBy('created', 'desc');

        if ($lastSixMonths) {
            $query->where('created', '>=', now()->subMonths(6));
        }

        if ($status === 'verifikasi') {
            $query->whereNull('status');
        } else if ($status === 'validasi') {
            $query->where('status', 20);
        }

        if ($request->filled('tuk_id')) {
            $query->whereHas('klasifikasi_kualifikasi', function ($q) use ($request) {
                $q->where('tuk', $request->tuk_id);
            });
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
            $q->where('nama', 'like', "{$search}%")
              ->orWhereIn('id_izin', function ($sq) use ($search) {
                  $sq->select('kk.id_izin')
                      ->from('klasifikasi_kualifikasis as kk')
                      ->join('mytuk', 'mytuk.id', '=', 'kk.tuk')
                      ->where('mytuk.nama_tuk', 'like', "{$search}%");
              });
        });
    }
}
