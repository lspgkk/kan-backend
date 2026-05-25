<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\BaKomtek;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Personal;
use App\Models\SkPelaksanaanUji;
use App\Models\SuratBlanko;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use App\Traits\BantuanTrait;
use App\Traits\ResolvesKomtekDecision;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BlankoController extends Controller
{
    use BantuanTrait, ResolvesKomtekDecision;

    private const SLEEP_SHORT = 500;
    private const SLEEP_MEDIUM = 2000;

    private function bnspHeaders(string $authData): array
    {
        return [
            'Content-Type' => 'application/json',
            'x-authorization' => $authData,
        ];
    }

    private function resolveSkemaBnsp(string $jabatanKerja, string $authData): int
    {
        $url = 'https://konstruksi.bnsp.go.id/api/v1/skema?kode_lpjk=' . $jabatanKerja;

        $response = rescue(function () use ($authData, $url) {
            return Http::withHeaders($this->bnspHeaders($authData))->get($url);
        }, function ($e) {
            return $e->getMessage();
        });

        $response_data = is_string($response) || !$response ? null : $response->json();

        if ($response_data && ($response_data['code'] ?? '') != 'ERR') {
            $id_skema = (int) ($response_data['data']['id'] ?? 0);
            if ($jabatanKerja == 'SI013041') {
                $id_skema = 63538;
            }
            return $id_skema;
        }

        if ($jabatanKerja == 'SI101015') {
            return 63566;
        }
        if ($jabatanKerja == 'SI013041') {
            return 63538;
        }

        throw new \RuntimeException('Error skema bnsp: id skema tidak ditemukan');
    }

    private function fetchBnspJadwal(string $jadwalId, string $authData): array
    {
        $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/blanko?jadwal_id=' . $jadwalId;

        $response = rescue(function () use ($authData, $url) {
            return Http::withHeaders($this->bnspHeaders($authData))->get($url);
        }, function ($e) {
            return $e->getMessage();
        });

        usleep(self::SLEEP_SHORT);

        $response_jadwal = is_string($response) || !$response ? null : $response->json();

        if (!$response_jadwal || ($response_jadwal['code'] ?? '') == 'ERR') {
            throw new \RuntimeException('Error surat tugas: ID Jadwal tidak ditemukan');
        }

        if (($response_jadwal['message'] ?? '') == 'Unauthorized') {
            Log::error('BNSP authorization expired! Generate through manual through artisan');
            abort(500);
        }

        return $response_jadwal;
    }

    private function postHasilUji(array $data, string $authData): array
    {
        $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/peserta/hasil-uji';

        $response = rescue(function () use ($authData, $url, $data) {
            return Http::withHeaders($this->bnspHeaders($authData))->post($url, $data);
        }, function ($e) {
            return $e->getMessage();
        });

        usleep(self::SLEEP_MEDIUM);

        if (is_string($response) || !$response) {
            throw new \RuntimeException('Error BNSP: gagal konek ke BNSP ' . ($data['nik_peserta'] ?? ''));
        }

        $responseData = $response->json();

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Error BNSP: ' . ($responseData['message'] ?? 'tidak diketahui') . ' ' . ($data['nik_peserta'] ?? '')
            );
        }

        if (($responseData['message'] ?? '') == 'Unauthorized') {
            Log::error('BNSP authorization expired! Generate through manual through artisan');
            abort(500);
        }

        return $responseData;
    }

    private function buildHasilUjiPayload(
        string $jadwalId,
        object $personal,
        object $aplFoto,
        int $idSkemaBnsp,
        int $kompeten
    ): array {
        return [
            'jadwal_id' => $jadwalId,
            'nik_peserta' => $personal->nik,
            'kompeten' => $kompeten,
            'skema_id' => $idSkemaBnsp,
            'metode_uji' => 'observasi',
            'uji_praktek_atau_observasi_lapangan' => 1,
            'uji_tulis' => 1,
            'uji_lisan' => 1,
            'wawancara' => 0,
            'penyelenggaraan_uji' => 1,
            'apl_01' => $kompeten === 1 ? $aplFoto->url_apl_01 : '',
            'apl_02' => $kompeten === 1 ? $aplFoto->url_apl_02 : '',
            'url_dokumentasi_asesmen' => $kompeten === 1 ? $aplFoto->url_docs_asesmen : '',
        ];
    }

    public function submitHasilUji(Request $request, $jadwal_id)
    {
        $allAsesiJadwal = AsesiJadwal::where('jadwal_id', $jadwal_id)->get();

        if ($allAsesiJadwal->isEmpty()) {
            return response()->json(['message' => 'Tidak ada asesi untuk jadwal ini'], 422);
        }

        // Check uploaded asesmen for all asesi
        foreach ($allAsesiJadwal as $asesiJadwal) {
            $id_izin = $asesiJadwal->id_izin;

            $asesmen_exist = DB::table('bukti_asesmens')->where('id_izin', $id_izin)->first();
            if (!$asesmen_exist) {
                return response()->json(['message' => $id_izin . ' belum mengupload bukti assesmen!'], 422);
            }

            if ($asesmen_exist->new_ia_05 == null and $asesmen_exist->new_ia_03) {
                return response()->json(['message' => $id_izin . ' belum mengupload IA 05 dan IA 03 baru!'], 422);
            }
        }

        $asesiKompeten = $allAsesiJadwal->filter(fn($a) => $this->resolveKomtekDecision($a->id_izin) === 'K');
        $asesiBelumKompeten = $allAsesiJadwal->filter(fn($a) => $this->resolveKomtekDecision($a->id_izin) === 'BK');

        // Fetch auth once, reuse everywhere
        $authData = DB::table('b_n_s_p_auths')->select('x_authorization')->first()->x_authorization;

        // Validate jadwal exists on BNSP once (before both loops and blanko section)
        $responseJadwal = $this->fetchBnspJadwal($jadwal_id, $authData);

        // Validate tanggal uji before processing
        $tgl_uji = Carbon::parse($responseJadwal['dataJadwal']['tanggal_mulai'] ?? 'now');
        if (Carbon::now()->lt($tgl_uji)) {
            return response()->json([
                'message' => 'Tidak boleh melakukan pengajuan, belum uji di tanggal ' . ($responseJadwal['dataJadwal']['tanggal_mulai'] ?? '')
            ], 422);
        }

        // Process competent (K) asesi
        foreach ($asesiKompeten as $asesiJadwal) {
            $personal = Personal::where('id_izin', $asesiJadwal->id_izin)->first();
            $klas_kua = KlasifikasiKualifikasi::select(['jabatan_kerja'])->where('id_izin', $asesiJadwal->id_izin)->first();
            $apl_foto = DB::table('bukti_asesmens')->where('id_izin', $asesiJadwal->id_izin)->first();

            try {
                $id_skema_bnsp = $this->resolveSkemaBnsp($klas_kua->jabatan_kerja, $authData);

                usleep(self::SLEEP_SHORT);

                $payload = $this->buildHasilUjiPayload($jadwal_id, $personal, $apl_foto, $id_skema_bnsp, 1);

                DB::beginTransaction();
                $responseData = $this->postHasilUji($payload, $authData);

                if (($responseData['code'] ?? '') == 'OK') {
                    AsesiJadwal::where('id_izin', $asesiJadwal->id_izin)->update(['kompeten' => 'K']);
                    DB::commit();
                } else {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Error BNSP: ' . ($responseData['message'] ?? 'tidak diketahui') . ' ' . $asesiJadwal->id_izin
                    ], 422);
                }
            } catch (\RuntimeException $e) {
                DB::rollBack();
                return response()->json(['message' => $e->getMessage()], 422);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Terjadi kesalahan saat hasil uji 1 - K (' . $e->getMessage() . ')'
                ], 422);
            }

            usleep(self::SLEEP_SHORT);
        }

        // Process not competent (BK) asesi
        foreach ($asesiBelumKompeten as $asesiJadwal) {
            $personal = Personal::where('id_izin', $asesiJadwal->id_izin)->first();
            $klas_kua = KlasifikasiKualifikasi::select(['jabatan_kerja'])->where('id_izin', $asesiJadwal->id_izin)->first();
            $apl_foto = DB::table('bukti_asesmens')->where('id_izin', $asesiJadwal->id_izin)->first();

            try {
                $id_skema_bnsp = $this->resolveSkemaBnsp($klas_kua->jabatan_kerja, $authData);

                usleep(self::SLEEP_SHORT);

                $payload = $this->buildHasilUjiPayload($jadwal_id, $personal, $apl_foto, $id_skema_bnsp, 2);

                $this->postHasilUji($payload, $authData);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Terjadi kesalahan saat hasil uji 2 - BK (' . $e->getMessage() . ')'
                ], 422);
            }
        }

        /* CREATE BLANKO */
        $belum_kompeten = $asesiBelumKompeten->count();
        $kompeten = $asesiKompeten->count();

        $ba_komtek = BaKomtek::where('jadwal_id', $jadwal_id)->first();
        if (!$ba_komtek || !$ba_komtek->no_surat) {
            return response()->json(['message' => 'BA Komtek belum tersedia'], 422);
        }

        $sk_pelaksanaan = SkPelaksanaanUji::where('jadwal_id', $jadwal_id)->first();
        if (!$sk_pelaksanaan || !$sk_pelaksanaan->no_surat) {
            return response()->json(['message' => 'SK Pelaksanaan Uji belum tersedia'], 422);
        }

        $tanggal_surat_bnsp = Carbon::parse($responseJadwal['dataJadwal']['tanggal_mulai'])->setTimezone('Asia/Jakarta');

        // Permohonan Blanko - nomor surat
        $check_blanko = SuratBlanko::where('jadwal_id', $jadwal_id)->first();
        if ($check_blanko && $check_blanko->no_surat) {
            $no_surat = $check_blanko->no_surat;
        } else {
            $defaultSurat = '1/PB/LSP - Gatensi/I/2025';

            $no_surat = SuratBlanko::whereYear('created_at', Carbon::now()->year)
                ->latest('id')->value('no_surat') ?? $defaultSurat;

            $no_surat_balai = rescue(
                fn() => DB::connection('balai')->table('surat_blankos')
                    ->select('no_surat')->whereYear('created_at', Carbon::now()->year)
                    ->latest('id')->value('no_surat'),
                $defaultSurat
            );

            $no_surat = explode('/', $no_surat);
            $no_surat_balai = explode('/', $no_surat_balai);

            if (count($no_surat) < 5) $no_surat = explode('/', $defaultSurat);
            if (count($no_surat_balai) < 5) $no_surat_balai = explode('/', $defaultSurat);

            if ((int) $no_surat_balai[0] > (int) $no_surat[0]) {
                $no_surat = $no_surat_balai;
            }

            $currentYear = now()->year;

            if ((int) $no_surat[4] == $currentYear) {
                $no_surat[0] = (string) ((int) $no_surat[0] + 1);
            } else {
                $no_surat[0] = '1';
            }

            $no_surat[3] = $this->bulanRomawi(now()->month);
            $no_surat[4] = (string) $currentYear;
            $no_surat = implode('/', $no_surat);
        }

        $barcodeKetua = (string) Uuid::uuid4();
        $qrSvg = '<img src="data:image/svg+xml;base64,'
            . base64_encode(QrCode::margin(0)->size(70)->generate("https://barcode.lspgatensi.id/$barcodeKetua"))
            . '" width="100" height="100" />';

        $pdfData = [
            'no_surat' => $no_surat,
            'kompeten' => $kompeten,
            'belum_kompeten' => $belum_kompeten,
            'svg' => $qrSvg,
            'tanggal_surat' => $tanggal_surat_bnsp,
        ];

        $pdf_sk = PDF::loadView('template-surat.permohonan_blanko', $pdfData);
        Storage::disk('str_moon')->put(
            'surat_blanko/' . trim($jadwal_id) . '.pdf',
            $pdf_sk->output()
        );

        $full_url_file_blanko = 'https://moon.lspgatensi.id/files/new_gatensi/surat_blanko/' . trim($jadwal_id) . '.pdf';

        try {
            $url = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/blanko';

            $request_create_blanko = [
                [
                    'jadwal_id' => $jadwal_id,
                    'form_id' => 1,
                    'nama' => 'Nomor Permohonan',
                    'nomor' => $no_surat,
                    'tanggal' => $tanggal_surat_bnsp->copy()->addDay()->isoFormat('YYYY-MM-DD'),
                    'file_dokumen' => $full_url_file_blanko,
                ],
                [
                    'jadwal_id' => $jadwal_id,
                    'form_id' => 2,
                    'nama' => 'Berita Acara Pleno Komite Teknis',
                    'nomor' => $ba_komtek->no_surat,
                    'tanggal' => $tanggal_surat_bnsp->copy()->addDay()->isoFormat('YYYY-MM-DD'),
                    'file_dokumen' => $ba_komtek->link,
                ],
                [
                    'jadwal_id' => $jadwal_id,
                    'form_id' => 3,
                    'nama' => 'SK Hasil Sertifikasi Kompetensi',
                    'nomor' => $sk_pelaksanaan->no_surat,
                    'tanggal' => $tanggal_surat_bnsp->copy()->addDay()->isoFormat('YYYY-MM-DD'),
                    'file_dokumen' => $sk_pelaksanaan->link,
                ],
            ];

            DB::beginTransaction();
            $response_post_bnsp = Http::retry(2, 100)
                ->withHeaders($this->bnspHeaders($authData))
                ->post($url, $request_create_blanko);

            if ($response_post_bnsp->successful()) {
                $responseData = $response_post_bnsp->json();
                if (($responseData['code'] ?? '') == 'OK') {
                    SuratBlanko::updateOrCreate(
                        ['jadwal_id' => $jadwal_id],
                        [
                            'link' => $full_url_file_blanko,
                            'no_surat' => $no_surat,
                            'inputby_id' => Auth::user()->id,
                        ]
                    );
                    DB::commit();

                    Jadwal::where('id', $jadwal_id)->update(['status' => 'get-blanko']);
                } elseif (($responseData['message'] ?? '') == 'Unauthorized') {
                    Log::error('BNSP authorization expired! Generate through manual through artisan');
                    DB::rollBack();
                    abort(500);
                } else {
                    Log::warning('Blanko BNSP unexpected response: ' . json_encode($responseData));
                    return response()->json([
                        'message' => 'Error BNSP: respons tidak terduga',
                        'detail' => $responseData,
                    ], 422);
                }
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => '3. Error BNSP: ' . ($response_post_bnsp->json()['message'] ?? 'tidak diketahui')
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat create blanko (' . $e->getMessage() . ')'
            ], 422);
        }

        /* CONFIRM JADWAL */
        try {
            $response_post_bnsp = Http::retry(2, 100)
                ->withHeaders($this->bnspHeaders($authData))
                ->post('https://konstruksi.bnsp.go.id/api/v1/jadwal/confirm', [
                    'jadwal_id' => $jadwal_id,
                ]);

            if ($response_post_bnsp->successful()) {
                $responseData = $response_post_bnsp->json();
                if (($responseData['code'] ?? '') == 'OK') {
                    return response()->json([
                        'message' => 'Berhasil submit hasil uji 1/create blanko/confirm jadwal ke BNSP!'
                    ]);
                } elseif (($responseData['message'] ?? '') == 'Unauthorized') {
                    Log::error('BNSP authorization expired! Generate through manual through artisan');
                    abort(500);
                } else {
                    Log::info(str($responseData));
                    return response()->json([
                        'message' => 'Terjadi kesalahan post bnsp, ' . str($responseData)
                    ], 422);
                }
            } else {
                return response()->json([
                    'message' => '4. Error BNSP: ' . ($response_post_bnsp->json()['message'] ?? 'tidak diketahui')
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat confirm jadwal (' . $e->getMessage() . ')'
            ], 422);
        }
    }
}
