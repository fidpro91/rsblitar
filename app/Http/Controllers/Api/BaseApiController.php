<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Libraries\SatuSehatService;

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
}
