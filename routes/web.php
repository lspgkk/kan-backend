<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CertificateController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/validasi-signature/{qr_signature}', [CertificateController::class, 'showSignature'])
    ->name('validasi-signature');
