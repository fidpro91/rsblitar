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
        $this->storage = new SatuSehatStorageService();
    }

    /**
     * Daftar builder resource yang digunakan.
     */
    private function getBuilders(): array
    {
        return [
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
            RadiologiBundle::class,
        ];
    }

    /**
     * Preview bundle.
     */
    public function buildBundlePreview($visitId): array
    {
        $entries = [];

        foreach ($this->getBuilders() as $builderClass) {
            $entries = array_merge(
                $entries,
                $this->safeBuild([$builderClass, 'build'], $visitId)
            );
        }

        $entries = array_values(array_filter($entries));

        return [
            'bundle' => [
                'resourceType' => 'Bundle',
                'type' => 'transaction',
                'entry' => $entries,
            ],
            'entries_count' => count($entries),
        ];
    }

    /**
     * Proses membangun dan mengirim seluruh bundle.
     *
     * Status database tetap di-update pada kondisi:
     * - Sukses
     * - Gagal dari API
     * - Exception saat proses
     */
    public function prepareAndSendBundle($visitId): array
    {
        $visitId = (string) $visitId;

        $url = 'https://api-satusehat.kemkes.go.id/fhir-r4/v1/';

        $entries = [];
        $response = [];
        $capturedIds = [];
        $httpStatus = 500;
        $issues = [];

        try {

            /*
             * 1. BUILD SELURUH RESOURCE
             */
            foreach ($this->getBuilders() as $builderClass) {
                $entries = array_merge(
                    $entries,
                    $this->safeBuild([$builderClass, 'build'], $visitId)
                );
            }

            $entries = array_values(array_filter($entries));

            if (empty($entries)) {
                throw new \Exception(
                    'Tidak ada entry bundle untuk visit_id ' . $visitId
                );
            }

            $combinedBundle = [
                'resourceType' => 'Bundle',
                'type' => 'transaction',
                'entry' => $entries,
            ];

            /*
             * 2. KIRIM KE SATUSEHAT
             */
            $response = $this->satusehat->connect(
                'post',
                $url,
                $combinedBundle
            );

            if (!is_array($response)) {
                throw new \Exception(
                    'Response SATUSEHAT tidak berbentuk array.'
                );
            }

            $httpStatus = (int) (
                $response['_http_status'] ?? 200
            );

            $isHttpSuccess = $response['_success']
                ?? ($httpStatus >= 200 && $httpStatus < 300);

            $isOperationOutcome = (
                ($response['resourceType'] ?? '') === 'OperationOutcome'
            );

            /*
             * 3. AMBIL REFERENSI PASIEN
             */
            $pasienId = null;

            foreach ($entries as $entry) {
                if (
                    isset($entry['resource']['resourceType'])
                    && $entry['resource']['resourceType'] === 'Encounter'
                ) {
                    $pasienId = $entry['resource']['subject']['reference']
                        ?? null;

                    break;
                }
            }

            if (
                $pasienId === null
                && isset($entries[0]['resource']['subject']['reference'])
            ) {
                $pasienId = $entries[0]['resource']['subject']['reference'];
            }

            /*
             * 4. AMBIL ERROR DARI RESPONSE
             */
            if (
                isset($response['issue'])
                && is_array($response['issue'])
            ) {
                foreach ($response['issue'] as $iss) {
                    $diag = $iss['diagnostics']
                        ?? $iss['details']['text']
                        ?? null;

                    if ($diag) {
                        $issues[] = $diag;
                    }
                }
            }

            /*
             * 5. BACA RESPONSE SETIAP RESOURCE
             */
            $hasEntryErrors = false;

            if (
                isset($response['entry'])
                && is_array($response['entry'])
            ) {
                foreach ($response['entry'] as $item) {

                    if (!isset($item['response'])) {
                        continue;
                    }

                    $resp = $item['response'];

                    $respStatus = (string) (
                        $resp['status'] ?? ''
                    );

                    /*
                     * Deteksi status 4xx dan 5xx.
                     */
                    if (preg_match('/^[45]\d\d/', $respStatus)) {

                        $hasEntryErrors = true;

                        if (
                            isset($resp['outcome']['issue'])
                            && is_array($resp['outcome']['issue'])
                        ) {
                            foreach ($resp['outcome']['issue'] as $iss) {
                                $diag = $iss['diagnostics']
                                    ?? $iss['details']['text']
                                    ?? null;

                                if ($diag) {
                                    $issues[] = $diag;
                                }
                            }
                        }
                    }

                    /*
                     * Ambil ID resource.
                     */
                    $location = $resp['location'] ?? '';

                    $resourceType = $resp['resourceType'] ?? null;

                    $resourceId = $resp['resourceID']
                        ?? $resp['id']
                        ?? null;

                    /*
                     * Fallback ambil ID dari location.
                     */
                    if (
                        (empty($resourceType) || empty($resourceId))
                        && !empty($location)
                    ) {
                        $path = parse_url(
                            $location,
                            PHP_URL_PATH
                        ) ?: $location;

                        $path = preg_replace(
                            '#/_history/.*$#',
                            '',
                            trim($path, '/')
                        );

                        if (
                            preg_match(
                                '#([A-Za-z]+)/([A-Za-z0-9\-\.]+)$#',
                                $path,
                                $m
                            )
                        ) {
                            $resourceType = $resourceType ?: $m[1];
                            $resourceId = $resourceId ?: $m[2];
                        }
                    }

                    /*
                     * Simpan ID resource.
                     */
                    if ($resourceType && $resourceId) {

                        $capturedIds[$resourceType] = $resourceId;

                        try {
                            $this->storage->saveResourceId(
                                $visitId,
                                $resourceType,
                                $resourceId,
                                ['location' => $location]
                            );
                        } catch (\Throwable $storageError) {
                            Log::error(
                                'Gagal menyimpan resource ID SATUSEHAT',
                                [
                                    'visit_id' => $visitId,
                                    'resource_type' => $resourceType,
                                    'resource_id' => $resourceId,
                                    'error' => $storageError->getMessage(),
                                ]
                            );
                        }
                    }

                    /*
                     * Simpan response per resource.
                     */
                    try {
                        Respon_satusehat::create([
                            'status' => $resp['status'] ?? 'unknown',
                            'resourcetype' => $resourceType
                                ?? ($resp['resourceType'] ?? null),
                            'resourceid' => $resourceId,
                            'metode' => 'POST',
                            'tgl_kirim' => now(),
                            'pasien_id' => $pasienId,
                            'visit_id' => $visitId,
                            'respon_all' => $resp,
                        ]);
                    } catch (\Throwable $responseError) {
                        Log::error(
                            'Gagal menyimpan response resource',
                            [
                                'visit_id' => $visitId,
                                'error' => $responseError->getMessage(),
                            ]
                        );
                    }
                }
            } else {

                /*
                 * Jika response tidak memiliki entry.
                 */
                $this->saveResponse([
                    'status' => $isHttpSuccess ? 'success' : 'error',
                    'resourcetype' => $response['resourceType']
                        ?? 'BundleResponse',
                    'resourceid' => $response['id'] ?? null,
                    'metode' => 'POST',
                    'tgl_kirim' => now(),
                    'pasien_id' => $pasienId,
                    'visit_id' => $visitId,
                    'respon_all' => $response,
                ]);
            }

            /*
             * 6. TENTUKAN HASIL PENGIRIMAN
             */
            $success = (
                $isHttpSuccess
                && !$isOperationOutcome
                && !$hasEntryErrors
                && !empty($capturedIds)
            );

            $errorMessage = !empty($issues)
                ? implode('; ', array_unique($issues))
                : (
                    $response['message']
                    ?? $response['error']
                    ?? 'Pengiriman bundle gagal.'
                );

            /*
             * 7. JIKA SUKSES
             */
            if ($success) {

                $updateData = [
                    'bundle_status' => 'SUCCESS',
                    'is_send' => true,
                    'last_error' => null,
                ];

                /*
                 * Simpan UUID Encounter.
                 */
                if (!empty($capturedIds['Encounter'])) {
                    $updateData['uuid_encounter'] =
                        $capturedIds['Encounter'];
                }

                /*
                 * Simpan UUID EpisodeOfCare.
                 */
                if (!empty($capturedIds['EpisodeOfCare'])) {
                    $updateData['uuid_episodeofcare'] =
                        $capturedIds['EpisodeOfCare'];
                }

                /*
                 * UPDATE DATABASE.
                 */
                $dbUpdated = $this->updateVisitEncounter(
                    $visitId,
                    $updateData
                );

                /*
                 * Log pengiriman sukses.
                 */
                try {
                    $this->storage->logSendAttempt($visitId, [
                        'status' => 'SUCCESS',
                        'http_code' => $httpStatus,
                        'error_type' => null,
                        'message' => 'Bundle berhasil dikirim ke SATUSEHAT.',
                        'resource_type' => 'Bundle',
                        'resource_ids' => $capturedIds,
                        'issues' => [],
                        'created_count' => count($capturedIds),
                    ]);
                } catch (\Throwable $logError) {
                    Log::error(
                        'Gagal menyimpan log pengiriman sukses',
                        [
                            'visit_id' => $visitId,
                            'error' => $logError->getMessage(),
                        ]
                    );
                }

                /*
                 * Audit log.
                 */
                $this->logActivity(
                    'SATUSEHAT',
                    (int) $visitId,
                    'success',
                    [
                        'step' => 'prepareAndSendBundle',
                        'raw' => $response,
                        'captured_ids' => $capturedIds,
                        'db_updated' => $dbUpdated,
                    ]
                );

                return [
                    'success' => true,
                    'status' => 'SUCCESS',
                    'db_updated' => $dbUpdated,
                    'message' => $dbUpdated
                        ? 'Bundle berhasil dikirim dan database berhasil diperbarui.'
                        : 'Bundle berhasil dikirim, tetapi update database gagal.',
                    'captured_ids' => $capturedIds,
                    'issues' => [],
                    'created_count' => count($capturedIds),
                    'data' => $response,
                ];
            }

            /*
             * 8. JIKA API GAGAL
             */
            $errorType = $response['error_type']
                ?? (
                    $isOperationOutcome
                        ? 'FHIR_VALIDATION_ERROR'
                        : 'API_ERROR'
                );

            /*
             * UPDATE DATABASE GAGAL.
             */
            $dbUpdated = $this->updateVisitEncounter(
                $visitId,
                [
                    'bundle_status' => 'FAILED',
                    'is_send' => false,
                    'last_error' => $errorMessage,
                    'retry_count' => DB::raw(
                        'COALESCE(retry_count, 0) + 1'
                    ),
                ]
            );

            /*
             * Log pengiriman gagal.
             */
            try {
                $this->storage->logSendAttempt($visitId, [
                    'status' => 'FAILED',
                    'http_code' => $httpStatus,
                    'error_type' => $errorType,
                    'message' => $errorMessage,
                    'resource_type' => 'Bundle',
                    'resource_ids' => $capturedIds,
                    'issues' => $issues,
                    'failed_count' => count($entries),
                ]);
            } catch (\Throwable $logError) {
                Log::error(
                    'Gagal menyimpan log pengiriman gagal',
                    [
                        'visit_id' => $visitId,
                        'error' => $logError->getMessage(),
                    ]
                );
            }

            /*
             * Audit log.
             */
            $this->logActivity(
                'SATUSEHAT',
                (int) $visitId,
                'error',
                [
                    'step' => 'prepareAndSendBundle_failed',
                    'detail' => $errorMessage,
                    'raw' => $response,
                    'captured_ids' => $capturedIds,
                    'db_updated' => $dbUpdated,
                ]
            );

            return [
                'success' => false,
                'status' => 'FAILED',
                'db_updated' => $dbUpdated,
                'http_status' => $httpStatus,
                'message' => $errorMessage,
                'error_type' => $errorType,
                'issues' => $issues,
                'captured_ids' => $capturedIds,
                'data' => $response,
            ];

        } catch (\Throwable $e) {

            /*
             * 9. JIKA TERJADI EXCEPTION
             */
            $errorMessage = $e->getMessage();

            Log::error(
                'Exception prepareAndSendBundle',
                [
                    'visit_id' => $visitId,
                    'error' => $errorMessage,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            /*
             * UPDATE DATABASE SAAT EXCEPTION.
             */
            $dbUpdated = $this->updateVisitEncounter(
                $visitId,
                [
                    'bundle_status' => 'FAILED',
                    'is_send' => false,
                    'last_error' => $errorMessage,
                    'retry_count' => DB::raw(
                        'COALESCE(retry_count, 0) + 1'
                    ),
                ]
            );

            /*
             * Simpan log pengiriman.
             */
            try {
                $this->storage->logSendAttempt($visitId, [
                    'status' => 'FAILED',
                    'http_code' => $httpStatus,
                    'error_type' => 'EXCEPTION',
                    'message' => $errorMessage,
                    'resource_type' => 'Bundle',
                    'resource_ids' => $capturedIds,
                    'issues' => [$errorMessage],
                ]);
            } catch (\Throwable $logError) {
                Log::error(
                    'Gagal menyimpan log exception',
                    [
                        'visit_id' => $visitId,
                        'error' => $logError->getMessage(),
                    ]
                );
            }

            /*
             * Simpan response exception.
             */
            $this->saveResponse([
                'status' => 'Exception',
                'resourcetype' => 'Exception',
                'resourceid' => null,
                'metode' => 'POST',
                'tgl_kirim' => now(),
                'visit_id' => $visitId,
                'respon_all' => [
                    'error' => $errorMessage,
                ],
            ]);

            /*
             * Audit log.
             */
            $this->logActivity(
                'SATUSEHAT',
                (int) $visitId,
                'error',
                [
                    'step' => 'prepareAndSendBundle_exception',
                    'detail' => $errorMessage,
                    'db_updated' => $dbUpdated,
                ],
                $e
            );

            return [
                'success' => false,
                'status' => 'FAILED',
                'db_updated' => $dbUpdated,
                'http_status' => $httpStatus,
                'message' => 'Exception saat mengirim bundle: '
                    . $errorMessage,
                'error_type' => 'EXCEPTION',
                'issues' => [$errorMessage],
                'error' => $errorMessage,
            ];
        }
    }

    /**
     * Update tabel visit_encounter.
     *
     * Fungsi ini digunakan untuk semua kondisi:
     * SUCCESS, FAILED, dan EXCEPTION.
     */
    private function updateVisitEncounter(
        $visitId,
        array $data
    ): bool {
        try {

            $data['updated_at'] = now();

            $updated = DB::table('visit_encounter')
                ->where('visit_id', $visitId)
                ->update($data);

            /*
             * Update bernilai 0 belum tentu gagal.
             * Bisa jadi data memang tidak berubah.
             */
            if ($updated === 0) {

                $exists = DB::table('visit_encounter')
                    ->where('visit_id', $visitId)
                    ->exists();

                if (!$exists) {
                    throw new \Exception(
                        'Data visit_encounter tidak ditemukan untuk visit_id '
                        . $visitId
                    );
                }
            }

            return true;

        } catch (\Throwable $e) {

            Log::error(
                'Gagal update visit_encounter',
                [
                    'visit_id' => $visitId,
                    'data' => $data,
                    'error' => $e->getMessage(),
                ]
            );

            return false;
        }
    }

    /**
     * Simpan response ke tabel respon_satusehat.
     */
    private function saveResponse(array $data): void
    {
        try {
            Respon_satusehat::create($data);
        } catch (\Throwable $e) {
            Log::error(
                'Gagal menyimpan respon_satusehat',
                [
                    'visit_id' => $data['visit_id'] ?? null,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Membangun entri bundle.
     *
     * Jika builder mengalami exception, proses dihentikan
     * agar kegagalan tidak diam-diam dilewati.
     */
    private function safeBuild(
        callable $builderCallable,
        $visitId
    ): array {
        try {

            $result = call_user_func(
                $builderCallable,
                $visitId
            );

            return $this->cekBundle($result);

        } catch (\Throwable $e) {

            $builderName = is_array($builderCallable)
                ? $builderCallable[0]
                : 'UnknownBuilder';

            Log::error(
                'Builder SATUSEHAT gagal',
                [
                    'visit_id' => $visitId,
                    'builder' => $builderName,
                    'error' => $e->getMessage(),
                ]
            );

            throw new \RuntimeException(
                'Gagal membangun resource '
                . $builderName
                . ': '
                . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Validasi dan normalisasi entry bundle.
     */
    private function cekBundle($bundle): array
    {
        if ($bundle === null || $bundle === false) {
            return [];
        }

        if (is_object($bundle)) {
            $bundle = method_exists($bundle, 'toArray')
                ? $bundle->toArray()
                : (array) $bundle;
        }

        if (
            is_array($bundle)
            && isset($bundle[0])
            && is_array($bundle[0])
        ) {
            return $bundle;
        }

        if (
            is_array($bundle)
            && isset($bundle['resourceType'])
            && $bundle['resourceType'] === 'Bundle'
            && isset($bundle['entry'])
            && is_array($bundle['entry'])
        ) {
            return $bundle['entry'];
        }

        if (
            is_array($bundle)
            && (
                isset($bundle['resource'])
                || isset($bundle['resourceType'])
            )
        ) {
            return [$bundle];
        }

        return [];
    }

    /**
     * Audit log ke simrs_error_logs.
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
                'type' => $type,
                'visit_id' => $visitId,

                'message' => $status === 'error'
                    ? (
                        $e
                            ? $e->getMessage()
                            : ($payload['detail'] ?? 'ERROR')
                    )
                    : 'SUCCESS',

                'file' => (
                    $status === 'error' && $e
                ) ? $e->getFile() : null,

                'line' => (
                    $status === 'error' && $e
                ) ? $e->getLine() : null,

                'trace' => (
                    $status === 'error' && $e
                ) ? $e->getTraceAsString() : null,

                'payload' => json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE
                ),

                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        } catch (\Throwable $logError) {

            Log::error(
                'Gagal menyimpan simrs_error_logs',
                [
                    'visit_id' => $visitId,
                    'error' => $logError->getMessage(),
                ]
            );
        }
    }
}
