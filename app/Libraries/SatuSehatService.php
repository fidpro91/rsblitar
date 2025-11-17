<?php

namespace App\Libraries;

use App\Models\Configs;
use Illuminate\Support\Facades\Http;

class SatuSehatService
{
    protected $config;
    protected $baseUrl;

    public function __construct()
    {
        // Ambil config sesuai tipe (staging/production)
        $this->config = Configs::where('tipe', 2)->firstOrFail();
        $this->baseUrl = $this->config->url;
    }

    public function getToken()
    {
        $url = rtrim($this->baseUrl);

        $body = http_build_query([
            'client_id'     => $this->config->client_key,
            'client_secret' => $this->config->secret_key,
        ]);

        $response = Http::withHeaders([
            "Content-Type" => "application/x-www-form-urlencoded"
        ])
            ->send('POST', $url, [
                'body' => $body
            ]);

        return $response->json();
    }
    public function connect($method, $url, $data = []): array
    {
        $token = $this->getToken();
        $accessToken = $token['access_token'] ?? null;

        if (!$accessToken) {
            return ['error' => 'Token gagal di-generate', 'detail' => $token];
        }

        $client = Http::withToken($accessToken);
        try {
            switch (strtolower($method)) {
                case 'post':
                    $response = $client->post($url, $data);
                    break;
                case 'put':
                    $response = $client->put($url, $data);
                    break;
                case 'patch':
                    $response = $client->patch($url, $data);
                    break;
                case 'delete':
                    $response = $client->delete($url);
                    break;
                default:
                    $response = $client->get($url, $data);
            }
            $json = json_decode($response->body(), true);

            return is_array($json) ? $json : ['raw' => $response->body()];
        } catch (\Exception $e) {
            return [
                'error' => 'Gagal melakukan request',
                'message' => $e->getMessage()
            ];
        }
    }
}
