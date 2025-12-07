<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BundleRawatjalanController;

Route::prefix('satusehat')->group(function () {
    Route::get('location', [BundleRawatjalanController::class, 'create_location']);    
    Route::get('patient/{nik}', [BundleRawatjalanController::class, 'search_patient']);
    Route::get('prepare_bundle/{visitId}', [BundleRawatjalanController::class, 'prepare_bundle']);
    Route::get('master/get_kfa',"MappingSatsetController@get_kfa");
    Route::get('master/get_pemeriksaan',"MappingSatsetController@get_pemeriksaan");
    Route::get('master/get_vital_sign',"MappingSatsetController@get_vital_sign");
    Route::get('master/get_alergi',"MappingSatsetController@get_alergi");
});

//TTE
Route::post('tte/sign_tte',"SignTteController@signedpdf");
Route::post('tte/tes_pdf',"SignTteController@tes_pdf");