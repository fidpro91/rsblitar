<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BundleRawatjalanController;

Route::prefix('satusehat')->group(function () {
    Route::get('location', [BundleRawatjalanController::class, 'create_location']);    
    Route::get('patient/{nik}', [BundleRawatjalanController::class, 'search_patient']);
    Route::get('prepare_bundle/{visitId}', [BundleRawatjalanController::class, 'prepare_bundle']);
});

//TTE
Route::post('tte/sign_tte',"SignTteController@signedpdf");
Route::post('tte/generate_word',"SignTteController@generateWord");