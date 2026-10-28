<?php

namespace App\Jobs;

use App\Models\Visit_encounter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAllSatusehatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $filters;

    /**
     * Batas timeout eksekusi parent job batching (detik).
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * Create a new job instance.
     *
     * @param array $filters
     */
    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        Log::info("🚀 [SendAllSatusehatJob] Memulai batch queue pengiriman data SATUSEHAT", $this->filters);

        try {
            $totalDispatched = 0;

            // 1. Jika ada daftar spesifik visit_ids
            if (!empty($this->filters['visit_ids']) && is_array($this->filters['visit_ids'])) {
                foreach ($this->filters['visit_ids'] as $vId) {
                    $vId = (string) $vId;
                    if (!empty($vId)) {
                        SendSatusehatBundleJob::dispatch($vId);
                        $totalDispatched++;
                    }
                }
            } else {
                $query = Visit_encounter::query();

                // 2. Filter Tanggal Kunjungan
                $startDate = $this->filters['start_date'] ?? null;
                $endDate   = $this->filters['end_date'] ?? null;

                if (!empty($startDate)) {
                    if (!empty($endDate)) {
                        $query->whereDate('tgl_kunjung', '>=', $startDate)
                              ->whereDate('tgl_kunjung', '<=', $endDate);
                    } else {
                        $query->whereDate('tgl_kunjung', $startDate);
                    }
                } elseif (!empty($endDate)) {
                    $query->whereDate('tgl_kunjung', '<=', $endDate);
                }

                // 3. Filter Pencarian Pasien
                if (!empty($this->filters['search'])) {
                    $search = trim($this->filters['search']);
                    $query->where(function ($q) use ($search) {
                        $q->where('px_name', 'ilike', "%{$search}%")
                          ->orWhere('px_norm', 'like', "%{$search}%")
                          ->orWhere('visit_id', 'like', "%{$search}%")
                          ->orWhere('unit_name', 'ilike', "%{$search}%");
                    });
                }

                // 4. Filter Status Pengiriman
                $status = strtoupper($this->filters['status'] ?? 'ALL');
                if ($status === 'FAILED') {
                    $query->where('bundle_status', 'FAILED');
                } elseif ($status === 'DRAFT' || $status === 'PENDING') {
                    $query->where(function ($q) {
                        $q->whereNull('bundle_status')
                          ->orWhereIn('bundle_status', ['DRAFT', 'PENDING']);
                    })->where(function ($q) {
                        $q->where('is_send', false)->orWhereNull('is_send');
                    });
                } elseif ($status === 'SUCCESS') {
                    $query->where(function ($q) {
                        $q->where('bundle_status', 'SUCCESS')
                          ->orWhere('is_send', true);
                    });
                } else {
                    // ALL: Kirim data yang belum sukses / belum terkirim
                    $query->where(function ($q) {
                        $q->whereNull('bundle_status')
                          ->orWhere('bundle_status', '!=', 'SUCCESS');
                    })->where(function ($q) {
                        $q->where('is_send', false)->orWhereNull('is_send');
                    });
                }

                $chunkSize = max(10, min(100, (int) ($this->filters['chunk_size'] ?? 50)));

                $query->orderBy('id', 'asc')
                    ->select('visit_id')
                    ->chunk($chunkSize, function ($encounters) use (&$totalDispatched) {
                        foreach ($encounters as $encounter) {
                            $visitId = (string) $encounter->visit_id;
                            if (!empty($visitId)) {
                                SendSatusehatBundleJob::dispatch($visitId);
                                $totalDispatched++;
                            }
                        }
                    });
            }

            Log::info("✅ [SendAllSatusehatJob] Berhasil menjadwalkan {$totalDispatched} job pengiriman per-kunjungan.");
        } catch (\Throwable $e) {
            Log::error("❌ [SendAllSatusehatJob] Gagal memproses batch send-all: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
