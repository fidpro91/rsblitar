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
        $post = [
            "api"           => config('tte.signDocx'),
            "nik"           => $request->nik,
            "passphrase"    => $request->passphrase
        ];
        try {
            $urlDocx = app(Word_builderController::class);
            $urlDocx = $urlDocx->generate_word($request);
            if ($urlDocx['code'] != 200) {
                throw new \Exception($urlDocx['message'],$urlDocx['code']);
            }
            $post["docx"] = config('tte.domainRS').$urlDocx['file'];
            $url = config('tte.api_url');
            // --- Mengirim request ke server TTE ---
            $response = Http::asForm()->timeout(60)->post($url, $post);
            if ($response->failed()) {
                throw new \Exception("Server TTE error: " . $response->body(),402);
            }

            $data = $response->json();
            // dd($response->json(), $response->body(), $response->status(), $response->headers());
            if (!isset($data['signed']) || trim($data['signed']) == "") {
                $error = $response->json();
                if (empty($error)) {
                    throw new \Exception("URL PDF 'signed' tidak valid dari server TTE. ",403);
                }else{
                    $error=json_encode($error);
                    throw new \Exception($error,403);
                }
            }
            // --- Download file hasil sign ---
            $fileContent = @file_get_contents($data['signed']);
            if ($fileContent === false) {
                throw new \Exception("Gagal mendownload PDF dari URL signed",404);
            }
            // --- Simpan ke storage ---
            $fileName = $request->berkas . "_" . $request->visit_id . "_" . $request->id_berkas . ".pdf";
            $directori = "signed/$request->visit_id";
            $saved = Storage::disk('public')->put($directori."/".$fileName, $fileContent);
            if (!$saved) {
                throw new \Exception("Gagal menyimpan file PDF ke storage",405);
            }
            @unlink($urlDocx['location']);
            $ehos = DB::connection('db_simrs');
            $pathTTE = request()->getSchemeAndHttpHost() . "/storage/$directori/" . $fileName;
            $ehos->table("yanmed.visit")
                 ->where('visit_id', $request->visit_id)
                 ->update([
                    'tteresume'         => $pathTTE,
                    'respond_message'   => 'Berhasil sign TTE',
                    'respond_status'    => '200',
                ]);
            // --- Return sukses ---
            $this->logging('sign TTE',[
                "url"       => $post['api'],
                "method"    => 'post',
                "code"      => 200,
                "body"      => json_encode($data),
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
            // @unlink($urlDocx['location']);
            $this->logging('sign TTE',[
                "url"       => $post['api'],
                "method"    => 'post',
                "code"      => $e->getCode(),
                "body"      => json_encode($post),
                "status"    => 500,
                "error_message" => $e->getMessage()
            ], $request->visit_id);
        }
    }

    protected function logging($service_name, $data = null,$visit_id)
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