<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class SatusehatResourceMonitorTest extends TestCase
{
    /**
     * Verifikasi menu dan halaman monitoring aliran data resource dapat diakses (200 OK)
     */
    public function test_resource_monitor_dashboard_renders_successfully()
    {
        $response = $this->get('/satusehat/monitoring-resource');

        $response->assertStatus(200);
        $response->assertSee('Monitoring Aliran Data Resource SATUSEHAT');
        $response->assertSee('Matriks Pemantauan Aliran Data Setiap Resource');
        $response->assertSee('Encounter');
        $response->assertSee('Observation');
        $response->assertSee('Condition');
        $response->assertSee('Procedure');
    }

    /**
     * Verifikasi filter tanggal dan resource pada dashboard
     */
    public function test_resource_monitor_dashboard_filters_work()
    {
        $response = $this->get('/satusehat/monitoring-resource?resource_type=Encounter&status=SUCCESS');

        $response->assertStatus(200);
        $response->assertSee('Monitoring Aliran Data Resource SATUSEHAT');
    }

    /**
     * Verifikasi endpoint stream data JSON untuk real-time polling
     */
    public function test_resource_monitor_stream_api_returns_valid_structure()
    {
        $response = $this->getJson('/satusehat/monitoring-resource/stream');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'timestamp',
            'summary' => [
                'total',
                'success',
                'failed',
                'success_rate',
            ],
            'resources',
            'chart' => [
                'labels',
                'success_data',
                'failed_data',
            ],
            'recent_logs',
        ]);
        $response->assertJson(['success' => true]);
    }

    /**
     * Verifikasi modal inspeksi log detail respons
     */
    public function test_resource_monitor_log_detail_endpoint()
    {
        $firstLog = DB::table('respon_satusehat')->first();
        if (!$firstLog) {
            $this->markTestSkipped('Tidak ada data di tabel respon_satusehat untuk pengujian');
        }

        $response = $this->getJson("/satusehat/monitoring-resource/log/{$firstLog->id_respon}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'log' => [
                'id_respon',
                'visit_id',
                'resourcetype',
                'status',
            ]
        ]);
        $response->assertJson(['success' => true]);
    }
}
