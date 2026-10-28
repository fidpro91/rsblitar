<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Libraries\Simrs\SimrsSyncService;
use App\Libraries\Simrs\SimrsSyncStorageService;
use App\Libraries\SimrsInsert;
use App\Models\Visit_encounter;
use Illuminate\Support\Facades\Artisan;

class SimrsSyncTest extends TestCase
{
    protected SimrsSyncStorageService $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = new SimrsSyncStorageService();
    }

    protected function tearDown(): void
    {
        try {
            \Illuminate\Support\Facades\DB::table('simrs_sync_logs')
                ->where('trigger', 'like', 'test_%')
                ->orWhere('dry_run', true)
                ->orWhere('warnings', 'like', '%simulasi%')
                ->delete();

            $syncTestFile = storage_path('app/simrs_sync/sync_logs_test.json');
            if (file_exists($syncTestFile)) {
                file_put_contents($syncTestFile, json_encode([], JSON_PRETTY_PRINT));
            }
        } catch (\Throwable $e) {
        }

        parent::tearDown();
    }

    /**
     * 1. Verifikasi halaman UI /simrs dan alias /simrs/lihat ter-render dengan benar
     */
    public function test_simrs_sync_view_renders_correctly()
    {
        $response = $this->get('/simrs');
        $response->assertStatus(200);
        $response->assertSee('Tarik Data SIMRS');
        $response->assertSee('Total Tarik Data');
        $response->assertSee('Berhasil');
        $response->assertSee('Gagal');
        $response->assertSee('Mulai Tarik Data');
        $response->assertSee('Uji Koneksi SIMRS');

        // Alias MedBridge /simrs/lihat
        $responseAlias = $this->get(route('lihat'));
        $responseAlias->assertStatus(200);
        $responseAlias->assertSee('Tarik Data SIMRS');
    }

    /**
     * 2. Verifikasi endpoint uji konektivitas SIMRS
     */
    public function test_simrs_sync_test_connection_endpoint()
    {
        $response = $this->postJson('/simrs/test-connection');
        // Status code bisa 200 jika host connectable atau 503 jika offline, struktur JSON tetap konsisten
        $this->assertContains($response->getStatusCode(), [200, 503]);
        $response->assertJsonStructure([
            'success',
            'message',
            'ping_ms'
        ]);
    }

    /**
     * 3. Verifikasi proses tarik data melalui endpoint POST /simrs/tarik (Simulation & Dry-run)
     */
    public function test_simrs_sync_pull_dry_run_and_simulation()
    {
        $response = $this->postJson('/simrs/tarik', [
            'start_date' => '2026-10-23',
            'end_date'   => '2026-10-23',
            'chunk_size' => 50,
            'dry_run'    => 1,
            'simulate'   => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'status'  => 'SUCCESS',
            'dry_run' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'sync_id',
            'status',
            'message',
            'total' => [
                'encounter',
                'diagnosis',
                'icd9',
                'observation',
                'farmasi',
                'lab',
                'allergi',
                'careplan',
                'radiologi',
                'quisioner'
            ],
            'duration'
        ]);
    }

    /**
     * 4. Verifikasi pencatatan dan filter riwayat log di SimrsSyncStorageService
     */
    public function test_simrs_sync_storage_logging_and_filtering()
    {
        // 1. Catat log uji sukses
        $log1 = $this->storage->startLog([
            'trigger'    => 'test_manual',
            'start_date' => '2026-10-01',
            'end_date'   => '2026-10-01',
            'tanggal'    => '2026-10-01',
        ]);
        $this->storage->finishLog($log1['id'], [
            'status'           => 'SUCCESS',
            'duration_seconds' => 5,
            'total_stats'      => ['encounter' => 20, 'diagnosis' => 30],
        ]);

        // 2. Catat log uji gagal
        $log2 = $this->storage->startLog([
            'trigger'    => 'test_cli',
            'start_date' => '2026-10-02',
            'end_date'   => '2026-10-02',
            'tanggal'    => '2026-10-02',
        ]);
        $this->storage->finishLog($log2['id'], [
            'status'           => 'FAILED',
            'duration_seconds' => 1,
            'error_message'    => 'Simulated connection timeout error',
        ]);

        // 3. Filter berdasarkan status FAILED
        $failedLogs = $this->storage->getLogs(['status' => 'FAILED']);
        $this->assertNotEmpty($failedLogs);
        $foundFailed = false;
        foreach ($failedLogs as $fl) {
            if ($fl['id'] === $log2['id']) {
                $foundFailed = true;
                $this->assertEquals('FAILED', $fl['status']);
                $this->assertStringContainsString('Simulated connection timeout', $fl['error_message']);
            }
        }
        $this->assertTrue($foundFailed, 'Log gagal harus ditemukan dalam filter status FAILED');

        // 4. Filter berdasarkan keyword
        $keywordLogs = $this->storage->getLogs(['keyword' => $log1['id']]);
        $this->assertCount(1, $keywordLogs);
        $this->assertEquals($log1['id'], $keywordLogs[0]['id']);

        // 5. Verifikasi ringkasan statistik
        $summary = $this->storage->getStatsSummary();
        $this->assertGreaterThanOrEqual(2, $summary['total_runs']);
        $this->assertGreaterThanOrEqual(1, $summary['success_runs']);
        $this->assertGreaterThanOrEqual(1, $summary['failed_runs']);
        $this->assertGreaterThanOrEqual(20, $summary['total_encounters']);
    }

    /**
     * 5. Verifikasi endpoint detail log JSON /simrs/logs/{id}
     */
    public function test_simrs_sync_detail_log_endpoint()
    {
        $log = $this->storage->startLog(['trigger' => 'test_detail']);
        $this->storage->finishLog($log['id'], [
            'status'      => 'SUCCESS',
            'total_stats' => ['encounter' => 15, 'farmasi' => 25],
        ]);

        $response = $this->getJson("/simrs/logs/{$log['id']}");
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data'    => [
                'id'     => $log['id'],
                'status' => 'SUCCESS',
            ]
        ]);
    }

    /**
     * 6. Verifikasi command artisan simrs:getall berjalan lancar
     */
    public function test_artisan_simrs_getall_command()
    {
        $exitCode = Artisan::call('simrs:getall', [
            '--simulate' => true,
            '--dry-run'  => true,
            '--chunk'    => 25,
        ]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsStringIgnoringCase('proses sinkronisasi data SIMRS', $output);
        $this->assertStringContainsString('Simulasi penarikan data SIMRS berhasil', $output);
    }

    /**
     * 7. Verifikasi mitigasi bottleneck: Idempotensi SimrsInsert mencegah duplikasi encounter
     */
    public function test_simrs_insert_idempotency_prevents_duplicate_encounters()
    {
        $testVisitId = rand(88880000, 88889999);
        $testSrvId   = 'SRV_IDEM_' . uniqid();

        $dummyData = [
            'visit_encounter' => [
                [
                    'visit_id'             => $testVisitId,
                    'srv_id'               => $testSrvId,
                    'px_noktp'             => '3506123456780001',
                    'px_norm'              => 'NORM-999',
                    'px_name'              => 'Pasien Idempotent Test',
                    'location_name'        => 'Poli Penyakit Dalam',
                    'idunitsatset'         => 'loc-test-1',
                    'dpjp'                 => 'dr. Dokter Test',
                    'kode_dokter'          => '10001',
                    'tgl_kunjung'          => '2026-10-23 08:00:00',
                    'tgl_dilayani'         => '2026-10-23 08:15:00',
                    'tgl_selesai_dilayani' => '2026-10-23 08:45:00',
                    'tgl_pulang'           => '2026-10-23 09:00:00',
                    'tipe_kunjungan'       => '1',
                ]
            ],
            'diagnosis' => [
                [
                    'visit_id' => $testVisitId,
                    'srv_id'   => $testSrvId,
                    'icd_code' => 'I10',
                    'icd_name' => 'Essential (primary) hypertension',
                    'urut'     => 1,
                ]
            ]
        ];

        // Eksekusi insert pertama
        $firstInsert = SimrsInsert::insert($dummyData, 50);
        $this->assertTrue($firstInsert);

        $initialCount = Visit_encounter::where('visit_id', $testVisitId)->count();
        $this->assertEquals(1, $initialCount);

        // Eksekusi insert kedua dengan data yang sama (simulasi re-run tarik data)
        $secondInsert = SimrsInsert::insert($dummyData, 50);
        $this->assertTrue($secondInsert);

        $afterSecondCount = Visit_encounter::where('visit_id', $testVisitId)->count();
        // Jumlah record encounter TIDAK boleh bertambah / terduplikasi
        $this->assertEquals(1, $afterSecondCount, 'Idempotensi gagal: Encounter tidak boleh terduplikasi saat re-run');

        // Bersihkan data dummy test
        Visit_encounter::where('visit_id', $testVisitId)->delete();
        \App\Models\Diagnosis::where('visit_id', $testVisitId)->delete();
    }
}
