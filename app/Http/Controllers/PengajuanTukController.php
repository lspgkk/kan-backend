<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\Barcode;
use App\Models\Jadwal;
use App\Models\PengajuanTuk;
use App\Models\Personal;
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

    // ─── AJJ: Submit with dokumentasi by Asesi ────────────────────

    public function submitAjj(Request $request)
    {
        $request->validate([
            'jadwal_id'                   => 'nullable|string',
            'id_izin'                     => 'required|string',
            'ruangan_uji_images'          => 'required|array|min:1',
            'ruangan_uji_images.*'        => 'image|mimes:jpeg,png,jpg|max:2048',
            'laptop_images'               => 'required|array|min:1',
            'laptop_images.*'             => 'image|mimes:jpeg,png,jpg|max:2048',
            'kecepatan_jaringan_images'   => 'required|array|min:1',
            'kecepatan_jaringan_images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Optional jadwal_id — AJJ may be submitted before jadwal is created
        if ($request->filled('jadwal_id')) {
            $asesiJadwal = AsesiJadwal::where('jadwal_id', $request->jadwal_id)
                ->where('id_izin', $request->id_izin)
                ->first();
            if (!$asesiJadwal) {
                return response()->json(['message' => 'Asesi tidak ditemukan pada jadwal ini'], 404);
            }
        }

        $existing = PengajuanTuk::where('tuk_type', 'ajj')
            ->where('data_tuk->id_izin', $request->id_izin)
            ->whereNotIn('status', ['selesai', 'ditolak'])
            ->first();
        if ($existing) {
            return response()->json(['message' => 'Verifikasi TUK AJJ sudah diajukan'], 409);
        }

        $personal = Personal::where('id_izin', $request->id_izin)->firstOrFail();

        $refNumber = 'TUK-AJJ-' . strtoupper(Str::random(8));
        $user = $request->user();

        $namaTuk = 'TUK SEWAKTU ' . ($personal->nama ?? '');
        $dataTuk = [
            'namaTuk'       => $namaTuk,
            'alamat'        => $personal->alamat ?? '',
            'organisasi'    => $personal->referensi_kerja ?? '',
            'emailTuk'      => $personal->email ?? '',
            'namaKetua'     => $personal->nama ?? '',
            'telpKetua'     => $personal->telepon ?? '',
            'namaAdmin'     => $personal->nama ?? '',
            'telpAdmin'     => $personal->telepon ?? '',
            'sosmedPlatform' => null,
            'sosmedNama'    => null,
        ];

        $moonBase = 'https://moon.lspgatensi.id/files/new_gatensi/';

        // Store images and collect URLs
        $ruanganUjiUrls = array_map(
            fn($f) => $moonBase . $f->store('verifikasi-tuk/ruangan-uji', 'str_moon'),
            $request->file('ruangan_uji_images')
        );
        $laptopUrls = array_map(
            fn($f) => $moonBase . $f->store('verifikasi-tuk/laptop', 'str_moon'),
            $request->file('laptop_images')
        );
        $jaringanUrls = array_map(
            fn($f) => $moonBase . $f->store('verifikasi-tuk/jaringan', 'str_moon'),
            $request->file('kecepatan_jaringan_images')
        );

        $dataSewaktu = [
            'id_izin'                     => $request->id_izin,
            'plannedExamDate'             => $personal->rencana_tanggal_uji,
            'kapasitasRuang'              => '30',
            'kesediaanObservasi'          => 'Ada',
            'perlengkapan'                => 'Meja ujian 1 unit, Kursi 1 unit, Proyektor, AC, WiFi',
            'ruangan_uji_images'          => $ruanganUjiUrls,
            'laptop_images'               => $laptopUrls,
            'kecepatan_jaringan_images'   => $jaringanUrls,
        ];

        // Generate QR for Ketua TUK
        $qrHtml = $this->generateQrBarcode($user->id, $user->role_id, 'TTD Ketua TUK AJJ', Carbon::now());

        $record = PengajuanTuk::create([
            'ref_number'    => $refNumber,
            'tuk_type'      => 'ajj',
            'status'        => 'diajukan',
            'data_tuk'      => $dataTuk,
            'asesor_list'   => [],
            'data_sewaktu'  => $dataSewaktu,
            'submitted_by'  => $user->id,
            'ketua_tuk_id'  => $user->id,
            'ketua_tuk_qr'  => $qrHtml,
        ]);

        // Generate PDF with AJJ template
        $this->dokumenService->generateDokumenVerifikasi($record);
        $record->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Verifikasi TUK AJJ berhasil diajukan dengan dokumentasi',
            'data'    => $record->fresh(),
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
            if (in_array($user->role_id, [5, 12])) {
                $query->where('verifikator_id', $user->id);
            } elseif ($user->role_id == 10) {
                $query->where('validator_id', $user->id);
            } elseif (in_array($user->role_id, [3, 11])) {
                // Filter by TUK assignment from master data users
                $tukName = $user->tuk?->nama_tuk;
                if ($tukName) {
                    $query->where('data_tuk->namaTuk', $tukName);
                }
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

        $pengajuan = PengajuanTuk::where('id', $id)->where('status', 'diajukan')->firstOrFail();
        $tukType = $request->tuk_type ?? $pengajuan->tuk_type;

        $request->validate([
            'tanggal_asesmen' => 'required|date',
            'tanggal_verifikasi' => 'required|date',
            'metode_verifikasi' => 'required|in:Daring,Luring',
            'verifikator_id' => 'required|exists:users,id',
            'validator_id' => in_array($tukType, ['sewaktu', 'ajj']) ? 'nullable|exists:users,id' : 'required|exists:users,id',
            'ketua_tuk_id' => $tukType === 'ajj' ? 'nullable|exists:users,id' : 'required|exists:users,id',
            'skema' => 'nullable|array',
            'skema.*.id' => 'nullable|string',
            'skema.*.nama' => 'nullable|string',
            'skema.*.jenjang' => 'nullable|string',
            'skema.*.metode' => 'nullable|string',
        ]);

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
        if ($tukType !== 'ajj') {
            $pengajuan->ketua_tuk_id = $request->ketua_tuk_id;
        }

        // Stash extra data for PDF generation
        $dataTuk = $pengajuan->data_tuk ?? [];
        $dataTuk['tanggal_asesmen'] = $request->tanggal_asesmen;
        $dataTuk['tanggal_verifikasi'] = $request->tanggal_verifikasi;
        $dataTuk['metode_verifikasi'] = $request->metode_verifikasi;
        $pengajuan->data_tuk = $dataTuk;

        // Store selections based on tuk type
        if ($request->filled('skema') && $tukType !== 'ajj') {
            if ($tukType === 'sewaktu') {
                // Jabatan kerja → asesor_list for PDF generation
                $pengajuan->asesor_list = array_map(function ($s) {
                    return [
                        'skemaSkk' => $s['nama'] ?? '',
                        'id_jabatan_kerja' => $s['id'] ?? '',
                        'metode' => $s['metode'] ?? 'Observasi',
                        'jenjang' => $s['jenjang'] ?? '',
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

        $pengajuan = PengajuanTuk::where('id', $id)
            ->where(function ($q) {
                $q->where('status', 'disetujui_admin')
                  ->orWhere(function ($q2) {
                      $q2->where('tuk_type', 'ajj')->where('status', 'dokumentasi_terisi');
                  });
            })
            ->firstOrFail();

        $qrHtml = $this->generateQrBarcode($user->id, $user->role_id, 'TTD Direktur TUK', Carbon::now());

        $pengajuan->direktur_id = $user->id;
        $pengajuan->direktur_qr = $qrHtml;
        $pengajuan->approved_at_direktur = Carbon::now();
        $pengajuan->status = 'disetujui_direktur';

        // Regenerate documents with Direktur QR
        $this->dokumenService->generateDokumenVerifikasi($pengajuan);
        $pengajuan->save();

        return response()->json([
            'status' => 'success',
            'message' => 'TTD Direktur berhasil.',
        ]);
    }

    // ─── Step 4: Ketua TUK Confirm ─────────────────────────────────

    public function konfirmasiTuk(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role_id, [11, 1])) {
            return response()->json(['message' => 'Hanya Ketua TUK yang dapat konfirmasi'], 403);
        }

        $pengajuan = PengajuanTuk::where('id', $id)->where('status', 'diverifikasi')->firstOrFail();

        if ($pengajuan->tuk_type === 'ajj') {
            return response()->json(['message' => 'AJJ tidak memerlukan konfirmasi TUK'], 422);
        }

        $qrHtml = $this->generateQrBarcode($user->id, $user->role_id, 'TTD Ketua TUK', Carbon::now());

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

    // ─── Step 3: Verifikator Submit Checklist ───────────────────────

    public function verifikasi(Request $request, $id)
    {
        $user = $request->user();
        if (!in_array($user->role_id, [5, 1])) {
            return response()->json(['message' => 'Hanya Verifikator yang dapat verifikasi'], 403);
        }

        $pengajuan = PengajuanTuk::where('id', $id)->firstOrFail();
        $isAjj = $pengajuan->tuk_type === 'ajj';
        $expectedStatus = 'disetujui_direktur'; // Both AJJ and sewaktu come from direktur approval

        if ($pengajuan->status !== $expectedStatus) {
            return response()->json(['message' => "Status harus {$expectedStatus}"], 422);
        }
        if ($user->role_id != 1 && $pengajuan->verifikator_id != $user->id) {
            return response()->json(['message' => 'Bukan Verifikator Anda'], 403);
        }

        $qrHtml = $this->generateQrBarcode($user->id, $user->role_id, 'TTD Verifikator TUK', Carbon::now());

        if ($isAjj) {
            $request->validate([
                'kondisi_ruangan' => 'required|in:baik,rusak',
                'keterangan_ruangan' => 'nullable|string|max:255',
                'kondisi_laptop' => 'required|in:baik,rusak',
                'keterangan_laptop' => 'nullable|string|max:255',
                'kondisi_jaringan' => 'required|in:baik,rusak',
                'keterangan_jaringan' => 'nullable|string|max:255',
                'rekomendasi' => 'required|in:layak,tidak_layak',
            ]);

            $dataSewaktu = $pengajuan->data_sewaktu ?? [];
            $dataSewaktu['kondisi_ruangan'] = $request->kondisi_ruangan;
            $dataSewaktu['keterangan_ruangan'] = $request->keterangan_ruangan;
            $dataSewaktu['kondisi_laptop'] = $request->kondisi_laptop;
            $dataSewaktu['keterangan_laptop'] = $request->keterangan_laptop;
            $dataSewaktu['kondisi_jaringan'] = $request->kondisi_jaringan;
            $dataSewaktu['keterangan_jaringan'] = $request->keterangan_jaringan;
            $pengajuan->data_sewaktu = $dataSewaktu;
        } else {
            $request->validate([
                'checklist_fasilitas' => 'required|json',
                'checklist_peralatan' => 'required|json',
                'spesifikasi_tuk' => 'required|json',
                'rekomendasi' => 'required|in:layak,tidak_layak',
            ]);
            $pengajuan->checklist_fasilitas = json_decode($request->checklist_fasilitas, true);
            $pengajuan->checklist_peralatan = json_decode($request->checklist_peralatan, true);
            $pengajuan->spesifikasi_tuk = json_decode($request->spesifikasi_tuk, true);
        }

        $pengajuan->rekomendasi = $request->rekomendasi;
        $pengajuan->verifikator_id = $user->id;
        $pengajuan->verifikator_qr = $qrHtml;
        $pengajuan->verified_at = Carbon::now();
        $pengajuan->status = 'diverifikasi';

        $this->dokumenService->generateDokumenVerifikasi($pengajuan);
        $pengajuan->save();

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

        if ($pengajuan->tuk_type !== 'mandiri') {
            return response()->json(['message' => 'Hanya tipe mandiri yang memerlukan validasi'], 422);
        }

        // Validator QR is not rendered on any current page, but we store it for audit
        $qrHtml = $this->generateQrBarcode($user->id, $user->role_id, 'TTD Validator TUK', Carbon::now());

        // Generate SK number — only if not already set.
        // Reuses counter from this pengajuan's peminjaman number so all 4
        // documents share the same sequence number.
        $now = Carbon::now();
        if (! $pengajuan->no_sk) {
            $romawi = $this->bulanRomawi($now->month);
            if ($pengajuan->no_surat_peminjaman && str_contains($pengajuan->no_surat_peminjaman, '/')) {
                $pemParts = explode('/', $pengajuan->no_surat_peminjaman);
                $counter = $pemParts[0];
                $pengajuan->no_sk = "{$counter}/LSP GKK C.003-G/{$romawi}/{$now->year}";
            } else {
                $pengajuan->no_sk = $this->generateNomor('no_sk', "0/LSP GKK C.003-G/{$romawi}/{$now->year}");
            }
        }

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

        if ($pengajuan->tuk_type !== 'mandiri') {
            return response()->json(['message' => 'Hanya tipe mandiri yang memerlukan validasi'], 422);
        }

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

        $pengajuan = PengajuanTuk::where('id', $id)->firstOrFail();

        // AJJ: selesai setelah diverifikasi (tanpa konfirmasi TUK / validasi)
        // Sewaktu: setelah dikonfirmasi_tuk
        // Mandiri: setelah divalidasi
        $allowedStatuses = match ($pengajuan->tuk_type) {
            'ajj'      => ['diverifikasi'],
            'sewaktu'  => ['dikonfirmasi_tuk'],
            'mandiri'  => ['divalidasi'],
            default    => [],
        };

        if (!in_array($pengajuan->status, $allowedStatuses)) {
            return response()->json([
                'message' => 'Status pengajuan tidak valid untuk TTD SK',
            ], 422);
        }

        // Separate barcode for SK page — date matches $tanggalKeputusan
        $qrHtml = $this->generateQrBarcode($user->id, $user->role_id, 'TTD SK Direktur TUK', Carbon::now());

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

    /**
     * Generate next surat number.
     * ALL document types share ONE master counter (tracked via no_surat_peminjaman)
     * so that peminjaman, tugas, berita acara, and SK stay in sync.
     */
    private function generateNomor(string $field, string $default): string
    {
        // Master counter always from no_surat_peminjaman (the first number generated)
        $last = PengajuanTuk::whereNotNull('no_surat_peminjaman')->latest('id')->first();
        $lastVal = ($last && $last->no_surat_peminjaman && str_contains($last->no_surat_peminjaman, '/'))
            ? $last->no_surat_peminjaman
            : $default;

        $lastParts    = explode('/', $lastVal);
        $defaultParts = explode('/', $default);
        $year = now()->year;

        // Increment counter from master, reset if year changed
        $counter = (count($lastParts) >= 4 && (int)($lastParts[3] ?? 0) === $year)
            ? ((int) $lastParts[0] + 1)
            : 1;

        $defaultParts[0] = (string) $counter;
        $defaultParts[2] = $this->bulanRomawi(now()->month);
        $defaultParts[3] = (string) $year;

        return implode('/', $defaultParts);
    }

    private function bulanRomawi(int $month): string
    {
        $map = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        return $map[$month - 1] ?? 'I';
    }

    private function generateQrBarcode(int $userId, int $roleId, string $jenisDokumen, Carbon $createdAt): string
    {
        $uuid = (string) Str::uuid();
        $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";

        $qrImage = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);

        $filename = "tuk_" . Str::uuid() . '.png';
        $path = "barcodes/{$filename}";
        Storage::disk('str_moon')->put($path, $qrImage);

        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        Barcode::create([
            'id_user'       => $userId,
            'id_jadwal'     => null,
            'jenis_dokumen' => $jenisDokumen,
            'url_ttd'       => $urlTtd,
            'url_image'     => $urlImage,
            'id_role'       => $roleId,
            'created_at'    => $createdAt,
        ]);

        return '<img src="' . $urlImage . '" width="100" height="100">';
    }
}
