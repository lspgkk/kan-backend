<?php

use App\Http\Controllers\AsesmenController;
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
use App\Http\Controllers\TukController;

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

Route::middleware(['auth:api'])->group(function () {

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
        Route::get('/{id}', [PermohonanController::class, 'detailPermohonan']);
    });

    Route::prefix('tuk')->group(function () {
        Route::get('/siki', [TukController::class, 'allTukSiki']);
        Route::get('/bnsp', [TukController::class, 'allTukBnsp']);
    });

    Route::prefix('subklasifikasi')->group(function () {
        Route::get('/', [SubklasifikasiController::class, 'index']);
    });

    Route::prefix('kualifikasi')->group(function () {
        Route::get('/', [KualifikasiController::class, 'index']);
    });

    Route::prefix('asesor')->group(function () {
        Route::get('/', [AsesorController::class, 'index']);
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
    });

    Route::prefix('direktur')->group(function () {
        Route::get('/files/{id}', [DirekturController::class, 'jadwalFiles']);
    });

    Route::prefix('jenjang')->group(function () {
        Route::get('/', [JenjangController::class, 'index']);
    });

    Route::prefix('city')->group(function () {
        Route::get('/', [CityController::class, 'index']);
    });

    Route::prefix('province')->group(function () {
        Route::get('/', [ProvinceController::class, 'index']);
    });

    Route::prefix('invoice')->group(function () {
        Route::get('/reguler', [InvoiceController::class, 'viewInvoiceBaruReguler']);
        Route::get('/balai', [InvoiceController::class, 'viewInvoiceBaruBalai']);
        Route::get('/fg', [InvoiceController::class, 'viewInvoiceBaruFg']);
        Route::get('/rekening', [InvoiceController::class, 'rekening']);
        Route::get('/konfirmasi', [InvoiceController::class, 'viewKonfirmasi']);
        Route::get('/history', [InvoiceController::class, 'history']);
        Route::post('/create', [InvoiceController::class, 'createInvoice']);
        Route::post('/konfirmasi', [InvoiceController::class, 'confirmInvoice']);
    });

    Route::prefix('jadwal')->group(function () {
        Route::get('/', [JadwalController::class, 'index']);
        Route::get('/blanko', [JadwalController::class, 'blankos']);
        Route::get('/sertifikasi', [JadwalController::class, 'sertifikasi']);
        Route::get('/list-asesi-create', [JadwalController::class, 'listAsesiCreateJadwal']);
        Route::post('/create-jadwal', [JadwalController::class, 'store']);
        Route::get('/{id}', [JadwalController::class, 'show']);
        Route::put('/{jadwal_id}', [JadwalController::class, 'updateJadwal']);
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
    });

    Route::prefix('bukti')->group(function () {
        Route::post('/{id}/foto-kegiatan', [BuktiController::class, 'uploadKegiatan']);
        Route::post('/{id}/kegiatan', [BuktiController::class, 'uploadBuktiKegiatan']);
        Route::get('/{id}/kegiatan', [BuktiController::class, 'getBuktiKegiatan']);
    });

    Route::get('/dokumen/asesi/{id}', [AsesmenController::class, 'dokumenAsesi']);
    Route::get('/dokumen/absen/{id}', [AsesmenController::class, 'absensi']);
    Route::get('/generate-penyusun', [BarcodeController::class, 'generateBarcodePenyusun']);
    
    Route::prefix('praasesmen')->group(function () {
        Route::post('/{id}/absen-awal', [PraAsesmenController::class, 'absenAwal']);
        Route::post('/{id}/absen-akhir', [PraAsesmenController::class, 'absenAkhir']);
        Route::get('/kebenaran-data', [KebenaranDataController::class, 'index']);
        Route::get('/{id}/data-dokumen', [PraAsesmenController::class, 'getDokumenHeader']);
        Route::get('/{id}/apl01', [PraAsesmenController::class, 'getApl01']);
        Route::get('/{id}/apl02', [PraAsesmenController::class, 'getApl02']);
        Route::get('{id}/apl02/files', [PraAsesmenController::class, 'getApl02Files']);
        Route::get('/{id}/mapa01', [PraAsesmenController::class, 'getMapa01']);
        Route::get('/{id}/mapa02', [PraAsesmenController::class, 'getMapa02']);
        Route::get('/{id}/ak07', [PraAsesmenController::class, 'getAk07']);
        Route::get('/{id}/ak04', [PraAsesmenController::class, 'getAk04']);
        Route::get('/file-k3', [PraAsesmenController::class, 'getK3Asesmen']);
        Route::get('/{id}/ak01', [PraAsesmenController::class, 'getAk01']);
        Route::post('/{id}/apl01', [PraAsesmenController::class, 'submitApl01']);
        Route::post('/{id}/apl02', [PraAsesmenController::class, 'submitApl02']);
        Route::post('/{id}/apl02/files', [PraAsesmenController::class, 'uploadApl02Files']);
        Route::post('/{id}/mapa01', [PraAsesmenController::class, 'generateMapa01']);
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
        Route::post('/{id}/ak07', [PraAsesmenController::class, 'ttdAk07']);
        Route::post('/{id}/ak04', [PraAsesmenController::class, 'ttdAk04']);
        Route::post('/{id}/ak01', [PraAsesmenController::class, 'ttdAk01']);
        Route::post('/{id}/ia01', [AsesmenController::class, 'ttdIa01']);
        Route::post('/{id}/ia02', [AsesmenController::class, 'ttdIa02']);
        Route::post('/{id}/ia03', [AsesmenController::class, 'ttdIa03']);
        Route::post('/{id}/ia04a', [AsesmenController::class, 'ttdIa04a']);
        Route::post('/{id}/ia04b', [AsesmenController::class, 'ttdIa04b']);
        Route::post('/{id}/ia05', [AsesmenController::class, 'ttdIa05']);
        Route::post('/{id}/ak02', [AsesmenController::class, 'ttdAk02']);
        Route::post('/{id}/ak05', [AsesmenController::class, 'ttdAk05']);
        Route::post('/{id}/ak06', [AsesmenController::class, 'ttdAk06']);
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
        Route::post('/{id}/ak02', [AsesmenController::class, 'submitAk02']);
        Route::post('/{id}/ak03', [AsesmenController::class, 'submitAk03']);
        Route::post('/{id}/ak05', [AsesmenController::class, 'submitAk05']);
        Route::post('/{id}/ak06', [AsesmenController::class, 'submitAk06']);
        Route::delete('/{id_izin}', [PraAsesmenController::class, 'deleteAsesmenData']);
    });

    Route::post('/verifikasi/{id_izin}', [HitStatusController::class, 'hitVerifikasi']);
    Route::post('/validasi/lengkap/{id_izin}', [HitStatusController::class, 'hitLengkap']);
    Route::post('/validasi/tidak-lengkap/{id_izin}', [HitStatusController::class, 'hitTidakLengkap']);
    Route::post('/tolak/{id_izin}', [HitStatusController::class, 'hitTolak']);

    // --- Penerbitan Sertifikat ---
    Route::prefix('penerbitan-sertifikat')->group(function () {
        Route::post('/cetak/{jadwal_id}', [PenerbitanSertifikatController::class, 'storePenerbitan']);
        Route::post('/create-blanko/{jadwal_id}', [BlankoController::class, 'submitHasilUji']);
        Route::post('/close/{jadwal_id}', [CetakJadwalController::class, 'storeCetakJadwal']);
    });
});