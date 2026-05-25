<?php

namespace App\Http\Controllers;

use App\Models\PengajuanTuk;
use App\Models\User;
use App\Services\DokumenTUKService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PengajuanTukController extends Controller
{
    protected $dokumenService;

    public function __construct(DokumenTUKService $dokumenService)
    {
        $this->dokumenService = $dokumenService;
    }

    // ─── Public Submit (via static API key) ─────────────────────────

    public function submit(Request $request)
    {
        $request->validate([
            'data' => 'required|json',
            'dokumenPermohonan' => 'required|file|mimes:pdf|max:10240',
            'lampiran' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $data = json_decode($request->input('data'), true);

        if (!in_array($data['tukType'] ?? '', ['mandiri', 'sewaktu'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tipe TUK tidak valid. Harus "mandiri" atau "sewaktu".',
            ], 422);
        }

        $tukType = $data['tukType'];

        $dokumenPath = $request->file('dokumenPermohonan')->store('pengajuan-tuk', 'str_moon');
        $lampiranPath = null;
        if ($request->hasFile('lampiran')) {
            $lampiranPath = $request->file('lampiran')->store('pengajuan-tuk', 'str_moon');
        }

        $refNumber = 'TUK-' . strtoupper(Str::random(8)) . '-' . strtoupper(Str::random(4));

        $record = PengajuanTuk::create([
            'ref_number' => $refNumber,
            'tuk_type' => $tukType,
            'status' => 'diajukan',
            'data_tuk' => $data['dataTuk'] ?? [],
            'asesor_list' => $data['asesorList'] ?? [],
            'data_sewaktu' => $data['dataSewaktu'] ?? null,
            'dokumen_permohonan' => $dokumenPath,
            'lampiran' => $lampiranPath,
            'submitted_by' => $request->user()?->id,
        ]);

        $moonBase = 'https://moon.lspgatensi.id/files/new_gatensi/';

        return response()->json([
            'success' => true,
            'refNumber' => $refNumber,
            'documentLinks' => [
                'dokumenPermohonan' => $moonBase . $dokumenPath,
                'lampiran' => $lampiranPath ? $moonBase . $lampiranPath : null,
            ],
        ], 201);
    }

    // ─── List & Detail (authenticated) ─────────────────────────────

    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $query = PengajuanTuk::with('submitter:id,name');

        // Role-based filtering (superadmin sees all)
        if (!in_array($user->role_id, [1])) {
            if ($user->role_id == 5) {
                $query->where('verifikator_id', $user->id);
            } elseif ($user->role_id == 10) {
                $query->where('validator_id', $user->id);
            }
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ref_number', 'like', "%{$search}%")
                  ->orWhere('tuk_type', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($tukType = $request->query('tuk_type')) {
            $query->where('tuk_type', $tukType);
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function show($id)
    {
        $record = PengajuanTuk::with([
            'submitter:id,name',
            'direktur:id,name',
            'ketuaTuk:id,name',
            'verifikator:id,name',
            'validator:id,name',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $record,
        ]);
    }

    // ─── Step 1: Admin LSP Approve ────────────────────────────────

    public function approve(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role_id, [2, 1])) {
            return response()->json(['message' => 'Hanya Admin LSP yang dapat menyetujui'], 403);
        }

        $request->validate([
            'tanggal_asesmen' => 'required|date',
            'tanggal_verifikasi' => 'required|date',
            'metode_verifikasi' => 'required|in:Daring,Luring',
            'jumlah_peserta' => 'required|integer|min:1',
            'verifikator_id' => 'required|exists:users,id',
            'validator_id' => 'required|exists:users,id',
            'skema' => 'nullable|array',
            'skema.*.id' => 'nullable|string',
            'skema.*.nama' => 'nullable|string',
            'skema.*.jenjang' => 'nullable|string',
            'skema.*.metode' => 'nullable|string',
        ]);

        $pengajuan = PengajuanTuk::where('id', $id)->where('status', 'diajukan')->firstOrFail();

        // Generate surat numbers
        $now = Carbon::now();
        $romawi = $this->bulanRomawi($now->month);

        $pengajuan->no_surat_peminjaman = $this->generateNomor('no_surat_peminjaman', "0/GKK B.006-C/{$romawi}/{$now->year}");
        $pengajuan->no_surat_tugas = $this->generateNomor('no_surat_tugas', "0/GKK B.007-E/{$romawi}/{$now->year}");
        $pengajuan->no_berita_acara = $this->generateNomor('no_berita_acara', "0/GKK B.008-E/{$romawi}/{$now->year}");

        $pengajuan->status = 'disetujui_admin';
        $pengajuan->approved_by_admin = $user->name;
        $pengajuan->approved_at_admin = $now;
        $pengajuan->verifikator_id = $request->verifikator_id;
        $pengajuan->validator_id = $request->validator_id;

        // Stash extra data for PDF generation
        $dataTuk = $pengajuan->data_tuk ?? [];
        $dataTuk['tanggal_asesmen'] = $request->tanggal_asesmen;
        $dataTuk['tanggal_verifikasi'] = $request->tanggal_verifikasi;
        $dataTuk['metode_verifikasi'] = $request->metode_verifikasi;
        $dataTuk['jumlah_peserta'] = (int) $request->jumlah_peserta;
        $pengajuan->data_tuk = $dataTuk;

        // Store selections based on tuk type
        if ($request->filled('skema')) {
            $tukType = $request->tuk_type ?? $pengajuan->tuk_type;
            if ($tukType === 'sewaktu') {
                // Jabatan kerja → asesor_list for PDF generation
                $pengajuan->asesor_list = array_map(function ($s) {
                    return [
                        'skemaSkk' => $s['nama'] ?? '',
                        'id_jabatan_kerja' => $s['id'] ?? '',
                        'metode' => $s['metode'] ?? 'Observasi',
                    ];
                }, $request->skema);
            } else {
                // Subklasifikasi → store in data_tuk
                $dataTuk['subklasifikasi_list'] = array_map(function ($s) {
                    return [
                        'kode' => $s['id'] ?? '',
                        'nama' => $s['nama'] ?? '',
                    ];
                }, $request->skema);
                $pengajuan->data_tuk = $dataTuk;
            }
        }

        // Generate documents (no SK yet)
        $this->dokumenService->generateDokumenVerifikasi($pengajuan);
        $pengajuan->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan TUK disetujui. Dokumen verifikasi telah dibuat.',
            'data' => $pengajuan->fresh()->load(['direktur', 'ketuaTuk', 'verifikator', 'validator']),
        ]);
    }

    // ─── Step 1b: Admin LSP Reject ────────────────────────────────

    public function reject(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role_id, [2, 1])) {
            return response()->json(['message' => 'Hanya Admin LSP yang dapat menolak'], 403);
        }

        $request->validate(['reason' => 'required|string|max:500']);

        $pengajuan = PengajuanTuk::where('id', $id)->where('status', 'diajukan')->firstOrFail();

        $pengajuan->status = 'ditolak';
        $pengajuan->rejected_by_admin = $user->name;
        $pengajuan->rejection_reason = $request->reason;
        $pengajuan->rejected_at = Carbon::now();
        $pengajuan->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan TUK ditolak.',
        ]);
    }

    // ─── Step 1c: Admin LSP Assign Verifikator & Validator ────────

    public function assign(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role_id, [2, 1])) {
            return response()->json(['message' => 'Hanya Admin LSP yang dapat menugaskan'], 403);
        }

        $request->validate([
            'verifikator_id' => 'required|exists:users,id',
            'validator_id' => 'required|exists:users,id',
        ]);

        $pengajuan = PengajuanTuk::where('id', $id)->whereIn('status', ['disetujui_admin', 'disetujui_direktur', 'dikonfirmasi_tuk'])->firstOrFail();

        $pengajuan->verifikator_id = $request->verifikator_id;
        $pengajuan->validator_id = $request->validator_id;
        $pengajuan->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Verifikator dan validator berditugaskan.',
        ]);
    }

    // ─── Step 2: Direktur TTD ──────────────────────────────────────

    public function ttdDirektur(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role_id, [6, 1])) {
            return response()->json(['message' => 'Hanya Direktur yang dapat TTD'], 403);
        }

        $pengajuan = PengajuanTuk::where('id', $id)->where('status', 'disetujui_admin')->firstOrFail();

        $qrData = $pengajuan->ref_number . '|direktur|' . now()->timestamp;
        $qrSvg = QrCode::size(200)->generate($qrData);
        $qrHtml = '<img src="data:image/svg+xml;base64,' . base64_encode($qrSvg) . '" width="100" height="100">';

        $pengajuan->direktur_id = $user->id;
        $pengajuan->direktur_qr = $qrHtml;
        $pengajuan->approved_at_direktur = Carbon::now();
        $pengajuan->status = 'disetujui_direktur';

        // Regenerate documents with Direktur QR
        $this->dokumenService->generateDokumenVerifikasi($pengajuan);
        $pengajuan->save(); // saves non-doc fields (status, approved_by, etc.)

        return response()->json([
            'status' => 'success',
            'message' => 'TTD Direktur berhasil.',
        ]);
    }

    // ─── Step 3: Ketua TUK Confirm ─────────────────────────────────

    public function konfirmasiTuk(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role_id, [11, 1])) {
            return response()->json(['message' => 'Hanya Ketua TUK yang dapat konfirmasi'], 403);
        }

        $pengajuan = PengajuanTuk::where('id', $id)->where('status', 'disetujui_direktur')->firstOrFail();

        $qrData = $pengajuan->ref_number . '|ketua_tuk|' . now()->timestamp;
        $qrSvg = QrCode::size(200)->generate($qrData);
        $qrHtml = '<img src="data:image/svg+xml;base64,' . base64_encode($qrSvg) . '" width="100" height="100">';

        $pengajuan->ketua_tuk_id = $user->id;
        $pengajuan->ketua_tuk_qr = $qrHtml;
        $pengajuan->confirmed_at_tuk = Carbon::now();
        $pengajuan->status = 'dikonfirmasi_tuk';

        // Regenerate documents with Ketua TUK QR
        $this->dokumenService->generateDokumenVerifikasi($pengajuan);
        $pengajuan->save(); // saves non-doc fields (status, approved_by, etc.)

        return response()->json([
            'status' => 'success',
            'message' => 'Konfirmasi Ketua TUK berhasil.',
        ]);
    }

    // ─── Step 4: Verifikator Submit Checklist ───────────────────────

    public function verifikasi(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role_id, [5, 1])) {
            return response()->json(['message' => 'Hanya Verifikator (Asesor) yang dapat verifikasi'], 403);
        }

        $query = PengajuanTuk::where('id', $id)->where('status', 'dikonfirmasi_tuk');
        if ($user->role_id != 1) {
            $query->where('verifikator_id', $user->id);
        }
        $pengajuan = $query->firstOrFail();

        $request->validate([
            'checklist_fasilitas' => 'required|json',
            'checklist_peralatan' => 'required|json',
            'spesifikasi_tuk' => 'required|json',
            'rekomendasi' => 'required|in:layak,tidak_layak',
        ]);

        $qrData = $pengajuan->ref_number . '|verifikator|' . now()->timestamp;
        $qrSvg = QrCode::size(200)->generate($qrData);
        $qrHtml = '<img src="data:image/svg+xml;base64,' . base64_encode($qrSvg) . '" width="100" height="100">';

        $pengajuan->checklist_fasilitas = json_decode($request->checklist_fasilitas, true);
        $pengajuan->checklist_peralatan = json_decode($request->checklist_peralatan, true);
        $pengajuan->spesifikasi_tuk = json_decode($request->spesifikasi_tuk, true);
        $pengajuan->rekomendasi = $request->rekomendasi;
        $pengajuan->verifikator_id = $user->id;
        $pengajuan->verified_at = Carbon::now();
        $pengajuan->status = 'diverifikasi';

        // Regenerate documents with Verifikator QR + checklist data
        $this->dokumenService->generateDokumenVerifikasi($pengajuan);
        $pengajuan->save(); // saves non-doc fields (status, approved_by, etc.)

        return response()->json([
            'status' => 'success',
            'message' => 'Verifikasi TUK berhasil dikirim.',
        ]);
    }

    // ─── Step 5: Validator Approve ──────────────────────────────────

    public function validateTuk(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role_id, [10, 1])) {
            return response()->json(['message' => 'Hanya Validator yang dapat memvalidasi'], 403);
        }

        $query = PengajuanTuk::where('id', $id)->where('status', 'diverifikasi');
        if ($user->role_id != 1) {
            $query->where('validator_id', $user->id);
        }
        $pengajuan = $query->firstOrFail();

        $qrData = $pengajuan->ref_number . '|validator|' . now()->timestamp;
        $qrSvg = QrCode::size(200)->generate($qrData);
        $qrHtml = '<img src="data:image/svg+xml;base64,' . base64_encode($qrSvg) . '" width="100" height="100">';

        // Generate SK number
        $now = Carbon::now();
        $romawi = $this->bulanRomawi($now->month);
        $pengajuan->no_sk = $this->generateNomor('no_sk', "0/LSP GKK C.003-G/{$romawi}/{$now->year}");

        $pengajuan->validator_id = $user->id;
        $pengajuan->hasil_validasi = 'layak';
        $pengajuan->validated_at = $now;
        $pengajuan->status = 'divalidasi';

        // Regenerate docs + SK + Lampiran SK
        $this->dokumenService->generateDokumenVerifikasi($pengajuan);
        $this->dokumenService->generateDokumenSK($pengajuan);

        $pengajuan->save(); // saves status, validator, etc.

        return response()->json([
            'status' => 'success',
            'message' => 'Validasi TUK berhasil. SK telah dibuat.',
        ]);
    }

    // ─── Step 5b: Validator Reject ──────────────────────────────────

    public function tolakValidasi(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role_id, [10, 1])) {
            return response()->json(['message' => 'Hanya Validator yang dapat menolak'], 403);
        }

        $request->validate(['reason' => 'required|string|max:500']);

        $query = PengajuanTuk::where('id', $id)->where('status', 'diverifikasi');
        if ($user->role_id != 1) {
            $query->where('validator_id', $user->id);
        }
        $pengajuan = $query->firstOrFail();

        $pengajuan->hasil_validasi = 'tidak_layak';
        $pengajuan->rejection_reason = $request->reason;
        $pengajuan->validated_at = Carbon::now();
        $pengajuan->status = 'ditolak';
        $pengajuan->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Validasi TUK ditolak.',
        ]);
    }

    // ─── Step 6: Direktur TTD SK (Final) ────────────────────────────

    public function ttdSk(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role_id, [6, 1])) {
            return response()->json(['message' => 'Hanya Direktur yang dapat TTD SK'], 403);
        }

        $pengajuan = PengajuanTuk::where('id', $id)->where('status', 'divalidasi')->firstOrFail();

        $qrData = $pengajuan->ref_number . '|direktur_sk|' . now()->timestamp;
        $qrSvg = QrCode::size(200)->generate($qrData);
        $qrHtml = '<img src="data:image/svg+xml;base64,' . base64_encode($qrSvg) . '" width="100" height="100">';

        $pengajuan->direktur_qr = $qrHtml;
        $pengajuan->sk_at = Carbon::now();
        $pengajuan->status = 'selesai';

        // Regenerate SK with Direktur QR
        $this->dokumenService->generateDokumenSK($pengajuan);
        $pengajuan->save(); // saves status, direktur_qr, sk_at

        return response()->json([
            'status' => 'success',
            'message' => 'TTD SK berhasil. Verifikasi TUK selesai.',
        ]);
    }

    // ─── Surat Number Generator (from VerifikasiTukController) ──────

    private function generateNomor(string $field, string $default): string
    {
        $last = PengajuanTuk::whereNotNull($field)->latest('id')->first();
        $lastVal = ($last && $last->{$field} && str_contains($last->{$field}, '/'))
            ? $last->{$field}
            : $default;

        $parts = explode('/', $lastVal);
        $year = now()->year;

        $parts[0] = ((int) ($parts[3] ?? 0) === $year) ? ((int) $parts[0] + 1) : 1;
        $parts[2] = $this->bulanRomawi(now()->month);
        $parts[3] = (string) $year;

        return implode('/', $parts);
    }

    private function bulanRomawi(int $month): string
    {
        $map = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $map[$month - 1] ?? 'I';
    }
}
