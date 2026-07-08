<?php

use App\Http\Controllers\AsesmenController;
use App\Http\Controllers\AsesiJadwalController;
use App\Http\Controllers\AsesorController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\BlankoController;
use App\Http\Controllers\BuktiController;
use App\Http\Controllers\core\CetakJadwalController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirekturController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermohonanController;
use App\Http\Controllers\HitStatusController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JabatanKerjaController;
use App\Http\Controllers\KanSoalController;
use App\Http\Controllers\KanConfigController;
use App\Http\Controllers\KanImportController;
use App\Http\Controllers\KanAsesmenController;
use App\Http\Controllers\PenyusunController;
use App\Http\Controllers\ValidatorController;
use App\Http\Controllers\ManagerMutuController;
use App\Http\Controllers\PraktisiController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\JenisAnggaranController;
use App\Http\Controllers\JenisKelasController;
use App\Http\Controllers\JenjangController;
use App\Http\Controllers\KebenaranDataController;
use App\Http\Controllers\KomtekController;
use App\Http\Controllers\KualifikasiController;
use App\Http\Controllers\MigrationController;
use App\Http\Controllers\PenerbitanSertifikatController;
use App\Http\Controllers\PraAsesmenController;
use App\Http\Controllers\ProvinceController;
use App\Http\Controllers\SesiController;
use App\Http\Controllers\SubklasifikasiController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\TukController;
use App\Http\Controllers\DiskonController;
use App\Http\Controllers\MetodePembayaranController;
use App\Http\Controllers\NegaraController;
use App\Http\Controllers\PekerjaanController;
use App\Http\Controllers\JenjangJabatanKerjaController;
use App\Http\Controllers\KlasifikasiController;
use App\Http\Controllers\TarifController;
use App\Http\Controllers\PengajuanTukController;
use App\Http\Controllers\TukChecklistTemplateController;

Route::get('/migrate/personals', [MigrationController::class, 'personals']);
Route::get('/migrate/klasifikasis', [MigrationController::class, 'klasifikasis']);
Route::get('/migrate/pendidikans', [MigrationController::class, 'pendidikans']);
Route::get('/migrate/proyeks', [MigrationController::class, 'proyeks']);
Route::get('/migrate/pelatihans', [MigrationController::class, 'pelatihans']);
Route::get('/migrate/sertifikats', [MigrationController::class, 'sertifikats']);
Route::get('/migrate/pencatatans', [MigrationController::class, 'pencatatans']);
Route::get('/migrate/jadwals', [MigrationController::class, 'jadwals']);

Route::middleware('guest')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/login-mobile', [AuthController::class, 'loginMobile']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOTP']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// --- Public Barcode Verification ---
Route::get('/get-barcode/{id}', [BarcodeController::class, 'getBarcode']);
Route::get('/barcode/verify', [BarcodeController::class, 'getBarcodeByUrlTtd']);
Route::get('/barcode/penyusun-validator/verify', [BarcodeController::class, 'getPenyusunValidatorByUrl']);

// Midtrans webhook (no auth required - must be publicly accessible for Midtrans servers)
Route::post('/midtrans/webhook', [MidtransWebhookController::class, 'handlePaymentNotification']);

// Public UUID resolve for WhatsApp magic link (no auth)
Route::get('/persiapan-asesmen/{uuid}', [PraAsesmenController::class, 'resolveUuid']);

// Public — Pengajuan TUK via static API key (for landing page)
Route::post('/pengajuan-tuk', [PengajuanTukController::class, 'submit'])
    ->middleware('static-api-key');

// Public — Survey submission & results (static API key)
Route::prefix('survey')->middleware('static-api-key')->group(function () {
    Route::get('/', [SurveyController::class, 'index']);
    Route::get('/{id_izin}', [SurveyController::class, 'show']);
    Route::post('/{id_izin}', [SurveyController::class, 'submit']);
});

// ─────────────────────────────────────────────
//  INTERNAL API — Document Generation (static API key)
//  Used by the external document generator service to fetch all data
//  without direct database access.
// ─────────────────────────────────────────────
Route::prefix('internal')->middleware(['static-api-key', 'throttle:300,1'])->group(function () {

    Route::get('/initialize/{idIzin}', [\App\Http\Controllers\InternalDocumentDataController::class, 'initialize']);

    Route::get('/header/{idIzin}', [\App\Http\Controllers\InternalDocumentDataController::class, 'dokumenHeader']);

    // Build*Data endpoint — method is a URL parameter (Apl01, Apl02, Mapa01, etc.)
    Route::get('/build/{method}/{idIzin}', [\App\Http\Controllers\InternalDocumentDataController::class, 'buildData']);

    // Data operations
    Route::get('/personal/{idIzin}', [\App\Http\Controllers\InternalDocumentDataController::class, 'getPersonal']);
    Route::get('/asesor/{noreg}', [\App\Http\Controllers\InternalDocumentDataController::class, 'getAsesor']);
    Route::get('/user/by-noreg/{noreg}', [\App\Http\Controllers\InternalDocumentDataController::class, 'getUserByNoreg']);
    Route::get('/jabatan-kerja/{idJabatan}', [\App\Http\Controllers\InternalDocumentDataController::class, 'getJabatanKerja']);
    Route::get('/komtek-decision/{idIzin}', [\App\Http\Controllers\InternalDocumentDataController::class, 'komtekDecision']);

    // Barcode operations
    Route::get('/barcodes/{idIzin}', [\App\Http\Controllers\InternalDocumentDataController::class, 'getBarcodes']);
    Route::post('/barcode', [\App\Http\Controllers\InternalDocumentDataController::class, 'saveBarcode']);
    Route::post('/barcodes/generate-all', [\App\Http\Controllers\InternalDocumentDataController::class, 'generateAllBarcodes']);

    // Bukti asesmen operations
    Route::get('/bukti-asesmen/{idIzin}', [\App\Http\Controllers\InternalDocumentDataController::class, 'getBuktiAsesmen']);
    Route::post('/bukti-asesmen/{idIzin}', [\App\Http\Controllers\InternalDocumentDataController::class, 'updateBuktiAsesmen']);

    // Pre-populate answer tables (write operation)
    Route::post('/prepopulate/{idIzin}', [\App\Http\Controllers\InternalDocumentDataController::class, 'prePopulate']);

    // Smart database sync
    Route::get('/smart-sync', [\App\Http\Controllers\InternalDocumentDataController::class, 'syncSmartAll']);
    Route::get('/smart-sync/{table}', [\App\Http\Controllers\InternalDocumentDataController::class, 'syncSmartTable']);
});

// Route::middleware(['auth:api', 'singlesession'])->group(function () {

Route::middleware(['auth:api'])->group(function () {

    // --- WebSocket Test Endpoint ---
    Route::post('/test-broadcast', [PraAsesmenController::class, 'testBroadcast']);

    // --- Connection health checks ---
    Route::get('/check-siki-connection', [\App\Http\Controllers\SikiController::class, 'checkConnection']);
    Route::get('/check-bnsp-connection', [\App\Http\Controllers\SikiController::class, 'checkBnspConnection']);

    // --- Auth & Users ---
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/auth/logs', [AuthController::class, 'logs']);

    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:user-list');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:user-create');
    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:user-edit');
    Route::post('/users/{id}', [UserController::class, 'update'])->middleware('permission:user-edit');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:user-delete');
    Route::delete('/users/{id}/avatar', [UserController::class, 'deleteAvatar'])->middleware('permission:user-edit');
    Route::put('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::put('/store-fcm-token', [AuthController::class, 'storeFcmToken']);

    // --- Roles ---
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->middleware('permission:role-list');
        Route::post('/', [RoleController::class, 'store'])->middleware('permission:role-create');
        Route::get('/{id}', [RoleController::class, 'show'])->middleware('permission:role-list');
        Route::put('/{id}', [RoleController::class, 'update'])->middleware('permission:role-edit');
        Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('permission:role-delete');
        Route::get('/permissions/all', [RoleController::class, 'permissions'])->middleware('permission:permission-list');
        Route::post('/{id}/permissions', [RoleController::class, 'assignPermissions'])->middleware('permission:permission-edit');
    });

    // --- Dashboard ---
    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::prefix('permohonan-skk')->group(function () {
        Route::get('/list/reguler', [PermohonanController::class, 'permohonanRegulerList']);
        Route::get('/list/balai', [PermohonanController::class, 'permohonanBalaiList']);
        Route::get('/list/fg', [PermohonanController::class, 'permohonanFgList']);
        Route::get('/baru/reguler', [PermohonanController::class, 'skkBaruRegulerList']);
        Route::get('/baru/balai', [PermohonanController::class, 'skkBaruBalaiList']);
        Route::get('/baru/fg', [PermohonanController::class, 'skkBaruFgList']);
        Route::get('/validasi/reguler', [PermohonanController::class, 'skkValidasiRegulerList']);
        Route::get('/validasi/balai', [PermohonanController::class, 'skkValidasiBalaiList']);
        Route::get('/validasi/fg', [PermohonanController::class, 'skkValidasiFgList']);
        Route::get('/sync/permohonan', [PermohonanController::class, 'syncPermohonan']);
        Route::post('/sync/single', [PermohonanController::class, 'syncSinglePermohonan']);
        Route::post('/refetch/single', [PermohonanController::class, 'refetchPermohonan']);
        Route::get('/{id}', [PermohonanController::class, 'detailPermohonan']);
    });

    Route::prefix('tuk')->group(function () {
        Route::get('/siki', [TukController::class, 'allTukSiki']);
        Route::post('/siki/sync', [TukController::class, 'syncTukSiki']);
        Route::get('/siki/{id}', [TukController::class, 'showTukSiki']);
        Route::put('/siki/{id}', [TukController::class, 'updateKategori']);
        Route::get('/bnsp', [TukController::class, 'allTukBnsp']);
        Route::get('/list', [TukController::class, 'listTuk']);
    });

    Route::prefix('klasifikasi')->group(function () {
        Route::get('/', [KlasifikasiController::class, 'index']);
        Route::post('/sync', [KlasifikasiController::class, 'sync']);
    });

    Route::prefix('subklasifikasi')->group(function () {
        Route::get('/', [SubklasifikasiController::class, 'index']);
    });

    Route::prefix('jabatan-kerja')->group(function () {
        Route::get('/', [JabatanKerjaController::class, 'index']);
    });

    Route::prefix('kualifikasi')->group(function () {
        Route::get('/', [KualifikasiController::class, 'index']);
    });

    Route::prefix('asesor')->group(function () {
        Route::get('/', [AsesorController::class, 'index']);
        Route::post('/bnsp/{noreg}', [AsesorController::class, 'fetchBnsp']);
    });

    Route::prefix('jenis-kelas')->group(function () {
        Route::get('/', [JenisKelasController::class, 'index']);
    });

    Route::prefix('jenis-anggaran')->group(function () {
        Route::get('/', [JenisAnggaranController::class, 'index']);
    });

    Route::prefix('sesi')->group(function () {
        Route::get('/', [SesiController::class, 'index']);
    });

    Route::prefix('komtek')->group(function () {
        Route::get('/', [KomtekController::class, 'index']);
        Route::post('/rekomendasi/{id}', [KomtekController::class, 'rekomendasi']);
        Route::get('/rekomendasi/{id}', [KomtekController::class, 'getRekomendasi']);
        Route::get('/files/{id}', [KomtekController::class, 'komtekFiles']);
        Route::post('/start/{id}', [KomtekController::class, 'startBaKomtek']);
        Route::post('/approve-ba-komtek/{jadwal_id}', [DirekturController::class, 'approveBaKomtek']);
    });

    Route::prefix('direktur')->group(function () {
        Route::get('/files/{id}', [DirekturController::class, 'jadwalFiles']);
        Route::post('/approve-sk-pelaksanaan-uji/{jadwal_id}', [DirekturController::class, 'approveSkPelaksanaanUji']);
        Route::post('/approve-spt-asesor/{jadwal_id}', [DirekturController::class, 'approveSptAsesor']);
        Route::post('/approve-spt-komtek/{jadwal_id}', [DirekturController::class, 'approveSptKomtek']);
        Route::post('/approve-sk-penetapan/{jadwal_id}', [DirekturController::class, 'approveSkPenetapan']);
    });

    Route::prefix('jenjang')->group(function () {
        Route::get('/', [JenjangController::class, 'index']);
    });

    Route::prefix('kabupaten')->group(function () {
        Route::get('/', [CityController::class, 'index']);
        Route::post('/sync', [CityController::class, 'sync']);
    });

    Route::prefix('province')->group(function () {
        Route::get('/', [ProvinceController::class, 'index']);
        Route::post('/sync', [ProvinceController::class, 'sync']);
    });

    Route::prefix('invoice')->group(function () {
        Route::get('/lap-asesor', [InvoiceController::class, 'lapAsesor']);
        Route::get('/reguler', [InvoiceController::class, 'viewInvoiceBaruReguler']);
        Route::get('/balai', [InvoiceController::class, 'viewInvoiceBaruBalai']);
        Route::get('/fg', [InvoiceController::class, 'viewInvoiceBaruFg']);
        Route::get('/rekening', [InvoiceController::class, 'rekening']);
        Route::get('/konfirmasi', [InvoiceController::class, 'viewKonfirmasi']);
        Route::get('/history', [InvoiceController::class, 'history']);
        Route::get('/unpaid', [InvoiceController::class, 'getUnpaidInvoices']);
        Route::post('/create', [InvoiceController::class, 'createInvoice']);
        Route::post('/konfirmasi', [InvoiceController::class, 'confirmInvoice']);
        Route::post('/preview-discount', [InvoiceController::class, 'previewDiscount']);
        Route::post('/estimate-fees', [InvoiceController::class, 'estimateMidtransFees']); // New endpoint for fee estimation
        Route::post('/midtrans-pay', [InvoiceController::class, 'createMidtransPayment']);
        Route::get('/{invoice}/midtrans-status', [InvoiceController::class, 'checkMidtransStatus']);
        Route::get('/{invoice}/midtrans-fees', [InvoiceController::class, 'getMidtransFeeBreakdown']);
        Route::post('/{invoice}/force-payment-success', [InvoiceController::class, 'forcePaymentSuccess']); // Debug endpoint
        Route::get('/test-midtrans', [InvoiceController::class, 'testMidtransConnection']); // Debug endpoint
    });

    Route::prefix('jadwal')->group(function () {
        Route::get('/', [JadwalController::class, 'index']);
        Route::get('/blanko', [JadwalController::class, 'blankos']);
        Route::get('/sertifikasi', [JadwalController::class, 'sertifikasi']);
        Route::get('/list-asesi-create', [JadwalController::class, 'listAsesiCreateJadwal']);
        Route::get('/pengajuan-tuk-list', [JadwalController::class, 'pengajuanTukList']);
        Route::post('/create-jadwal', [JadwalController::class, 'store']);
        Route::post('/upload-dokumen-verifikasi', [JadwalController::class, 'uploadDokumenVerifikasi']);
        Route::post('/{id}/post-ke-bnsp', [JadwalController::class, 'postToBnsp']);
        Route::get('/{id}', [JadwalController::class, 'show']);
        Route::put('/{jadwal_id}', [JadwalController::class, 'updateJadwal']);
        Route::put('/{jadwal_id}/link-video', [JadwalController::class, 'updateLinkVideo']);
        Route::get('/{jadwal_id}/link-video', [JadwalController::class, 'getLinkVideo']);
    });

    Route::prefix('asesi-jadwal')->group(function () {
        Route::get('/id-izins', [AsesiJadwalController::class, 'listIdIzins']);
    });

    Route::prefix('dokumen')->group(function () {
        Route::post('/sk/{jadwalId}', [JadwalController::class, 'ttdSk']);
    });

    Route::prefix('kegiatan')->group(function () {
        Route::get('/asesi', [JadwalController::class, 'getKegiatanAsesi']);
        Route::get('/asesor', [JadwalController::class, 'getKegiatanAsesor']);
        Route::get('/komtek', [JadwalController::class, 'getKegiatanKomtek']);
        Route::get('/direktur', [JadwalController::class, 'getKegiatanDirektur']);
        Route::get('/admin-tuk', [JadwalController::class, 'getKegiatanAdminTuk']);
        Route::post('/{id}/start', [JadwalController::class, 'startAsesmen']);
        Route::post('/{id}/start-praasesmen', [JadwalController::class, 'startPraAsesmen']);
        Route::get('/{id}/list-asesi', [JadwalController::class, 'listAsesi']);
        Route::get('/{id}/dokumen-asesi', [BuktiController::class, 'dokumenAsesi']);
        Route::get('/{jadwal_id}/ak05', [AsesmenController::class, 'getAk05ByJadwal']);
    });

    Route::prefix('bukti')->group(function () {
        Route::post('/{id}/foto-kegiatan', [BuktiController::class, 'uploadKegiatan']);
        Route::post('/{id}/kegiatan', [BuktiController::class, 'uploadBuktiKegiatan']);
        Route::get('/{id}/kegiatan', [BuktiController::class, 'getBuktiKegiatan']);
    });

    Route::get('/dokumen/asesi/{id}', [AsesmenController::class, 'dokumenAsesi']);
    Route::get('/dokumen/asesi/{id}/download', [AsesmenController::class, 'downloadDokumenAsesi']);
    Route::get('/dokumen/absen/{id}', [AsesmenController::class, 'absensi']);
    Route::get('/dokumen/{idIzin}/muk', [PenyusunController::class, 'getMuk']);
    Route::get('/generate-penyusun', [BarcodeController::class, 'generateBarcodePenyusun']);
    Route::get('/generate-manajer', [BarcodeController::class, 'generateBarcodeManajer']);

    Route::prefix('praasesmen')->group(function () {
        Route::post('/{id}/absen-awal', [PraAsesmenController::class, 'absenAwal']);
        Route::post('/{id}/absen-akhir', [PraAsesmenController::class, 'absenAkhir']);
        Route::get('/kebenaran-data', [KebenaranDataController::class, 'index']);
        Route::get('/kebenaran-data/{id_izin}', [KebenaranDataController::class, 'index']);
        Route::get('/{id}/data-dokumen', [PraAsesmenController::class, 'getDokumenHeader']);
        Route::get('/{id}/apl01', [PraAsesmenController::class, 'getApl01']);
        Route::get('/{id}/apl02', [PraAsesmenController::class, 'getApl02']);
        Route::get('{id}/apl02/files', [PraAsesmenController::class, 'getApl02Files']);
        Route::get('/{id}/mapa01', [PraAsesmenController::class, 'getMapa01']);
        Route::get('/{id}/mapa02', [PraAsesmenController::class, 'getMapa02']);
        Route::get('/{id}/ak07', [PraAsesmenController::class, 'getAk07']);
        Route::get('/{id}/ak04', [PraAsesmenController::class, 'getAk04']);
        Route::get('/{id}/file-k3', [PraAsesmenController::class, 'getK3Asesmen']);
        Route::get('/{id}/ak01', [PraAsesmenController::class, 'getAk01']);
        Route::post('/{id}/apl01', [PraAsesmenController::class, 'submitApl01']);
        Route::post('/{id}/apl02', [PraAsesmenController::class, 'submitApl02']);
        Route::post('/{id}/apl02/files', [PraAsesmenController::class, 'uploadApl02Files']);
        Route::post('/{id}/mapa02', [PraAsesmenController::class, 'generateMapa02']);
        Route::post('/{id}/ak07', [PraAsesmenController::class, 'submitAk07']);
        Route::post('/{id}/ak04', [PraAsesmenController::class, 'submitAk04']);
        Route::post('/file-k3', [PraAsesmenController::class, 'submitK3Asesmen']);
        Route::post('/{id}/ak01', [PraAsesmenController::class, 'submitAk01']);
        Route::delete('/apl02/files/{fileId}', [PraAsesmenController::class, 'deleteApl02File']);
    });

    Route::prefix('qr')->group(function () {
        Route::post('/{id}/apl01', [PraAsesmenController::class, 'ttdApl01']);
        Route::post('/{id}/apl02', [PraAsesmenController::class, 'ttdApl02']);
        Route::post('/{id}/mapa01', [PraAsesmenController::class, 'ttdMapa01']);
        Route::post('/{id}/mapa02', [PraAsesmenController::class, 'ttdMapa02']);
        Route::post('/{id}/ak07', [PraAsesmenController::class, 'ttdAk07']);
        Route::post('/{id}/ak04', [PraAsesmenController::class, 'ttdAk04']);
        Route::post('/{id}/ak01', [PraAsesmenController::class, 'ttdAk01']);
        Route::post('/{id}/ia01', [AsesmenController::class, 'ttdIa01']);
        Route::post('/{id}/ia02', [AsesmenController::class, 'ttdIa02']);
        Route::post('/{id}/ia03', [AsesmenController::class, 'ttdIa03']);
        Route::post('/{id}/ia04a', [AsesmenController::class, 'ttdIa04a']);
        Route::post('/{id}/ia04b', [AsesmenController::class, 'ttdIa04b']);
        Route::post('/{id}/ia05', [AsesmenController::class, 'ttdIa05']);
        Route::post('/{id}/ia06', [AsesmenController::class, 'ttdIa06']);
        Route::post('/{id}/ia08', [AsesmenController::class, 'ttdIa08']);
        Route::post('/{id}/ia09', [AsesmenController::class, 'ttdIa09']);
        Route::post('/{id}/ia10', [AsesmenController::class, 'ttdIa10']);
        Route::post('/{id}/ak02', [AsesmenController::class, 'ttdAk02']);
        Route::post('/{id}/ak03', [AsesmenController::class, 'ttdAk03']);
        Route::post('/{id}/ak05', [AsesmenController::class, 'ttdAk05']);
        Route::post('/{id}/ak06', [AsesmenController::class, 'ttdAk06']);
        Route::post('/{id}/k3', [PraAsesmenController::class, 'ttdK3Asesmen']);
        Route::post('/{id}/tugas', [AsesmenController::class, 'ttdTugas']);
    });

    Route::prefix('asesmen')->group(function () {
        Route::post('/{id}/absen-awal', [AsesmenController::class, 'absenAwal']);
        Route::post('/{id}/absen-akhir', [AsesmenController::class, 'absenAkhir']);
        Route::get('/{id}/data-dokumen', [AsesmenController::class, 'getDokumenHeader']);
        Route::get('/{id}/ia01', [AsesmenController::class, 'getIa01']);
        Route::get('/{id}/ia02', [AsesmenController::class, 'getIa02']);
        Route::get('/{id}/ia03', [AsesmenController::class, 'getIa03']);
        Route::get('/{id}/ia04a', [AsesmenController::class, 'getIa04a']);
        Route::get('/{id}/tugas', [AsesmenController::class, 'getTugas']);
        Route::get('/{id}/ia04b', [AsesmenController::class, 'getIa04b']);
        Route::get('/{id}/ia05', [AsesmenController::class, 'getIa05']);
        Route::get('/{id}/ia06', [AsesmenController::class, 'getIa06']);
        Route::get('/{id}/ia08', [AsesmenController::class, 'getIa08']);
        Route::get('/{id}/ia09', [AsesmenController::class, 'getIa09']);
        Route::get('/{id}/ia10', [AsesmenController::class, 'getIa10']);
        Route::get('/{id}/ak02', [AsesmenController::class, 'getAk02']);
        Route::get('/{id}/ak03', [AsesmenController::class, 'getAk03']);
        Route::get('/{id}/ak05', [AsesmenController::class, 'getAk05']);
        Route::get('/{id}/ak06', [AsesmenController::class, 'getAk06']);
        Route::post('/{id}/ia01', [AsesmenController::class, 'submitIa01']);
        Route::post('/{id}/ia02', [AsesmenController::class, 'submitIa02']);
        Route::post('/{id}/ia03', [AsesmenController::class, 'submitIa03']);
        Route::post('/{id}/ia04a', [AsesmenController::class, 'submitIa04a']);
        Route::post('/{id}/tugas', [AsesmenController::class, 'uploadTugas']);
        Route::post('/{id}/ia04b', [AsesmenController::class, 'submitIa04b']);
        Route::post('/{id}/nilai-ia04b', [AsesmenController::class, 'nilaiIa04b']);
        Route::post('/{id}/ia05', [AsesmenController::class, 'submitIa05']);
        Route::post('/{id}/ia06', [AsesmenController::class, 'submitIa06']);
        Route::post('/{id}/ia08', [AsesmenController::class, 'submitIa08']);
        Route::post('/{id}/ia09', [AsesmenController::class, 'submitIa09']);
        Route::post('/{id}/ia10', [AsesmenController::class, 'submitIa10']);
        Route::post('/{id}/ak02', [AsesmenController::class, 'submitAk02']);
        Route::post('/{id}/ak03', [AsesmenController::class, 'submitAk03']);
        Route::post('/{id}/ak05', [AsesmenController::class, 'submitAk05']);
        Route::post('/{id}/ak06', [AsesmenController::class, 'submitAk06']);
        Route::delete('/{id_izin}', [PraAsesmenController::class, 'deleteAsesmenData']);
    });

    Route::post('/verifikasi/{id_izin}', [HitStatusController::class, 'hitVerifikasi']);
    Route::post('/validasi/lengkap/{id_izin}', [HitStatusController::class, 'hitLengkap']);
    Route::post('/validasi/tidak-lengkap/{id_izin}', [HitStatusController::class, 'hitTidakLengkap']);
    Route::post('/validasi/pj/{id_izin}', [HitStatusController::class, 'hitValidasiPJ']);
    Route::post('/tolak/{id_izin}', [HitStatusController::class, 'hitTolak']);

    // --- Penerbitan Sertifikat ---
    Route::prefix('penerbitan-sertifikat')->group(function () {
        Route::post('/cetak/{jadwal_id}', [PenerbitanSertifikatController::class, 'storePenerbitan']);
        Route::post('/create-blanko/{jadwal_id}', [BlankoController::class, 'submitHasilUji']);
        Route::post('/close/{jadwal_id}', [CetakJadwalController::class, 'storeCetakJadwal']);
    });

    // --- Survey pertanyaan (admin CRUD, inside auth) ---
    Route::prefix('survey')->group(function () {
        Route::get('/pertanyaan', [SurveyController::class, 'indexPertanyaan']);
        Route::post('/pertanyaan', [SurveyController::class, 'storePertanyaan']);
        Route::get('/pertanyaan/{id}', [SurveyController::class, 'showPertanyaan']);
        Route::put('/pertanyaan/{id}', [SurveyController::class, 'updatePertanyaan']);
        Route::delete('/pertanyaan/{id}', [SurveyController::class, 'destroyPertanyaan']);
    });

Route::prefix('diskon')->group(function () {
        Route::get('/', [DiskonController::class, 'index']);
    });

    Route::prefix('metode-pembayaran')->group(function () {
        Route::get('/', [MetodePembayaranController::class, 'index']);
    });

    Route::prefix('negara')->group(function () {
        Route::get('/', [NegaraController::class, 'index']);
    });

    Route::prefix('pekerjaan')->group(function () {
        Route::get('/', [PekerjaanController::class, 'index']);
    });

    Route::prefix('jenjang-jabatan-kerja')->group(function () {
        Route::get('/', [JenjangJabatanKerjaController::class, 'index']);
    });

    Route::prefix('tarif')->group(function () {
        Route::get('/', [TarifController::class, 'index']);
    });

    Route::get('/tuk-checklist-templates', [TukChecklistTemplateController::class, 'index']);

    Route::prefix('pengajuan-tuk')->group(function () {
        Route::get('/', [PengajuanTukController::class, 'index']);
        Route::post('/ajj/submit', [PengajuanTukController::class, 'submitAjj']);
        Route::post('/{id}/approve', [PengajuanTukController::class, 'approve']);
        Route::post('/{id}/reject', [PengajuanTukController::class, 'reject']);
        Route::post('/{id}/assign', [PengajuanTukController::class, 'assign']);
        Route::post('/{id}/ttd-direktur', [PengajuanTukController::class, 'ttdDirektur']);
        Route::post('/{id}/konfirmasi-tuk', [PengajuanTukController::class, 'konfirmasiTuk']);
        Route::post('/{id}/verifikasi', [PengajuanTukController::class, 'verifikasi']);
        Route::post('/{id}/validate', [PengajuanTukController::class, 'validateTuk']);
        Route::post('/{id}/tolak-validasi', [PengajuanTukController::class, 'tolakValidasi']);
        Route::post('/{id}/ttd-sk', [PengajuanTukController::class, 'ttdSk']);
        Route::get('/{id}', [PengajuanTukController::class, 'show']);
    });

    Route::prefix('dokumen-asesi')->group(function () {
        Route::get('/', [BuktiController::class, 'indexDokumenAsesi']);
    });

    Route::prefix('generate-dokumen')->group(function () {
        Route::get('/template', [\App\Http\Controllers\GenerateDokumenController::class, 'downloadTemplate']);
        Route::post('/import', [\App\Http\Controllers\GenerateDokumenController::class, 'importAndGenerate']);
    });

    Route::prefix('kan/config')->group(function () {
        Route::post('/submit', [KanConfigController::class, 'submit']);
        Route::get('/download-users', [KanConfigController::class, 'downloadUsers']);
        Route::get('/praktisi-by-jabatan/{id_jabatan_kerja}', [KanConfigController::class, 'praktisiByJabatan']);
        Route::post('/assign-praktisi', [KanConfigController::class, 'assignPraktisi']);
        Route::delete('/assign-praktisi', [KanConfigController::class, 'removePraktisi']);
    });

    // --- KAN Asesmen (authenticated) ---
    Route::prefix('kan/asesmen')->group(function () {
        Route::get('/{id}/ia04b', [KanAsesmenController::class, 'getKanIa04b']);
        Route::post('/{id}/ia04b', [KanAsesmenController::class, 'submitKanIa04b']);
        Route::get('/{id}/ia05', [KanAsesmenController::class, 'getKanIa05']);
        Route::post('/{id}/ia05', [KanAsesmenController::class, 'submitKanIa05']);
        Route::get('/{id}/ia06', [KanAsesmenController::class, 'getKanIa06']);
        Route::post('/{id}/ia06', [KanAsesmenController::class, 'submitKanIa06']);
        Route::get('/{id}/ak02', [KanAsesmenController::class, 'getKanAk02']);
        Route::post('/{id}/ak02', [KanAsesmenController::class, 'submitKanAk02']);
    });

    Route::prefix('kan/qr')->group(function () {
        Route::post('/{id}/ia04b', [KanAsesmenController::class, 'ttdKanIa04b']);
        Route::post('/{id}/ia05', [KanAsesmenController::class, 'ttdKanIa05']);
        Route::post('/{id}/ia06', [KanAsesmenController::class, 'ttdKanIa06']);
        Route::post('/{id}/ak02', [KanAsesmenController::class, 'ttdKanAk02']);
    });

    // --- Penyusun ---
    Route::prefix('penyusun')->group(function () {
        Route::get('/jabker', [PenyusunController::class, 'jabker']);
        Route::get('/skema', [PenyusunController::class, 'skema']);
        Route::get('/skema/{id}/peserta', [PenyusunController::class, 'skemaPeserta']);
        Route::get('/{idIzin}/ia04b', [PenyusunController::class, 'getIa04b']);
        Route::post('/{idIzin}/ia04b', [PenyusunController::class, 'submitIa04b']);
        Route::get('/{idIzin}/ia05', [PenyusunController::class, 'getIa05']);
        Route::post('/{idIzin}/ia05', [PenyusunController::class, 'submitIa05']);
        Route::get('/{idIzin}/ia06', [PenyusunController::class, 'getIa06']);
        Route::post('/{idIzin}/ia06', [PenyusunController::class, 'submitIa06']);
        Route::get('/{idIzin}/muk', [PenyusunController::class, 'getMuk']);
    });

    // --- Validator ---
    Route::prefix('validator')->group(function () {
        Route::get('/jabker', [ValidatorController::class, 'jabker']);
        Route::get('/skema', [ValidatorController::class, 'skema']);
        Route::get('/{idIzin}/ia04b', [ValidatorController::class, 'getIa04b']);
        Route::post('/{idIzin}/ia04b', [ValidatorController::class, 'submitIa04b']);
        Route::get('/{idIzin}/ia05', [ValidatorController::class, 'getIa05']);
        Route::post('/{idIzin}/ia05', [ValidatorController::class, 'submitIa05']);
        Route::get('/{idIzin}/ia06', [ValidatorController::class, 'getIa06']);
        Route::post('/{idIzin}/ia06', [ValidatorController::class, 'submitIa06']);
    });

    // --- Manager Mutu ---
    Route::prefix('manager-mutu')->group(function () {
        Route::get('/jabker', [ManagerMutuController::class, 'jabker']);
        Route::get('/skema', [ManagerMutuController::class, 'skema']);
        Route::get('/{idIzin}/ia04b', [ManagerMutuController::class, 'getIa04b']);
        Route::post('/{idIzin}/ia04b', [ManagerMutuController::class, 'submitIa04b']);
        Route::get('/{idIzin}/ia05', [ManagerMutuController::class, 'getIa05']);
        Route::post('/{idIzin}/ia05', [ManagerMutuController::class, 'submitIa05']);
        Route::get('/{idIzin}/ia06', [ManagerMutuController::class, 'getIa06']);
        Route::post('/{idIzin}/ia06', [ManagerMutuController::class, 'submitIa06']);
    });

    // --- Praktisi ---
    Route::prefix('praktisi')->group(function () {
        Route::get('/jabker', [PraktisiController::class, 'jabker']);
        Route::get('/{idIzin}/status', [PraktisiController::class, 'status']);
        Route::post('/{idIzin}/jawab', [PraktisiController::class, 'jawab']);
        Route::get('/{idIzin}/ia04b', [PraktisiController::class, 'getIa04b']);
        Route::get('/{idIzin}/ia05', [PraktisiController::class, 'getIa05']);
        Route::get('/{idIzin}/ia06', [PraktisiController::class, 'getIa06']);
        Route::get('/{idIzin}/apl01', [PraktisiController::class, 'getApl01']);
        Route::post('/{idIzin}/apl01', [PraktisiController::class, 'submitApl01']);
    });

    // --- QR TTD for KAN Roles ---
    Route::prefix('qr')->group(function () {
        Route::post('/{idIzin}/penyusun', [PenyusunController::class, 'ttd']);
        Route::post('/{idIzin}/validator', [ValidatorController::class, 'ttd']);
        Route::post('/{idIzin}/manager-mutu', [ManagerMutuController::class, 'ttd']);
        Route::post('/{idIzin}/praktisi', [PraktisiController::class, 'ttd']);
    });
});

// --- KAN Public Routes (no auth required) ---
Route::post('/kan/import', [KanImportController::class, 'import']);
Route::post('/kan/import/ia04b', [KanImportController::class, 'importIa04b']);
Route::post('/kan/import/ia05', [KanImportController::class, 'importIa05']);
Route::post('/kan/import/ia06', [KanImportController::class, 'importIa06']);

Route::prefix('kan/soal')->group(function () {
    Route::get('/', [KanSoalController::class, 'index']);
    Route::post('/', [KanSoalController::class, 'store']);
    Route::get('/ia04b/{id_jabatan_kerja}', [KanSoalController::class, 'getIa04bByJabatan']);
    Route::get('/ia05/{id_jabatan_kerja}', [KanSoalController::class, 'getIa05ByJabatan']);
    Route::get('/ia06/{id_jabatan_kerja}', [KanSoalController::class, 'getIa06ByJabatan']);
    Route::get('/{id}', [KanSoalController::class, 'show']);
    Route::put('/{id}', [KanSoalController::class, 'update']);
    Route::delete('/{id}', [KanSoalController::class, 'destroy']);
    Route::post('/bulk', [KanSoalController::class, 'bulkStore']);
});

Route::prefix('kan/ref')->group(function () {
    Route::get('/unit-kompetensi/{id_jabatan}', [KanSoalController::class, 'refUnitKompetensi']);
    Route::get('/kuk/{id_unit}', [KanSoalController::class, 'refKuk']);
    Route::get('/dokumen/{id_jabatan}', [KanSoalController::class, 'refDokumen']);
    Route::get('/skema/{id_jabatan}', [KanSoalController::class, 'refSkema']);
});