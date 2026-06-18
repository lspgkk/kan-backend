<?php

namespace App\Http\Controllers;

use App\Services\SikiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SikiController extends Controller
{
    protected SikiService $sikiService;

    public function __construct(SikiService $sikiService)
    {
        $this->sikiService = $sikiService;
    }

    /**
     * Check connection to SIKI API.
     * Hits https://siki.pu.go.id/siki-api/v1/status to verify connectivity.
     */
    public function checkConnection(): JsonResponse
    {
        $result = $this->sikiService->checkConnection();

        return response()->json([
            'success'      => $result['success'],
            'message'      => $result['message'],
            'status_code'  => $result['status_code'],
            'data'         => $result['data'],
            'siki_active'  => $this->sikiService->isActive(),
        ]);
    }

    /**
     * Check connection to BNSP API.
     * Hits https://konstruksi.bnsp.go.id/api/v1/master/status to verify connectivity.
     */
    public function checkBnspConnection(): JsonResponse
    {
        $authData = DB::connection('balai')
            ->table('b_n_s_p_auths')
            ->value('x_authorization');

        if (!$authData) {
            return response()->json([
                'success'      => false,
                'message'      => 'x-authorization tidak ditemukan di tabel b_n_s_p_auths',
                'status_code'  => null,
                'data'         => null,
                'bnsp_active'  => false,
            ]);
        }

        $url = 'https://konstruksi.bnsp.go.id/api/v1/master/status';

        try {
            $response = Http::timeout(15)
                ->retry(2, 100)
                ->withHeaders([
                    'Content-Type'    => 'application/json',
                    'x-authorization' => $authData,
                ])->get($url);

            $statusCode = $response->status();

            if ($response->successful()) {
                return response()->json([
                    'success'      => true,
                    'message'      => 'Koneksi ke BNSP berhasil',
                    'status_code'  => $statusCode,
                    'data'         => $response->json(),
                    'bnsp_active'  => true,
                ]);
            }

            return response()->json([
                'success'      => false,
                'message'      => 'BNSP merespon dengan status: ' . $statusCode,
                'status_code'  => $statusCode,
                'data'         => $response->json(),
                'bnsp_active'  => true,
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json([
                'success'      => false,
                'message'      => 'Tidak dapat terhubung ke BNSP: ' . $e->getMessage(),
                'status_code'  => null,
                'data'         => null,
                'bnsp_active'  => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('[SikiController] checkBnspConnection error: ' . $e->getMessage());

            return response()->json([
                'success'      => false,
                'message'      => 'Error saat menghubungi BNSP: ' . $e->getMessage(),
                'status_code'  => null,
                'data'         => null,
                'bnsp_active'  => true,
            ]);
        }
    }
}
