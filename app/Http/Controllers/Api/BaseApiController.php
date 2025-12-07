<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Libraries\SatuSehatService;
use App\Models\Log_http;

class BaseApiController extends Controller
{
    protected $satusehat;    

    public function __construct()
    {
        $this->satusehat = new SatuSehatService();
    }

    protected function success($data, $message = "Success", $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ], $code);
    }

    protected function error($message, $code = 400, $data = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => $data
        ], $code);
    }
    
    protected function logging($service_name, $data = null)
    {
        Log_http::create(
            [
                'service_name'      => $service_name,
                'endpoint_url'      => $data['url'],
                'http_method'       => $data['method'],
                'response_code'     => $data['code'],
                'response_body'     => $data['body'],
                'status'            => $data['status'],
                'response_message'  => $data['error_message'],
            ]
        );
    }
}
