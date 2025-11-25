<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SignTteController
{
    /* public function signedpdf(Request $request)
    {
        $post = [
            "api"        => (function() use ($request){
                if ($request->jenis_berkas == 'pdf') {
                    return config('tte.signPdf');
                }else {
                    return config('tte.signDocx');
                }
            })(),
            "nik"        => $request->nik,
            "passphrase" => $request->passphrase,
            "docx"        => "https://simrs-rsudmw.blitarkota.go.id/sign/report_1764047937.docx"
        ];
        $url = config('tte.api_url');

        $response = Http::asForm()->post($url, $post);
        // dd($response->json(), $response->body(), $response->status(), $response->headers());
        $resp = [];
        if ($response->failed()) {
            $resp = [
                "code"      => "201",
                "message"   => "error server : ".$response->body()
            ];
            return response()->json($resp);
        }
        $data = $response->json();

        if (!isset($data['signed']) || trim($data['signed']) == "") {
            $resp = [
                "code"      => "202",
                "message"   => "data url pdf tidak valid"
            ];
            return response()->json($resp);
        }

        $fileContent = file_get_contents($data['signed']);
        $pathpdf = "public/signed";
        if (file_exists($pathpdf)) {
            unlink($pathpdf);
        }
        // file_put_contents($pdfPath, $fileContent);
        $fileName = $request->berkas."_".$request->visit_id."_".$request->id_berkas.".pdf";
        Storage::disk('public')->put('signed/'.$fileName, $fileContent);
        $resp = [
                "code"      => "200",
                "message"   => "Berhasil sign TTE",
                "data"      => [
                    "url"   => request()->getSchemeAndHttpHost()."/signed/".$fileName
                ]
            ];
        return $resp;
    } */

    public function signedpdf(Request $request)
    {
        try {
            $this->validateSignedPdfRequest($request);
            // --- Persiapan POST ---
            $post = [
                "api"           => $request->jenis_berkas == 'pdf'
                                    ? config('tte.signPdf')
                                    : config('tte.signDocx'),
                "nik"           => $request->nik,
                "passphrase"    => $request->passphrase
            ];

            if ($request->jenis_berkas == 'pdf') {
                $post["pdf"] = $request->url;
            } else {
                $urlDocx      = $this->generateWord($request->url);
                $post["docx"] = "https://simrs-rsudmw.blitarkota.go.id/sign/".$urlDocx;
            }

            $url = config('tte.api_url');

            // --- Mengirim request ke server TTE ---
            $response = Http::asForm()->timeout(60)->post($url, $post);

            if ($response->failed()) {
                throw new \Exception("Server TTE error: " . $response->body());
            }

            $data = $response->json();
            // dd($response->json(), $response->body(), $response->status(), $response->headers());
            if (!isset($data['signed']) || trim($data['signed']) == "") {
                throw new \Exception("URL PDF 'signed' tidak valid dari server TTE");
            }

            // --- Download file hasil sign ---
            $fileContent = @file_get_contents($data['signed']);
            if ($fileContent === false) {
                throw new \Exception("Gagal mendownload PDF dari URL signed");
            }

            // --- Simpan ke storage ---
            $fileName = $request->berkas . "_" . $request->visit_id . "_" . $request->id_berkas . ".pdf";

            $saved = Storage::disk('public')->put("signed/".$fileName, $fileContent);
            if (!$saved) {
                throw new \Exception("Gagal menyimpan file PDF ke storage");
            }

            // --- Return sukses ---
            return response()->json([
                "code"    => "200",
                "message" => "Berhasil sign TTE",
                "data"    => [
                    "url" => request()->getSchemeAndHttpHost() . "/signed/" . $fileName
                ]
            ]);

        } catch (\Exception $e) {

            return response()->json([
                "code"    => "500",
                "message" => "Gagal memproses tanda tangan",
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
            "id_berkas"    => "required|numeric",
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

    /* public function generateWord($url)
    {
        $html = Http::get($url)->body();
        if (!$html) {
            throw new \Exception("HTML kosong atau gagal diambil");
        }
        $html = str_replace('{QR}', '&#123;QR&#125;', $html);
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();

        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $html, false, false);

        $filename = "report_" . time() . ".docx";
        // $path = storage_path('app/public/' . $filename);
        $path = "/mnt/docxfile/" . $filename;

        try {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($path);
        } catch (\Throwable $e) {
            throw new \Exception("Gagal generate word: " . $e->getMessage());
        }

        return $filename;
    } */

    public function generateWord($url)
    {
        $response = Http::get($url);
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

}
