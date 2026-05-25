<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\ServicesBundle\CronsendBundle;
use App\Models\Visit_encounter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendBundleSatusehat extends Command
{
    protected $signature = 'satusehat:send {--chunk=20}';
    protected $description = 'Kirim bundle SatuSehat';

    public function handle()
    {
        $chunk = (int) $this->option('chunk');
        $service = new CronsendBundle();

        $this->info("🚀 Mulai proses kirim bundle...");
        $this->info("⏰ Waktu mulai: " . now());

        Visit_encounter::where('is_send', false)
            ->whereNotNull('tgl_kunjung')
            ->whereNotNull('tgl_dilayani')
            ->whereNotNull('tgl_selesai_dilayani')
            ->whereNotNull('tgl_pulang')
            ->orderBy('id')
            ->chunkById($chunk, function ($visits) use ($service) {

                foreach ($visits as $visit) {

                    $visitId = $visit->visit_id;

                    try {

                        $response = $service->prepareAndSendBundle($visitId);

                        if (!is_array($response)) {
                            throw new \Exception("Invalid response format from service");
                        }
                        if (!($response['success'] ?? false)) {
                            throw new \Exception($response['message'] ?? 'SATUSEHAT failed (no success flag)');
                        }

                        $data = $response['data'] ?? null;
                        if (($data['resourceType'] ?? null) === 'OperationOutcome') {

                            $issues = $data['issue'] ?? [];

                            foreach ($issues as $issue) {

                                $severity = $issue['severity'] ?? null;
                                $detail = $issue['details']['text'] ?? 'Unknown error';
                                if ($severity === 'error') {
                                    $this->logActivity(
                                        'SATUSEHAT',
                                        (int) $visitId,
                                        'error',
                                        [
                                            'step' => 'OperationOutcome',
                                            'detail' => $detail,
                                            'raw' => $data
                                        ]
                                    );

                                    throw new \Exception("SATUSEHAT ERROR: " . $detail);
                                }
                            }
                        }
                        $visit->is_send = true;
                        $visit->save();
                        $this->logActivity(
                            'SATUSEHAT',
                            (int) $visitId,
                            'success',
                            [
                                'step' => 'prepareAndSendBundle',
                                'raw' => $data ?? null
                            ]
                        );

                        $this->info("✅ Sukses visit_id: {$visitId}");
                    } catch (\Throwable $e) {

                        $this->logActivity(
                            'SATUSEHAT',
                            (int) $visitId,
                            'error',
                            [
                                'step' => 'prepareAndSendBundle_failed'
                            ],
                            $e
                        );

                        $this->error("❌ Gagal visit_id: {$visitId} - " . $e->getMessage());

                        Log::error('SATUSEHAT BUNDLE ERROR', [
                            'visit_id' => $visitId,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("🎉 Selesai kirim bundle");
    }

    private function logActivity(
        string $type,
        ?int $visitId,
        string $status,
        array $payload = [],
        ?\Throwable $e = null
    ): void {
        try {
            DB::table('simrs_error_logs')->insert([
                'type' => $type,
                'visit_id' => $visitId,
                'message' => $status === 'error'
                    ? ($e->getMessage() ?? 'ERROR')
                    : 'SUCCESS',

                'file' => $status === 'error' ? $e->getFile() : null,
                'line' => $status === 'error' ? $e->getLine() : null,
                'trace' => $status === 'error' ? $e->getTraceAsString() : null,

                'payload' => json_encode($payload),

                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $logError) {
            Log::error('FAILED WRITE SIMRS LOG: ' . $logError->getMessage());
        }
    }
}
