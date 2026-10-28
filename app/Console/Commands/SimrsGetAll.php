<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Libraries\Simrs\SimrsSyncService;

class SimrsGetAll extends Command
{
    protected $signature = 'simrs:getall 
                            {--tanggal= : Tanggal spesifik YYYY-MM-DD}
                            {--start-date= : Tanggal mulai rentang}
                            {--end-date= : Tanggal selesai rentang}
                            {--days=10 : Mundur berapa hari jika tanggal tidak diisi}
                            {--chunk=50 : Ukuran batch chunking}
                            {--limit= : Batas maksimal record yang diambil}
                            {--dry-run : Hanya simulasi kalkulasi tanpa insert ke staging}
                            {--simulate : Mode simulasi data}';

    protected $description = 'Ambil dan sinkronisasi data SIMRS dengan mitigasi bottleneck dan pencatatan log monitoring';

    public function handle(SimrsSyncService $service): int
    {
        $this->info("🚀 Memulai proses sinkronisasi data SIMRS...");
        $this->info("⏰ Waktu mulai: " . now()->toDateTimeString());

        $startDate = $this->option('start-date');
        $endDate   = $this->option('end-date');
        $tanggal   = $this->option('tanggal');

        if (!$startDate && !$tanggal) {
            $days = (int) $this->option('days');
            $startDate = now()->subDays($days)->toDateString();
            $endDate   = $startDate;
            $tanggal   = $startDate;
        } elseif ($tanggal && !$startDate) {
            $startDate = $tanggal;
            $endDate   = $tanggal;
        }

        $this->info("📅 Periode target: {$startDate} s/d " . ($endDate ?? $startDate));
        $this->info("📦 Chunk size: " . $this->option('chunk'));

        $result = $service->sync([
            'tanggal'    => $tanggal ?? $startDate,
            'start_date' => $startDate,
            'end_date'   => $endDate ?? $startDate,
            'chunk_size' => (int) $this->option('chunk'),
            'limit'      => $this->option('limit') ? (int) $this->option('limit') : null,
            'dry_run'    => (bool) $this->option('dry-run'),
            'simulate'   => (bool) $this->option('simulate'),
            'trigger'    => 'cli',
        ]);

        $this->line('');
        if ($result['success']) {
            $this->info("✅ " . $result['message']);
            $this->info("⏱️  Durasi: " . ($result['duration'] ?? 0) . " detik");
            $this->info("🆔 Sync Log ID: " . $result['sync_id']);

            $stats = $result['total'] ?? [];
            $tableRows = [];
            foreach ($stats as $k => $v) {
                $tableRows[] = [ucfirst($k), number_format($v)];
            }
            $this->table(['Modul / Resource', 'Total Record Ditarik'], $tableRows);
        } else {
            $this->error("❌ Gagal: " . ($result['message'] ?? 'Terjadi kesalahan'));
            if (!empty($result['error'])) {
                $this->error("   Detail: " . $result['error']);
            }
        }

        $this->info("⏰ Waktu selesai: " . now()->toDateTimeString());
        return $result['success'] ? 0 : 1;
    }
}
