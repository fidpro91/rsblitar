<?php
namespace App\Services;

use App\Http\Controllers\Api\Word_builderController;
use App\Models\Log_http;
use App\Models\Tte_successfully;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TteService
{
    public function signPdf($request)
    {
        $request = new Request($request);
        $ehos = DB::connection('db_simrs');
        $params = [];

        try {
            $urlDocx = app(Word_builderController::class);
            $urlDocx = $urlDocx->generate_word($request);
            if ($urlDocx['code'] != 200) {
                throw new \Exception($urlDocx['message'], $urlDocx['code']);
            }

            // Susun parameter GET sesuai spesifikasi API TTE:
            // ?api=signdocx&docx=https://...&nik=...&passphrase=...
            $params = [
                "api"        => config('tte.signDocx', 'signdocx'),
                "docx"       => config('tte.domainRS') . $urlDocx['file'],
                "nik"        => (string) $request->nik,
                "passphrase" => (string) $request->passphrase,
            ];

            $url = config('tte.api_url');

            // --- Mengirim request GET ke server TTE ---
            $response = Http::withoutVerifying()->timeout(60)->get($url, $params);

            if ($response->failed()) {
                throw new \Exception("Server TTE HTTP error (" . $response->status() . "): " . $response->body(), $response->status());
            }

            $rawBody = trim($response->body());
            $data = $response->json();

            // Cek jika field 'signed' tidak ada atau kosong
            if (!is_array($data) || empty($data['signed'])) {
                $errorMessage = null;

                // 1. Cek format error JSON dari BSrE/TTE: {"error": "Passphrase anda salah", "status_code": 2031}
                if (is_array($data)) {
                    if (!empty($data['error'])) {
                        $errorMessage = is_string($data['error']) ? $data['error'] : json_encode($data['error']);
                    } elseif (!empty($data['message'])) {
                        $errorMessage = is_string($data['message']) ? $data['message'] : json_encode($data['message']);
                    }
                }

                // 2. Jika respons berupa teks/HTML
                if (!$errorMessage && !empty($rawBody)) {
                    $errorMessage = strip_tags(substr($rawBody, 0, 200));
                }

                // 3. Jika respons benar-benar kosong (khas server TTE jika NIK tidak terdaftar di BSrE)
                if (!$errorMessage) {
                    $errorMessage = "Server TTE merespons kosong. NIK ({$params['nik']}) kemungkinan belum terdaftar / belum aktif di BSrE, atau passphrase tidak valid.";
                }

                throw new \Exception($errorMessage, 403);
            }

            // --- Download file hasil sign ---
            $fileContent = @file_get_contents($data['signed']);
            if ($fileContent === false) {
                throw new \Exception("Gagal mendownload PDF dari URL signed: " . $data['signed'], 404);
            }

            // --- Simpan ke storage ---
            $fileName = $request->berkas . "_" . $request->visit_id . "_" . $request->id_berkas . ".pdf";
            $directori = "signed/$request->visit_id";
            $saved = Storage::disk('public')->put($directori . "/" . $fileName, $fileContent);
            if (!$saved) {
                throw new \Exception("Gagal menyimpan file PDF ke storage", 405);
            }

            @unlink($urlDocx['location']);
            $pathTTE = config('tte.ip_server') . "storage/$directori/" . $fileName;
            $ehos->table("yanmed.visit")
                ->where('visit_id', $request->visit_id)
                ->update([
                    'tteresume'         => $pathTTE,
                    'respond_message'   => 'Berhasil sign TTE',
                    'respond_status'    => '200',
                ]);

            // --- Return sukses & simpan response asli ke log ---
            $this->logging('sign TTE', [
                "url"       => $params['api'],
                "method"    => 'get',
                "code"      => 200,
                "body"      => $rawBody,
                "status"    => 200,
                "error_message" => "Success"
            ], $request->visit_id);

            // --- Simpan ke tabel tte_successfully ---
            Tte_successfully::create([
                'visit_id'  => $request->visit_id,
                'doc_id'    => $request->id_berkas,
                'path_tte'  => $pathTTE,
            ]);

        } catch (\Exception $e) {
            // Simpan respons asli server TTE jika ada ke log_http
            $responseBodyToLog = isset($response) ? $response->body() : json_encode($params);
            if (empty(trim($responseBodyToLog))) {
                $responseBodyToLog = json_encode([
                    'request' => $params,
                    'server_response' => 'RESPONS_KOSONG (Server TTE tidak mengembalikan data, kemungkinan NIK tidak terdaftar di BSrE)'
                ]);
            }

            $this->logging('sign TTE', [
                "url"       => $params['api'] ?? config('tte.signDocx', 'signdocx'),
                "method"    => 'get',
                "code"      => $e->getCode() ?: 500,
                "body"      => $responseBodyToLog,
                "status"    => 500,
                "error_message" => $e->getMessage()
            ], $request->visit_id);

            $ehos->table("yanmed.visit")
                ->where('visit_id', $request->visit_id)
                ->update([
                    'respond_message'   => $e->getMessage(),
                    'respond_status'    => '500',
                ]);
        }
    }

    protected function logging($service_name, $data = null, $visit_id)
    {
        Log_http::create(
            [
                'service_name'      => $service_name,
                'endpoint_url'      => $data['url'],
                'http_method'       => $data['method'],
                'response_code'     => $data['code'],
                'response_body'     => $data['body'],
                'status'            => $data['status'],
                'fk_id'             => $visit_id,
                'response_message'  => $data['error_message'],
            ]
        );
    }
}
