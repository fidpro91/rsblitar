<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SendAllSatusehatJob;
use Illuminate\Support\Facades\Log;

class SendBundleSatusehat extends Command
{
    protected $signature = 'satusehat:send
        {--chunk=50 : Jumlah data per batch}
        {--start-date= : Tanggal awal kunjungan}
        {--end-date= : Tanggal akhir kunjungan}
        {--search= : Pencarian pasien atau kunjungan}
        {--status=ALL : Status pengiriman}';

    protected $description = 'Jadwalkan pengiriman bundle SATUSEHAT melalui Queue';

    public function handle(): int
    {
        $filters = [
            'chunk_size' => max(
                10,
                min(100, (int) $this->option('chunk'))
            ),

            'start_date' => $this->option('start-date'),
            'end_date'   => $this->option('end-date'),
            'search'     => $this->option('search'),
            'status'     => strtoupper($this->option('status') ?: 'ALL'),
        ];

        $this->info('==========================================');
        $this->info('     SATUSEHAT BUNDLE SCHEDULER');
        $this->info('==========================================');

        $this->info('Waktu       : ' . now());
        $this->info('Status      : ' . $filters['status']);
        $this->info('Chunk       : ' . $filters['chunk_size']);
        $this->info('Tanggal Awal: ' . ($filters['start_date'] ?: 'Semua'));
        $this->info('Tanggal Akhir: ' . ($filters['end_date'] ?: 'Semua'));

        try {

            SendAllSatusehatJob::dispatch($filters);

            Log::info('[SATUSEHAT COMMAND] Batch berhasil dijadwalkan', [
                'filters' => $filters,
                'time' => now(),
            ]);

            $this->info('------------------------------------------');
            $this->info('Berhasil memasukkan batch ke queue.');
            $this->info('Pengiriman akan diproses oleh Supervisor.');
            $this->info('==========================================');

            return self::SUCCESS;

        } catch (\Throwable $e) {

            Log::error('[SATUSEHAT COMMAND] Gagal dispatch batch', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error('Gagal menjadwalkan pengiriman!');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
