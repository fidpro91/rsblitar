<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BundleRawatjalanController;

Route::prefix('satusehat')->group(function () {
    Route::get('location', [BundleRawatjalanController::class, 'create_location']);
    Route::get('encounter/{visitId}', [BundleRawatjalanController::class, 'encounter']);
    Route::get('patient/{nik}', [BundleRawatjalanController::class, 'search_patient']);
    Route::get('prepare_bundle/{visitId}', [BundleRawatjalanController::class, 'prepare_bundle']);
});
