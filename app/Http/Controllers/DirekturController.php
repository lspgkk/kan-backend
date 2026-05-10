<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\AsesorBnsp;
use App\Models\BaKomtek;
use App\Models\Barcode;
use App\Models\JabatanKerja;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Personal;
use App\Models\SkPelaksanaanUji;
use App\Models\SPTAsesor;
use App\Models\SPTKomtek;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DirekturController extends Controller
{
    public function jadwalFiles($id)
    {
        $jadwal = Jadwal::where('jadwal_id', $id)->first();

        $baKomtekModel = BaKomtek::where('jadwal_id', $id)->first();
        $ba_komtek = $baKomtekModel?->link;
        $spt_komtek = SPTKomtek::where('jadwal_id', $id)->value('link');
        $spt_asesor = SPTAsesor::where('jadwal_id', $id)->value('link');
        $sk_pelaksanaan_uji = SkPelaksanaanUji::where('jadwal_id', $id)->value('link');

        return response()->json([
            'message' => 'Success',
            'data' => [
                'sk_pelaksanaan_uji' => $sk_pelaksanaan_uji,
                'spt_asesor' => $spt_asesor,
                'spt_komtek' => $spt_komtek,
                'sk_komtek' => env('APP_URL') . '/files/sk_komtek.pdf',
                'ba_komtek' => $ba_komtek,
                'approval_status' => [
                    'sk_komtek' => $jadwal ? (bool) $jadwal->sk_komtek_direktur_approved : false,
                    'sk_pelaksanaan_uji' => $jadwal ? (bool) $jadwal->sk_pelaksanaan_uji_direktur_approved : false,
                    'spt_asesor' => $jadwal ? (bool) $jadwal->spt_asesor_direktur_approved : false,
                    'spt_komtek' => $jadwal ? (bool) $jadwal->spt_komtek_direktur_approved : false,
                    'ba_komtek' => [
                        'komtek1' => (bool) ($baKomtekModel->has_ttd_komtek1 ?? false),
                        'komtek2' => (bool) ($baKomtekModel->has_ttd_komtek2 ?? false),
                        'komtek3' => (bool) ($baKomtekModel->has_ttd_komtek3 ?? false),
                    ],
                ],
            ]
        ]);
    }

    public function approveBaKomtek($jadwal_id)
    {
        $user = auth()->user();

        // Verify user is a komtek (role_id=4)
        if ($user->role_id !== '4') {
            return response()->json(['message' => 'Invalid Role'], 403);
        }

        $jadwal = Jadwal::where('jadwal_id', $jadwal_id)->first();
        if (!$jadwal) {
            return response()->json(['message' => 'Jadwal tidak ditemukan'], 404);
        }

        // Determine which komtek position this user holds
        $field = null;
        if ((int) $jadwal->komtek1 === (int) $user->id) {
            $field = 'has_ttd_komtek1';
        } elseif ((int) $jadwal->komtek2 === (int) $user->id) {
            $field = 'has_ttd_komtek2';
        } elseif ((int) $jadwal->komtek3 === (int) $user->id) {
            $field = 'has_ttd_komtek3';
        }

        if (!$field) {
            return response()->json(['message' => 'Anda bukan komtek untuk jadwal ini'], 403);
        }

        // Get or create BA Komtek record
        $ba = BaKomtek::where('jadwal_id', $jadwal_id)->first();

        if (!$ba) {
            $last  = BaKomtek::latest('id')->first();
            $roman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];
            $now   = Carbon::now();
            $num   = $last ? ((int) explode('/', $last->no_surat)[0] + 1) : 1;
            $noSurat = "{$num}/BA-KOMTEK/{$roman[$now->month]}/{$now->year}";

            $ba = BaKomtek::create([
                'jadwal_id' => $jadwal_id,
                'no_surat'  => $noSurat,
                'link'      => '',
                'has_ttd_komtek1' => 0,
                'has_ttd_komtek2' => 0,
                'has_ttd_komtek3' => 0,
            ]);
        }

        // Mark this komtek as signed
        BaKomtek::where('jadwal_id', $jadwal_id)->update([
            $field => 1,
            'updated_at' => now(),
        ]);

        // -- Regenerate BA Komtek PDF with updated signatures --

        // Komtek names
        $komtekIds = array_filter([$jadwal->komtek1, $jadwal->komtek2, $jadwal->komtek3]);
        $komtekNames = User::whereIn('id', $komtekIds)->pluck('name', 'id');

        $komtek1Name = $komtekNames[$jadwal->komtek1] ?? null;
        $komtek2Name = $komtekNames[$jadwal->komtek2] ?? null;
        $komtek3Name = $komtekNames[$jadwal->komtek3] ?? null;

        // QR barcode for each komtek (generated earlier in KomtekController)
        $svg1 = $svg2 = $svg3 = null;

        $getBarcodeSvg = function ($komtekId) use ($jadwal_id) {
            if (!$komtekId) return null;
            $barcode = Barcode::where('id_user', $komtekId)
                ->where('id_jadwal', $jadwal_id)
                ->where('jenis_dokumen', 'BA Komtek')
                ->first();
            return $barcode
                ? '<img src="' . $barcode->url_image . '" width="100" height="100">'
                : null;
        };

        $svg1 = $getBarcodeSvg($jadwal->komtek1);
        $svg2 = $getBarcodeSvg($jadwal->komtek2);
        $svg3 = $getBarcodeSvg($jadwal->komtek3);

        // Peserta list
        $asesiList = AsesiJadwal::where('jadwal_id', $jadwal_id)->get();
        $nama_peserta = [];
        foreach ($asesiList as $asesi) {
            $nama = Personal::where('id_izin', $asesi->id_izin)->value('nama');
            $jabatanKerjaId = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->value('jabatan_kerja');
            $namaSkema = JabatanKerja::where('id_jabatan_kerja', $jabatanKerjaId)->value('jabatan_kerja');
            $nama_peserta[] = [
                'nama'     => $nama,
                'skema'    => $namaSkema,
                'kompeten' => $asesi->kompeten,
            ];
        }

        $tanggalBarcode = Carbon::parse($jadwal->tanggal_uji)
            ->addDay()
            ->setTimezone('Asia/Jakarta');

        $viewData = [
            'no_surat'       => $ba->no_surat,
            'komtek1'        => $komtek1Name,
            'komtek2'        => $komtek2Name,
            'komtek3'        => $komtek3Name,
            'hari_tanggal'   => $tanggalBarcode->isoFormat('D MMMM YYYY'),
            'jmlh_peserta'   => $asesiList->count(),
            'nama_peserta'   => $nama_peserta,
            'svg1'           => $svg1,
            'svg2'           => $svg2,
            'svg3'           => $svg3,
            'tanggal_surat'  => $tanggalBarcode->copy()->subDay(),
        ];

        $pdf = Pdf::loadView('template-surat.ba_komteks', $viewData);
        Storage::disk('str_moon')->put(
            "/ba_komtek/{$jadwal_id}.pdf",
            $pdf->output()
        );

        $pdfUrl = "https://moon.lspgatensi.id/files/new_gatensi/ba_komtek/{$jadwal_id}.pdf";

        BaKomtek::where('jadwal_id', $jadwal_id)->update([
            'link' => $pdfUrl,
        ]);

        return response()->json(['message' => 'BA Komtek berhasil disetujui']);
    }

    public function approveSkPelaksanaanUji($jadwal_id)
    {
        if ($error = $this->verifyDirektur($jadwal_id)) {
            return $error;
        }

        $qrData = $this->generateDirekturQr($jadwal_id, 'SK Pelaksanaan Uji');

        $jadwal = Jadwal::where('jadwal_id', $jadwal_id)->firstOrFail();

        $jadwal->update(['sk_pelaksanaan_uji_direktur_approved' => true]);

        // Get peserta list
        $asesiJadwals = AsesiJadwal::where('jadwal_id', $jadwal_id)->with(['personal'])->get();
        $peserta = [];
        foreach ($asesiJadwals as $asesi) {
            $jabatanKerjaId = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->value('jabatan_kerja');
            $namaSkema = JabatanKerja::where('id_jabatan_kerja', $jabatanKerjaId)->value('jabatan_kerja');
            $peserta[] = [
                'nama' => $asesi->personal->nama ?? '',
                'skema' => $namaSkema ?? '',
            ];
        }

        $sk = SkPelaksanaanUji::where('jadwal_id', $jadwal_id)->first();

        if ($sk) {
            $nomorSk = $sk->no_surat;
        } else {
            $last = SkPelaksanaanUji::latest('id')->first();
            $noSk = $last?->no_surat ?? '0/LSP-GATENSI/SK-UJI/I/2024';

            $romawi = [
                1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
                5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
                9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
            ];

            $parts = explode('/', $noSk);
            $year  = now()->year;

            $parts[0] = ((int)($parts[3] ?? 0) === $year) ? ((int)$parts[0] + 1) : 1;
            $parts[2] = $romawi[now()->month];
            $parts[3] = (string)$year;

            $nomorSk = implode('/', $parts);
        }

        $viewData = [
            'nomor_sk' => $nomorSk,
            'tanggal_uji' => Carbon::parse($jadwal->tanggal_uji)->isoFormat('dddd, D MMMM Y'),
            'peserta' => $peserta,
            'qr' => $qrData['base64'],
        ];

        $pdf = Pdf::loadView('template-surat.sk_pelaksanaan_uji', $viewData);
        $timestamp = now()->format('YmdHis');
        $path = "sk_pelaksanaan_uji/{$jadwal_id}_{$timestamp}.pdf";
        Storage::disk('str_moon')->put($path, $pdf->output());
        $fileUrl = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        SkPelaksanaanUji::updateOrCreate(
            ['jadwal_id' => $jadwal_id],
            ['no_surat' => $nomorSk, 'link' => $fileUrl, 'is_approved' => true]
        );

        return response()->json(['message' => 'SK Pelaksanaan Uji berhasil disetujui']);
    }

    public function approveSptAsesor($jadwal_id)
    {
        if ($error = $this->verifyDirektur($jadwal_id)) {
            return $error;
        }

        $qrData = $this->generateDirekturQr($jadwal_id, 'SPT Asesor');

        $jadwal = Jadwal::where('jadwal_id', $jadwal_id)->firstOrFail();
        $jadwal->update(['spt_asesor_direktur_approved' => true]);

        // Get asesor data
        $asesor1 = AsesorBnsp::where('id', $jadwal->asesor_id)->first();
        $asesor2 = $jadwal->asesor2_id ? AsesorBnsp::where('id', $jadwal->asesor2_id)->first() : null;

        $asesiJadwals = AsesiJadwal::where('jadwal_id', $jadwal_id)->with(['personal'])->get();
        $peserta = $asesiJadwals->pluck('personal.nama')->toArray();

        $spt = SPTAsesor::where('jadwal_id', $jadwal_id)->first();

        $viewData = [
            'no_surat' => $spt?->spt_no ?? '-',
            'nama_asesor' => $asesor1?->Nama ?? '',
            'nama_met' => $asesor1?->Noreg ?? '',
            'nama_asesor2' => $asesor2?->Nama ?? '',
            'nama_met2' => $asesor2?->Noreg ?? '',
            'nama_tuk' => '',
            'alamat_tuk' => '',
            'hari_tanggal' => Carbon::parse($jadwal->tanggal_uji)->isoFormat('dddd, D MMMM Y'),
            'jmlh_peserta' => count($peserta),
            'nama_skema' => '',
            'nama_peserta' => $peserta,
            'qr' => $qrData['html'],
        ];

        $pdf = Pdf::loadView('template-surat.spt_asesor', $viewData);
        $path = "spt_asesor/{$jadwal_id}.pdf";
        Storage::disk('str_moon')->put($path, $pdf->output());
        $fileUrl = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        SPTAsesor::updateOrCreate(
            ['jadwal_id' => $jadwal_id],
            ['link' => $fileUrl, 'spt_no' => $spt?->spt_no ?? '-', 'asesor_id' => $jadwal->asesor_id ?? 0]
        );

        return response()->json(['message' => 'SPT Asesor berhasil disetujui']);
    }

    public function approveSptKomtek($jadwal_id)
    {
        if ($error = $this->verifyDirektur($jadwal_id)) {
            return $error;
        }

        $qrData = $this->generateDirekturQr($jadwal_id, 'SPT Komtek');

        $jadwal = Jadwal::where('jadwal_id', $jadwal_id)->firstOrFail();
        $jadwal->update(['spt_komtek_direktur_approved' => true]);

        // Get komtek names
        $getNamaKomtek = function ($id) {
            return $id ? User::where('id', $id)->value('name') : null;
        };

        $asesiJadwals = AsesiJadwal::where('jadwal_id', $jadwal_id)->with(['personal'])->get();
        $peserta = [];
        foreach ($asesiJadwals as $asesi) {
            $jabatanKerjaId = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->value('jabatan_kerja');
            $namaSkema = JabatanKerja::where('id_jabatan_kerja', $jabatanKerjaId)->value('jabatan_kerja');
            $peserta[] = [
                'nama' => $asesi->personal->nama ?? '',
                'skema' => $namaSkema ?? '',
            ];
        }

        $spt = SPTKomtek::where('jadwal_id', $jadwal_id)->first();

        $viewData = [
            'nomor' => $spt?->no_surat ?? '-',
            'tanggal_surat' => now()->isoFormat('dddd, D MMMM Y'),
            'nama_komtek1' => $getNamaKomtek($jadwal->komtek1),
            'nama_komtek2' => $getNamaKomtek($jadwal->komtek2),
            'nama_komtek3' => $getNamaKomtek($jadwal->komtek3),
            'peserta' => $peserta,
            'qr' => $qrData['html'],
        ];

        $pdf = Pdf::loadView('template-surat.spt_komtek', $viewData);
        $path = "spt_komtek/{$jadwal_id}.pdf";
        Storage::disk('str_moon')->put($path, $pdf->output());
        $fileUrl = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        SPTKomtek::updateOrCreate(
            ['jadwal_id' => $jadwal_id],
            ['link' => $fileUrl, 'no_surat' => $spt?->no_surat ?? '-']
        );

        return response()->json(['message' => 'SPT Komtek berhasil disetujui']);
    }

    /**
     * Verify current user is Direktur (role_id=6) and owns this jadwal
     */
    private function verifyDirektur($jadwal_id)
    {
        if (auth()->user()->role_id !== '6') {
            return response()->json(['message' => 'Invalid Role'], 403);
        }

        $jadwal = Jadwal::where('jadwal_id', $jadwal_id)->first();

        if (!$jadwal) {
            return response()->json(['message' => 'Jadwal tidak ditemukan'], 404);
        }

        if ((int) $jadwal->ketua_lsp !== (int) auth()->id()) {
            return response()->json(['message' => 'Anda bukan ketua LSP untuk jadwal ini'], 403);
        }

        return null;
    }

    /**
     * Generate QR barcode for Direktur approval signature
     */
    private function generateDirekturQr($jadwal_id, $jenisDokumen)
    {
        $uuid = (string) \Ramsey\Uuid\Uuid::uuid4();
        $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";

        $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);

        $filename = "direktur_" . \Illuminate\Support\Str::uuid() . '.png';
        $path = "barcodes/{$filename}";
        Storage::disk('str_moon')->put($path, $qrImage);

        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        Barcode::create([
            'id_user'       => auth()->id(),
            'id_jadwal'     => $jadwal_id,
            'jenis_dokumen' => $jenisDokumen,
            'url_ttd'       => $urlTtd,
            'url_image'     => $urlImage,
            'id_role'       => 6,
        ]);

        return [
            'html'   => '<img src="' . $urlImage . '" width="100" height="100">',
            'base64' => base64_encode($qrImage),
        ];
    }
}
