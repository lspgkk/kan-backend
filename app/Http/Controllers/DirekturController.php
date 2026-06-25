<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\AsesorBnsp;
use App\Models\BaKomtek;
use App\Models\Barcode;
use App\Models\SkPenetapan;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Traits\BantuanTrait;
use App\Traits\ResolvesKomtekDecision;

class DirekturController extends Controller
{
    use BantuanTrait, ResolvesKomtekDecision;
    public function jadwalFiles($id)
    {
        $jadwal = Jadwal::where('jadwal_id', $id)->first();

        $baKomtekModel = BaKomtek::where('jadwal_id', $id)->first();
        $ba_komtek = $baKomtekModel?->link;
        $spt_komtek = SPTKomtek::where('jadwal_id', $id)->value('link');
        $spt_asesor = SPTAsesor::where('jadwal_id', $id)->value('link');
        $sk_pelaksanaan_uji = SkPelaksanaanUji::where('jadwal_id', $id)->value('link');
        $skPenetapanModel = SkPenetapan::where('jadwal_id', $id)->first();
        $sk_penetapan = $skPenetapanModel?->link;

        return response()->json([
            'message' => 'Success',
            'data' => [
                'sk_pelaksanaan_uji' => $sk_pelaksanaan_uji,
                'spt_asesor' => $spt_asesor,
                'spt_komtek' => $spt_komtek,
                'sk_komtek' => env('APP_URL') . '/files/sk_komtek.pdf',
                'ba_komtek' => $ba_komtek,
                'sk_penetapan' => $sk_penetapan,
                'approval_status' => [
                    'sk_komtek' => $jadwal ? (bool) $jadwal->sk_komtek_direktur_approved : false,
                    'sk_pelaksanaan_uji' => $jadwal ? (bool) $jadwal->sk_pelaksanaan_uji_direktur_approved : false,
                    'spt_asesor' => $jadwal ? (bool) $jadwal->spt_asesor_direktur_approved : false,
                    'spt_komtek' => $jadwal ? (bool) $jadwal->spt_komtek_direktur_approved : false,
                    'sk_penetapan' => [
                        'asesi_status' => $jadwal ? AsesiJadwal::where('jadwal_id', $jadwal->jadwal_id)
                            ->pluck('rekomen_direktur', 'id_izin')
                            ->map(fn($v) => !is_null($v))
                            ->toArray() : [],
                    ],
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

        if ($ba && (!$ba->no_surat || !str_contains($ba->no_surat, 'LSP GKK'))) {
            // Stale record with placeholder number — regenerate
            $last  = BaKomtek::latest('id')->first();
            $now   = Carbon::now();
            $num   = $last ? ((int) explode('/', $last->no_surat)[0] + 1) : 1;
            $ba->update(['no_surat' => "{$num}/LSP GKK B.002-A/{$this->bulanRomawi($now->month)}/{$now->year}"]);
        }

        if (!$ba) {
            $last  = BaKomtek::latest('id')->first();
            $now   = Carbon::now();
            $num   = $last ? ((int) explode('/', $last->no_surat)[0] + 1) : 1;
            $noSurat = "{$num}/LSP GKK B.002-A/{$this->bulanRomawi($now->month)}/{$now->year}";

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

        $ba->refresh();

        // -- Regenerate BA Komtek PDF with updated signatures --

        // Komtek names
        $komtekIds = array_filter([$jadwal->komtek1, $jadwal->komtek2, $jadwal->komtek3]);
        $komtekNames = User::whereIn('id', $komtekIds)->pluck('name', 'id');

        $komtek1Name = $komtekNames[$jadwal->komtek1] ?? null;
        $komtek2Name = $komtekNames[$jadwal->komtek2] ?? null;
        $komtek3Name = $komtekNames[$jadwal->komtek3] ?? null;

        // QR barcode for each komtek — only generate for those who have signed
        $svg1 = $svg2 = $svg3 = null;

        $getBarcodeSvg = function ($komtekId, $hasSigned) use ($jadwal_id) {
            if (!$komtekId || !$hasSigned) return null;

            $barcode = Barcode::where('id_user', $komtekId)
                ->where('id_jadwal', $jadwal_id)
                ->where('jenis_dokumen', 'BA Komtek')
                ->first();

            if (!$barcode) {
                $uuid = (string) \Ramsey\Uuid\Uuid::uuid4();
                $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";
                $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);
                $filename = "komtek_" . \Illuminate\Support\Str::uuid() . '.png';
                $path = "barcodes/{$filename}";
                Storage::disk('str_moon')->put($path, $qrImage);
                $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

                $barcode = Barcode::create([
                    'id_user'       => $komtekId,
                    'id_jadwal'     => $jadwal_id,
                    'jenis_dokumen' => 'BA Komtek',
                    'url_ttd'       => $urlTtd,
                    'url_image'     => $urlImage,
                    'id_role'       => 4,
                ]);
            }

            return '<img src="' . $barcode->url_image . '" width="100" height="100">';
        };

        // Read current signed status
        $signed1 = (bool) ($ba->has_ttd_komtek1 ?? false);
        $signed2 = (bool) ($ba->has_ttd_komtek2 ?? false);
        $signed3 = (bool) ($ba->has_ttd_komtek3 ?? false);

        $svg1 = $getBarcodeSvg($jadwal->komtek1, $signed1);
        $svg2 = $getBarcodeSvg($jadwal->komtek2, $signed2);
        $svg3 = $getBarcodeSvg($jadwal->komtek3, $signed3);

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
                'kompeten' => $this->resolveKomtekDecision($asesi->id_izin),
            ];
        }

        // Use started_at from BaKomtek if available, otherwise fallback to tanggal_uji
        $tanggal = $ba->started_at
            ? Carbon::parse($ba->started_at)->isoFormat('D MMMM Y')
            : Carbon::parse($jadwal->tanggal_uji)->isoFormat('D MMMM Y');

        $viewData = [
            'nomor'          => $ba->no_surat,
            'tanggal'        => $tanggal,
            'nama_komtek1'   => $komtek1Name,
            'nama_komtek2'   => $komtek2Name,
            'nama_komtek3'   => $komtek3Name,
            'nama_peserta'   => $nama_peserta,
            'qr1'            => $svg1,
            'qr2'            => $svg2,
            'qr3'            => $svg3,
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

    public function approveSkPenetapan(Request $request, $jadwal_id)
    {
        if ($error = $this->verifyDirektur($jadwal_id)) {
            return $error;
        }

        $idIzin = $request->input('id_izin');
        $kompeten = $request->input('kompeten'); // 'K' or 'BK'

        // Save direktur's recommendation for this asesi
        if ($idIzin) {
            $asesiJadwal = AsesiJadwal::where('jadwal_id', $jadwal_id)
                ->where('id_izin', $idIzin)
                ->first();

            if (!$asesiJadwal) {
                return response()->json(['message' => 'Asesi tidak ditemukan dalam jadwal ini'], 404);
            }

            $asesiJadwal->update([
                'rekomen_direktur' => $kompeten === 'K',
            ]);
        }

        // Regenerate SK Penetapan PDF with all asesi data
        $qrData = $this->generateDirekturQr($jadwal_id, 'SK Penetapan');

        $jadwal = Jadwal::where('jadwal_id', $jadwal_id)->firstOrFail();

        $baKomtek = BaKomtek::where('jadwal_id', $jadwal_id)->first();
        $skDir = SkPelaksanaanUji::where('jadwal_id', $jadwal_id)->first();

        $sk = SkPenetapan::where('jadwal_id', $jadwal_id)->first();

        if ($sk && $sk->no_surat && str_contains($sk->no_surat, 'LSP GKK')) {
            $nomorSk = $sk->no_surat;
        } else {
            $nomorSk = $this->generateNextNumber(
                SkPenetapan::class, 'no_surat', '0/LSP GKK B.003-B/I/2024'
            );
        }

        // Build peserta list — use direktur's decision if set, fallback to komtek
        $asesiList = AsesiJadwal::where('jadwal_id', $jadwal_id)->get();
        $peserta = [];
        foreach ($asesiList as $asesi) {
            $nama = Personal::where('id_izin', $asesi->id_izin)->value('nama');
            $jabatanKerjaId = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->value('jabatan_kerja');
            $namaSkema = JabatanKerja::where('id_jabatan_kerja', $jabatanKerjaId)->value('jabatan_kerja');

            if (!is_null($asesi->rekomen_direktur)) {
                $kompetenVal = $asesi->rekomen_direktur ? 'K' : 'BK';
            } else {
                $kompetenVal = $this->resolveKomtekDecision($asesi->id_izin);
            }

            $peserta[] = [
                'nama'     => $nama,
                'skema'    => $namaSkema,
                'kompeten' => $kompetenVal,
            ];
        }

        $direkturName = User::where('role_id', 6)->where('is_deleted', false)->value('name')
            ?? 'Radinal Efendy, S.T.';

        $viewData = [
            'nomor'            => $nomorSk,
            'tanggal'          => now()->isoFormat('D MMMM Y'),
            'tanggal_uji'      => Carbon::parse($jadwal->tanggal_uji)->isoFormat('D MMMM Y'),
            'no_ba_komtek'     => $baKomtek?->no_surat ?? '-',
            'no_sk_direktur'   => $skDir?->no_surat ?? '-',
            'tanggal_ba_komtek' => $baKomtek?->started_at
                ? Carbon::parse($baKomtek->started_at)->isoFormat('D MMMM Y')
                : Carbon::parse($jadwal->tanggal_uji)->isoFormat('D MMMM Y'),
            'peserta'          => $peserta,
            'qr'               => $qrData['html'],
            'direktur_name'    => $direkturName,
        ];

        $pdf = Pdf::loadView('template-surat.skpenetapan', $viewData);
        $path = "sk_penetapan/{$jadwal_id}.pdf";
        Storage::disk('str_moon')->put($path, $pdf->output());
        $pdfUrl = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        SkPenetapan::updateOrCreate(
            ['jadwal_id' => $jadwal_id],
            [
                'no_surat'         => $nomorSk,
                'link'             => $pdfUrl,
                'has_ttd_direktur' => 1,
                'inputby_id'       => auth()->id(),
            ]
        );

        return response()->json(['message' => 'Rekomendasi disimpan & SK Penetapan diperbarui']);
    }

    public function approveSkPelaksanaanUji($jadwal_id)
    {
        if ($error = $this->verifyDirektur($jadwal_id)) {
            return $error;
        }

        $qrData = $this->generateDirekturQr($jadwal_id, 'SK Pelaksanaan Uji');

        $jadwal = Jadwal::where('jadwal_id', $jadwal_id)->firstOrFail();

        $jadwal->update(['sk_pelaksanaan_uji_direktur_approved' => true, 'status' => 'buat-blanko']);

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

        if ($sk && $sk->no_surat && str_contains($sk->no_surat, 'LSP GKK')) {
            $nomorSk = $sk->no_surat;
        } else {
            $nomorSk = $this->generateNextNumber(
                SkPelaksanaanUji::class, 'no_surat', '0/LSP GKK B.003-B/I/2024'
            );
        }

        $direkturName = User::where('role_id', 6)->where('is_deleted', false)->value('name')
            ?? 'Radinal Efendy, S.T.';

        $viewData = [
            'nomor_sk'      => $nomorSk,
            'tanggal_uji'   => Carbon::parse($jadwal->tanggal_uji)->isoFormat('dddd, D MMMM Y'),
            'peserta'       => $peserta,
            'qr'            => $qrData['base64'],
            'direktur_name' => $direkturName,
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

        $tuk = $jadwal->tuk;
        $namaSkemaList = [];
        foreach ($asesiJadwals as $asesi) {
            $jabatanKerjaId = KlasifikasiKualifikasi::where('id_izin', $asesi->id_izin)->value('jabatan_kerja');
            $skema = JabatanKerja::where('id_jabatan_kerja', $jabatanKerjaId)->value('jabatan_kerja');
            if ($skema) $namaSkemaList[] = $skema;
        }
        $namaSkemaList = array_unique($namaSkemaList);

        $spt = SPTAsesor::where('jadwal_id', $jadwal_id)->first();

        if ($spt && $spt->spt_no && str_contains($spt->spt_no, 'LSP GKK')) {
            $sptNo = $spt->spt_no;
        } else {
            $sptNo = $this->generateNextNumber(
                SPTAsesor::class, 'spt_no', '0/LSP GKK B.012-D/I/2024'
            );
        }

        $viewData = [
            'no_surat' => $sptNo,
            'nama_asesor' => $asesor1?->nama ?? '',
            'nama_met' => $asesor1?->noreg ?? '',
            'nama_asesor2' => $asesor2?->nama ?? '',
            'nama_met2' => $asesor2?->noreg ?? '',
            'nama_tuk' => $tuk->nama ?? '',
            'alamat_tuk' => $tuk->alamat ?? '',
            'hari_tanggal' => Carbon::parse($jadwal->tanggal_uji)->isoFormat('dddd, D MMMM Y'),
            'jmlh_peserta' => count($peserta),
            'nama_skema' => implode(', ', $namaSkemaList),
            'nama_peserta' => $peserta,
            'qr' => $qrData['html'],
        ];

        $pdf = Pdf::loadView('template-surat.spt_asesor', $viewData);
        $path = "spt_asesor/{$jadwal_id}.pdf";
        Storage::disk('str_moon')->put($path, $pdf->output());
        $fileUrl = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        SPTAsesor::updateOrCreate(
            ['jadwal_id' => $jadwal_id],
            ['link' => $fileUrl, 'spt_no' => $sptNo, 'asesor_id' => $jadwal->asesor_id ?? 0, 'send' => 1]
        );

        // Post SPT Asesor to BNSP
        $authData = DB::connection('balai')->table('b_n_s_p_auths')->value('x_authorization');
        $bnspUrl = 'https://konstruksi.bnsp.go.id/api/v1/jadwal/asesor/surat-tugas';
        $headers = [
            'Content-Type' => 'application/json',
            'x-authorization' => $authData,
        ];

        // Post asesor 1 — use id_asesor from asesor_bnsp (matching generateSkKomtek)
        $bnspPayload = null;
        $bnspResponse = null;
        $bnspOk = false;
        if ($asesor1) {
            $bnspPayload = [
                'jadwal_id'       => $jadwal_id,
                'asesor_id'       => $asesor1->id_asesor,
                'url_surat_tugas' => $fileUrl,
            ];
            try {
                $response1 = Http::retry(8, 1000)->withHeaders($headers)->post($bnspUrl, $bnspPayload);
                $bnspResponse = $response1->json();
                $bnspOk = ($bnspResponse['code'] ?? '') === 'OK';

                Log::info('BNSP SPT Asesor response', [
                    'jadwal_id'  => $jadwal_id,
                    'asesor_id'  => $jadwal->asesor_id,
                    'status'     => $response1->status(),
                    'body'       => $response1->body(),
                ]);
            } catch (\Exception $e) {
                $bnspResponse = ['error' => $e->getMessage()];
                Log::error('BNSP post SPT Asesor failed', [
                    'jadwal_id' => $jadwal_id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message'       => $bnspOk ? 'SPT Asesor berhasil disetujui dan dikirim ke BNSP' : 'SPT Asesor disetujui, namun gagal dikirim ke BNSP',
        ], $bnspOk ? 200 : 502);
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

        if ($spt && $spt->no_surat && str_contains($spt->no_surat, 'LSP GKK')) {
            $sptNo = $spt->no_surat;
        } else {
            $sptNo = $this->generateNextNumber(
                SPTKomtek::class, 'no_surat', '0/LSP GKK B.001-D/I/2024'
            );
        }

        $tuk = $jadwal->tuk;
        $namaSkemaList = [];
        foreach ($peserta as $p) {
            if (!empty($p['skema'])) $namaSkemaList[] = $p['skema'];
        }
        $namaSkemaList = array_unique($namaSkemaList);

        $direkturName = User::where('role_id', 6)->where('is_deleted', false)->value('name')
            ?? 'Radinal Efendy, S.T.';

        $viewData = [
            'nomor' => $sptNo,
            'tanggal_surat' => now()->isoFormat('dddd, D MMMM Y'),
            'nama_komtek1' => $getNamaKomtek($jadwal->komtek1),
            'nama_komtek2' => $getNamaKomtek($jadwal->komtek2),
            'nama_komtek3' => $getNamaKomtek($jadwal->komtek3),
            'nama_tuk' => $tuk->nama ?? '',
            'alamat_tuk' => $tuk->alamat ?? '',
            'hari_tanggal' => Carbon::parse($jadwal->tanggal_uji)->isoFormat('dddd, D MMMM Y'),
            'jmlh_peserta' => count($peserta),
            'nama_skema' => implode(', ', $namaSkemaList),
            'peserta' => $peserta,
            'qr' => $qrData['html'],
            'direktur_name' => $direkturName,
        ];

        $pdf = Pdf::loadView('template-surat.spt_komtek', $viewData);
        $path = "spt_komtek/{$jadwal_id}.pdf";
        Storage::disk('str_moon')->put($path, $pdf->output());
        $fileUrl = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        SPTKomtek::updateOrCreate(
            ['jadwal_id' => $jadwal_id],
            ['link' => $fileUrl, 'no_surat' => $sptNo]
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
     * Generate the next sequential document number.
     *
     * @param  string  $modelClass  Full model class name
     * @param  string  $field       Column name (e.g. 'no_surat', 'spt_no')
     * @param  string  $default     Fallback pattern like '0/LSP GKK B.XXX-X/I/2024'
     * @return string
     */
    private function generateNextNumber($modelClass, $field, $default)
    {
        $last = $modelClass::latest('id')->first();
        $lastVal = $last?->{$field};

        // Only use the last record as base if it has a valid format
        if (!$lastVal || !str_contains($lastVal, 'LSP GKK')) {
            $lastVal = $default;
        }

        $romawi = $this->bulanRomawi(now()->month);
        $parts = explode('/', $lastVal);
        $year = now()->year;

        $parts[0] = ((int) ($parts[3] ?? 0) === $year) ? ((int) $parts[0] + 1) : 1;
        $parts[2] = $romawi;
        $parts[3] = (string) $year;

        return implode('/', $parts);
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
