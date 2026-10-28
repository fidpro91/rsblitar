<?php

namespace App\ServicesBundle;

use App\Libraries\SatuSehat\AllergyBundle;
use App\Libraries\SatuSehat\EncounterBundle;
use App\Libraries\SatuSehat\ConditionBundle;
use App\Libraries\SatuSehat\ObservationBundle;
use App\Libraries\SatuSehat\CompositionBundle;
use App\Libraries\SatuSehat\MedicationBundle;
use App\Libraries\SatuSehat\QuisionerResponsBundle;
use App\Libraries\SatuSehat\ServiceRequestLabBundle;
use App\Libraries\SatuSehat\CarePlanBundle;
use App\Libraries\SatuSehat\ClinicalImpressionBundle;
use App\Libraries\SatuSehat\EpisodeOfCareBundle;
use App\Libraries\SatuSehat\ProsedureBundle;
use App\Libraries\SatuSehat\RadiologiBundle;
use App\Libraries\SatuSehatService;
use App\Libraries\SatuSehatStorageService;
use App\Models\Respon_satusehat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendAllSatusehatService
{
    protected SatuSehatService $satusehat;
    protected SatuSehatStorageService $storage;

    public function __construct()
    {
        $this->satusehat = new SatuSehatService();
        $this->storage   = new SatuSehatStorageService();
    }

    /**
     * Membangun payload bundle FHIR in-memory untuk preview dan validasi sebelum dikirim.
     *
     * @param string|int $visitId
     * @return array
     */
    public function buildBundlePreview($visitId): array
    {
        $builders = [
            EncounterBundle::class,
            ConditionBundle::class,
            ObservationBundle::class,
            AllergyBundle::class,
            CompositionBundle::class,
            ServiceRequestLabBundle::class,
            MedicationBundle::class,
            QuisionerResponsBundle::class,
            CarePlanBundle::class,
            ClinicalImpressionBundle::class,
            EpisodeOfCareBundle::class,
            ProsedureBundle::class,
            RadiologiBundle::class
        ];

        $entries = [];
        foreach ($builders as $builderClass) {
            $entries = array_merge($entries, $this->safeBuild([$builderClass, 'build'], $visitId));
        }

        $entries = array_filter($entries, fn($e) => !empty($e));
        $entries = array_values($entries);

        $combinedBundle = [
            "resourceType" => "Bundle",
            "type"         => "transaction",
            "entry"        => $entries
        ];

        return [
            'bundle'        => $combinedBundle,
            'entries_count' => count($entries),
        ];
    }

    /**
     
     *
     * @param string|int $visitId
     * @return array
     */
    public function prepareAndSendBundle($visitId): array
    {
        $visitId = (string) $visitId;
        $url = "https://api-satusehat.kemkes.go.id/fhir-r4/v1/";

        $builders = [
            EncounterBundle::class,
            ConditionBundle::class,
            ObservationBundle::class,
            AllergyBundle::class,
            CompositionBundle::class,
            ServiceRequestLabBundle::class,
            MedicationBundle::class,
            QuisionerResponsBundle::class,
            CarePlanBundle::class,
            ClinicalImpressionBundle::class,
            EpisodeOfCareBundle::class,
            ProsedureBundle::class,
            RadiologiBundle::class
        ];

        try {
            $entries = [];
            foreach ($builders as $builderClass) {
                $entries = array_merge($entries, $this->safeBuild([$builderClass, 'build'], $visitId));
            }

            $entries = array_filter($entries, fn($e) => !empty($e));
            $entries = array_values($entries);

            if (empty($entries)) {
                throw new \Exception('Tidak ada entry bundle untuk visitId ' . $visitId);
            }

            $combinedBundle = [
                "resourceType" => "Bundle",
                "type"         => "transaction",
                "entry"        => $entries
            ];

            // Kirim transaksi Bundle ke SATUSEHAT API
            $response = $this->satusehat->connect('post', $url, $combinedBundle);

            // Ekstraksi referensi pasien (Patient/{id})
            $pasienId = null;
            if (!empty($entries)) {
                foreach ($entries as $entry) {
                    if (isset($entry['resource']['resourceType']) && $entry['resource']['resourceType'] === 'Encounter') {
                        $pasienId = $entry['resource']['subject']['reference'] ?? null;
                        break;
                    }
                }
                if ($pasienId === null && isset($entries[0]['resource']['subject']['reference'])) {
                    $pasienId = $entries[0]['resource']['subject']['reference'];
                }
            }

            $httpStatus         = (int) ($response['_http_status'] ?? 200);
            $isHttpSuccess      = $response['_success'] ?? ($httpStatus >= 200 && $httpStatus < 300);
            $isOperationOutcome = (($response['resourceType'] ?? '') === 'OperationOutcome');

            $issues = [];
            if (isset($response['issue']) && is_array($response['issue'])) {
                foreach ($response['issue'] as $iss) {
                    $diag = $iss['diagnostics'] ?? $iss['details']['text'] ?? null;
                    if ($diag) {
                        $issues[] = $diag;
                    }
                }
            }

            $capturedIds = [];
            $hasEntryErrors = false;

            // 1. Simpan setiap resource response ke respon_satusehat & satusehat_resource_ids
            if (isset($response['entry']) && is_array($response['entry'])) {
                foreach ($response['entry'] as $item) {
                    if (!isset($item['response'])) continue;

                    $resp = $item['response'];
                    $respStatus = $resp['status'] ?? '';

                    if (preg_match('/^[45]\d\d/', $respStatus)) {
                        $hasEntryErrors = true;
                        if (isset($resp['outcome']['issue']) && is_array($resp['outcome']['issue'])) {
                            foreach ($resp['outcome']['issue'] as $iss) {
                                $diag = $iss['diagnostics'] ?? $iss['details']['text'] ?? null;
                                if ($diag) $issues[] = $diag;
                            }
                        }
                    }

                    $location     = $resp['location'] ?? '';
                    $resourceType = $resp['resourceType'] ?? null;
                    $resourceId   = $resp['resourceID'] ?? ($resp['id'] ?? null);

                    // Fallback ekstraksi resourceType & ID dari header location jika tidak disertakan di root
                    if ((empty($resourceType) || empty($resourceId)) && !empty($location)) {
                        $path = parse_url($location, PHP_URL_PATH) ?: $location;
                        $path = preg_replace('#/_history/.*$#', '', trim($path, '/'));
                        if (preg_match('#([A-Za-z]+)/([A-Za-z0-9\-\.]+)$#', $path, $m)) {
                            $resourceType = $resourceType ?: $m[1];
                            $resourceId   = $resourceId ?: $m[2];
                        }
                    }

                    if ($resourceType && $resourceId) {
                        $capturedIds[$resourceType] = $resourceId;
                        $this->storage->saveResourceId($visitId, $resourceType, $resourceId, ['location' => $location]);
                    }

                    // Simpan respon_satusehat
                    try {
                        Respon_satusehat::create([
                            'status'        => $resp['status'] ?? 'unknown',
                            'resourcetype'  => $resourceType ?? ($resp['resourceType'] ?? null),
                            'resourceid'    => $resourceId,
                            'metode'        => 'POST',
                            'tgl_kirim'     => now(),
                            'pasien_id'     => $pasienId,
                            'visit_id'      => $visitId,
                            'respon_all'    => $resp,
                        ]);
                    } catch (\Throwable $e) {
                        Log::info("Respon_satusehat create entry fallback: " . $e->getMessage());
                    }
                }
            } else {
                try {
                    Respon_satusehat::create([
                        'status'        => $isHttpSuccess ? 'success' : 'error',
                        'resourcetype'  => $response['resourceType'] ?? 'BundleResponse',
                        'resourceid'    => $response['id'] ?? null,
                        'metode'        => 'POST',
                        'tgl_kirim'     => now(),
                        'pasien_id'     => $pasienId,
                        'visit_id'      => $visitId,
                        'respon_all'    => $response,
                    ]);
                } catch (\Throwable $e) {
                    Log::info("Respon_satusehat create fallback: " . $e->getMessage());
                }
            }

            $success = $isHttpSuccess && !$isOperationOutcome && !$hasEntryErrors && !empty($capturedIds);
            $errorMessage = !empty($issues) ? implode('; ', $issues) : ($response['message'] ?? $response['error'] ?? 'Pengiriman bundle gagal');

            // 2. Jika Berhasil
            if ($success) {
                // Update visit_encounter
                try {
                    $updateData = [
                        'bundle_status' => 'SUCCESS',
                        'is_send'       => true,
                        'last_error'    => null,
                    ];
                    if (isset($capturedIds['Encounter'])) {
                        $updateData['uuid_encounter'] = $capturedIds['Encounter'];
                    }
                    if (isset($capturedIds['EpisodeOfCare'])) {
                        $updateData['uuid_episodeofcare'] = $capturedIds['EpisodeOfCare'];
                    }
                    DB::table('visit_encounter')->where('visit_id', $visitId)->update($updateData);
                } catch (\Throwable $e) {
                    Log::info("DB update visit_encounter success fallback: " . $e->getMessage());
                }

                // Log pengiriman sukses ke satusehat_send_logs
                $this->storage->logSendAttempt($visitId, [
                    'status'        => 'SUCCESS',
                    'http_code'     => $httpStatus,
                    'error_type'    => null,
                    'message'       => 'Bundle berhasil dikirim ke SATUSEHAT.',
                    'resource_type' => 'Bundle',
                    'resource_ids'  => $capturedIds,
                    'issues'        => [],
                    'created_count' => count($capturedIds),
                ]);

                // Audit log ke simrs_error_logs
                $this->logActivity('SATUSEHAT', (int) $visitId, 'success', [
                    'step' => 'prepareAndSendBundle',
                    'raw'  => $response,
                ]);

                return [
                    'success'       => true,
                    'status'        => 'SUCCESS',
                    'message'       => 'Bundle berhasil dikirim ke SATUSEHAT.',
                    'captured_ids'  => $capturedIds,
                    'issues'        => [],
                    'created_count' => count($capturedIds),
                    'data'          => $response,
                ];
            } else {
                // 3. Jika Gagal
                try {
                    DB::table('visit_encounter')->where('visit_id', $visitId)->update([
                        'bundle_status' => 'FAILED',
                        'last_error'    => $errorMessage,
                        'retry_count'   => DB::raw('COALESCE(retry_count, 0) + 1'),
                    ]);
                } catch (\Throwable $e) {
                    Log::info("DB update visit_encounter failed fallback: " . $e->getMessage());
                }

                $errorType = $response['error_type'] ?? ($isOperationOutcome ? 'FHIR_VALIDATION_ERROR' : 'API_ERROR');

                // Log kegagalan ke satusehat_send_logs
                $this->storage->logSendAttempt($visitId, [
                    'status'        => 'FAILED',
                    'http_code'     => $httpStatus,
                    'error_type'    => $errorType,
                    'message'       => $errorMessage,
                    'resource_type' => 'Bundle',
                    'resource_ids'  => $capturedIds,
                    'issues'        => $issues,
                    'failed_count'  => count($entries),
                ]);

                // Audit log ke simrs_error_logs
                $this->logActivity('SATUSEHAT', (int) $visitId, 'error', [
                    'step'   => 'prepareAndSendBundle_failed',
                    'detail' => $errorMessage,
                    'raw'    => $response,
                ]);

                return [
                    'success'      => false,
                    'status'       => 'FAILED',
                    'http_status'  => $httpStatus,
                    'message'      => $errorMessage,
                    'error_type'   => $errorType,
                    'issues'       => $issues,
                    'captured_ids' => $capturedIds,
                    'data'         => $response,
                ];
            }
        } catch (\Throwable $e) {
            // 4. Jika terjadi Exception tak terduga
            try {
                $this->storage->logSendAttempt($visitId, [
                    'status'        => 'FAILED',
                    'http_code'     => 500,
                    'error_type'    => 'EXCEPTION',
                    'message'       => $e->getMessage(),
                    'resource_type' => 'Bundle',
                    'issues'        => [$e->getMessage()],
                ]);

                DB::table('visit_encounter')->where('visit_id', $visitId)->update([
                    'bundle_status' => 'FAILED',
                    'last_error'    => $e->getMessage(),
                    'retry_count'   => DB::raw('COALESCE(retry_count, 0) + 1'),
                ]);
            } catch (\Throwable $ex) {
                Log::info("DB exception log fallback: " . $ex->getMessage());
            }

            try {
                Respon_satusehat::create([
                    'status'        => 'Exception',
                    'resourcetype'  => 'Exception',
                    'resourceid'    => null,
                    'metode'        => 'POST',
                    'tgl_kirim'     => now(),
                    'visit_id'      => $visitId,
                    'respon_all'    => ['error' => $e->getMessage()],
                ]);
            } catch (\Throwable $ex) {
                Log::info("Respon_satusehat exception create fallback: " . $ex->getMessage());
            }

            $this->logActivity('SATUSEHAT', (int) $visitId, 'error', [
                'step' => 'prepareAndSendBundle_exception',
            ], $e);

            return [
                'success'     => false,
                'status'      => 'FAILED',
                'http_status' => 500,
                'message'     => 'Exception saat mengirim bundle: ' . $e->getMessage(),
                'error_type'  => 'EXCEPTION',
                'issues'      => [$e->getMessage()],
                'error'       => $e->getMessage(),
            ];
        }
    }

    /**
     * Membangun entri bundle secara aman dengan error suppression per modul.
     */
    private function safeBuild(callable $builderCallable, $visitId): array
    {
        try {
            $result = call_user_func($builderCallable, $visitId);
            return $this->cekBundle($result);
        } catch (\Throwable $e) {
            Log::info("safeBuild exception: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Validasi dan normalisasi entri resource bundle.
     */
    private function cekBundle($bundle): array
    {
        if ($bundle === null || $bundle === false) return [];

        if (is_object($bundle)) {
            $bundle = method_exists($bundle, 'toArray') ? $bundle->toArray() : (array) $bundle;
        }

        if (is_array($bundle) && isset($bundle[0]) && is_array($bundle[0])) {
            return $bundle;
        }

        if (is_array($bundle) && (isset($bundle['resource']) || isset($bundle['resourceType']))) {
            if (isset($bundle['resourceType']) && $bundle['resourceType'] === 'Bundle' && isset($bundle['entry']) && is_array($bundle['entry'])) {
                return $bundle['entry'];
            }
            if (isset($bundle['resource']) || isset($bundle['resourceType'])) {
                return [$bundle];
            }
        }

        return [];
    }

    /**
     * Pencatatan riwayat aktivitas ke tabel simrs_error_logs.
     */
    private function logActivity(
        string $type,
        ?int $visitId,
        string $status,
        array $payload = [],
        ?\Throwable $e = null
    ): void {
        try {
            DB::table('simrs_error_logs')->insert([
                'type'       => $type,
                'visit_id'   => $visitId,
                'message'    => $status === 'error' ? ($e ? $e->getMessage() : ($payload['detail'] ?? 'ERROR')) : 'SUCCESS',
                'file'       => ($status === 'error' && $e) ? $e->getFile() : null,
                'line'       => ($status === 'error' && $e) ? $e->getLine() : null,
                'trace'      => ($status === 'error' && $e) ? $e->getTraceAsString() : null,
                'payload'    => json_encode($payload),
                'status'     => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $logError) {
            Log::info("logActivity fallback: " . $logError->getMessage());
        }
    }
}
