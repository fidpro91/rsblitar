<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SendBundleManualController;

Route::prefix('satusehat')->group(function () {    
    Route::get('sendBundleManual/{visitId}', [SendBundleManualController::class, 'sendBundleManual']);
    Route::get('master/get_kfa', "MappingSatsetController@get_kfa");
    Route::get('master/get_pemeriksaan', "MappingSatsetController@get_pemeriksaan");
    Route::get('master/get_vital_sign', "MappingSatsetController@get_vital_sign");
    Route::get('master/get_alergi', "MappingSatsetController@get_alergi");
});

//TTE
Route::post('tte/sign_tte', "SignTteController@signedpdf");
