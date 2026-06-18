<?php

namespace App\Services;

use App\Models\SikiAuth;

/**
 * Service untuk mengontrol interaksi dengan SIKI API.
 *
 * - Jika ada SikiAuth dengan is_active = true:
 *   → App akan melakukan POST/request asli ke SIKI API.
 *
 * - Jika tidak ada SikiAuth yang aktif (is_active = false / table kosong):
 *   → App hanya update status di database lokal, tanpa hit SIKI API.
 */
class SikiService
{
    protected ?SikiAuth $activeAuth = null;

    /**
     * Cari & cache auth aktif.
     */
    protected function resolve(): ?SikiAuth
    {
        if ($this->activeAuth === null) {
            $this->activeAuth = SikiAuth::where('is_active', true)->first();
        }
        return $this->activeAuth;
    }

    /**
     * Apakah koneksi SIKI aktif?
     * Jika true → request asli ke SIKI API.
     * Jika false → hanya update DB lokal.
     */
    public function isActive(): bool
    {
        return $this->resolve() !== null;
    }

    /**
     * Ambil token SIKI.
     */
    public function getToken(): ?string
    {
        return $this->resolve()?->token;
    }

    /**
     * Ambil base URL SIKI API.
     */
    public function getBaseUrl(): string
    {
        return $this->resolve()?->base_url ?? 'https://siki.pu.go.id/siki-api/v1';
    }

    /**
     * Headers standar untuk request ke SIKI API.
     */
    public function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'token'        => $this->getToken(),
        ];
    }

    /**
     * Reset cache — panggil jika ada perubahan data auth di runtime.
     */
    public function reset(): void
    {
        $this->activeAuth = null;
    }

    /**
     * Check connection to SIKI API by hitting the /status endpoint.
     *
     * @return array{success: bool, message: string, status_code: int|null, data: mixed}
     */
    public function checkConnection(): array
    {
        // Jika tidak ada auth aktif, jangan hit SIKI — anggap berhasil saja
        if (!$this->isActive()) {
            return [
                'success'     => true,
                'message'     => 'Mode offline: koneksi SIKI tidak aktif, sistem berjalan secara lokal.',
                'status_code' => 200,
                'data'        => null,
            ];
        }

        $url = $this->getBaseUrl() . '/status';

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10, 'verify' => false]);
            $response = $client->get($url, [
                'headers' => $this->getHeaders(),
                'http_errors' => true,
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            return [
                'success'     => $statusCode === 200,
                'message'     => $statusCode === 200 ? 'Koneksi ke SIKI berhasil' : 'SIKI merespon dengan status ' . $statusCode,
                'status_code' => $statusCode,
                'data'        => $body,
            ];
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            return [
                'success'     => false,
                'message'     => 'Tidak dapat terhubung ke SIKI: ' . $e->getMessage(),
                'status_code' => null,
                'data'        => null,
            ];
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = json_decode($e->getResponse()->getBody()->getContents(), true);

            return [
                'success'     => false,
                'message'     => 'SIKI merespon dengan error: ' . $statusCode . ' ' . $e->getMessage(),
                'status_code' => $statusCode,
                'data'        => $body,
            ];
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = json_decode($e->getResponse()->getBody()->getContents(), true);

            return [
                'success'     => false,
                'message'     => 'SIKI server error: ' . $statusCode . ' ' . $e->getMessage(),
                'status_code' => $statusCode,
                'data'        => $body,
            ];
        } catch (\Exception $e) {
            return [
                'success'     => false,
                'message'     => 'Error saat menghubungi SIKI: ' . $e->getMessage(),
                'status_code' => null,
                'data'        => null,
            ];
        }
    }
}
