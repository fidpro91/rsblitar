<?php

use App\Http\Controllers\Api\LogResponseController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SendBundleManualController;
use App\Http\Controllers\Simrs\SimrsController;
use App\Http\Controllers\Api\MastersatsetController;

Route::prefix('satusehat')->group(function () {
    Route::get('sendBundleManual/{visitId}', [SendBundleManualController::class, 'sendBundleManual']);
    Route::get('master/get_kfa', "MappingSatsetController@get_kfa");
    Route::get('master/get_pemeriksaan', "MappingSatsetController@get_pemeriksaan");
    Route::get('master/get_vital_sign', "MappingSatsetController@get_vital_sign");
    Route::get('master/get_alergi', "MappingSatsetController@get_alergi");
    Route::get('search_practitioner/{nik?}', [MastersatsetController::class, 'search_practitioner']);
    Route::post('location', [MastersatsetController::class, 'createlocation']);
    Route::get('search_patient', [MastersatsetController::class, 'search_patient']);
    Route::get('cari_pasien/{nik}', [MastersatsetController::class, 'serachPxbynik']);
});

Route::prefix('simrs')->group(function () {
    Route::get('get_all', [SimrsController::class, 'get_all']);
    Route::post('log_response', [LogResponseController::class, 'index']);
});
//TTE
Route::post('tte/sign_tte', "SignTteController@signedpdf");
Route::post('tte/sign_tte_withTemplate', "SignTteController@signedWithTemplate");
Route::post('tte/tes_pdf', "SignTteController@generateWordSimple");
Route::post('word/generate_word', "Word_builderController@generate_word");
