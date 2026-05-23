<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\AsesorBnsp;
use App\Models\JabatanKerja;
use App\Models\Jadwal;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Personal;
use App\Models\Sesi;
use App\Models\Skema;
use App\Models\User;
use App\Models\VerifikasiTuk;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class VerifikasiTukController extends Controller
{
    private const ROMAWI = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];

    // ─── Step 1: Admin TUK mengajukan — surat tergenerate, semua TTD kosong ───

    public function submit(Request $request)
    {
        $request->validate([
            'jadwal_id' => 'required|string',
            'id_izin'   => 'required|string',
        ]);

        $jadwalId = $request->jadwal_id;
        $idIzin   = $request->id_izin;

        $asesiJadwal = AsesiJadwal::where('jadwal_id', $jadwalId)
            ->where('id_izin', $idIzin)
            ->first();
        if (!$asesiJadwal) {
            return response()->json(['message' => 'Asesi tidak ditemukan pada jadwal ini'], 404);
        }

        // Check duplicate
        $existing = VerifikasiTuk::where('jadwal_id', $jadwalId)
            ->where('id_izin', $idIzin)
            ->first();
        if ($existing) {
            return response()->json(['message' => 'Verifikasi TUK sudah diajukan', 'data' => $existing], 409);
        }

        $jadwal   = Jadwal::where('jadwal_id', $jadwalId)->firstOrFail();
        $personal = Personal::where('id_izin', $idIzin)->firstOrFail();

        $viewData = $this->buildViewData($jadwal, $personal, $asesiJadwal, null);
        // Step 1: all TTD + dokumentasi + verifikasi still null
        $now   = Carbon::now();
        $ts    = $now->format('YmdHis');

        $pdfPath = "verifikasi_tuk/{$jadwalId}_{$idIzin}_{$ts}.pdf";
        $pdf     = Pdf::loadView('template-surat.verifikasi-tuk-ajj', $viewData);
        Storage::disk('str_moon')->put($pdfPath, $pdf->output());
        $pdfUrl  = "https://moon.lspgatensi.id/files/new_gatensi/{$pdfPath}";

        $record = VerifikasiTuk::create([
            'jadwal_id'                => $jadwalId,
            'id_izin'                  => $idIzin,
            'no_surat_tugas'           => $viewData['no_surat_tugas'],
            'no_surat_ba'              => $viewData['no_surat_ba'],
            'no_surat_sk'              => $viewData['no_surat_sk'],
            'no_surat_peminjaman'      => $viewData['no_surat_peminjaman'],
            'nama_tuk'                 => $viewData['nama_tuk'],
            'alamat'                   => $viewData['alamat'],
            'hari_tanggal'             => $jadwal->tanggal_uji,
            'metode_verifikasi'        => 'Daring',
            'jumlah_peserta'           => $viewData['jumlah_peserta'],
            'skema_sertifikasi'        => $viewData['skema_sertifikasi'],
            'jenjang'                  => $viewData['jenjang'],
            'status'                   => 'diajukan',
            'pdf_link'                 => $pdfUrl,
        ]);

        return response()->json([
            'message' => 'Verifikasi TUK berhasil diajukan (TTD kosong)',
            'data'    => ['id' => $record->id, 'status' => $record->status, 'pdf_link' => $pdfUrl],
        ]);
    }

    // ─── Step 2: Asesi upload foto dokumentasi + TTD Ketua TUK ───

    public function uploadDokumentasi(Request $request, $id)
    {
        $verifikasi = VerifikasiTuk::where('id', $id)
            ->where('status', 'diajukan')
            ->firstOrFail();

        $request->validate([
            'ruangan_uji_images'          => 'required|array|min:1',
            'ruangan_uji_images.*'        => 'image|mimes:jpeg,png,jpg|max:2048',
            'laptop_images'               => 'required|array|min:1',
            'laptop_images.*'             => 'image|mimes:jpeg,png,jpg|max:2048',
            'kecepatan_jaringan_images'   => 'required|array|min:1',
            'kecepatan_jaringan_images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $ruanganUjiPaths   = $this->uploadImages($request->file('ruangan_uji_images'), 'verifikasi-tuk/ruangan-uji');
        $laptopPaths       = $this->uploadImages($request->file('laptop_images'), 'verifikasi-tuk/laptop');
        $jaringanPaths     = $this->uploadImages($request->file('kecepatan_jaringan_images'), 'verifikasi-tuk/jaringan');

        $user = auth()->user();

        $verifikasi->update([
            'ruangan_uji_images'          => $ruanganUjiPaths,
            'laptop_images'               => $laptopPaths,
            'kecepatan_jaringan_images'   => $jaringanPaths,
            'ketua_tuk_id'                => $user->id,
            'ketua_tuk_qr'                => $this->generateQrHtml($user, $verifikasi->jadwal_id, 'Verifikasi TUK - Ketua TUK'),
            'status'                      => 'dokumentasi_terisi',
        ]);

        $pdfUrl = $this->regeneratePdf($verifikasi);

        return response()->json([
            'message' => 'Dokumentasi berhasil diupload, TTD Ketua TUK terisi',
            'data'    => ['id' => $verifikasi->id, 'status' => $verifikasi->status, 'pdf_link' => $pdfUrl],
        ]);
    }

    // ─── Step 3: Direktur TTD (belum TTD SK) ───

    public function ttdDirektur(Request $request, $id)
    {
        $user = auth()->user();

        if ($user->role_id !== '6') {
            return response()->json(['message' => 'Hanya direktur yang dapat melakukan TTD'], 403);
        }

        $verifikasi = VerifikasiTuk::where('id', $id)
            ->whereIn('status', ['dokumentasi_terisi'])
            ->firstOrFail();

        $verifikasi->update([
            'direktur_id' => $user->id,
            'direktur_qr' => $this->generateQrHtml($user, $verifikasi->jadwal_id, 'Verifikasi TUK - Direktur'),
            'status'      => 'direktur_ttd',
        ]);

        $pdfUrl = $this->regeneratePdf($verifikasi);

        return response()->json([
            'message' => 'Direktur TTD berhasil (SK belum TTD)',
            'data'    => ['id' => $verifikasi->id, 'status' => $verifikasi->status, 'pdf_link' => $pdfUrl],
        ]);
    }

    // ─── Step 4: Asesor — kondisi & TTD Verifikator ───

    public function verifikasi(Request $request, $id)
    {
        $verifikasi = VerifikasiTuk::where('id', $id)
            ->where('status', 'direktur_ttd')
            ->firstOrFail();

        $request->validate([
            'kondisi_ruangan'     => 'required|in:baik,rusak',
            'keterangan_ruangan'  => 'nullable|string|max:255',
            'kondisi_laptop'      => 'required|in:baik,rusak',
            'keterangan_laptop'   => 'nullable|string|max:255',
            'kondisi_jaringan'    => 'required|in:baik,rusak',
            'keterangan_jaringan' => 'nullable|string|max:255',
            'rekomendasi'         => 'required|in:layak,tidak_layak',
        ]);

        $user = auth()->user();

        $verifikasi->update([
            'kondisi_ruangan'     => $request->kondisi_ruangan,
            'keterangan_ruangan'  => $request->keterangan_ruangan,
            'kondisi_laptop'      => $request->kondisi_laptop,
            'keterangan_laptop'   => $request->keterangan_laptop,
            'kondisi_jaringan'    => $request->kondisi_jaringan,
            'keterangan_jaringan' => $request->keterangan_jaringan,
            'rekomendasi'         => $request->rekomendasi,
            'verifikator_id'      => $user->id,
            'verifikator_qr'      => $this->generateQrHtml($user, $verifikasi->jadwal_id, 'Verifikasi TUK - Verifikator'),
            'status'              => 'diverifikasi',
        ]);

        $pdfUrl = $this->regeneratePdf($verifikasi);

        return response()->json([
            'message' => 'Verifikasi & TTD Verifikator berhasil',
            'data'    => ['id' => $verifikasi->id, 'status' => $verifikasi->status, 'pdf_link' => $pdfUrl],
        ]);
    }

    // ─── Step 5: Direktur TTD SK (final) ───

    public function ttdSk(Request $request, $id)
    {
        $user = auth()->user();

        if ($user->role_id !== '6') {
            return response()->json(['message' => 'Hanya direktur yang dapat melakukan TTD SK'], 403);
        }

        $verifikasi = VerifikasiTuk::where('id', $id)
            ->where('status', 'diverifikasi')
            ->firstOrFail();

        $verifikasi->update([
            'has_ttd_sk'     => true,
            'direktur_sk_qr' => $this->generateQrHtml($user, $verifikasi->jadwal_id, 'Verifikasi TUK - Direktur SK'),
            'status'         => 'selesai',
        ]);

        $pdfUrl = $this->regeneratePdf($verifikasi);

        return response()->json([
            'message' => 'TTD SK berhasil, Verifikasi TUK selesai',
            'data'    => ['id' => $verifikasi->id, 'status' => $verifikasi->status, 'pdf_link' => $pdfUrl],
        ]);
    }

    // ─── List ───────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user    = auth()->user();
        $perPage = $request->input('per_page', 15);

        $query = VerifikasiTuk::with(['personal', 'jadwal']);

        if ($user->role_id === '6' || $user->role_id === '10') {
            // Direktur / Ketua TUK: see all
        } else {
            $query->where('verifikator_id', $user->id);
        }

        if ($request->has('status') && $request->status) {
            $validStatuses = ['diajukan', 'dokumentasi_terisi', 'direktur_ttd', 'diverifikasi', 'selesai'];
            if (in_array($request->status, $validStatuses)) {
                $query->where('status', $request->status);
            }
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_tuk', 'like', "%{$search}%")
                  ->orWhere('alamat', 'like', "%{$search}%")
                  ->orWhere('skema_sertifikasi', 'like', "%{$search}%")
                  ->orWhere('id_izin', 'like', "%{$search}%")
                  ->orWhere('jadwal_id', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json(['message' => 'Success', 'data' => $data]);
    }

    // ─── Show single ───────────────────────────────────────────────

    public function show($id)
    {
        $verifikasi = VerifikasiTuk::with(['personal', 'jadwal', 'verifikator', 'ketuaTuk', 'direktur'])->findOrFail($id);

        return response()->json(['message' => 'Success', 'data' => $verifikasi]);
    }

    // ─── Private helpers ────────────────────────────────────────────

    private function buildViewData(Jadwal $jadwal, Personal $personal, AsesiJadwal $asesiJadwal, ?VerifikasiTuk $verifikasi): array
    {
        $asesorList = [];
        if ($jadwal->asesor_id) {
            $a = AsesorBnsp::where('id', $jadwal->asesor_id)->first();
            if ($a) $asesorList[] = $a->Nama;
        }
        if ($jadwal->asesor2_id) {
            $a = AsesorBnsp::where('id', $jadwal->asesor2_id)->first();
            if ($a) $asesorList[] = $a->Nama;
        }

        $skemaName = '';
        $skema = Skema::where('id', $asesiJadwal->skema_id)->first();
        if ($skema) $skemaName = $skema->nama ?? '';

        $jenjangName = '';
        $jkId = KlasifikasiKualifikasi::where('id_izin', $personal->id_izin)->value('jabatan_kerja');
        if ($jkId) {
            $jk = JabatanKerja::where('id_jabatan_kerja', $jkId)->first();
            if ($jk) $jenjangName = $jk->jenjang ?? $jk->jabatan_kerja ?? '';
        }

        $jumlahPeserta = AsesiJadwal::where('jadwal_id', $jadwal->jadwal_id)->count();
        $tanggalUji    = Carbon::parse($jadwal->tanggal_uji);
        $now           = Carbon::now();

        $namaTuk = 'TUK Sewaktu – ' . $personal->nama;
        $alamat  = $personal->alamat ?? '';

        // Nomor surat — use existing if available, else generate new
        if ($verifikasi) {
            $noSuratTugas      = $verifikasi->no_surat_tugas;
            $noSuratBa         = $verifikasi->no_surat_ba;
            $noSuratSk         = $verifikasi->no_surat_sk;
            $noSuratPeminjaman = $verifikasi->no_surat_peminjaman;
        } else {
            $rm = self::ROMAWI[$now->month];
            $yr = $now->year;
            $noSuratTugas      = $this->generateNomor('no_surat_tugas', "0/GKK B.007-E/{$rm}/{$yr}");
            $noSuratBa         = $this->generateNomor('no_surat_ba', "0/GKK B.008-E/{$rm}/{$yr}");
            $noSuratSk         = $this->generateNomor('no_surat_sk', "0/LSP GKK C.003-G/{$rm}/{$yr}");
            $noSuratPeminjaman = $this->generateNomor('no_surat_peminjaman', "0/LSP GKK B.006-C/{$rm}/{$yr}");
        }

        $ruanganUjiUrls = [];
        $laptopUrls     = [];
        $jaringanUrls   = [];
        if ($verifikasi) {
            $ruanganUjiUrls = array_map(fn($p) => "https://moon.lspgatensi.id/files/new_gatensi/{$p}", $verifikasi->ruangan_uji_images ?? []);
            $laptopUrls     = array_map(fn($p) => "https://moon.lspgatensi.id/files/new_gatensi/{$p}", $verifikasi->laptop_images ?? []);
            $jaringanUrls   = array_map(fn($p) => "https://moon.lspgatensi.id/files/new_gatensi/{$p}", $verifikasi->kecepatan_jaringan_images ?? []);
        }

        $verifikatorName = null;
        if ($verifikasi && $verifikasi->verifikator_id) {
            $verifikatorName = User::where('id', $verifikasi->verifikator_id)->value('name');
        }

        $rekomLabel = null;
        if ($verifikasi) {
            $rekomLabel = $verifikasi->rekomendasi === 'layak' ? 'Layak' : ($verifikasi->rekomendasi === 'tidak_layak' ? 'Tidak Layak' : null);
        }

        return [
            'no_surat_tugas'            => $noSuratTugas,
            'no_surat_ba'               => $noSuratBa,
            'no_surat_sk'               => $noSuratSk,
            'no_surat_peminjaman'       => $noSuratPeminjaman,
            'asesor_list'               => $asesorList,
            'nama_tuk'                  => $namaTuk,
            'alamat'                    => $alamat,
            'hari_tanggal'              => $tanggalUji->isoFormat('dddd, D MMMM Y'),
            'jumlah_peserta'            => $jumlahPeserta,
            'skema_sertifikasi'         => $skemaName,
            'jenjang'                   => $jenjangName,
            'nama_asesi'                => $personal->nama,
            'direktur_name'             => 'Radinal Efendy, S.T.',
            'tanggal_surat_tugas'       => $tanggalUji->isoFormat('D MMMM Y'),
            'tanggal_verifikasi'        => $now->isoFormat('D MMMM Y'),
            'tanggal_sk'                => $now->isoFormat('D MMMM Y'),
            'jam_uji'                   => Sesi::where('id', $jadwal->sesi)->value('sesi') ?? '',
            'background_image'          => 'template_page-0001.jpg',
            'ruangan_uji_images'        => $ruanganUjiUrls,
            'laptop_images'             => $laptopUrls,
            'kecepatan_jaringan_images' => $jaringanUrls,
            'kondisi_ruangan'           => $verifikasi->kondisi_ruangan ?? null,
            'keterangan_ruangan'        => $verifikasi->keterangan_ruangan ?? null,
            'kondisi_laptop'            => $verifikasi->kondisi_laptop ?? null,
            'keterangan_laptop'         => $verifikasi->keterangan_laptop ?? null,
            'kondisi_jaringan'          => $verifikasi->kondisi_jaringan ?? null,
            'keterangan_jaringan'       => $verifikasi->keterangan_jaringan ?? null,
            'rekomendasi_label'         => $rekomLabel,
            'verifikator_name'          => $verifikatorName,
            'verifikator_qr'            => $verifikasi->verifikator_qr ?? null,
            'ketua_tuk_qr'              => $verifikasi->ketua_tuk_qr ?? null,
            'direktur_qr'               => $verifikasi->direktur_qr ?? null,
            'direktur_sk_qr'            => $verifikasi->direktur_sk_qr ?? null,
        ];
    }

    private function generateNomor(string $field, string $default): string
    {
        $last = VerifikasiTuk::whereNotNull($field)->latest('id')->first();
        $lastVal = ($last && $last->{$field} && str_contains($last->{$field}, '/'))
            ? $last->{$field}
            : $default;

        $parts = explode('/', $lastVal);
        $year  = now()->year;

        $parts[0] = ((int) ($parts[3] ?? 0) === $year) ? ((int) $parts[0] + 1) : 1;
        $parts[2] = self::ROMAWI[now()->month];
        $parts[3] = (string) $year;

        return implode('/', $parts);
    }

    private function uploadImages(array $files, string $folder): array
    {
        $paths = [];
        foreach ($files as $file) {
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = "{$folder}/{$filename}";
            Storage::disk('str_moon')->put($path, file_get_contents($file->getRealPath()));
            $paths[] = $path;
        }
        return $paths;
    }

    private function generateQrHtml($user, string $jadwalId, string $jenis): string
    {
        $qrData = $user->id . '|' . $jadwalId . '|' . $jenis . '|' . now()->timestamp;
        $qr = QrCode::size(200)->generate($qrData);
        return '<img src="data:image/svg+xml;base64,' . base64_encode($qr) . '" width="100" height="100">';
    }

    private function regeneratePdf(VerifikasiTuk $verifikasi): string
    {
        $jadwal   = Jadwal::where('jadwal_id', $verifikasi->jadwal_id)->firstOrFail();
        $personal = Personal::where('id_izin', $verifikasi->id_izin)->firstOrFail();

        $asesiJadwal = AsesiJadwal::where('jadwal_id', $verifikasi->jadwal_id)
            ->where('id_izin', $verifikasi->id_izin)
            ->firstOrFail();

        $viewData = $this->buildViewData($jadwal, $personal, $asesiJadwal, $verifikasi);

        $now     = Carbon::now();
        $ts      = $now->format('YmdHis');
        $pdfPath = "verifikasi_tuk/{$verifikasi->jadwal_id}_{$verifikasi->id_izin}_{$ts}.pdf";
        $pdf     = Pdf::loadView('template-surat.verifikasi-tuk-ajj', $viewData);
        Storage::disk('str_moon')->put($pdfPath, $pdf->output());
        $pdfUrl  = "https://moon.lspgatensi.id/files/new_gatensi/{$pdfPath}";

        $verifikasi->update(['pdf_link' => $pdfUrl]);

        return $pdfUrl;
    }
}
