<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CertificateController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/validasi-signature/{qr_signature}', [CertificateController::class, 'showSignature'])
    ->name('validasi-signature');

Route::prefix('generate-dokumen')->group(function () {
    Route::get('/', [\App\Http\Controllers\GenerateDokumenController::class, 'showForm'])
        ->name('generate-dokumen.form');
    Route::get('/template', [\App\Http\Controllers\GenerateDokumenController::class, 'downloadTemplate'])
        ->name('generate-dokumen.template');
    Route::post('/import', [\App\Http\Controllers\GenerateDokumenController::class, 'importFromWeb'])
        ->name('generate-dokumen.import');
    Route::get('/progress/{batchId}', [\App\Http\Controllers\GenerateDokumenController::class, 'showProgress'])
        ->name('generate-dokumen.progress');
    Route::get('/api-progress/{batchId}', [\App\Http\Controllers\GenerateDokumenController::class, 'getProgress']);
    Route::get('/download/{batchId}', [\App\Http\Controllers\GenerateDokumenController::class, 'downloadResult'])
        ->name('generate-dokumen.download');
});
