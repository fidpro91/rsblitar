<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Simrs\SimrsController;

class SimrsGetAll extends Command
{
    protected $signature = 'simrs:getall';
    protected $description = 'Ambil semua data SIMRS';

    public function handle()
    {
        $this->info("🚀 Proses SIMRS get_all dimulai...");
        $this->info("⏰ Waktu mulai: " . now());

        try {
            $controller = new SimrsController();
            $controller->get_all();

            $this->info("✅ Proses berhasil dijalankan");
        } catch (\Throwable $e) {
            $this->error("❌ Terjadi error: " . $e->getMessage());
        }

        $this->info("⏰ Waktu selesai: " . now());
        $this->info("🎉 Proses selesai.");

        return 0;
    }
}
