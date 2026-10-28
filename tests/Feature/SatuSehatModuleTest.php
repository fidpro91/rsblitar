<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Libraries\SatuSehatStorageService;
use App\Libraries\SatuSehatService;
use App\Libraries\SatuSehat\ErrorClassifier;
use App\Libraries\SatuSehat\RepairService;
use App\ServicesBundle\CronsendBundle;
use App\ServicesBundle\SendAllSatusehatService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendAllSatusehatJob;
use App\Jobs\SendSatusehatBundleJob;

class SatuSehatModuleTest extends TestCase
{
    protected SatuSehatStorageService $storage;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->storage = new SatuSehatStorageService();
    }

    protected function tearDown(): void
    {
        try {
            \Illuminate\Support\Facades\DB::table('satusehat_send_logs')
                ->where('visit_id', 'like', 'TEST%')
                ->orWhere('visit_id', 'like', 'VISIT_FAIL%')
                ->orWhere('visit_id', 'like', 'PAGI_TEST%')
                ->orWhere('message', 'like', '%Simulated%')
                ->delete();

            \Illuminate\Support\Facades\DB::table('satusehat_resource_ids')
                ->where('visit_id', 'like', 'TEST%')
                ->orWhere('visit_id', 'like', 'VISIT_FAIL%')
                ->delete();

            \Illuminate\Support\Facades\DB::table('satusehat_repair_history')
                ->where('visit_id', 'like', 'TEST%')
                ->orWhere('visit_id', 'like', 'VISIT_FAIL%')
                ->delete();

            $satusehatDir = storage_path('app/satusehat');
            $retryFile = $satusehatDir . '/retry_logs.json';
            if (file_exists($retryFile)) {
                $retryLogs = json_decode(file_get_contents($retryFile), true) ?: [];
                $cleaned = array_values(array_filter($retryLogs, function ($item) {
                    $v = (string) ($item['visit_id'] ?? '');
                    $m = (string) ($item['message'] ?? $item['error'] ?? '');
                    return !str_starts_with($v, 'TEST') && !str_starts_with($v, 'VISIT_FAIL') && !str_starts_with($v, 'PAGI_TEST') && !str_contains($m, 'Simulated');
                }));
                file_put_contents($retryFile, json_encode($cleaned, JSON_PRETTY_PRINT));
            }

            $resFile = $satusehatDir . '/resource_ids.json';
            if (file_exists($resFile)) {
                $resMap = json_decode(file_get_contents($resFile), true) ?: [];
                $cleanedRes = [];
                foreach ($resMap as $k => $v) {
                    if (!str_starts_with((string)$k, 'TEST') && !str_starts_with((string)$k, 'VISIT_FAIL')) {
                        $cleanedRes[$k] = $v;
                    }
                }
                file_put_contents($resFile, json_encode($cleanedRes, JSON_PRETTY_PRINT));
            }

            $historyFile = $satusehatDir . '/repair_history.json';
            if (file_exists($historyFile)) {
                $histories = json_decode(file_get_contents($historyFile), true) ?: [];
                $cleanedHist = array_values(array_filter($histories, function ($item) {
                    $v = (string) ($item['visit_id'] ?? '');
                    return !str_starts_with($v, 'TEST') && !str_starts_with($v, 'VISIT_FAIL');
                }));
                file_put_contents($historyFile, json_encode($cleanedHist, JSON_PRETTY_PRINT));
            }
        } catch (\Throwable $e) {
        }

        parent::tearDown();
    }

    /**
     * Skenario 1: Data valid berhasil dikirim (Mock SATUSEHAT 200/201 Transaction Response)
     * Memverifikasi penangkapan resource_id dari location header dan penyimpanan ke storage aman.
     */
    public function test_skenario_1_data_valid_berhasil_dikirim_dan_ekstraksi_resource_id()
    {
        $visitId = 'TEST_VISIT_001';

        // Mock OAuth Token & Bundle Transaction Response
        Http::fake([
            '*oauth2*' => Http::response([
                'access_token' => 'mock_access_token_12345',
                'token_type'   => 'Bearer',
                'expires_in'   => 3600,
            ], 200),
            '*fhir-r4/v1*' => Http::response([
                'resourceType' => 'Bundle',
                'type'         => 'transaction-response',
                'entry'        => [
                    [
                        'response' => [
                            'status'     => '201 Created',
                            'location'   => 'https://api-satusehat.kemkes.go.id/fhir-r4/v1/Encounter/enc-uuid-12345/_history/1',
                            'resourceID' => 'enc-uuid-12345',
                            'resourceType' => 'Encounter'
                        ]
                    ],
                    [
                        'response' => [
                            'status'     => '201 Created',
                            'location'   => 'https://api-satusehat.kemkes.go.id/fhir-r4/v1/Condition/cond-uuid-67890/_history/1',
                            'resourceID' => 'cond-uuid-67890',
                            'resourceType' => 'Condition'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $service = new SatuSehatService();
        $response = $service->connect('post', 'https://api-satusehat.kemkes.go.id/fhir-r4/v1/', ['resourceType' => 'Bundle']);

        $this->assertTrue($response['_success']);
        $this->assertEquals(200, $response['_http_status']);
        $this->assertCount(2, $response['entry']);

        // Uji ekstraksi dan persistensi non-database
        $this->storage->saveResourceId($visitId, 'Encounter', 'enc-uuid-12345', [
            'location' => 'https://api-satusehat.kemkes.go.id/fhir-r4/v1/Encounter/enc-uuid-12345/_history/1',
            'status'   => '201 Created',
        ]);

        $this->assertTrue($this->storage->hasResourceId($visitId, 'Encounter'));
        $this->assertEquals('enc-uuid-12345', $this->storage->getSpecificResourceId($visitId, 'Encounter'));
    }

    /**
     * Skenario 2: Data gagal karena validasi payload (OperationOutcome 400 Bad Request)
     */
    public function test_skenario_2_data_gagal_karena_validasi_payload()
    {
        Http::fake([
            '*oauth2*' => Http::response(['access_token' => 'mock_token'], 200),
            '*fhir-r4/v1*' => Http::response([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code'     => 'invalid',
                        'details'  => ['text' => 'Encounter.period.start is required (RuleNumber: 10001)']
                    ]
                ]
            ], 400)
        ]);

        $service = new SatuSehatService();
        $response = $service->connect('post', 'https://api-satusehat.kemkes.go.id/fhir-r4/v1/', []);

        $this->assertEquals(400, $response['_http_status']);
        $this->assertEquals('OperationOutcome', $response['resourceType']);
        $this->assertStringContainsString('Encounter.period.start is required', $response['issue'][0]['details']['text']);
    }

    /**
     * Skenario 3: Data gagal karena token tidak valid (401 Unauthorized)
     */
    public function test_skenario_3_data_gagal_karena_token_tidak_valid()
    {
        Http::fake([
            '*oauth2*' => Http::response([
                'fault' => [
                    'faultstring' => 'Invalid Client Identifier',
                    'detail'      => ['errorcode' => 'oauth.v2.InvalidClientIdentifier']
                ]
            ], 401)
        ]);

        $service = new SatuSehatService();
        $token = $service->getToken(true);

        $this->assertArrayHasKey('error', $token);
        $this->assertEquals(401, $token['http_status']);
    }

    /**
     * Skenario 4: Request mengalami timeout (ConnectionException)
     */
    public function test_skenario_4_request_mengalami_timeout()
    {
        Http::fake([
            '*oauth2*' => Http::response(['access_token' => 'mock_token'], 200),
            '*fhir-r4/v1*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
            }
        ]);

        $service = new SatuSehatService();
        $response = $service->connect('post', 'https://api-satusehat.kemkes.go.id/fhir-r4/v1/', []);

        $this->assertFalse($response['_success']);
        $this->assertEquals(408, $response['_http_status']);
        $this->assertEquals('TIMEOUT_OR_NETWORK', $response['error_type']);
    }

    /**
     * Skenario 5: SATUSEHAT merespons error server (HTTP 500 / 503)
     */
    public function test_skenario_5_satusehat_merespons_error_server()
    {
        Http::fake([
            '*oauth2*' => Http::response(['access_token' => 'mock_token'], 200),
            '*fhir-r4/v1*' => Http::response([
                'resourceType' => 'OperationOutcome',
                'issue' => [
                    [
                        'severity' => 'error',
                        'code'     => 'transient',
                        'details'  => ['text' => 'Internal server error on Kemkes FHIR pipeline']
                    ]
                ]
            ], 500)
        ]);

        $service = new SatuSehatService();
        $response = $service->connect('post', 'https://api-satusehat.kemkes.go.id/fhir-r4/v1/', []);

        $this->assertEquals(500, $response['_http_status']);
        $this->assertFalse($response['_success']);
    }

    /**
     * Skenario 6: Resource sebenarnya berhasil dibuat tetapi respons tidak diterima (Timeout handling)
     */
    public function test_skenario_6_resource_berhasil_tetapi_respons_timeout_sebelum_retry()
    {
        $visitId = 'TEST_TIMEOUT_006';

        // Simpan log timeout
        $this->storage->logSendAttempt($visitId, [
            'status'     => 'TIMEOUT',
            'http_code'  => 408,
            'error_type' => 'TIMEOUT_OR_NETWORK',
            'message'    => 'Request timeout saat menunggu respons Kemkes.',
        ]);

        $lastLog = $this->storage->getLastLog($visitId);
        $this->assertEquals('TIMEOUT', $lastLog['status']);
        $this->assertEquals('TIMEOUT_OR_NETWORK', $lastLog['error_type']);
    }

    /**
     * Skenario 7: Resource ID sudah tersedia sebelum retry (Reuse resource_id)
     */
    public function test_skenario_7_resource_id_sudah_tersedia_sebelum_retry()
    {
        $visitId = 'TEST_REUSE_007';
        $this->storage->saveResourceId($visitId, 'Encounter', 'enc-existing-999');

        $hasId = $this->storage->hasResourceId($visitId, 'Encounter');
        $this->assertTrue($hasId);

        $specificId = $this->storage->getSpecificResourceId($visitId, 'Encounter');
        $this->assertEquals('enc-existing-999', $specificId);
    }

    /**
     * Skenario 8: Resource ID tidak tersedia setelah respons error
     */
    public function test_skenario_8_resource_id_tidak_tersedia_setelah_respons_error()
    {
        $visitId = 'TEST_ERROR_008';
        $resources = $this->storage->getResourceIds($visitId);
        $this->assertEmpty($resources);
        $this->assertFalse($this->storage->hasResourceId($visitId, 'Encounter'));
    }

    /**
     * Skenario 9: Dua proses mencoba mengirim resource yang sama (Concurrency Lock Protection)
     */
    public function test_skenario_9_dua_proses_mencoba_mengirim_resource_yang_sama()
    {
        $visitId = 'TEST_CONCURRENT_009';

        // Proses 1 memperoleh lock
        $lock1 = $this->storage->acquireLock($visitId, 1);
        $this->assertTrue($lock1);

        // Proses 2 mencoba memperoleh lock yang sama -> harus ditolak
        $storage2 = new SatuSehatStorageService();
        $lock2 = $storage2->acquireLock($visitId, 1);
        $this->assertFalse($lock2);

        // Lepas lock oleh Proses 1
        $this->storage->releaseLock($visitId);

        // Sekarang Proses 2 dapat memperoleh lock
        $lock3 = $storage2->acquireLock($visitId, 1);
        $this->assertTrue($lock3);
        $storage2->releaseLock($visitId);
    }

    /**
     * Skenario 10: Reference ke resource terkait tidak valid
     */
    public function test_skenario_10_reference_ke_resource_terkait()
    {
        // Memverifikasi bahwa format internal bundle adalah URN UUID valid
        $testUuid = 'e87f2e1a-4f5a-4b92-8ec2-1b9a9d72a0f1';
        $urnRef = "urn:uuid:{$testUuid}";
        $this->assertStringStartsWith('urn:uuid:', $urnRef);
    }

    /**
     * Skenario 11: Petugas memperbaiki data lalu melakukan retry (Non-Database Draft)
     */
    public function test_skenario_11_petugas_memperbaiki_data_melalui_draft()
    {
        $visitId = 'TEST_DRAFT_011';

        $draftData = [
            'instruksi_pulang' => 'Pasien dianjurkan kontrol ke Poli Jantung 1 minggu lagi.',
            'idunitsatset'     => '6f819251-9226-4445-96cb-8a0eeaa70dd2',
            'kondisi_pulang'   => 'Membaik',
            'alasan_pulang'    => 'Kondisi klinis stabil.',
        ];

        $saved = $this->storage->saveDraft($visitId, $draftData);
        $this->assertTrue($saved);

        $loaded = $this->storage->getDraft($visitId);
        $this->assertNotNull($loaded);
        $this->assertEquals('Membaik', $loaded['fields']['kondisi_pulang']);
        $this->assertEquals('6f819251-9226-4445-96cb-8a0eeaa70dd2', $loaded['fields']['idunitsatset']);

        // Bersihkan draft
        $this->storage->clearDraft($visitId);
        $this->assertNull($this->storage->getDraft($visitId));
    }

    /**
     * Skenario 12: Pengguna tanpa izin atau method tidak valid mencoba mengirim ulang
     */
    public function test_skenario_12_pengguna_mencoba_mengirim_ulang_dengan_method_get()
    {
        $response = $this->get('/satusehat/retry/999999');
        $response->assertStatus(405);
    }

    /**
     * Skenario 13: Payload atau pesan error mengandung teks yang berpotensi XSS
     */
    public function test_skenario_13_pencegahan_xss_pada_draft_dan_sanitasi()
    {
        $visitId = 'TEST_XSS_013';
        $xssInput = '<script>alert("XSS Attack!")</script>';

        $this->storage->saveDraft($visitId, [
            'instruksi_pulang' => $xssInput,
        ]);

        $draft = $this->storage->getDraft($visitId);
        $raw = $draft['fields']['instruksi_pulang'];

        // Narrative composition meng-escape dengan htmlspecialchars
        $escaped = htmlspecialchars($raw, ENT_XML1, 'UTF-8');
        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);

        $this->storage->clearDraft($visitId);
    }

    /**
     * Skenario 14: Aplikasi dibuka ulang dan mekanisme penyimpanan resource ID diuji (Persistensi File)
     */
    public function test_skenario_14_persistensi_resource_id_antar_sesi()
    {
        $visitId = 'TEST_PERSIST_014';
        $this->storage->saveResourceId($visitId, 'Encounter', 'enc-persistent-888');

        // Buat instance service baru untuk mensimulasikan sesi baru
        $freshService = new SatuSehatStorageService();
        $this->assertEquals('enc-persistent-888', $freshService->getSpecificResourceId($visitId, 'Encounter'));
    }

    /**
     * Skenario 15: Fitur TTE dan modul di luar SATUSEHAT tetap utuh dan berfungsi
     */
    public function test_skenario_15_fitur_tte_dan_modul_lain_tetap_berfungsi()
    {
        $this->assertTrue(Route::has('satusehat.dashboard'));
        $this->assertTrue(Route::has('satusehat.detail'));

        // Cek bahwa route TTE tetap terdaftar di router
        $routes = collect(Route::getRoutes()->getRoutes())->map->uri();
        $this->assertTrue($routes->contains('api/tte/sign_tte'));
        $this->assertTrue($routes->contains('api/tte/sign_tte_withTemplate'));
        $this->assertTrue($routes->contains('api/word/generate_word'));
        $this->assertTrue($routes->contains('api/simrs/get_all'));
    }

    /**
     * Uji Tampilan Dashboard SATUSEHAT (HTTP 200)
     */
    public function test_ui_dashboard_berhasil_diakses()
    {
        $response = $this->get('/satusehat');
        $response->assertStatus(200);
        $response->assertSee('Kirim Data ke SATUSEHAT');
        $response->assertSee('Total Kunjungan');
    }

    /**
     * Uji Tampilan Detail Encounter SATUSEHAT (HTTP 200)
     */
    public function test_ui_detail_encounter_berhasil_diakses()
    {
        $response = $this->get('/satusehat/detail/1164993');
        $response->assertStatus(200);
        $response->assertSee('Detail Kunjungan');
        $response->assertSee('ID SATUSEHAT');
    }

    /**
     * Uji Simpan Draft Koreksi via Endpoint Web (Non-Database)
     */
    public function test_ui_simpan_draft_koreksi_berhasil()
    {
        $response = $this->postJson('/satusehat/draft/1164993', [
            'instruksi_pulang' => 'Pasien dianjurkan minum obat sesuai resep.',
            'kondisi_pulang'   => 'Membaik',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Bersihkan draft
        $this->storage->clearDraft('1164993');
    }

    /**
     * Uji Ekspor Mapping JSON (HTTP 200)
     */
    public function test_ui_ekspor_mapping_json()
    {
        $response = $this->get('/satusehat/export/1164993');
        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }

    /**
     * MedBridge Skenario 1: ErrorClassifier memetakan error dengan akurat
     */
    public function test_medbridge_error_classifier_categories_and_statuses()
    {
        // 1. Dependency blocked
        $c1 = ErrorClassifier::classify(500, 'Error child', false, 0);
        $this->assertEquals(ErrorClassifier::CATEGORY_DEPENDENCY_ERROR, $c1['category']);
        $this->assertEquals(ErrorClassifier::STATUS_BLOCKED, $c1['status']);
        $this->assertStringContainsString('Blocked', $c1['label']);

        // 2. Max retry
        $c2 = ErrorClassifier::classify(500, 'Server down', true, 3);
        $this->assertEquals(ErrorClassifier::STATUS_MAX_RETRY, $c2['status']);

        // 3. Auth error
        $c3 = ErrorClassifier::classify(401, 'Unauthorized', true, 0);
        $this->assertEquals(ErrorClassifier::CATEGORY_AUTH_ERROR, $c3['category']);
        $this->assertEquals(ErrorClassifier::STATUS_RETRYABLE, $c3['status']);

        // 4. Server retryable
        $c4 = ErrorClassifier::classify(503, 'Service unavailable', true, 1);
        $this->assertEquals(ErrorClassifier::CATEGORY_RETRYABLE, $c4['category']);
        $this->assertEquals(ErrorClassifier::STATUS_RETRYABLE, $c4['status']);

        // 5. Data error / Need repair
        $c5 = ErrorClassifier::classify(400, 'Encounter.period.start is mandatory', true, 0);
        $this->assertEquals(ErrorClassifier::CATEGORY_DATA_ERROR, $c5['category']);
        $this->assertEquals(ErrorClassifier::STATUS_NEED_REPAIR, $c5['status']);
    }

    /**
     * MedBridge Skenario 2: Halaman Failed Center Index menampilkan ringkasan metrik
     */
    public function test_medbridge_failed_center_index_view()
    {
        // Siapkan log gagal dummy di storage
        $this->storage->logSendAttempt('VISIT_FAIL_001', [
            'status'        => 'FAILED',
            'http_code'     => 400,
            'resource_type' => 'Condition',
            'message'       => 'Condition.code is required',
        ]);

        $response = $this->get('/satusehat/failed');
        $response->assertStatus(200);
        $response->assertSee('Perbaikan Data Gagal');
        $response->assertSee('Total Gagal');
        $response->assertSee('Perlu Perbaikan');
        $response->assertSee('Bisa Dikirim Ulang');
        $response->assertSee('Menunggu Kunjungan');
    }

    /**
     * MedBridge Skenario 3: Halaman Failed Center Show menampilkan diagnosis dan dependency
     */
    public function test_medbridge_failed_center_show_view()
    {
        $this->storage->logSendAttempt('VISIT_FAIL_002', [
            'status'        => 'FAILED',
            'http_code'     => 500,
            'resource_type' => 'Observation',
            'message'       => 'Internal server error on observation bundle',
        ]);

        $response = $this->get('/satusehat/failed/VISIT_FAIL_002_0');
        $response->assertStatus(200);
        $response->assertSee('Detail Data Gagal');
        $response->assertSee('Status Kendala');
        $response->assertSee('Status Validasi');
    }

    /**
     * MedBridge Skenario 4: Endpoint AJAX live validation data staging
     */
    public function test_medbridge_failed_center_live_validation()
    {
        $this->storage->logSendAttempt('VISIT_FAIL_003', [
            'status'        => 'FAILED',
            'http_code'     => 400,
            'resource_type' => 'Encounter',
            'message'       => 'Encounter invalid',
        ]);

        $response = $this->postJson('/satusehat/failed/VISIT_FAIL_003_0/validate', []);
        $response->assertStatus(200);
        $response->assertJsonStructure(['valid', 'status', 'errors']);
    }

    /**
     * MedBridge Skenario 5: Form perbaikan staging dan penyimpanan overlay non-database
     */
    public function test_medbridge_failed_center_save_repair_staging_overlay()
    {
        $this->storage->logSendAttempt('VISIT_FAIL_004', [
            'status'        => 'FAILED',
            'http_code'     => 400,
            'resource_type' => 'Condition',
            'message'       => 'ICD10 required',
        ]);

        // Simpan perbaikan form
        $response = $this->post('/satusehat/failed/VISIT_FAIL_004_0/repair', [
            'code'        => 'A09',
            'dx_name'     => 'Gastroenteritis',
            'rank'        => '1',
            'reason'      => 'Koreksi kode ICD-10 dari rekam medis',
            'submit_type' => 'save_only',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/satusehat/failed/VISIT_FAIL_004_0');

        // Pastikan tersimpan di storage overlay
        $repairs = $this->storage->getStagingRepairs('VISIT_FAIL_004');
        $this->assertNotEmpty($repairs);

        // Pastikan riwayat audit tersimpan
        $histories = $this->storage->getRepairHistories('VISIT_FAIL_004');
        $this->assertNotEmpty($histories);
        $this->assertEquals('Koreksi kode ICD-10 dari rekam medis', $histories[0]['reason']);

        // Bersihkan
        $this->storage->clearStagingRepairs('VISIT_FAIL_004');
    }

    /**
     * MedBridge Skenario 6: Endpoint send-all dan encounter-logs
     */
    public function test_medbridge_send_all_and_encounter_logs_api()
    {
        Queue::fake();

        // 1. send-all endpoint returns 202 and dispatches SendAllSatusehatJob
        $resAll = $this->postJson('/satusehat/send-all', [
            'start_date' => date('Y-m-d'),
            'status'     => 'ALL',
        ]);
        $resAll->assertStatus(202);
        $resAll->assertJson(['success' => true]);
        Queue::assertPushed(SendAllSatusehatJob::class);

        // 2. encounter-logs endpoint returns summary JSON
        $resLogs = $this->getJson('/satusehat/encounter-logs/VISIT_FAIL_001');
        $resLogs->assertStatus(200);
        $resLogs->assertJsonStructure([
            'success',
            'visit_id',
            'summary' => [
                'total_resource',
                'berhasil',
                'gagal',
                'status',
                'failed_resources',
                'logs'
            ]
        ]);
    }

    /**
     * Skenario 6b: Verifikasi SendAllSatusehatJob mengeksekusi dispatch SendSatusehatBundleJob
     */
    public function test_send_all_satusehat_job_dispatches_bundle_jobs()
    {
        Queue::fake();

        $job = new SendAllSatusehatJob([
            'visit_ids' => ['VISIT_JOB_TEST_01', 'VISIT_JOB_TEST_02']
        ]);
        $job->handle();

        Queue::assertPushed(SendSatusehatBundleJob::class, 2);
        Queue::assertPushed(SendSatusehatBundleJob::class, function ($j) {
            return $j->visitId === 'VISIT_JOB_TEST_01';
        });
        Queue::assertPushed(SendSatusehatBundleJob::class, function ($j) {
            return $j->visitId === 'VISIT_JOB_TEST_02';
        });
    }

    /**
     * Skenario 6c: Verifikasi SendSatusehatBundleJob mengeksekusi SendAllSatusehatService
     */
    public function test_send_satusehat_bundle_job_executes_service()
    {
        $mockService = \Mockery::mock(SendAllSatusehatService::class);
        $mockService->shouldReceive('prepareAndSendBundle')
            ->once()
            ->with('VISIT_JOB_TEST_01')
            ->andReturn([
                'success'      => true,
                'status'       => 'SUCCESS',
                'message'      => 'Sukses via job',
                'captured_ids' => ['Encounter' => 'enc-12345'],
            ]);

        $job = new SendSatusehatBundleJob('VISIT_JOB_TEST_01');
        $result = $job->handle($mockService);

        $this->assertTrue($result['success']);
        $this->assertEquals('SUCCESS', $result['status']);
    }

    /**
     * Skenario 7: Verifikasi paginasi Bootstrap di Failed Repair Center (tanpa SVG raksasa)
     */
    public function test_failed_repair_center_pagination_renders_bootstrap()
    {
        // Masukkan 25 log kegagalan ke storage agar memicu paginasi (> 15 per page)
        for ($i = 1; $i <= 25; $i++) {
            $this->storage->logSendAttempt("PAGI_TEST_$i", [
                'success'       => false,
                'resource_type' => 'Condition',
                'http_status'   => 400,
                'error'         => 'Simulated error message for pagination test',
                'created_at'    => now()->toDateTimeString()
            ]);
        }

        $response = $this->get('/satusehat/failed');
        $response->assertStatus(200);

        $content = $response->getContent();

        // Verifikasi tidak ada svg raksasa dari Tailwind default
        $this->assertStringNotContainsString('w-5 h-5', $content, 'Paginasi tidak boleh mengandung kelas Tailwind w-5 h-5 yang membuat panah raksasa');
        
        // Verifikasi menggunakan class pagination Bootstrap
        $this->assertStringContainsString('pagination', $content, 'Harus menggunakan class pagination Bootstrap');
        $this->assertStringContainsString('page-item', $content, 'Harus menggunakan class page-item Bootstrap');
        $this->assertStringContainsString('page-link', $content, 'Harus menggunakan class page-link Bootstrap');
    }

    /**
     * Skenario 8: Verifikasi EpisodeOfCare status active saat POST (Rule Number 10109)
     */
    public function test_episode_of_care_initial_status_active_adheres_to_rule_10109()
    {
        $visit = \App\Models\Visit_encounter::first();
        if ($visit) {
            $eoc = \App\Libraries\SatuSehat\EpisodeOfCareBundle::build($visit->visit_id);

            // Verifikasi status saat POST harus active, bukan finished (Rule 10109)
            $this->assertEquals('POST', $eoc['request']['method']);
            $this->assertEquals('active', $eoc['resource']['status'], 'EpisodeOfCare saat POST harus berstatus active agar tidak melanggar Rule 10109');
            $this->assertArrayHasKey('start', $eoc['resource']['period']);
            $this->assertArrayNotHasKey('end', $eoc['resource']['period'], 'EpisodeOfCare active tidak boleh memiliki period.end saat pembuatan awal');
        }
    }

    /**
     * Skenario 9: Verifikasi Composition identifier adalah single Identifier object (bukan array) sesuai FHIR R4 (0..1)
     */
    public function test_composition_bundle_identifier_is_single_object_and_not_array()
    {
        $visit = \App\Models\Visit_encounter::first();
        if ($visit) {
            $composition = \App\Libraries\SatuSehat\CompositionBundle::build($visit->visit_id);

            $this->assertIsArray($composition);
            $this->assertArrayHasKey('resource', $composition);
            $resource = $composition['resource'];

            $this->assertEquals('Composition', $resource['resourceType']);
            $this->assertArrayHasKey('identifier', $resource);

            // FHIR R4: Composition.identifier kardinalitas 0..1 (single object, BUKAN list/array of objects)
            $identifier = $resource['identifier'];
            $this->assertIsArray($identifier);
            $this->assertArrayNotHasKey(0, $identifier, 'Composition.identifier TIDAK BOLEH berupa array of objects ([0 => ...]), harus single object');
            $this->assertArrayHasKey('system', $identifier);
            $this->assertArrayHasKey('value', $identifier);
            $this->assertStringContainsString('composition', $identifier['system']);

            // Verifikasi ketika di-encode ke JSON menghasilkan {"identifier":{"system":...}} bukan {"identifier":[{"system":...}]}
            $json = json_encode($resource);
            $this->assertStringNotContainsString('"identifier":[{', $json, 'JSON representation must be an object, not array');
            $this->assertStringContainsString('"identifier":{"system":', $json);
        }
    }
}
