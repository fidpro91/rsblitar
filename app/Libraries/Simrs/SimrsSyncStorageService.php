<?php

namespace App\Libraries\Simrs;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SimrsSyncStorageService
{
    protected string $storageDir;
    protected string $logsFile;
    protected string $lockFile;

    public function __construct(?string $customLogsFile = null)
    {
        $this->storageDir = storage_path('app/simrs_sync');
        $this->logsFile   = $customLogsFile ?? (app()->environment('testing') ? $this->storageDir . '/sync_logs_test.json' : $this->storageDir . '/sync_logs.json');
        $this->lockFile   = $this->storageDir . '/sync.lock';

        $this->ensureDirectories();
    }

    protected function ensureDirectories(): void
    {
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }

        if (!file_exists($this->logsFile)) {
            file_put_contents($this->logsFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    /**
     * Catat log baru ketika proses tarik data dimulai
     */
    public function startLog(array $params): array
    {
        $id = 'SYNC-' . date('Ymd-His') . '-' . strtoupper(Str::random(4));
        $now = now()->toDateTimeString();

        $entry = [
            'id'               => $id,
            'trigger'          => $params['trigger'] ?? 'manual',
            'status'           => 'RUNNING',
            'tanggal'          => $params['tanggal'] ?? date('Y-m-d'),
            'start_date'       => $params['start_date'] ?? ($params['tanggal'] ?? date('Y-m-d')),
            'end_date'         => $params['end_date'] ?? ($params['tanggal'] ?? date('Y-m-d')),
            'unit_type'        => $params['unit_type'] ?? 'ALL',
            'chunk_size'       => $params['chunk_size'] ?? 100,
            'limit'            => $params['limit'] ?? null,
            'dry_run'          => !empty($params['dry_run']),
            'started_at'       => $now,
            'finished_at'      => null,
            'duration_seconds' => null,
            'memory_peak_mb'   => null,
            'total_stats'      => [
                'encounter'   => 0,
                'diagnosis'   => 0,
                'icd9'        => 0,
                'observation' => 0,
                'farmasi'     => 0,
                'lab'         => 0,
                'allergi'     => 0,
                'careplan'    => 0,
                'radiologi'   => 0,
                'quisioner'   => 0,
            ],
            'chunks_processed' => 0,
            'error_message'    => null,
            'warnings'         => [],
            'details'          => []
        ];

        // 1. Simpan ke Database Lokal (Primary Production Storage)
        try {
            DB::table('simrs_sync_logs')->insert([
                'id'               => $id,
                'trigger'          => $entry['trigger'],
                'status'           => $entry['status'],
                'tanggal'          => $entry['tanggal'],
                'start_date'       => $entry['start_date'],
                'end_date'         => $entry['end_date'],
                'chunk_size'       => $entry['chunk_size'],
                'limit_data'       => $entry['limit'],
                'dry_run'          => $entry['dry_run'],
                'started_at'       => $entry['started_at'],
                'total_stats'      => json_encode($entry['total_stats']),
                'created_at'       => $now,
            ]);
        } catch (\Throwable $e) {
            Log::info("DB sync log insert fallback: " . $e->getMessage());
        }

        // 2. Simpan ke JSON File (Fallback)
        $this->updateJsonFile($this->logsFile, function (array $logs) use ($entry) {
            array_unshift($logs, $entry);
            return array_slice($logs, 0, 500);
        });

        return $entry;
    }

    /**
     * Perbarui log setelah selesai atau gagal
     */
    public function finishLog(string $id, array $updates): ?array
    {
        $updatedEntry = null;

        $this->updateJsonFile($this->logsFile, function (array $logs) use ($id, $updates, &$updatedEntry) {
            foreach ($logs as &$log) {
                if ($log['id'] === $id) {
                    $log = array_merge($log, $updates);
                    if (empty($log['finished_at'])) {
                        $log['finished_at'] = now()->toDateTimeString();
                    }
                    if (isset($log['started_at']) && !isset($updates['duration_seconds'])) {
                        $start = strtotime($log['started_at']);
                        $end = strtotime($log['finished_at']);
                        $log['duration_seconds'] = max(0, $end - $start);
                    }
                    if (!isset($updates['memory_peak_mb'])) {
                        $log['memory_peak_mb'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
                    }
                    $updatedEntry = $log;
                    break;
                }
            }
            return $logs;
        });

        // Update ke Database PostgreSQL
        if ($updatedEntry) {
            try {
                $dbUpdates = [
                    'status'           => $updatedEntry['status'] ?? 'SUCCESS',
                    'finished_at'      => $updatedEntry['finished_at'] ?? now()->toDateTimeString(),
                    'duration_seconds' => $updatedEntry['duration_seconds'] ?? null,
                    'memory_peak_mb'   => $updatedEntry['memory_peak_mb'] ?? null,
                    'chunks_processed' => $updatedEntry['chunks_processed'] ?? 0,
                ];
                if (isset($updatedEntry['total_stats'])) {
                    $dbUpdates['total_stats'] = json_encode($updatedEntry['total_stats']);
                }
                if (isset($updatedEntry['error_message'])) {
                    $dbUpdates['error_message'] = $updatedEntry['error_message'];
                }
                if (isset($updatedEntry['warnings'])) {
                    $dbUpdates['warnings'] = json_encode($updatedEntry['warnings']);
                }
                DB::table('simrs_sync_logs')->where('id', $id)->update($dbUpdates);
            } catch (\Throwable $e) {
                Log::info("DB sync log update fallback: " . $e->getMessage());
            }
        }

        return $updatedEntry;
    }

    /**
     * Ambil daftar log tarik data dengan filter & pagination
     */
    public function getLogs(array $filters = []): array
    {
        // 1. Coba baca dari Database Lokal (Primary)
        try {
            $query = DB::table('simrs_sync_logs');

            if (!empty($filters['status']) && strtoupper($filters['status']) !== 'ALL') {
                $query->where('status', strtoupper($filters['status']));
            }

            if (!empty($filters['keyword'])) {
                $kw = strtolower(trim($filters['keyword']));
                $query->where(function ($q) use ($kw) {
                    $q->where('id', 'ilike', "%{$kw}%")
                      ->orWhere('error_message', 'ilike', "%{$kw}%")
                      ->orWhere('trigger', 'ilike', "%{$kw}%");
                });
            }

            if (!empty($filters['start_date'])) {
                $query->whereDate('started_at', '>=', $filters['start_date']);
            }

            if (!empty($filters['end_date'])) {
                $query->whereDate('started_at', '<=', $filters['end_date']);
            }

            $dbLogs = $query->orderBy('started_at', 'desc')->get();
            if ($dbLogs->isNotEmpty()) {
                return $dbLogs->map(function ($r) {
                    return [
                        'id'               => $r->id,
                        'trigger'          => $r->trigger,
                        'status'           => $r->status,
                        'tanggal'          => $r->tanggal,
                        'start_date'       => $r->start_date,
                        'end_date'         => $r->end_date,
                        'chunk_size'       => $r->chunk_size,
                        'limit'            => $r->limit_data,
                        'dry_run'          => (bool) $r->dry_run,
                        'started_at'       => $r->started_at,
                        'finished_at'      => $r->finished_at,
                        'duration_seconds' => $r->duration_seconds,
                        'memory_peak_mb'   => $r->memory_peak_mb,
                        'total_stats'      => is_string($r->total_stats) ? json_decode($r->total_stats, true) : (array) $r->total_stats,
                        'chunks_processed' => $r->chunks_processed,
                        'error_message'    => $r->error_message,
                        'warnings'         => is_string($r->warnings) ? json_decode($r->warnings, true) : (array) $r->warnings,
                    ];
                })->toArray();
            }
        } catch (\Throwable $e) {
            // DB fallback ke JSON
        }

        // 2. Fallback baca JSON File
        $logs = $this->readJsonFile($this->logsFile);

        if (!empty($filters['status']) && strtoupper($filters['status']) !== 'ALL') {
            $status = strtoupper($filters['status']);
            $logs = array_filter($logs, fn($item) => ($item['status'] ?? '') === $status);
        }

        if (!empty($filters['keyword'])) {
            $kw = strtolower(trim($filters['keyword']));
            $logs = array_filter($logs, function ($item) use ($kw) {
                return str_contains(strtolower($item['id'] ?? ''), $kw)
                    || str_contains(strtolower($item['tanggal'] ?? ''), $kw)
                    || str_contains(strtolower($item['error_message'] ?? ''), $kw)
                    || str_contains(strtolower($item['trigger'] ?? ''), $kw);
            });
        }

        if (!empty($filters['start_date'])) {
            $startDate = $filters['start_date'];
            $logs = array_filter($logs, function ($item) use ($startDate) {
                $created = substr($item['started_at'] ?? '', 0, 10);
                return $created >= $startDate;
            });
        }

        if (!empty($filters['end_date'])) {
            $endDate = $filters['end_date'];
            $logs = array_filter($logs, function ($item) use ($endDate) {
                $created = substr($item['started_at'] ?? '', 0, 10);
                return $created <= $endDate;
            });
        }

        return array_values($logs);
    }

    /**
     * Ambil 1 log berdasarkan ID
     */
    public function getLogById(string $id): ?array
    {
        try {
            $r = DB::table('simrs_sync_logs')->where('id', $id)->first();
            if ($r) {
                return [
                    'id'               => $r->id,
                    'trigger'          => $r->trigger,
                    'status'           => $r->status,
                    'tanggal'          => $r->tanggal,
                    'start_date'       => $r->start_date,
                    'end_date'         => $r->end_date,
                    'chunk_size'       => $r->chunk_size,
                    'limit'            => $r->limit_data,
                    'dry_run'          => (bool) $r->dry_run,
                    'started_at'       => $r->started_at,
                    'finished_at'      => $r->finished_at,
                    'duration_seconds' => $r->duration_seconds,
                    'memory_peak_mb'   => $r->memory_peak_mb,
                    'total_stats'      => is_string($r->total_stats) ? json_decode($r->total_stats, true) : (array) $r->total_stats,
                    'chunks_processed' => $r->chunks_processed,
                    'error_message'    => $r->error_message,
                    'warnings'         => is_string($r->warnings) ? json_decode($r->warnings, true) : (array) $r->warnings,
                ];
            }
        } catch (\Throwable $e) {}

        $logs = $this->readJsonFile($this->logsFile);
        foreach ($logs as $log) {
            if ($log['id'] === $id) {
                return $log;
            }
        }
        return null;
    }

    /**
     * Ringkasan metrik statistik untuk Dashboard Card
     */
    public function getStatsSummary(?array $filters = null): array
    {
        $hasFilters = !empty($filters) && (!empty($filters['start_date']) || !empty($filters['end_date']) || (!empty($filters['status']) && $filters['status'] !== 'ALL') || !empty($filters['keyword']));

        if (!$hasFilters) {
            try {
                $totalRuns = DB::table('simrs_sync_logs')->count();
                if ($totalRuns > 0) {
                    $successRuns = DB::table('simrs_sync_logs')->where('status', 'SUCCESS')->count();
                    $failedRuns = DB::table('simrs_sync_logs')->where('status', 'FAILED')->count();
                    $avgDuration = round((float) DB::table('simrs_sync_logs')->whereNotNull('duration_seconds')->avg('duration_seconds'), 1);

                    $logs = DB::table('simrs_sync_logs')->get();
                    $totalEncounters = 0;
                    foreach ($logs as $l) {
                        $stats = is_string($l->total_stats) ? json_decode($l->total_stats, true) : (array) $l->total_stats;
                        $totalEncounters += (int) ($stats['encounter'] ?? 0);
                    }

                    return [
                        'total_runs'       => $totalRuns,
                        'success_runs'     => $successRuns,
                        'failed_runs'      => $failedRuns,
                        'total_encounters' => $totalEncounters,
                        'avg_duration_sec' => $avgDuration,
                    ];
                }
            } catch (\Throwable $e) {}
        }

        $logs = $this->getLogs($filters ?: []);

        $totalRuns = count($logs);
        $successRuns = 0;
        $failedRuns = 0;
        $totalEncounters = 0;
        $totalDurations = 0;
        $durationCount = 0;

        foreach ($logs as $log) {
            $st = $log['status'] ?? '';
            if ($st === 'SUCCESS') $successRuns++;
            if ($st === 'FAILED')  $failedRuns++;

            $totalEncounters += (int) ($log['total_stats']['encounter'] ?? 0);

            if (isset($log['duration_seconds']) && is_numeric($log['duration_seconds'])) {
                $totalDurations += (float) $log['duration_seconds'];
                $durationCount++;
            }
        }

        $avgDuration = $durationCount > 0 ? round($totalDurations / $durationCount, 1) : 0;

        return [
            'total_runs'       => $totalRuns,
            'success_runs'     => $successRuns,
            'failed_runs'      => $failedRuns,
            'total_encounters' => $totalEncounters,
            'avg_duration_sec' => $avgDuration,
        ];
    }

    /**
     * Bersihkan log lama
     */
    public function clearLogs(): bool
    {
        try {
            DB::table('simrs_sync_logs')->truncate();
        } catch (\Throwable $e) {}

        return $this->updateJsonFile($this->logsFile, fn() => []);
    }

    protected function readJsonFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }
        $content = @file_get_contents($filePath);
        if ($content === false || trim($content) === '') {
            return [];
        }
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    protected function updateJsonFile(string $filePath, callable $modifier): bool
    {
        $this->ensureDirectories();

        $fp = @fopen($filePath, 'c+');
        if (!$fp) {
            return false;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }

        $raw = stream_get_contents($fp);
        $data = !empty($raw) ? json_decode($raw, true) : [];
        if (!is_array($data)) {
            $data = [];
        }

        $updated = $modifier($data);

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($updated, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }
}
