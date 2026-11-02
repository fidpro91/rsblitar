<?php

namespace App\Http\Controllers\Api;

use App\Jobs\SendTteJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SignTteController extends BaseApiController
{

    public function signedpdf(Request $request)
    {
        try {
            $this->validateSignedPdfRequest($request);
            SendTteJob::dispatch($request);
            return response()->json([
                "code"    => "200",
                "message" => "TTE sedang diproses, file akan tersedia dalam beberapa saat",
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "code"    => "500",
                "message" => "Gagal memproses tanda tangan",
                "error"   => $e->getMessage() . $e->getLine() . $e->getFile()
            ], 500);
        }

    }

    public function signedpdfJob($request)
    {
        $params = [];
        try {
            $this->validateSignedPdfRequest($request);

            if ($request->jenis_berkas == 'pdf') {
                $params = [
                    "api"        => config('tte.signPdf', 'sign'),
                    "pdf"        => $request->url,
                    "nik"        => (string) $request->nik,
                    "passphrase" => (string) $request->passphrase,
                ];
            } else {
                $urlDocx = $this->generateWord($request->url);
                $params = [
                    "api"        => config('tte.signDocx', 'signdocx'),
                    "docx"       => config('tte.domainRS', "https://simrs-rsudmw.blitarkota.go.id/sign/") . $urlDocx,
                    "nik"        => (string) $request->nik,
                    "passphrase" => (string) $request->passphrase,
                ];
            }

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

            $saved = Storage::disk('public')->put("signed/" . $fileName, $fileContent);
            if (!$saved) {
                throw new \Exception("Gagal menyimpan file PDF ke storage", 405);
            }

            // --- Return sukses ---
            return response()->json([
                "code"    => "200",
                "message" => "Berhasil sign TTE",
                "data"    => [
                    "url" => request()->getSchemeAndHttpHost() . "/storage/signed/" . $fileName
                ]
            ]);
        } catch (\Exception $e) {
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
            ]);

            return response()->json([
                "code"    => "500",
                "message" => "Gagal memproses tanda tangan: " . $e->getMessage(),
                "error"   => $e->getMessage()
            ], 500);
        }
    }

    private function validateSignedPdfRequest($request)
    {
        $rules = [
            "nik"          => "required",
            "passphrase"   => "required",
            "jenis_berkas" => "required|in:pdf,docx",
            "berkas"       => "required",
            "visit_id"     => "required|numeric",
            "id_berkas"    => "required",
        ];

        $messages = [
            "required" => ":attribute wajib diisi",
            "in"       => ":attribute tidak valid",
            "numeric"  => ":attribute harus angka"
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            throw new \Exception("Validasi gagal: " . $validator->errors()->first());
        }
    }

    public function generateWord($url)
    {
        $response = Http::withoutVerifying()->get($url);
        $html = $response->body();
        if (!$html) {
            throw new \Exception("HTML kosong atau gagal diambil");
        }
        // Escape {QR} agar tidak error di LibreOffice
        $html = str_replace('{QR}', '&#123;QR&#125;', $html);
        Storage::makeDirectory('temp');
        $tempHtmlPath = Storage::path('temp/report_' . time() . '.html');
        file_put_contents($tempHtmlPath, $html);

        if (!file_exists($tempHtmlPath)) {
            throw new \Exception("Gagal membuat file HTML sementara");
        }
        // 3. Tentukan nama file output DOCX
        $filename = "report_" . time() . ".docx";
        $outputDir = '/mnt/docxfile';
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0777, true);
        }
        $outputPath = $outputDir . '/' . $filename;
        $userConfig = '/tmp/libreoffice_user_' . time(); // Unique directory setiap kali
        // Gunakan shell command PERSIS seperti di terminal
        $command = sprintf(
            'soffice --headless --convert-to "docx:MS Word 2007 XML" --outdir %s -env:UserInstallation=file://%s %s',
            escapeshellarg($outputDir),
            $userConfig,
            escapeshellarg($tempHtmlPath)
        );
        // Buat user directory
        if (!file_exists($userConfig)) {
            mkdir($userConfig, 0777, true);
        }
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(60);
        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            @unlink($tempHtmlPath);
            @exec("rm -rf " . escapeshellarg($userConfig));
            throw new \Exception("Gagal convert LibreOffice: " . $e->getMessage());
        }
        if (!file_exists($outputPath)) {
            @unlink($tempHtmlPath);
            @exec("rm -rf " . escapeshellarg($userConfig));
            throw new \Exception("File DOCX gagal dibuat.");
        }
        @unlink($tempHtmlPath);
        sleep(1);
        @exec("rm -rf " . escapeshellarg($userConfig));
        return $filename;
    }

    public function signedWithTemplate(Request $request)
    {
        $params = [];
        try {
            $this->validateSignedPdfRequest($request);
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

            // --- Return sukses ---
            return response()->json([
                "code"    => "200",
                "message" => "Berhasil sign TTE",
                "data"    => [
                    "url" => request()->getSchemeAndHttpHost() . "/storage/$directori/" . $fileName
                ]
            ]);
        } catch (\Exception $e) {
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
            ]);

            return response()->json([
                "code"    => $e->getCode() ?: 500,
                "message" => "Gagal memproses tanda tangan: " . $e->getMessage(),
                "error"   => $e->getMessage()
            ], 500);
        }
    }
}
