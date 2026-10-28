<?php

namespace App\Libraries;

use App\Models\Configs;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SatuSehatService
{
    protected $config;
    protected $baseUrl;
    protected $timeout = 30;

    public function __construct()
    {
        $this->initConfig();
    }

    /**
     * Inisialisasi konfigurasi SATUSEHAT secara aman tanpa crash jika tabel database tidak ada.
     */
    protected function initConfig()
    {
        try {
            $this->config = Configs::where('tipe', 2)->first();
        } catch (\Throwable $e) {
            $this->config = null;
        }

        if (!$this->config) {
            // Fallback konfigurasi aman dari Environment / default
            $this->config = (object) [
                'id'                => 1,
                'tipe'              => 2,
                'kode'              => '01',
                'url'               => env('SATUSEHAT_AUTH_URL', 'https://api-satusehat.kemkes.go.id/oauth2/v1/accesstoken?grant_type=client_credentials'),
                'client_key'        => env('SATUSEHAT_CLIENT_ID', env('SATUSEHAT_CLIENT_KEY', 'satusehat_client_id')),
                'secret_key'        => env('SATUSEHAT_CLIENT_SECRET', env('SATUSEHAT_SECRET_KEY', 'satusehat_secret_key')),
                'kode_organization' => env('ORG_ID_PROUD', '100026368'),
            ];
        }

        $this->baseUrl = $this->config->url ?? 'https://api-satusehat.kemkes.go.id/oauth2/v1/accesstoken?grant_type=client_credentials';
    }

    /**
     * Mengambil OAuth2 Access Token dari SATUSEHAT dengan Caching aman (TTL ~50 menit).
     */
    public function getToken(bool $forceRefresh = false): array
    {
        $cacheKey = 'satusehat_oauth_token_' . md5($this->config->client_key ?? 'default');

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 3000, function () {
            return $this->requestNewToken();
        });
    }

    /**
     * Permintaan token baru ke endpoint OAuth2 Kemkes.
     */
    protected function requestNewToken(): array
    {
        $url = rtrim($this->baseUrl);

        if (empty($this->config->client_key) || empty($this->config->secret_key)) {
            return [
                'error'   => 'Konfigurasi SATUSEHAT belum lengkap',
                'message' => 'Client ID atau Client Secret kosong.',
            ];
        }

        $body = http_build_query([
            'client_id'     => $this->config->client_key,
            'client_secret' => $this->config->secret_key,
        ]);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json',
            ])
            ->timeout($this->timeout)
            ->send('POST', $url, [
                'body' => $body,
            ]);

            $json = $response->json();

            if ($response->successful() && !empty($json['access_token'])) {
                return $json;
            }

            Log::error('SATUSEHAT OAuth2 Token Error: HTTP ' . $response->status());
            return [
                'error'       => 'Token gagal di-generate',
                'http_status' => $response->status(),
                'detail'      => is_array($json) ? $this->sanitizeResponse($json) : ['raw' => 'Invalid token response'],
            ];
        } catch (\Throwable $e) {
            Log::error('SATUSEHAT OAuth2 Request Exception: ' . $e->getMessage());
            return [
                'error'   => 'Gagal menghubungi server autentikasi SATUSEHAT',
                'message' => 'Koneksi timeout atau jaringan bermasalah.',
            ];
        }
    }

    /**
     * Menghubungi API SATUSEHAT dengan method HTTP yang ditentukan.
     * @param mixed $data
     */
    public function connect(string $method, string $url, $data = [], bool $isRetryAfter401 = false): array
    {
        $token = $this->getToken($isRetryAfter401);
        $accessToken = $token['access_token'] ?? null;

        if (!$accessToken) {
            return [
                'error'        => 'Token gagal di-generate',
                '_success'     => false,
                '_http_status' => 401,
                'detail'       => $this->sanitizeResponse($token),
            ];
        }

        $client = Http::withToken($accessToken)
            ->withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout);

        try {
            $methodLower = strtolower($method);
            switch ($methodLower) {
                case 'post':
                    $response = $client->post($url, (array) $data);
                    break;
                case 'put':
                    $response = $client->put($url, (array) $data);
                    break;
                case 'patch':
                    $response = $client->patch($url, (array) $data);
                    break;
                case 'delete':
                    $response = $client->delete($url);
                    break;
                default:
                    // GET: jangan kirim body array jika kosong
                    $response = empty($data) ? $client->get($url) : $client->get($url, (array) $data);
            }

            // Jika token kedaluwarsa (HTTP 401), coba refresh token 1 kali
            if ($response->status() === 401 && !$isRetryAfter401) {
                return $this->connect($method, $url, $data, true);
            }

            $rawBody = $response->body();
            $json = json_decode($rawBody, true);

            if (is_array($json)) {
                $json['_http_status'] = $response->status();
                $json['_success'] = $response->successful();
                $json['_raw'] = $rawBody;
                return $json;
            }

            return [
                '_http_status' => $response->status(),
                '_success'     => $response->successful(),
                'raw'          => $rawBody,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("SATUSEHAT Connection/Timeout Error: " . $e->getMessage());
            return [
                'error'        => 'Gagal melakukan request (Koneksi timeout/jaringan)',
                'error_type'   => 'TIMEOUT_OR_NETWORK',
                '_success'     => false,
                '_http_status' => 408,
                'message'      => 'Request ke API SATUSEHAT mengalami timeout atau koneksi terputus.',
            ];
        } catch (\Throwable $e) {
            Log::error("SATUSEHAT Request Exception: " . $e->getMessage());
            return [
                'error'        => 'Gagal melakukan request',
                'error_type'   => 'EXCEPTION',
                '_success'     => false,
                '_http_status' => 500,
                'message'      => 'Terjadi kesalahan sistem saat menghubungi SATUSEHAT.',
            ];
        }
    }

    /**
     * Sanitasi data respons untuk mencegah kebocoran kredensial.
     */
    protected function sanitizeResponse(array $data): array
    {
        $sensitiveKeys = ['client_secret', 'secret_key', 'access_token', 'token'];
        foreach ($data as $k => $v) {
            if (in_array(strtolower($k), $sensitiveKeys, true)) {
                $data[$k] = '***REDACTED***';
            } elseif (is_array($v)) {
                $data[$k] = $this->sanitizeResponse($v);
            }
        }
        return $data;
    }
}
