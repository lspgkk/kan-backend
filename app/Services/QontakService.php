<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QontakService
{
    private string $baseUrl = 'https://service-chat.qontak.com';
    private ?string $accessToken = null;
    private ?string $refreshToken;
    private string $clientId;
    private string $clientSecret;
    private string $channelIntegrationId;
    private string $templateId;

    public function __construct()
    {
        $this->clientId = config('services.qontak.client_id');
        $this->clientSecret = config('services.qontak.client_secret');
        $this->refreshToken = config('services.qontak.refresh_token');
        $this->channelIntegrationId = config('services.qontak.channel_integration_id');
        $this->templateId = config('services.qontak.template_id');
    }

    /**
     * Refresh OAuth token from Qontak.
     */
    private function refreshToken(): void
    {
        $response = Http::post("{$this->baseUrl}/oauth/token", [
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if (!$response->successful()) {
            Log::error('Qontak token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Gagal refresh token Qontak: ' . $response->body());
        }

        $data = $response->json();
        $this->accessToken = $data['access_token'] ?? null;

        if (!$this->accessToken) {
            throw new \RuntimeException('Qontak token refresh: access_token tidak ditemukan');
        }
    }

    /**
     * Send WhatsApp template message via Qontak.
     *
     * @param string $toNumber  Recipient phone number (e.g. 6281234567890)
     * @param string $toName    Recipient name
     * @param array  $params    Body parameters: ['nama_asesi', 'tanggal_uji', 'tuk', 'skema', 'link']
     * @return array|null       Response data or null on failure
     */
    public function sendMessage(string $toNumber, string $toName, array $params): ?array
    {
        if (!$this->accessToken) {
            $this->refreshToken();
        }

        $body = [
            'to_name' => $toName,
            'to_number' => $toNumber,
            'message_template_id' => $this->templateId,
            'channel_integration_id' => $this->channelIntegrationId,
            'language' => [
                'code' => 'id',
            ],
            'parameters' => [
                'body' => [
                    ['key' => '1', 'value_text' => $params['nama_asesi'], 'value' => 'nama_asesi'],
                    ['key' => '2', 'value_text' => $params['tanggal_uji'], 'value' => 'tanggal_uji'],
                    ['key' => '3', 'value_text' => $params['tuk'], 'value' => 'tuk'],
                    ['key' => '4', 'value_text' => $params['skema'], 'value' => 'skema'],
                    ['key' => '5', 'value_text' => $params['link'], 'value' => 'link'],
                ],
            ],
        ];

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->accessToken}",
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/api/open/v1/broadcasts/whatsapp/direct", $body);

        if ($response->successful()) {
            return $response->json();
        }

        // If 401, try refreshing token once
        if ($response->status() === 401) {
            Log::info('Qontak token expired, refreshing...');
            try {
                $this->refreshToken();
            } catch (\RuntimeException $e) {
                Log::error('Qontak token refresh failed on retry');
                return null;
            }

            // Retry with new token
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/open/v1/broadcasts/whatsapp/direct", $body);

            if ($response->successful()) {
                return $response->json();
            }
        }

        Log::error('Qontak send message failed', [
            'status' => $response->status(),
            'body' => $response->body(),
            'to' => $toNumber,
        ]);

        return null;
    }
}
