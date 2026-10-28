<?php

namespace App\Libraries;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SatuSehatStorageService
{
    protected string $baseDir;
    protected string $locksDir;
    protected string $resourceFile;
    protected string $logsFile;
    protected string $draftsFile;
    protected string $repairsFile;
    protected string $historyFile;
    protected array $activeFilePointers = [];

    public function __construct()
    {
        $this->baseDir = storage_path('app/satusehat');
        $this->locksDir = $this->baseDir . DIRECTORY_SEPARATOR . 'locks';
        $this->resourceFile = $this->baseDir . DIRECTORY_SEPARATOR . 'resource_ids.json';
        $this->logsFile = $this->baseDir . DIRECTORY_SEPARATOR . 'retry_logs.json';
        $this->draftsFile = $this->baseDir . DIRECTORY_SEPARATOR . 'data_drafts.json';
        $this->repairsFile = $this->baseDir . DIRECTORY_SEPARATOR . 'staging_repairs.json';
        $this->historyFile = $this->baseDir . DIRECTORY_SEPARATOR . 'repair_history.json';

        $this->ensureDirectories();
    }

    protected function ensureDirectories(): void
    {
        if (!is_dir($this->baseDir)) {
            @mkdir($this->baseDir, 0750, true);
        }
        if (!is_dir($this->locksDir)) {
            @mkdir($this->locksDir, 0750, true);
        }
    }

    /**
     * Menyimpan mapping resource_id SATUSEHAT secara aman ke file terstruktur.
     * @param string|int $visitId
     */
    public function saveResourceId($visitId, string $resourceType, string $resourceId, array $extra = []): bool
    {
        $visitId = (string) $visitId;
        $resourceType = trim($resourceType);
        $resourceId = trim($resourceId);

        if (empty($visitId) || empty($resourceType) || empty($resourceId)) {
            return false;
        }

        // 1. Simpan ke database PostgreSQL (Primary Production)
        try {
            DB::table('satusehat_resource_ids')->updateOrInsert(
                ['visit_id' => $visitId, 'resource_type' => $resourceType],
                [
                    'resource_id' => $resourceId,
                    'location'    => $extra['location'] ?? null,
                    'status'      => $extra['status'] ?? '201 Created',
                    'updated_at'  => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::info("DB saveResourceId fallback: " . $e->getMessage());
        }

        return $this->updateJsonFile($this->resourceFile, function (array $data) use ($visitId, $resourceType, $resourceId, $extra) {
            if (!isset($data[$visitId])) {
                $data[$visitId] = [
                    'visit_id'    => $visitId,
                    'resources'   => [],
                    'updated_at'  => now()->toIso8601String(),
                ];
            }

            $data[$visitId]['resources'][$resourceType] = [
                'resource_id'   => $resourceId,
                'resource_type' => $resourceType,
                'location'      => $extra['location'] ?? null,
                'status'        => $extra['status'] ?? '201 Created',
                'captured_at'   => now()->toIso8601String(),
            ];

            $data[$visitId]['updated_at'] = now()->toIso8601String();
            return $data;
        });
    }

    /**
     * Mengambil seluruh resource_id untuk visit_id tertentu.
     * @param string|int $visitId
     */
    public function getResourceIds($visitId): array
    {
        $visitId = (string) $visitId;

        // 1. Coba baca dari Database PostgreSQL (Primary)
        try {
            $dbRows = DB::table('satusehat_resource_ids')->where('visit_id', $visitId)->get();
            if ($dbRows->isNotEmpty()) {
                $mapped = [];
                foreach ($dbRows as $row) {
                    $mapped[$row->resource_type] = [
                        'resource_id'   => $row->resource_id,
                        'resource_type' => $row->resource_type,
                        'location'      => $row->location,
                        'status'        => $row->status ?? '201 Created',
                        'captured_at'   => $row->updated_at,
                    ];
                }
                return $mapped;
            }
        } catch (\Throwable $e) {}

        // 2. Fallback baca JSON File
        $all = $this->readJsonFile($this->resourceFile);
        return $all[$visitId]['resources'] ?? [];
    }

    /**
     * Mengambil satu resource_id spesifik berdasarkan visitId dan tipe resource.
     * @param string|int $visitId
     */
    public function getSpecificResourceId($visitId, string $resourceType): ?string
    {
        $resources = $this->getResourceIds($visitId);
        return $resources[$resourceType]['resource_id'] ?? null;
    }

    /**
     * Memeriksa apakah resource sudah memiliki resource_id SATUSEHAT.
     * @param string|int $visitId
     */
    public function hasResourceId($visitId, string $resourceType): bool
    {
        return !empty($this->getSpecificResourceId($visitId, $resourceType));
    }

    /**
     * Mengambil seluruh data mapping resource_id yang tersimpan.
     */
    public function getAllResourceMappings(): array
    {
        return $this->readJsonFile($this->resourceFile);
    }

    /**
     * Mencatat riwayat pengiriman / retry ke file terstruktur.
     * @param string|int $visitId
     */
    public function logSendAttempt($visitId, array $attemptData): bool
    {
        $visitId = (string) $visitId;
        $sanitized = $this->sanitizeData($attemptData);

        $entry = array_merge([
            'id'         => (string) \Illuminate\Support\Str::uuid(),
            'visit_id'   => $visitId,
            'timestamp'  => now()->toIso8601String(),
            'status'     => 'UNKNOWN',
            'http_code'  => null,
            'error_type' => null,
            'message'    => '',
            'summary'    => [],
        ], $sanitized);

        // 1. Simpan ke Database PostgreSQL (Primary Production)
        try {
            DB::table('satusehat_send_logs')->updateOrInsert(
                ['id' => $entry['id']],
                [
                    'visit_id'      => $visitId,
                    'resource_type' => $entry['resource_type'] ?? ($entry['error_type'] ?? 'Encounter'),
                    'status'        => $entry['status'] ?? 'UNKNOWN',
                    'http_code'     => $entry['http_code'] ?? null,
                    'error_type'    => $entry['error_type'] ?? null,
                    'message'       => $entry['message'] ?? null,
                    'created_count' => (int) ($entry['created_count'] ?? 0),
                    'updated_count' => (int) ($entry['updated_count'] ?? 0),
                    'failed_count'  => (int) ($entry['failed_count'] ?? 0),
                    'resource_ids'  => json_encode($entry['resource_ids'] ?? []),
                    'issues'        => json_encode($entry['issues'] ?? []),
                    'summary'       => json_encode($entry['summary'] ?? []),
                    'created_at'    => $entry['timestamp'] ?? now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::info("DB logSendAttempt fallback: " . $e->getMessage());
        }

        return $this->updateJsonFile($this->logsFile, function (array $logs) use ($entry) {
            // Tambahkan di awal (descending)
            array_unshift($logs, $entry);

            // Batasi log maksimal 1000 entri terbaru untuk menjaga performa
            if (count($logs) > 1000) {
                $logs = array_slice($logs, 0, 1000);
            }

            return $logs;
        });
    }

    /**
     * Mengambil riwayat pengiriman untuk visit tertentu atau seluruh riwayat.
     * @param string|int|null $visitId
     */
    public function getLogs($visitId = null, int $limit = 50): array
    {
        $all = $this->readJsonFile($this->logsFile);
        if ($visitId !== null) {
            $visitId = (string) $visitId;
            $all = array_filter($all, fn($item) => ($item['visit_id'] ?? '') === $visitId);
            $all = array_values($all);
        }
        return array_slice($all, 0, $limit);
    }

    /**
     * Mengambil log pengiriman terakhir untuk visitId tertentu.
     * @param string|int $visitId
     */
    public function getLastLog($visitId): ?array
    {
        $logs = $this->getLogs($visitId, 1);
        return $logs[0] ?? null;
    }

    /**
     * Menyimpan draft koreksi data non-database (tidak mengubah database).
     * @param string|int $visitId
     */
    public function saveDraft($visitId, array $draftFields): bool
    {
        $visitId = (string) $visitId;
        $sanitized = $this->sanitizeData($draftFields);

        return $this->updateJsonFile($this->draftsFile, function (array $drafts) use ($visitId, $sanitized) {
            $drafts[$visitId] = [
                'visit_id'   => $visitId,
                'fields'     => $sanitized,
                'updated_at' => now()->toIso8601String(),
            ];
            return $drafts;
        });
    }

    /**
     * Mengambil draft koreksi data untuk visitId tertentu.
     * @param string|int $visitId
     */
    public function getDraft($visitId): ?array
    {
        $visitId = (string) $visitId;
        $all = $this->readJsonFile($this->draftsFile);
        return $all[$visitId] ?? null;
    }

    /**
     * Menghapus draft koreksi setelah berhasil sinkronisasi.
     * @param string|int $visitId
     */
    public function clearDraft($visitId): bool
    {
        $visitId = (string) $visitId;
        return $this->updateJsonFile($this->draftsFile, function (array $drafts) use ($visitId) {
            unset($drafts[$visitId]);
            return $drafts;
        });
    }

    /**
     * Menyimpan perbaikan data staging (in-memory / file overlay) tanpa mengubah database READ-ONLY.
     * @param string|int $visitId
     * @param string $stagingTable
     * @param string|int $stagingId
     * @param array $fieldUpdates [field_name => ['old' => val, 'new' => val]]
     * @param string $reason
     * @param string $user
     */
    public function saveStagingRepair($visitId, string $resourceType, string $stagingTable, $stagingId, array $fieldUpdates, string $reason, string $user = 'Admin SIMRS'): array
    {
        $visitId = (string) $visitId;
        $stagingId = (string) $stagingId;
        $now = now()->toIso8601String();
        $updatedCount = 0;

        // 1. Simpan ke staging_repairs.json
        $this->updateJsonFile($this->repairsFile, function (array $repairs) use ($visitId, $stagingTable, $stagingId, $fieldUpdates, $resourceType, $now, &$updatedCount) {
            if (!isset($repairs[$visitId])) {
                $repairs[$visitId] = [];
            }
            if (!isset($repairs[$visitId][$stagingTable])) {
                $repairs[$visitId][$stagingTable] = [];
            }
            if (!isset($repairs[$visitId][$stagingTable][$stagingId])) {
                $repairs[$visitId][$stagingTable][$stagingId] = [
                    'resource_type' => $resourceType,
                    'fields'        => [],
                    'updated_at'    => $now,
                ];
            }

            foreach ($fieldUpdates as $field => $change) {
                $newVal = is_array($change) ? ($change['new'] ?? '') : $change;
                $repairs[$visitId][$stagingTable][$stagingId]['fields'][$field] = $newVal;
                $updatedCount++;
            }

            $repairs[$visitId][$stagingTable][$stagingId]['updated_at'] = $now;
            return $repairs;
        });

        // 2. Simpan jejak audit ke repair_history.json
        $this->updateJsonFile($this->historyFile, function (array $histories) use ($visitId, $resourceType, $stagingTable, $stagingId, $fieldUpdates, $reason, $user, $now) {
            foreach ($fieldUpdates as $field => $change) {
                $oldVal = is_array($change) ? ($change['old'] ?? '') : '';
                $newVal = is_array($change) ? ($change['new'] ?? '') : $change;

                $histories[] = [
                    'id'            => count($histories) + 1,
                    'visit_id'      => $visitId,
                    'resource_type' => $resourceType,
                    'staging_table' => $stagingTable,
                    'staging_id'    => $stagingId,
                    'field_name'    => $field,
                    'old_value'     => (string) $oldVal,
                    'new_value'     => (string) $newVal,
                    'reason'        => $reason,
                    'repaired_by'   => $user,
                    'repaired_at'   => $now,
                ];
            }
            return $histories;
        });

        // 3. Bangun usulan query SQL aman untuk diserahkan ke DBA
        $flatValues = [];
        foreach ($fieldUpdates as $field => $change) {
            $flatValues[$field] = is_array($change) ? ($change['new'] ?? '') : $change;
        }
        $proposedSql = $this->generateRepairSql($stagingTable, $stagingId, $flatValues);

        return [
            'success'       => true,
            'updated_count' => $updatedCount,
            'proposed_sql'  => $proposedSql,
        ];
    }

    /**
     * Mengambil perbaikan staging tersimpan untuk visitId tertentu.
     * @param string|int $visitId
     */
    public function getStagingRepairs($visitId, ?string $stagingTable = null, $stagingId = null): array
    {
        $visitId = (string) $visitId;
        $all = $this->readJsonFile($this->repairsFile);
        $visitRepairs = $all[$visitId] ?? [];

        if ($stagingTable !== null) {
            $tableRepairs = $visitRepairs[$stagingTable] ?? [];
            if ($stagingId !== null) {
                return $tableRepairs[(string) $stagingId]['fields'] ?? [];
            }
            return $tableRepairs;
        }

        return $visitRepairs;
    }

    /**
     * Menghapus perbaikan staging untuk visitId tertentu (misalnya setelah sukses dikirim).
     * @param string|int $visitId
     */
    public function clearStagingRepairs($visitId): bool
    {
        $visitId = (string) $visitId;
        return $this->updateJsonFile($this->repairsFile, function (array $repairs) use ($visitId) {
            unset($repairs[$visitId]);
            return $repairs;
        });
    }

    /**
     * Mengambil riwayat perbaikan (audit trail).
     * @param string|int|null $visitId
     */
    public function getRepairHistories($visitId = null, ?string $resourceType = null): array
    {
        $histories = $this->readJsonFile($this->historyFile);
        if ($visitId !== null) {
            $visitId = (string) $visitId;
            $histories = array_filter($histories, function ($h) use ($visitId) {
                return (string) ($h['visit_id'] ?? '') === $visitId;
            });
        }
        if ($resourceType !== null) {
            $histories = array_filter($histories, function ($h) use ($resourceType) {
                return strcasecmp($h['resource_type'] ?? '', $resourceType) === 0;
            });
        }

        return array_values(array_reverse($histories));
    }

    /**
     * Membentuk usulan SQL UPDATE secara read-only (tidak dieksekusi).
     */
    public function generateRepairSql(string $stagingTable, $stagingId, array $fieldUpdates, string $pk = 'id'): string
    {
        if (empty($fieldUpdates)) {
            return '-- Tidak ada perubahan data.';
        }

        $sets = [];
        foreach ($fieldUpdates as $col => $val) {
            $cleanCol = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            if ($val === null) {
                $sets[] = "{$cleanCol} = NULL";
            } elseif (is_numeric($val) && !is_string($val)) {
                $sets[] = "{$cleanCol} = {$val}";
            } else {
                $escapedVal = str_replace("'", "''", (string) $val);
                $sets[] = "{$cleanCol} = '{$escapedVal}'";
            }
        }

        $cleanTable = preg_replace('/[^a-zA-Z0-9_\.]/', '', $stagingTable);
        $cleanId = is_numeric($stagingId) ? (int) $stagingId : "'" . str_replace("'", "''", $stagingId) . "'";
        $sql = "UPDATE {$cleanTable}\nSET " . implode(",\n    ", $sets) . "\nWHERE {$pk} = {$cleanId};";

        return "-- USULAN SQL UNTUK DBA (ATURAN READ-ONLY: JANGAN JALANKAN SECARA OTOMATIS)\n" . $sql;
    }

    /**
     * Mengambil seluruh log pengiriman yang FAILED untuk Failed Center.
     */
    public function getFailedLogs(?string $resourceType = null, ?string $startDate = null, ?string $endDate = null, ?string $keyword = null): array
    {
        // 1. Coba baca dari Database PostgreSQL (Primary Production)
        try {
            $query = DB::table('satusehat_send_logs')
                ->whereIn('status', ['FAILED', 'EXCEPTION', 'TIMEOUT']);

            if ($resourceType && $resourceType !== 'all') {
                $query->where('resource_type', $resourceType);
            }

            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            if ($keyword) {
                $kw = strtolower($keyword);
                $query->where(function ($q) use ($kw) {
                    $q->where('visit_id', 'ilike', "%{$kw}%")
                      ->orWhere('id', 'ilike', "%{$kw}%")
                      ->orWhere('message', 'ilike', "%{$kw}%")
                      ->orWhere('resource_type', 'ilike', "%{$kw}%");
                });
            }

            $dbLogs = $query->orderBy('created_at', 'desc')->get();
            if ($dbLogs->isNotEmpty()) {
                return $dbLogs->map(function ($item) {
                    $arr = (array) $item;
                    $arr['log_id'] = $arr['id'];
                    $arr['error_message'] = $arr['message'];
                    $arr['http_status'] = $arr['http_code'];
                    $arr['timestamp'] = $arr['created_at'];
                    $arr['resource_ids'] = is_string($arr['resource_ids']) ? json_decode($arr['resource_ids'], true) : (array) $arr['resource_ids'];
                    $arr['issues'] = is_string($arr['issues']) ? json_decode($arr['issues'], true) : (array) $arr['issues'];
                    $arr['summary'] = is_string($arr['summary']) ? json_decode($arr['summary'], true) : (array) $arr['summary'];
                    return $arr;
                })->toArray();
            }
        } catch (\Throwable $e) {}

        // 2. Fallback baca JSON File
        $allLogs = $this->readJsonFile($this->logsFile);
        $failed = [];

        foreach ($allLogs as $index => $item) {
            if (!is_array($item)) continue;
            $status = strtoupper($item['status'] ?? '');
            if (!in_array($status, ['FAILED', 'EXCEPTION', 'TIMEOUT'], true)) {
                continue;
            }

            $visitId = (string) ($item['visit_id'] ?? '');
            $logId = (string) ($item['id'] ?? "log_{$index}");
            $itemResourceType = $item['resource_type'] ?? ($item['error_type'] ?? 'Encounter');

            if ($resourceType && $resourceType !== 'all' && strcasecmp($itemResourceType, $resourceType) !== 0) {
                continue;
            }

            $loggedAt = $item['timestamp'] ?? ($item['logged_at'] ?? ($item['created_at'] ?? null));
            if ($startDate && $loggedAt && substr($loggedAt, 0, 10) < $startDate) {
                continue;
            }
            if ($endDate && $loggedAt && substr($loggedAt, 0, 10) > $endDate) {
                continue;
            }

            if ($keyword) {
                $haystack = strtolower($visitId . ' ' . $logId . ' ' . ($item['message'] ?? '') . ' ' . $itemResourceType);
                if (!str_contains($haystack, strtolower($keyword))) {
                    continue;
                }
            }

            $failed[] = array_merge($item, [
                'id'            => $logId,
                'log_id'        => $logId,
                'visit_id'      => $visitId,
                'resource_type' => $itemResourceType,
                'error_message' => $item['message'] ?? 'Unknown Error',
                'http_status'   => $item['http_code'] ?? ($item['http_status'] ?? 500),
                'retry_count'   => $item['retry_count'] ?? 0,
                'created_at'    => $loggedAt ?? now()->toDateTimeString(),
            ]);
        }

        // Urutkan dari yang terbaru
        usort($failed, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        return $failed;
    }

    /**
     * Lock pengiriman concurrency untuk mencegah double-send bersamaan pada visitId yang sama.
     * @param string|int $visitId
     */
    public function acquireLock($visitId, int $timeoutSeconds = 15): bool
    {
        $visitId = (string) $visitId;
        $lockFile = $this->locksDir . DIRECTORY_SEPARATOR . "send_{$visitId}.lock";

        $fp = @fopen($lockFile, 'c+');
        if (!$fp) {
            return false;
        }

        $startTime = time();
        while (!flock($fp, LOCK_EX | LOCK_NB)) {
            if (time() - $startTime >= $timeoutSeconds) {
                fclose($fp);
                return false;
            }
            usleep(200000); // 200ms
        }

        ftruncate($fp, 0);
        fwrite($fp, json_encode([
            'pid'        => getmypid(),
            'locked_at'  => now()->toIso8601String(),
            'visit_id'   => $visitId,
        ]));
        fflush($fp);

        $this->activeFilePointers[$visitId] = $fp;
        return true;
    }

    /**
     * Melepas lock pengiriman untuk visitId.
     * @param string|int $visitId
     */
    public function releaseLock($visitId): void
    {
        $visitId = (string) $visitId;
        if (isset($this->activeFilePointers[$visitId])) {
            $fp = $this->activeFilePointers[$visitId];
            flock($fp, LOCK_UN);
            fclose($fp);
            unset($this->activeFilePointers[$visitId]);
        }

        $lockFile = $this->locksDir . DIRECTORY_SEPARATOR . "send_{$visitId}.lock";
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
    }

    /**
     * Membaca file JSON dengan Shared Lock (LOCK_SH).
     */
    protected function readJsonFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $fp = @fopen($filePath, 'r');
        if (!$fp) {
            return [];
        }

        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if (empty($content)) {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Mengupdate file JSON secara atomik dengan Exclusive Lock (LOCK_EX).
     */
    protected function updateJsonFile(string $filePath, callable $modifier): bool
    {
        $this->ensureDirectories();

        $fp = @fopen($filePath, 'c+');
        if (!$fp) {
            Log::error("SatuSehatStorageService: Gagal membuka file $filePath");
            return false;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            Log::error("SatuSehatStorageService: Gagal mendapatkan lock pada $filePath");
            return false;
        }

        $raw = stream_get_contents($fp);
        $data = !empty($raw) ? json_decode($raw, true) : [];
        if (!is_array($data)) {
            $data = [];
        }

        try {
            $updatedData = $modifier($data);
            if (!is_array($updatedData)) {
                $updatedData = [];
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($updatedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            fflush($fp);
            $success = true;
        } catch (\Throwable $e) {
            Log::error("SatuSehatStorageService Exception: " . $e->getMessage());
            $success = false;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return $success;
    }

    /**
     * Sanitasi data untuk mencegah kebocoran kredensial, token, atau informasi sensitif.
     * @param mixed $data
     * @return mixed
     */
    public function sanitizeData($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $sensitiveKeys = [
            'client_secret', 'secret_key', 'access_token', 'token',
            'authorization', 'password', 'passphrase', 'api_key'
        ];

        $sanitized = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string)$key);
            if (in_array($lowerKey, $sensitiveKeys, true)) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
