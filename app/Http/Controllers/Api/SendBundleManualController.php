<?php

namespace App\Http\Controllers\Api;

use App\ServicesBundle\CronsendBundle;
use App\Illuminate\Support\Facades\DB;

class SendBundleManualController extends BaseApiController
{
    public function sendBundleManual($visitId)
    {
        $service = new CronsendBundle();
        $result = $service->prepareAndSendBundle($visitId);
        return response()->json($result);
    }

  
}
