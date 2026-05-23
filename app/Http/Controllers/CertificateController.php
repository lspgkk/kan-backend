<?php

namespace App\Http\Controllers;

use App\Models\DataPencatatan;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

class CertificateController extends Controller
{
    /**
     * Display certificate signature validation data from QR code scan.
     */
    public function showSignature(string $qrSignature)
    {
        $data = DataPencatatan::where('qr_signature', $qrSignature)->first();

        if (!$data) {
            return view('certificate.signature', [
                'result' => null,
                'error' => 'Signature tidak ditemukan. Sertifikat mungkin tidak valid.',
            ]);
        }

        $updatedAt = new DateTime($data->updated_at, new DateTimeZone('UTC'));
        $updatedAt->setTimezone(new DateTimeZone('Asia/Jakarta'));

        $ditetapkan = new DateTime($data->tanggal_ditetapkan, new DateTimeZone('UTC'));
        $ditetapkan->setTimezone(new DateTimeZone('Asia/Jakarta'));

        $result = [
            'nama'   => $data->nama,
            'jabker' => $data->jabatan_kerja,
            'tgl'    => $ditetapkan->format('d-M-Y') . ' ' . $updatedAt->format('H:i:s'),
        ];

        return view('certificate.signature', [
            'result' => $result,
            'error' => null,
        ]);
    }
}
