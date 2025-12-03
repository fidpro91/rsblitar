<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SendBundleManualController;

Route::prefix('satusehat')->group(function () {   
    Route::get('sendBundleManual/{visitId}', [SendBundleManualController::class, 'sendBundleManual']);
    Route::get('createdata', [SendBundleManualController::class, 'createdata'])->name('make');
});
