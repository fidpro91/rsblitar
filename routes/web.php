<?php

use App\Http\Controllers\CobaController;
use App\Http\Controllers\Satusehat\SatusehatDashboardController;
use App\Http\Controllers\Satusehat\SatusehatResourceMonitorController;
use App\Http\Controllers\Simrs\SimrsSyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('satusehat')->name('satusehat.')->group(function () {
    // Send & Monitoring Dashboard
    Route::get('/', [SatusehatDashboardController::class, 'index'])->name('dashboard');
    Route::get('/send', [SatusehatDashboardController::class, 'index'])->name('send.index');
    Route::get('/detail/{visitId}', [SatusehatDashboardController::class, 'detail'])->name('detail');
    Route::get('/encounter-logs/{visitId}', [SatusehatDashboardController::class, 'getEncounterLogs'])->name('send.encounter-logs');
    Route::post('/send/{visitId}', [SatusehatDashboardController::class, 'resend'])->name('send.single');
    Route::post('/send-all', [SatusehatDashboardController::class, 'sendAll'])->name('send.all');
    Route::post('/draft/{visitId}', [SatusehatDashboardController::class, 'saveDraft'])->name('draft.save');
    Route::post('/draft/{visitId}/clear', [SatusehatDashboardController::class, 'clearDraft'])->name('draft.clear');
    Route::post('/retry/{visitId}', [SatusehatDashboardController::class, 'resend'])->name('retry');
    Route::get('/export/{visitId}', [SatusehatDashboardController::class, 'exportMapping'])->name('export');

    // Failed Center & Repair Center (MedBridge Concept)
    Route::get('/failed', [SatusehatDashboardController::class, 'failedIndex'])->name('failed.index');
    Route::get('/failed/{id}', [SatusehatDashboardController::class, 'failedShow'])->name('failed.show');
    Route::get('/failed/{id}/repair', [SatusehatDashboardController::class, 'failedRepairForm'])->name('failed.repair');
    Route::post('/failed/{id}/repair', [SatusehatDashboardController::class, 'failedSaveRepair'])->name('failed.save-repair');
    Route::post('/failed/{id}/validate', [SatusehatDashboardController::class, 'failedValidateStaging'])->name('failed.validate');
    Route::post('/failed/{id}/retry', [SatusehatDashboardController::class, 'failedRetrySingle'])->name('failed.retry');
    Route::post('/failed/{id}/queue-retry', [SatusehatDashboardController::class, 'failedRetryQueue'])->name('failed.queue-retry');
    Route::get('/failed/{id}/history', [SatusehatDashboardController::class, 'failedHistory'])->name('failed.history');

    // Monitoring Aliran Data Resource (Sent vs Failed Per Resource)
    Route::get('/monitoring-resource', [SatusehatResourceMonitorController::class, 'index'])->name('resource-monitor.index');
    Route::get('/monitoring-resource/stream', [SatusehatResourceMonitorController::class, 'streamData'])->name('resource-monitor.stream');
    Route::get('/monitoring-resource/log/{id}', [SatusehatResourceMonitorController::class, 'getLogDetail'])->name('resource-monitor.log-detail');
});

// SIMRS Tarik Data & Monitoring Log (MedBridge Compatible)
Route::prefix('simrs')->group(function () {
    Route::get('/', [SimrsSyncController::class, 'index'])->name('satusehat.simrs.index');
    Route::post('/', [SimrsSyncController::class, 'pull']);
    Route::post('/tarik', [SimrsSyncController::class, 'pull'])->name('satusehat.simrs.pull');
    Route::get('/logs/{id}', [SimrsSyncController::class, 'logDetail'])->name('satusehat.simrs.log-detail');
    Route::post('/test-connection', [SimrsSyncController::class, 'testConnection'])->name('satusehat.simrs.test-connection');

    // MedBridge compatible route aliases
    Route::get('/lihat', [SimrsSyncController::class, 'index'])->name('lihat');
    Route::post('/postdata', [SimrsSyncController::class, 'pull'])->name('postdata');
});

Route::get('contoh', function () {
    return view('sample');
});
Route::get('tes_script', function () {
    return view('tes_script');
});
Route::get('/cetak-resume', [CobaController::class, 'cetakResume']);
Route::get('/generate_word', [CobaController::class, 'tes_word']);
Route::get('/cobaJob', [CobaController::class, 'tes_jobs']);
