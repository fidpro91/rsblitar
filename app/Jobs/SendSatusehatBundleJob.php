<?php

namespace App\Jobs;

use App\ServicesBundle\SendAllSatusehatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSatusehatBundleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $visitId;

    /**
     * Jumlah percobaan maksimal jika terjadi kegagalan jaringan / error temporer.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Batas timeout eksekusi job (detik).
     *
     * @var int
     */
    public $timeout = 180;

    /**
     * Waktu jeda sebelum percobaan ulang (detik).
     *
     * @var int
     */
    public $backoff = 5;

    /**
     * Create a new job instance.
     *
     * @param string|int $visitId
     */
    public function __construct($visitId)
    {
        $this->visitId = (string) $visitId;
    }

    /**
     * Execute the job.
     *
     * @param SendAllSatusehatService $bundleService
     * @return array
     */
    public function handle(SendAllSatusehatService $bundleService): array
    {
        Log::info("🚀 [SendSatusehatBundleJob] Memulai pengiriman bundle SATUSEHAT untuk visit_id: {$this->visitId}");

        try {
            $result = $bundleService->prepareAndSendBundle($this->visitId);

            if ($result['success'] ?? false) {
                Log::info("✅ [SendSatusehatBundleJob] Sukses kirim bundle visit_id: {$this->visitId}", [
                    'captured_ids' => $result['captured_ids'] ?? [],
                ]);
            } else {
                Log::warning("⚠️ [SendSatusehatBundleJob] Gagal kirim bundle visit_id: {$this->visitId}", [
                    'message' => $result['message'] ?? 'Gagal',
                    'issues'  => $result['issues'] ?? [],
                ]);
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error("❌ [SendSatusehatBundleJob] Exception saat kirim visit_id: {$this->visitId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("❌ [SendSatusehatBundleJob] Job gagal definitif untuk visit_id: {$this->visitId}: " . $exception->getMessage());
    }
}
