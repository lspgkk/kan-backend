<?php

namespace App\Http\Controllers;

use App\Models\AbsenKomtek;
use App\Models\AsesiJadwal;
use App\Models\BaKomtek;
use App\Models\Barcode;
use App\Models\JabatanKerja;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\KomtekRecommendation;
use App\Models\Personal;
use App\Models\SkKomtek;
use App\Models\SPTKomtek;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class KomtekController extends Controller
{
    public function index(Request $request)
    {
        // If frontend explicitly asks for all data
        if ($request->boolean('all')) {
            $komteks = User::where('role_id', 4)->select(['id', 'name'])->get();

            return response()->json([
                'status' => 'success',
                'data' => $komteks,
                'meta' => [
                    'all' => true,
                    'total' => $komteks->count(),
                ],
            ]);
        }

        // Normal pagination
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $komteks = User::where('role_id', 4)->select(['id', 'name'])->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $komteks,
        ]);
    }

    public function getRekomendasi($id)
    {
        $jadwal_id = AsesiJadwal::where('id_izin', $id)->value('jadwal_id');
        $jadwal = Jadwal::where('jadwal_id', $jadwal_id)->firstOrFail();

        $komtekIds = array_filter([
            $jadwal->komtek1,
            $jadwal->komtek2,
            $jadwal->komtek3,
        ]);

        $recommendations = KomtekRecommendation::where('id_izin', $id)
            ->whereIn('id_komtek', $komtekIds)
            ->get()
            ->keyBy('id_komtek');

        return response()->json([
            'komtek1' => [
                'id' => $jadwal->komtek1,
                'rekomendasi' => $recommendations[$jadwal->komtek1]->rekomendasi ?? null,
            ],
            'komtek2' => [
                'id' => $jadwal->komtek2,
                'rekomendasi' => $recommendations[$jadwal->komtek2]->rekomendasi ?? null,
            ],
            'komtek3' => [
                'id' => $jadwal->komtek3,
                'rekomendasi' => $recommendations[$jadwal->komtek3]->rekomendasi ?? null,
            ],
        ]);
    }

    public function komtekFiles($id)
    {
        $ba = BaKomtek::where('jadwal_id', $id)->first();
        $spt_komtek = SPTKomtek::where('jadwal_id', $id)->value('link');
        $absen_komtek = AbsenKomtek::where('jadwal_id', $id)->value('link');

        return response()->json([
            'ba_komtek' => $ba?->link,
            'ba_komtek_no_surat' => $ba?->no_surat,
            'ba_komtek_ttd_progress' => [
                'komtek1' => (bool) ($ba->has_ttd_komtek1 ?? false),
                'komtek2' => (bool) ($ba->has_ttd_komtek2 ?? false),
                'komtek3' => (bool) ($ba->has_ttd_komtek3 ?? false),
            ],
            'spt_komtek' => $spt_komtek,
            'sk_komtek' => env('APP_URL') . '/files/sk_komtek.pdf',
            'pleno_komtek' => $absen_komtek
        ]);
    }

    public function rekomendasi(Request $request, $id)
    {
        $validated = $request->validate([
            'kompeten' => 'required|boolean',
        ]);

        /**
         * 0. Get jadwal_id from AsesiJadwal (SOURCE OF TRUTH)
         */
        $asesiJadwal = AsesiJadwal::where('id_izin', $id)->firstOrFail();
        $jadwal_id   = $asesiJadwal->jadwal_id;

        /**
         * 1. Save / update recommendation (current komtek)
         */
        KomtekRecommendation::updateOrCreate(
            [
                'id_izin'   => $id,
                'id_komtek' => auth()->id(),
            ],
            [
                'rekomendasi' => $validated['kompeten'] ? 'K' : 'BK',
            ]
        );

        /**
         * 2. BA Komtek number
         */
        $ba = BaKomtek::where('jadwal_id', $jadwal_id)->first();

        if ($ba) {
            $no_ba_komtek = $ba->no_surat;
        } else {
            $last  = BaKomtek::latest('id')->first();
            $roman = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII'];
            $now   = Carbon::now();

            $num = $last ? ((int) explode('/', $last->no_surat)[0] + 1) : 1;
            $no_ba_komtek = "{$num}/BA-KOMTEK/{$roman[$now->month]}/{$now->year}";

            BaKomtek::create([
                'jadwal_id' => $jadwal_id,
                'no_surat'  => $no_ba_komtek,
            ]);
        }

        /**
         * 3. Get jadwal & komtek list
         */
        $jadwal = Jadwal::where('jadwal_id', $jadwal_id)->firstOrFail();

        $komtek1 = $jadwal->komtek1;
        $komtek2 = $jadwal->komtek2;
        $komtek3 = $jadwal->komtek3;

        $komtekIds = array_filter([$jadwal->komtek1, $jadwal->komtek2, $jadwal->komtek3]);

        $komtekNames = \App\Models\User::whereIn('id', $komtekIds)
            ->pluck('name', 'id');

        $komtek1Name = $komtekNames[$jadwal->komtek1] ?? null;
        $komtek2Name = $komtekNames[$jadwal->komtek2] ?? null;
        $komtek3Name = $komtekNames[$jadwal->komtek3] ?? null;

        /**
         * 4. Get all recommendations for this asesi
         */
        $recommendations = KomtekRecommendation::where('id_izin', $id)
            ->get()
            ->keyBy('id_komtek');

        /**
         * 5. Get asesi list from database
         */
        $asesiList  = AsesiJadwal::where('jadwal_id', $jadwal_id)->get();
        $count_data = $asesiList->count();

        /**
         * 6. Build peserta list
         */
        $nama_peserta = [];

        foreach ($asesiList as $asesi) {

            $nama = Personal::where('id_izin', $asesi->id_izin)
                ->value('nama');

            $jabatanKerjaId = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)
                ->value('jabatan_kerja');

            $namaSkema = JabatanKerja::where('id_jabatan_kerja', $jabatanKerjaId)
                ->value('jabatan_kerja');

            $nama_peserta[] = [
                'nama'     => $nama,
                'skema'    => $namaSkema,
                'kompeten' => $asesi->kompeten,
            ];
        }

        /**
         * 7. Conditional barcode generation (FIXED)
         */
        $svg1 = $svg2 = $svg3 = null;

        $tanggalBarcode = Carbon::parse($jadwal->tanggal_uji)
            ->addDay()
            ->setTimezone('Asia/Jakarta');

        $makeBarcode = function ($komtekId, &$svgVar) use ($jadwal_id) {

            if (!$komtekId) return;

            // 1️⃣ Check existing barcode (per komtek + BA Komtek)
            $barcode = Barcode::where('id_user', $komtekId)
                ->where('jenis_dokumen', 'BA Komtek')
                ->first();

            if (!$barcode) {

                // 2️⃣ Generate UUID & URL TTD
                $uuid   = (string) \Ramsey\Uuid\Uuid::uuid4();
                $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";

                // 3️⃣ Generate filename
                $filename = "ba_komtek_" . \Illuminate\Support\Str::uuid() . '.png';

                // 4️⃣ Generate QR image
                $qrImage = QrCode::format('png')
                    ->size(300)
                    ->margin(1)
                    ->generate($urlTtd);

                // 5️⃣ FTP path
                $path = "barcodes/{$filename}";

                // 6️⃣ Upload to FTP
                Storage::disk('str_moon')->put($path, $qrImage);

                // 7️⃣ Public image URL
                $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

                // 8️⃣ Save to DB
                $barcode = Barcode::create([
                    'id_user'       => $komtekId,
                    'id_jadwal'     => $jadwal_id,
                    'jenis_dokumen' => 'BA Komtek',
                    'url_ttd'       => $urlTtd,
                    'url_image'     => $urlImage,
                    'id_role'       => 4, // komtek
                ]);
            }

            // 9️⃣ Embed QR into PDF
            $svgVar = '<img src="' . $barcode->url_image . '" width="100" height="100">';
        };

        if (isset($recommendations[$komtek1])) $makeBarcode($komtek1, $svg1);
        if (isset($recommendations[$komtek2])) $makeBarcode($komtek2, $svg2);
        if (isset($recommendations[$komtek3])) $makeBarcode($komtek3, $svg3);

        /**
         * 8. PDF payload (UNCHANGED)
         */
        $result = [
            "no_surat"       => $no_ba_komtek,
            "komtek1"        => $komtek1Name,
            "komtek2"        => $komtek2Name,
            "komtek3"        => $komtek3Name,
            "hari_tanggal"  => $tanggalBarcode->isoFormat('D MMMM YYYY'),
            "jmlh_peserta"  => $count_data,
            "nama_peserta"  => $nama_peserta,
            "svg1"          => $svg1,
            "svg2"          => $svg2,
            "svg3"          => $svg3,
            "tanggal_surat" => $tanggalBarcode->copy()->subDay(),
        ];

        /**
         * 9. Generate & save PDF
         */
        $pdf = Pdf::loadView('template-surat.ba_komteks', $result);

        Storage::disk('str_moon')->put(
            "/ba_komtek/{$jadwal_id}.pdf",
            $pdf->output()
        );
        /**
         * 10. Update BA Komtek after PDF generated
         */

        // public PDF URL
        $pdfPath = "/ba_komtek/{$jadwal_id}.pdf";
        $pdfUrl  = "https://moon.lspgatensi.id/files/new_gatensi{$pdfPath}";

        // detect which komtek already signed
        $hasKomtek1 = isset($recommendations[$komtek1]) ? 1 : 0;
        $hasKomtek2 = isset($recommendations[$komtek2]) ? 1 : 0;
        $hasKomtek3 = isset($recommendations[$komtek3]) ? 1 : 0;

        BaKomtek::where('jadwal_id', $jadwal_id)->update([
            'link'              => $pdfUrl,
            'has_ttd_komtek1'   => $hasKomtek1,
            'has_ttd_komtek2'   => $hasKomtek2,
            'has_ttd_komtek3'   => $hasKomtek3,
            'inputby_id'        => auth()->id(),
            'updated_at'        => now(),
        ]);

        return response()->json([
            'message' => 'Rekomendasi berhasil disimpan',
        ]);
    }
}
