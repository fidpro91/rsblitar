<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\ServicesBundle\SendAllSatusehatService;
use App\Models\Visit_encounter;
use App\Models\Respon_satusehat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SendAllSatusehatServiceTest extends TestCase
{
    protected string $testVisitId = '88889999';

    protected function setUp(): void
    {
        parent::setUp();

        // Siapkan mock encounter di database staging
        DB::table('visit_encounter')->where('visit_id', $this->testVisitId)->delete();
        DB::table('visit_encounter')->insert([
            'visit_id'             => $this->testVisitId,
            'px_norm'              => 'NORM-TEST-999',
            'px_name'              => 'Pasien Service Test',
            'tgl_kunjung'          => now()->toDateTimeString(),
            'tgl_dilayani'         => now()->toDateTimeString(),
            'tgl_selesai_dilayani' => now()->toDateTimeString(),
            'tgl_pulang'           => now()->toDateTimeString(),
            'is_send'              => false,
            'bundle_status'        => 'DRAFT',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    protected function tearDown(): void
    {
        // Bersihkan data uji coba agar tidak mencemari database
        DB::table('visit_encounter')->where('visit_id', $this->testVisitId)->delete();
        DB::table('respon_satusehat')->where('visit_id', $this->testVisitId)->delete();
        DB::table('satusehat_resource_ids')->where('visit_id', $this->testVisitId)->delete();
        DB::table('satusehat_send_logs')->where('visit_id', $this->testVisitId)->delete();
        DB::table('simrs_error_logs')->where('visit_id', (int) $this->testVisitId)->delete();

        parent::tearDown();
    }

    /**
     * Verifikasi proses simpan SendAllSatusehatService identik dengan kirim satu persatu:
     * 1. respon_satusehat terisi
     * 2. satusehat_resource_ids terisi
     * 3. visit_encounter terupdate is_send=true, bundle_status=SUCCESS, uuid_encounter
     * 4. satusehat_send_logs mencatat riwayat
     * 5. simrs_error_logs mencatat audit aktivitas
     */
    public function test_send_all_satusehat_service_stores_identical_data_as_single_send()
    {
        $mockUuid = 'a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d';

        Http::fake([
            '*oauth2*' => Http::response([
                'access_token' => 'mock_token_abc123',
                'token_type'   => 'Bearer',
                'expires_in'   => 3600,
            ], 200),
            '*fhir-r4/v1*' => Http::response([
                'resourceType' => 'Bundle',
                'type'         => 'transaction-response',
                'entry'        => [
                    [
                        'response' => [
                            'status'       => '201 Created',
                            'location'     => "https://api-satusehat.kemkes.go.id/fhir-r4/v1/Encounter/{$mockUuid}/_history/1",
                            'resourceID'   => $mockUuid,
                            'resourceType' => 'Encounter'
                        ]
                    ]
                ]
            ], 200),
        ]);

        $service = new SendAllSatusehatService();
        $result = $service->prepareAndSendBundle($this->testVisitId);

        // 1. Verifikasi return result
        $this->assertTrue($result['success']);
        $this->assertEquals('SUCCESS', $result['status']);
        $this->assertEquals($mockUuid, $result['captured_ids']['Encounter'] ?? null);

        // 2. Verifikasi Respon_satusehat tersimpan
        $respon = Respon_satusehat::where('visit_id', $this->testVisitId)->first();
        $this->assertNotNull($respon, 'respon_satusehat harus tersimpan');
        $this->assertEquals('201 Created', $respon->status);
        $this->assertEquals('Encounter', $respon->resourcetype);
        $this->assertEquals($mockUuid, $respon->resourceid);

        // 3. Verifikasi satusehat_resource_ids tersimpan
        $resourceIdRow = DB::table('satusehat_resource_ids')
            ->where('visit_id', $this->testVisitId)
            ->where('resource_type', 'Encounter')
            ->first();
        $this->assertNotNull($resourceIdRow, 'satusehat_resource_ids harus tersimpan');
        $this->assertEquals($mockUuid, $resourceIdRow->resource_id);

        // 4. Verifikasi visit_encounter terupdate
        $encRow = DB::table('visit_encounter')->where('visit_id', $this->testVisitId)->first();
        $this->assertNotNull($encRow);
        $this->assertTrue((bool)$encRow->is_send, 'is_send harus true');
        $this->assertEquals('SUCCESS', $encRow->bundle_status);
        $this->assertEquals($mockUuid, $encRow->uuid_encounter);

        // 5. Verifikasi satusehat_send_logs mencatat log pengiriman
        $sendLog = DB::table('satusehat_send_logs')->where('visit_id', $this->testVisitId)->first();
        $this->assertNotNull($sendLog, 'satusehat_send_logs harus tersimpan');
        $this->assertEquals('SUCCESS', $sendLog->status);
        $this->assertEquals(200, $sendLog->http_code);

        // 6. Verifikasi simrs_error_logs mencatat audit trail
        $errorLog = DB::table('simrs_error_logs')->where('visit_id', (int) $this->testVisitId)->first();
        $this->assertNotNull($errorLog, 'simrs_error_logs harus mencatat aktivitas');
        $this->assertEquals('success', $errorLog->status);
    }
}
