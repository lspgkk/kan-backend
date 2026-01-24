<?php

use App\Http\Controllers\AsesorController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermohonanController;
use App\Http\Controllers\HitStatusController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JadwalController;
use App\Http\Controllers\JenisAnggaranController;
use App\Http\Controllers\JenisKelasController;
use App\Http\Controllers\JenjangController;
use App\Http\Controllers\KomtekController;
use App\Http\Controllers\KualifikasiController;
use App\Http\Controllers\MigrationController;
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
        Route::get('/sync/permohonan', [PermohonanController::class, 'syncPermohonan']);
        Route::get('/{id}', [PermohonanController::class, 'detailPermohonan']);
    });

    Route::prefix('jadwal')->group(function () {
        Route::get('/', [JadwalController::class, 'index']);
        Route::get('/blanko', [JadwalController::class, 'blankos']);
        Route::get('/sertifikasi', [JadwalController::class, 'sertifikasi']);
        Route::get('/{id}', [JadwalController::class, 'show']);
    });

    Route::prefix('kegiatan')->group(function () {
        Route::get('/asesi', [JadwalController::class, 'getKegiatanAsesi']);
        Route::get('/asesor', [JadwalController::class, 'getKegiatanAsesor']);
        Route::get('/komtek', [JadwalController::class, 'getKegiatanKomtek']);
        Route::get('/direktur', [JadwalController::class, 'getKegiatanDirektur']);
        Route::get('/admin-tuk', [JadwalController::class, 'getKegiatanAdminTuk']);
        Route::post('/{id}/start', [JadwalController::class, 'startAsesmen']);
        Route::get('/{id}/list-asesi', [JadwalController::class, 'listAsesi']);
    });

    Route::post('/verifikasi/{id_izin}', [HitStatusController::class, 'hitVerifikasi']);
});