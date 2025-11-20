<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class SignTteController
{
    public function signedpdf(Request $request)
    {
        $publicPdfUrl = request()->getSchemeAndHttpHost() . $request->urlpdf;
        $post = [
            "api"        => "sign",
            "nik"        => $request->nik,
            "passphrase" => $request->passphrase,
            "pdf"        => "https://simrs-rsudmw.blitarkota.go.id/simrs/resep.pdf"
        ];

        $url = "https://signed.blitarkota.go.id/";

        $response = Http::asForm()->post($url, $post);
        // dd($response->json());
        dd($response->json(), $response->body(), $response->status(), $response->headers());

        if ($response->failed()) {
            return false;
        }

        $data = $response->json();

        if (!isset($data['signed']) || trim($data['signed']) == "") {
            return false;
        }

        $fileContent = file_get_contents($data['signed']);
        $pathpdf = "public/assets";
        if (file_exists($pathpdf)) {
            unlink($pathpdf);
        }

        file_put_contents($pathpdf, $fileContent);

        return true;
    }
}
