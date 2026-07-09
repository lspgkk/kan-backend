<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use App\Models\Barcode;
use App\Models\JabatanConfigA;
use App\Models\JabatanKerja;
use App\Models\Jadwal;
use App\Models\KanIa4bAnswer;
use App\Models\KanIa5Answer;
use App\Models\KanIa6Answer;
use App\Models\KanPraktisiJabatan;
use App\Models\KlasifikasiKualifikasi;
use App\Models\Personal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PraktisiController extends Controller
{
    // ──────────────────────────────────────────────
    //  DASHBOARD: List Assigned Jabatan Kerja
    // ──────────────────────────────────────────────

    public function jabker(): JsonResponse
    {
        $user = auth()->user();

        // Find the Personal record linked to this user (by email match)
        $personal = Personal::select(['id_izin', 'nama'])
            ->where('email', $user->email)
            ->orWhere('account', $user->email)
            ->first();

        if (!$personal) {
            return response()->json(['message' => 'OK', 'data' => []]);
        }

        // Get assigned jabatan via KlasifikasiKualifikasi
        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])
            ->where('id_izin', $personal->id_izin)
            ->first();

        if (!$klasifikasi) {
            return response()->json(['message' => 'OK', 'data' => []]);
        }

        $jabker = JabatanKerja::select(['id_jabatan_kerja', 'jabatan_kerja'])
            ->where('id_jabatan_kerja', $klasifikasi->jabatan_kerja)
            ->where('lsp_id_klasifikasi', 'SI')
            ->where('versi', 2)
            ->first();

        if (!$jabker) {
            return response()->json(['message' => 'OK', 'data' => []]);
        }

        // Check progress
        $asesiJadwal = AsesiJadwal::where('id_izin', $personal->id_izin)->first();
        $status = 'belum';
        if ($asesiJadwal) {
            $status = $asesiJadwal->is_started ? 'proses' : 'terdaftar';
        }

        return response()->json([
            'message' => 'OK',
            'data'    => [[
                'id_izin'          => $personal->id_izin,
                'id_jabatan_kerja' => $jabker->id_jabatan_kerja,
                'jabatan_kerja'    => $jabker->jabatan_kerja,
                'jenjang'          => $klasifikasi->jenjang,
                'status'           => $status,
            ]],
        ]);
    }

    // ──────────────────────────────────────────────
    //  LIST: Jabatan Kerja from kan_praktisi_jabatan
    // ──────────────────────────────────────────────

    public function jabatanKerja(): JsonResponse
    {
        $user = auth()->user();

        $data = KanPraktisiJabatan::with([
            'jabatanKerja:id_jabatan_kerja,jabatan_kerja',
        ])
            ->where('id_user', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id'                => $item->id,
                    'id_jabatan_kerja'  => $item->id_jabatan_kerja,
                    'jabatan_kerja'     => $item->jabatanKerja?->jabatan_kerja,
                    'created_at'        => $item->created_at,
                ];
            });

        return response()->json([
            'message' => 'OK',
            'data'    => $data,
        ]);
    }

    // ──────────────────────────────────────────────
    //  STATUS: Progress Pengerjaan
    // ──────────────────────────────────────────────

    public function status($idIzin): JsonResponse
    {
        $personal = Personal::select(['id_izin', 'nama'])->where('id_izin', $idIzin)->first();
        if (!$personal) {
            return response()->json(['message' => 'Data not found'], 404);
        }

        $asesiJadwal = AsesiJadwal::where('id_izin', $idIzin)->first();
        $jadwal = $asesiJadwal
            ? Jadwal::select(['jadwal_id', 'tanggal_uji', 'tanggal_selesai'])
                ->where('jadwal_id', $asesiJadwal->jadwal_id)
                ->first()
            : null;

        $klasifikasi = KlasifikasiKualifikasi::select(['jabatan_kerja', 'jenjang'])
            ->where('id_izin', $idIzin)->first();

        // Check document completion
        $config = $klasifikasi
            ? JabatanConfigA::select(['kan_no_skema'])->where('id_jabatan', $klasifikasi->jabatan_kerja)->first()
            : null;

        // Check if IA04B/IA05/IA06 answers exist
        $hasIa04b = KanIa4bAnswer::where('id_izin', $idIzin)->exists();
        $hasIa05  = KanIa5Answer::where('id_izin', $idIzin)->exists();
        $hasIa06  = KanIa6Answer::where('id_izin', $idIzin)->exists();

        $allAnswered = $hasIa04b && $hasIa05 && $hasIa06;

        return response()->json([
            'message' => 'OK',
            'data'    => [
                'id_izin'        => $idIzin,
                'nama'           => $personal->nama,
                'kan_no_skema'   => $config?->kan_no_skema,
                'tanggal_uji'    => $jadwal?->tanggal_uji,
                'tanggal_selesai'=> $jadwal?->tanggal_selesai,
                'is_started'     => $asesiJadwal?->is_started ?? false,
                'progress'       => [
                    'ia04b'       => $hasIa04b,
                    'ia05'        => $hasIa05,
                    'ia06'        => $hasIa06,
                    'all_answered'=> $allAnswered,
                ],
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  JAWAB SOAL: Submit Jawaban ke IA04B/IA05/IA06
    // ──────────────────────────────────────────────

    public function jawab(Request $request, $idIzin): JsonResponse
    {
        $validated = $request->validate([
            'type'      => 'required|in:ia04b,ia05,ia06',
            'dokumen_id'=> 'required|integer',
            'answers'   => 'required|array',
            'answers.*.soal_id' => 'required|integer',
            'answers.*.jawaban' => 'nullable|string',
        ]);

        // Delegate to KanAsesmenController (jawaban only, no skor)
        $kan = app(KanAsesmenController::class);

        return match ($validated['type']) {
            'ia04b' => $kan->submitKanIa04b($request, $idIzin),
            'ia05'  => $kan->submitKanIa05($request, $idIzin),
            'ia06'  => $kan->submitKanIa06($request, $idIzin),
        };
    }

    // ──────────────────────────────────────────────
    //  GET: IA04B Soal (read-only, for answering)
    // ──────────────────────────────────────────────

    public function getIa04b($idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->getKanIa04b($idIzin);
    }

    // ──────────────────────────────────────────────
    //  GET: IA05 Soal (read-only, for answering)
    // ──────────────────────────────────────────────

    public function getIa05($idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->getKanIa05($idIzin);
    }

    // ──────────────────────────────────────────────
    //  GET: IA06 Soal (read-only, for answering)
    // ──────────────────────────────────────────────

    public function getIa06($idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->getKanIa06($idIzin);
    }

    // ──────────────────────────────────────────────
    //  APL.01: Get & Submit (delegates to PraAsesmenController)
    // ──────────────────────────────────────────────

    public function getApl01($idIzin): JsonResponse
    {
        $pra = app(PraAsesmenController::class);
        return $pra->getApl01($idIzin);
    }

    public function submitApl01(Request $request, $idIzin): JsonResponse
    {
        $pra = app(PraAsesmenController::class);
        return $pra->submitApl01($request, $idIzin);
    }

    // ──────────────────────────────────────────────
    //  TTD: Generate QR Code for Praktisi
    // ──────────────────────────────────────────────

    public function ttd(Request $request, $idIzin): JsonResponse
    {
        $user = auth()->user();

        $request->validate(['id_jadwal' => 'required']);

        $uuid = (string) Uuid::uuid4();
        $urlTtd = "https://barcode.lspgatensi.id/ttd/{$uuid}";
        $filename = 'ttd_praktisi_' . Str::uuid() . '.png';

        $qrImage = QrCode::format('png')->size(300)->margin(1)->generate($urlTtd);

        $path = "barcodes/{$filename}";
        Storage::disk('str_moon')->put($path, $qrImage);

        $urlImage = "https://moon.lspgatensi.id/files/new_gatensi/{$path}";

        Barcode::updateOrCreate(
            [
                'id_user'       => $user->id,
                'id_izin'       => $idIzin,
                'jenis_dokumen' => 'PRAKTISI',
            ],
            [
                'id_jadwal' => $request->id_jadwal,
                'url_image' => $urlImage,
                'url_ttd'   => $urlTtd,
                'id_role'   => $user->role_id,
            ]
        );

        return response()->json([
            'message'  => 'TTD saved',
            'data'     => [
                'url_image' => $urlImage,
                'url_ttd'   => $urlTtd,
                'tanggal'   => Carbon::now()->toDateString(),
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    //  POST QR + GENERATE PDF per Dokumen KAN
    //  Diisi oleh praktisi — delegate ke KanAsesmenController
    // ──────────────────────────────────────────────

    public function qrIa04b(Request $request, $idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->ttdKanIa04b($request, $idIzin);
    }

    public function qrIa05(Request $request, $idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->ttdKanIa05($request, $idIzin);
    }

    public function qrIa06(Request $request, $idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->ttdKanIa06($request, $idIzin);
    }

    public function qrAk02(Request $request, $idIzin): JsonResponse
    {
        $kan = app(KanAsesmenController::class);
        return $kan->ttdKanAk02($request, $idIzin);
    }

    // ═══════════════════════════════════════════════════════════════
    //  KAN PRAKTISI JABATAN FLOW (keyed by kan_praktisi_jabatan.id)
    //  BNSP endpoints above are NOT modified.
    // ═══════════════════════════════════════════════════════════════

    /**
     * Check that the authenticated user owns this kan_praktisi_jabatan record.
     */
    private function authorizePraktisiJabatan(int $id): KanPraktisiJabatan
    {
        $record = KanPraktisiJabatan::findOrFail($id);
        if ($record->id_user !== auth()->user()->id) {
            abort(403, 'Forbidden: Anda tidak memiliki akses ke data ini.');
        }
        return $record;
    }

    // ──────────────────────────────────────────────
    //  GET: IA04B By Jabatan
    // ──────────────────────────────────────────────

    public function getIa04bByJabatan(int $id): JsonResponse
    {
        $this->authorizePraktisiJabatan($id);
        $kan = app(KanAsesmenController::class);
        return $kan->getKanIa04bByPraktisi($id);
    }

    // ──────────────────────────────────────────────
    //  GET: IA05 By Jabatan
    // ──────────────────────────────────────────────

    public function getIa05ByJabatan(int $id): JsonResponse
    {
        $this->authorizePraktisiJabatan($id);
        $kan = app(KanAsesmenController::class);
        return $kan->getKanIa05ByPraktisi($id);
    }

    // ──────────────────────────────────────────────
    //  GET: IA06 By Jabatan
    // ──────────────────────────────────────────────

    public function getIa06ByJabatan(int $id): JsonResponse
    {
        $this->authorizePraktisiJabatan($id);
        $kan = app(KanAsesmenController::class);
        return $kan->getKanIa06ByPraktisi($id);
    }

    // ──────────────────────────────────────────────
    //  GET: AK02 By Jabatan
    // ──────────────────────────────────────────────

    public function getAk02ByJabatan(int $id): JsonResponse
    {
        $this->authorizePraktisiJabatan($id);
        $kan = app(KanAsesmenController::class);
        return $kan->getKanAk02ByPraktisi($id);
    }

    // ──────────────────────────────────────────────
    //  POST: Submit Jawaban By Jabatan
    // ──────────────────────────────────────────────

    public function jawabByJabatan(Request $request, int $id): JsonResponse
    {
        $this->authorizePraktisiJabatan($id);

        $validated = $request->validate([
            'type'        => 'required|in:ia04b,ia05,ia06',
            'dokumen_id'  => 'required|integer',
            'answers'     => 'required|array',
            'answers.*.soal_id' => 'required|integer',
            'answers.*.jawaban' => 'nullable|string',
        ]);

        $kan = app(KanAsesmenController::class);

        return match ($validated['type']) {
            'ia04b' => $kan->submitKanIa04bByPraktisi($request, $id),
            'ia05'  => $kan->submitKanIa05ByPraktisi($request, $id),
            'ia06'  => $kan->submitKanIa06ByPraktisi($request, $id),
        };
    }

    // ──────────────────────────────────────────────
    //  POST: QR/TTD IA04B By Jabatan
    // ──────────────────────────────────────────────

    public function qrIa04bByJabatan(Request $request, int $id): JsonResponse
    {
        $this->authorizePraktisiJabatan($id);
        $kan = app(KanAsesmenController::class);
        return $kan->ttdKanIa04bByPraktisi($request, $id);
    }

    // ──────────────────────────────────────────────
    //  POST: QR/TTD IA05 By Jabatan
    // ──────────────────────────────────────────────

    public function qrIa05ByJabatan(Request $request, int $id): JsonResponse
    {
        $this->authorizePraktisiJabatan($id);
        $kan = app(KanAsesmenController::class);
        return $kan->ttdKanIa05ByPraktisi($request, $id);
    }

    // ──────────────────────────────────────────────
    //  POST: QR/TTD IA06 By Jabatan
    // ──────────────────────────────────────────────

    public function qrIa06ByJabatan(Request $request, int $id): JsonResponse
    {
        $this->authorizePraktisiJabatan($id);
        $kan = app(KanAsesmenController::class);
        return $kan->ttdKanIa06ByPraktisi($request, $id);
    }

    // ──────────────────────────────────────────────
    //  POST: QR/TTD AK02 By Jabatan
    // ──────────────────────────────────────────────

    public function qrAk02ByJabatan(Request $request, int $id): JsonResponse
    {
        $this->authorizePraktisiJabatan($id);
        $kan = app(KanAsesmenController::class);
        return $kan->ttdKanAk02ByPraktisi($request, $id);
    }
}
