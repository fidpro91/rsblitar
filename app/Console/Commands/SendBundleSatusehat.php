<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\ServicesBundle\CronsendBundle;
use App\Models\Visit_encounter;
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
                        $service->prepareAndSendBundle($visitId);
                        $this->info("✅ Sukses visit_id: {$visitId}");
                    } catch (\Exception $e) {
                        Log::error("Gagal kirim visit_id={$visitId} : " . $e->getMessage());
                    }
                    $visit->is_send = true;
                    $visit->save();
                }
            });

        $this->info("🎉 Selesai kirim bundle");
    }
}
