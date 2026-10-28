<?php

namespace App\Libraries\SatuSehat;

use App\Libraries\SatuSehatStorageService;
use App\Libraries\SatuSehatService;
use App\ServicesBundle\CronsendBundle;
use App\Models\Visit_encounter;
use App\Models\Diagnosis;
use App\Models\Observation;
use App\Models\Visit_farmasi;
use App\Models\Visit_icd9;
use App\Models\Visit_lab;
use App\Models\Visit_radiologi;
use App\Models\Visit_alergy;
use App\Models\Visit_careplane;
use App\Models\Visit_quisioner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Service perbaikan data staging SATUSEHAT.
 * Mengadopsi pola MedBridge RepairService dengan kepatuhan mutlak pada aturan READ-ONLY DATABASE:
 * Tidak mengeksekusi UPDATE ke database Postgres lokal, melainkan menyimpan overlay perbaikan
 * ke SatuSehatStorageService dan menghasilkan proposal SQL untuk DBA.
 */
class RepairService
{
    protected SatuSehatStorageService $storage;
    protected CronsendBundle $bundleService;

    public function __construct()
    {
        $this->storage = new SatuSehatStorageService();
        $this->bundleService = new CronsendBundle();
    }

    /**
     * Mengambil item log failed beserta konteks staging dan klasifikasi error.
     * @param string|int $logId
     * @param int|null $stagingId
     * @param string|null $tableName
     * @return array|null
     */
    public function getFailedItem($logId, ?int $stagingId = null, ?string $tableName = null): ?array
    {
        $failedLogs = $this->storage->getFailedLogs();
        $targetLog = null;

        foreach ($failedLogs as $fl) {
            if (
                (string) ($fl['id'] ?? '') === (string) $logId ||
                (string) ($fl['log_id'] ?? '') === (string) $logId ||
                (string) ($fl['visit_id'] ?? '') === (string) $logId
            ) {
                $targetLog = (object) $fl;
                break;
            }
        }

        if (!$targetLog) {
            // Coba hilangkan akhiran index seperti _0 atau _1
            $candidateVisitId = preg_replace('/_\d+$/', '', (string) $logId);
            foreach ($failedLogs as $fl) {
                if ((string) ($fl['visit_id'] ?? '') === $candidateVisitId) {
                    $targetLog = (object) $fl;
                    break;
                }
            }

            if (!$targetLog) {
                $lastLog = $this->storage->getLastLog($candidateVisitId);
                if ($lastLog) {
                    $targetLog = (object) array_merge($lastLog, [
                        'id'            => (string) $logId,
                        'log_id'        => (string) $logId,
                        'visit_id'      => $candidateVisitId,
                        'resource_type' => $lastLog['resource_type'] ?? ($lastLog['error_type'] ?? 'Encounter'),
                        'error_message' => $lastLog['message'] ?? 'Unknown Error',
                        'http_status'   => $lastLog['http_code'] ?? ($lastLog['http_status'] ?? 500),
                        'retry_count'   => $lastLog['retry_count'] ?? 0,
                        'created_at'    => $lastLog['timestamp'] ?? ($lastLog['logged_at'] ?? now()->toDateTimeString()),
                    ]);
                }
            }

            // Fallback: cari langsung dari database visit_encounter
            if (!$targetLog) {
                try {
                    $enc = Visit_encounter::where('visit_id', $candidateVisitId)->orWhere('id', $candidateVisitId)->first();
                    if ($enc) {
                        $targetLog = (object) [
                            'id'            => (string) $logId,
                            'log_id'        => (string) $logId,
                            'visit_id'      => (string) $enc->visit_id,
                            'resource_type' => 'Encounter',
                            'error_message' => $enc->last_error ?? null,
                            'http_status'   => ($enc->bundle_status === 'SUCCESS' || $enc->is_send ? 200 : 500),
                            'retry_count'   => (int) ($enc->retry_count ?? 0),
                            'created_at'    => $enc->updated_at ? $enc->updated_at->toDateTimeString() : now()->toDateTimeString(),
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::info("Encounter fallback notice: " . $e->getMessage());
                }
            }
        }

        if (!$targetLog) {
            return null;
        }

        $visitId = (string) $targetLog->visit_id;
        $encounter = null;
        try {
            $encounter = Visit_encounter::where('visit_id', $visitId)->first();
        } catch (\Throwable $e) {
            Log::info("Encounter lookup notice: " . $e->getMessage());
        }

        $resourceIds = $this->storage->getResourceIds($visitId);
        $encounterResourceId = $resourceIds['Encounter']['resource_id'] ?? ($encounter->uuid_encounter ?? null);
        $isEncounterSuccess = !empty($encounterResourceId) || ((bool) ($encounter->is_send ?? false));

        $classification = ErrorClassifier::classify(
            isset($targetLog->http_status) ? (int) $targetLog->http_status : 500,
            $targetLog->error_message ?? null,
            in_array(($targetLog->resource_type ?? ''), ['Encounter', 'EpisodeOfCare'], true) ? true : $isEncounterSuccess,
            (int) ($targetLog->retry_count ?? 0)
        );

        $stagingInfo = $this->getStagingRecord(
            $targetLog->resource_type ?? 'Encounter',
            $visitId,
            $targetLog->resource_uuid ?? null,
            $stagingId,
            $tableName
        );

        $allStaging = $this->getAllStagingData($visitId);

        return [
            'log'                  => $targetLog,
            'visit_id'             => $visitId,
            'encounter'            => $encounter,
            'is_encounter_success' => $isEncounterSuccess,
            'classification'       => $classification,
            'staging_info'         => $stagingInfo,
            'all_staging'          => $allStaging,
        ];
    }

    /**
     * Mengambil seluruh data dari semua tabel staging untuk visitId tertentu.
     */
    public function getAllStagingData(string $visitId): array
    {
        $encounter = null;
        try {
            $encounter = Visit_encounter::where('visit_id', $visitId)->first();
        } catch (\Throwable $e) {}

        try {
            $diagnoses = Diagnosis::where('visit_id', $visitId)->orderBy('rank', 'asc')->orderBy('id', 'asc')->get();
        } catch (\Throwable $e) {
            $diagnoses = collect();
        }

        try {
            $medications = Visit_farmasi::with('ms_kfa')->where('visit_id', $visitId)->orderBy('id', 'asc')->get();
        } catch (\Throwable $e) {
            $medications = collect();
        }

        try {
            $procedures = Visit_icd9::where('visit_id', $visitId)->orderBy('id', 'asc')->get();
        } catch (\Throwable $e) {
            $procedures = collect();
        }

        try {
            $observations = Observation::with('vital')->where('visit_id', $visitId)->orderBy('id', 'asc')->get();
        } catch (\Throwable $e) {
            $observations = collect();
        }

        try {
            $labs = Visit_lab::with(['pemeriksaan', 'specimen'])->where('visit_id', $visitId)->orderBy('id', 'asc')->get();
        } catch (\Throwable $e) {
            $labs = collect();
        }

        try {
            $careplans = Visit_careplane::where('visit_id', $visitId)->orderBy('id', 'asc')->get();
        } catch (\Throwable $e) {
            $careplans = collect();
        }

        try {
            $radiologies = Visit_radiologi::with('ms_radiologi')->where('visit_id', $visitId)->orderBy('id', 'asc')->get();
        } catch (\Throwable $e) {
            $radiologies = collect();
        }

        try {
            $quisioners = Visit_quisioner::where('visit_id', $visitId)->orderBy('id_quisioner', 'asc')->get();
        } catch (\Throwable $e) {
            $quisioners = collect();
        }

        try {
            $allergies = Visit_alergy::where('visit_id', $visitId)->orderBy('id', 'asc')->get();
        } catch (\Throwable $e) {
            $allergies = collect();
        }

        return [
            'encounter' => [
                'key'           => 'encounter',
                'title'         => 'Encounter & Kunjungan',
                'table'         => 'visit_encounter',
                'resource_type' => 'Encounter',
                'pk'            => 'id',
                'icon'          => 'bi-hospital',
                'records'       => $encounter ? collect([$encounter]) : collect(),
                'count'         => $encounter ? 1 : 0,
            ],
            'diagnosis' => [
                'key'           => 'diagnosis',
                'title'         => 'Diagnosa ICD-10',
                'table'         => 'diagnosis',
                'resource_type' => 'Condition',
                'pk'            => 'id',
                'icon'          => 'bi-clipboard-pulse',
                'records'       => $diagnoses,
                'count'         => $diagnoses->count(),
            ],
            'medication' => [
                'key'           => 'medication',
                'title'         => 'Farmasi / Obat KFA',
                'table'         => 'visit_farmasi',
                'resource_type' => 'Medication',
                'pk'            => 'id',
                'icon'          => 'bi-capsule',
                'records'       => $medications,
                'count'         => $medications->count(),
            ],
            'procedure' => [
                'key'           => 'procedure',
                'title'         => 'Tindakan ICD-9',
                'table'         => 'visit_icd9',
                'resource_type' => 'Procedure',
                'pk'            => 'id',
                'icon'          => 'bi-bandaid',
                'records'       => $procedures,
                'count'         => $procedures->count(),
            ],
            'observation' => [
                'key'           => 'observation',
                'title'         => 'Observasi & Tanda Vital',
                'table'         => 'observation',
                'resource_type' => 'Observation',
                'pk'            => 'id',
                'icon'          => 'bi-heart-pulse',
                'records'       => $observations,
                'count'         => $observations->count(),
            ],
            'lab' => [
                'key'           => 'lab',
                'title'         => 'Laboratorium',
                'table'         => 'visit_lab',
                'resource_type' => 'ServiceRequest',
                'pk'            => 'id',
                'icon'          => 'bi-virus',
                'records'       => $labs,
                'count'         => $labs->count(),
            ],
            'careplan' => [
                'key'           => 'careplan',
                'title'         => 'Rencana Rawat',
                'table'         => 'visit_careplane',
                'resource_type' => 'CarePlan',
                'pk'            => 'id',
                'icon'          => 'bi-journal-medical',
                'records'       => $careplans,
                'count'         => $careplans->count(),
            ],
            'radiologi' => [
                'key'           => 'radiologi',
                'title'         => 'Radiologi',
                'table'         => 'visit_radiologi',
                'resource_type' => 'Radiologi',
                'pk'            => 'id',
                'icon'          => 'bi-camera',
                'records'       => $radiologies,
                'count'         => $radiologies->count(),
            ],
            'quisioner' => [
                'key'           => 'quisioner',
                'title'         => 'Kuesioner Farmasi',
                'table'         => 'visit_quisioner',
                'resource_type' => 'QuestionnaireResponse',
                'pk'            => 'id_quisioner',
                'icon'          => 'bi-ui-checks',
                'records'       => $quisioners,
                'count'         => $quisioners->count(),
            ],
            'allergy' => [
                'key'           => 'allergy',
                'title'         => 'Riwayat Alergi',
                'table'         => 'visit_alergy',
                'resource_type' => 'AllergyIntolerance',
                'pk'            => 'id',
                'icon'          => 'bi-shield-slash',
                'records'       => $allergies,
                'count'         => $allergies->count(),
            ],
        ];
    }

    /**
     * Mengidentifikasi tabel staging, record spesifik, dan daftar record untuk visitId.
     */
    public function getStagingRecord(
        string $resourceType,
        string $visitId,
        ?string $resourceUuid = null,
        ?int $stagingId = null,
        ?string $tableName = null
    ): array {
        if ($tableName) {
            $tableMap = [
                'visit_encounter' => 'Encounter',
                'diagnosis'       => 'Condition',
                'visit_farmasi'   => 'Medication',
                'visit_icd9'      => 'Procedure',
                'observation'     => 'Observation',
                'visit_lab'       => 'ServiceRequest',
                'visit_careplane' => 'CarePlan',
                'visit_radiologi' => 'Radiologi',
                'visit_quisioner' => 'QuestionnaireResponse',
                'visit_alergy'    => 'Allergy',
            ];
            $resourceType = $tableMap[$tableName] ?? $resourceType;
        }

        $encounter = null;
        try {
            $encounter = Visit_encounter::where('visit_id', $visitId)->first();
        } catch (\Throwable $e) {
            // DB fallback
        }

        $table = 'visit_encounter';
        $type = $resourceType;
        $pk = 'id';
        $uuidFields = ['uuid_encounter'];
        $description = 'Tabel Kunjungan Utama (Encounter)';
        $allRecords = collect();

        switch (strtolower($resourceType)) {
            case 'episodeofcare':
                $table = 'visit_encounter';
                $type = 'EpisodeOfCare';
                $pk = 'id';
                $uuidFields = ['uuid_encounter'];
                $description = 'Tabel Kunjungan Utama / Episode of Care';
                $allRecords = $encounter ? collect([$encounter]) : collect();
                break;

            case 'encounter':
                $table = 'visit_encounter';
                $type = 'Encounter';
                $pk = 'id';
                $uuidFields = ['uuid_encounter'];
                $description = 'Tabel Kunjungan Utama Pasien (Encounter)';
                $allRecords = $encounter ? collect([$encounter]) : collect();
                break;

            case 'condition':
            case 'diagnosa':
                $table = 'diagnosis';
                $type = 'Condition';
                $pk = 'id';
                $uuidFields = ['uuid'];
                $description = 'Tabel Diagnosa ICD-10 (Condition)';
                try {
                    $allRecords = Diagnosis::where('visit_id', $visitId)->orderBy('rank', 'asc')->get();
                } catch (\Throwable $e) {
                    $allRecords = collect();
                }
                break;

            case 'observation':
                $table = 'observation';
                $type = 'Observation';
                $pk = 'id';
                $uuidFields = ['uuid_observation'];
                $description = 'Tabel Tanda Vital & Observasi LOINC (Observation)';
                try {
                    $allRecords = Observation::where('visit_id', $visitId)->orderBy('id')->get();
                } catch (\Throwable $e) {
                    $allRecords = collect();
                }
                break;

            case 'medication':
            case 'farmasi':
                $table = 'visit_farmasi';
                $type = 'Medication';
                $pk = 'id';
                $uuidFields = ['uuid'];
                $description = 'Tabel Resep & Pengeluaran Obat KFA (Medication)';
                try {
                    $allRecords = Visit_farmasi::where('visit_id', $visitId)->orderBy('id')->get();
                } catch (\Throwable $e) {
                    $allRecords = collect();
                }
                break;

            case 'procedure':
            case 'icd9':
                $table = 'visit_icd9';
                $type = 'Procedure';
                $pk = 'id';
                $uuidFields = ['uuid'];
                $description = 'Tabel Tindakan Medis ICD-9-CM (Procedure)';
                try {
                    $allRecords = Visit_icd9::where('visit_id', $visitId)->orderBy('id')->get();
                } catch (\Throwable $e) {
                    $allRecords = collect();
                }
                break;

            case 'servicerequest':
            case 'lab':
                $table = 'visit_lab';
                $type = 'ServiceRequest';
                $pk = 'id';
                $uuidFields = ['uuid'];
                $description = 'Tabel Permintaan & Spesimen Laboratorium (ServiceRequest)';
                try {
                    $allRecords = Visit_lab::where('visit_id', $visitId)->orderBy('id')->get();
                } catch (\Throwable $e) {
                    $allRecords = collect();
                }
                break;

            case 'radiologi':
            case 'diagnosticreport':
                $table = 'visit_radiologi';
                $type = 'Radiologi';
                $pk = 'id';
                $uuidFields = ['uuid'];
                $description = 'Tabel Pemeriksaan & Hasil Radiologi (DiagnosticReport)';
                try {
                    $allRecords = Visit_radiologi::where('visit_id', $visitId)->orderBy('id')->get();
                } catch (\Throwable $e) {
                    $allRecords = collect();
                }
                break;

            case 'allergy':
            case 'allergyintolerance':
                $table = 'visit_alergy';
                $type = 'Allergy';
                $pk = 'id';
                $uuidFields = ['uuid'];
                $description = 'Tabel Riwayat Alergi Pasien (AllergyIntolerance)';
                try {
                    $allRecords = Visit_alergy::where('visit_id', $visitId)->orderBy('id')->get();
                } catch (\Throwable $e) {
                    $allRecords = collect();
                }
                break;

            case 'careplan':
                $table = 'visit_careplane';
                $type = 'CarePlan';
                $pk = 'id';
                $uuidFields = ['uuid'];
                $description = 'Tabel Rencana Perawatan & Edukasi (CarePlan)';
                try {
                    $allRecords = Visit_careplane::where('visit_id', $visitId)->orderBy('id')->get();
                } catch (\Throwable $e) {
                    $allRecords = collect();
                }
                break;

            case 'questionnaireresponse':
            case 'quisioner':
                $table = 'visit_quisioner';
                $type = 'QuestionnaireResponse';
                $pk = 'id';
                $uuidFields = ['uuid'];
                $description = 'Tabel Kuesioner Telaah Resep Farmasi (QuestionnaireResponse)';
                try {
                    $allRecords = Visit_quisioner::where('visit_id', $visitId)->orderBy('id')->get();
                } catch (\Throwable $e) {
                    $allRecords = collect();
                }
                break;

            default:
                $allRecords = $encounter ? collect([$encounter]) : collect();
                break;
        }

        $selectedRecord = $this->resolveTargetRecord($allRecords, $pk, $uuidFields, $type, $stagingId, $resourceUuid);

        // Pasangkan overlay perbaikan yang tersimpan di storage (jika ada)
        $stagingIdVal = $selectedRecord ? ($selectedRecord->$pk ?? null) : null;
        $savedRepairs = $stagingIdVal ? $this->storage->getStagingRepairs($visitId, $table, $stagingIdVal) : [];

        return [
            'table'         => $table,
            'model'         => $selectedRecord,
            'all_records'   => $allRecords,
            'primary_key'   => $pk,
            'type'          => $type,
            'id'            => $stagingIdVal,
            'description'   => $description,
            'saved_repairs' => $savedRepairs,
        ];
    }

    /**
     * Memilih baris data target dari kumpulan record staging.
     */
    protected function resolveTargetRecord(
        Collection $allRecords,
        string $pk,
        array $uuidFields,
        string $type,
        ?int $stagingId = null,
        ?string $resourceUuid = null
    ) {
        if ($allRecords->isEmpty()) {
            return null;
        }

        if ($stagingId !== null) {
            $found = $allRecords->firstWhere($pk, $stagingId);
            if ($found) {
                return $found;
            }
        }

        // Prioritas ke record dengan kolom required yang kosong
        foreach ($allRecords as $rec) {
            if ($type === 'Condition' && empty($rec->code)) {
                return $rec;
            }
            if ($type === 'Procedure' && (empty($rec->icd_code) && empty($rec->code))) {
                return $rec;
            }
            if ($type === 'Observation' && empty($rec->result)) {
                return $rec;
            }
            if ($type === 'Medication' && empty($rec->item_id_kfa)) {
                return $rec;
            }
        }

        return $allRecords->first();
    }

    /**
     * Memvalidasi data staging secara in-memory sebelum pengiriman / retry.
     */
    public function validateStaging($logId, ?int $stagingId = null, ?string $tableName = null): array
    {
        $item = $this->getFailedItem($logId, $stagingId, $tableName);
        if (!$item) {
            return [
                'valid'  => false,
                'status' => ErrorClassifier::STATUS_NEED_REPAIR,
                'errors' => ['Log pengiriman tidak ditemukan.'],
            ];
        }

        $log = $item['log'];
        $encounter = $item['encounter'];
        $errors = [];

        // 1. Dependency Check
        if (!in_array(strtolower($log->resource_type ?? ''), ['encounter', 'episodeofcare'], true)) {
            if (!$item['is_encounter_success']) {
                $errors[] = 'Dependency Error: Encounter belum berstatus SUCCESS di SATUSEHAT. Pengiriman resource turunan diblokir (BLOCKED).';
                return [
                    'valid'  => false,
                    'status' => ErrorClassifier::STATUS_BLOCKED,
                    'errors' => $errors,
                ];
            }
        }

        // 2. Patient & Doctor Data Check
        if ($encounter) {
            if (empty($encounter->no_ktp) && empty($encounter->kode_pasien)) {
                $errors[] = 'Data Pasien: NIK Pasien (no_ktp) atau Kode SATUSEHAT Pasien tidak boleh kosong.';
            }
            if (empty($encounter->kode_dokter)) {
                $errors[] = 'Data Dokter: Kode SATUSEHAT Dokter (kode_dokter) belum terisi.';
            }
        }

        // 3. Staging Record Specific Validation (memperhitungkan perbaikan tersimpan di storage)
        $stagingInfo = $item['staging_info'];
        $model = $stagingInfo['model'];
        $type = $stagingInfo['type'];
        $repairs = $stagingInfo['saved_repairs'] ?? [];

        if (!$model && $stagingInfo['table'] !== 'visit_encounter') {
            $errors[] = "Record pada tabel staging {$stagingInfo['table']} tidak ditemukan di database.";
        } elseif ($model) {
            $val = function (string $field) use ($model, $repairs) {
                return $repairs[$field] ?? ($model->$field ?? null);
            };

            switch ($type) {
                case 'Condition':
                    if (empty($val('code'))) {
                        $errors[] = "Tabel {$stagingInfo['table']} (#{$stagingInfo['id']}): Kode ICD-10 (code) wajib diisi.";
                    }
                    if (empty($val('dx_name'))) {
                        $errors[] = "Tabel {$stagingInfo['table']} (#{$stagingInfo['id']}): Nama Diagnosa (dx_name) wajib diisi.";
                    }
                    break;

                case 'Observation':
                    if (empty($val('result'))) {
                        $errors[] = "Tabel {$stagingInfo['table']} (#{$stagingInfo['id']}): Hasil/Nilai Observasi (result) wajib diisi.";
                    }
                    break;

                case 'Procedure':
                    if (empty($val('code'))) {
                        $errors[] = "Tabel {$stagingInfo['table']} (#{$stagingInfo['id']}): Kode ICD-9-CM (code) wajib diisi.";
                    }
                    break;
            }
        }

        // 4. Test In-Memory Bundle Preview
        try {
            $preview = $this->bundleService->buildBundlePreview($item['visit_id']);
            if (empty($preview['bundle']['entry'])) {
                $errors[] = 'Bundle FHIR masih kosong untuk visitId ' . $item['visit_id'];
            }
        } catch (\Throwable $e) {
            $errors[] = 'Builder Bundle Error: ' . $e->getMessage();
        }

        if (!empty($errors)) {
            return [
                'valid'  => false,
                'status' => ErrorClassifier::STATUS_NEED_REPAIR,
                'errors' => $errors,
            ];
        }

        return [
            'valid'  => true,
            'status' => ErrorClassifier::STATUS_RETRYABLE,
            'errors' => [],
        ];
    }

    /**
     * Memperbarui data staging langsung ke database staging lokal dan mencatat riwayat audit.
     */
    public function updateStagingDirect(
        $logId,
        string $tableName,
        $stagingId,
        array $inputs,
        string $reason = 'Perbaikan data staging',
        string $user = 'Admin SIMRS',
        ?string $resourceType = null
    ): array {
        $allowedTables = [
            'visit_encounter' => [
                'pk'      => 'id',
                'resType' => 'Encounter',
                'columns' => [
                    'no_ktp', 'px_norm', 'px_name', 'kode_pasien', 'kode_dokter',
                    'dpjp_name', 'unit_name', 'idunitsatset', 'tgl_kunjung',
                    'tgl_dilayani', 'tgl_selesai_dilayani', 'tgl_pulang',
                    'tipe_kunjungan', 'instruksi_pulang'
                ]
            ],
            'diagnosis' => [
                'pk'      => 'id',
                'resType' => 'Condition',
                'columns' => ['rank', 'code', 'dx_name']
            ],
            'visit_farmasi' => [
                'pk'      => 'id',
                'resType' => 'Medication',
                'columns' => [
                    'item_id_kfa', 'item_id_simrs', 'sale_qty', 'racikan', 'dosis',
                    'dokter_peresep', 'kode_dokter', 'unit_name', 'uuid_unit',
                    'waktu_resep_dibuat', 'waktu_resep_diterima', 'waktu_resep_diproses',
                    'waktu_diserahkan', 'waktu_selesai'
                ]
            ],
            'visit_icd9' => [
                'pk'      => 'id',
                'resType' => 'Procedure',
                'columns' => ['icd_code', 'icd_name']
            ],
            'observation' => [
                'pk'      => 'id',
                'resType' => 'Observation',
                'columns' => ['observation_name', 'result', 'vital_id']
            ],
            'visit_lab' => [
                'pk'      => 'id',
                'resType' => 'ServiceRequest',
                'columns' => [
                    'nama_pemeriksaan', 'dokter_lab', 'kode_dokter_lab', 'tgl_periksa',
                    'tgl_ambil_sample', 'tgl_selesai', 'dokter_pengirim', 'kode_pengirim',
                    'hasil_lab', 'map_pemeriksaan_id', 'map_specimen_id', 'jml_sample',
                    'satuan_sample', 'status_normal'
                ]
            ],
            'visit_careplane' => [
                'pk'      => 'id',
                'resType' => 'CarePlan',
                'columns' => ['kondisi_pulang', 'alasan_pulang', 'keterangan']
            ],
            'visit_radiologi' => [
                'pk'      => 'id',
                'resType' => 'Radiologi',
                'columns' => [
                    'nama_pemeriksaan', 'dokter_pengirim', 'kode_dokter_pengirim',
                    'dokter_radiologi', 'kode_dokter_radiologi', 'alasan_klinis',
                    'status_order', 'tanggal_order', 'tanggal_pemeriksaan',
                    'tanggal_hasil', 'hasil_pemeriksaan', 'code_map_rad', 'acsn_number'
                ]
            ],
            'visit_quisioner' => [
                'pk'      => 'id_quisioner',
                'resType' => 'QuestionnaireResponse',
                'columns' => ['kode_apoteker', 'nama_apoteker', 'tgl_quisioner', 'data_quisioner']
            ],
            'visit_alergy' => [
                'pk'      => 'id',
                'resType' => 'AllergyIntolerance',
                'columns' => ['allergy_id', 'tanggal_alergi', 'note']
            ]
        ];

        if (!isset($allowedTables[$tableName])) {
            return [
                'success' => false,
                'message' => "Tabel staging '{$tableName}' tidak dikenali atau tidak diizinkan."
            ];
        }

        $config = $allowedTables[$tableName];
        $pk = $config['pk'];
        $resType = $resourceType ?: $config['resType'];
        $allowedCols = $config['columns'];

        $item = $this->getFailedItem($logId);
        $visitId = $item ? $item['visit_id'] : preg_replace('/_\d+$/', '', (string)$logId);

        // Cari record di database
        $dbRecord = null;
        try {
            if ($stagingId !== null) {
                $dbRecord = DB::table($tableName)->where($pk, $stagingId)->first();
            }
            if (!$dbRecord && $tableName === 'visit_encounter') {
                $dbRecord = DB::table($tableName)->where('visit_id', $visitId)->first();
                if ($dbRecord) {
                    $stagingId = $dbRecord->$pk;
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Staging lookup notice: " . $e->getMessage());
        }

        $fieldUpdates = [];
        $dbUpdates = [];

        foreach ($allowedCols as $col) {
            if (array_key_exists($col, $inputs)) {
                $oldVal = $dbRecord ? ($dbRecord->$col ?? '') : '';
                $newVal = $inputs[$col];
                if (is_array($newVal)) {
                    $newVal = json_encode($newVal);
                }
                $fieldUpdates[$col] = [
                    'old' => (string) $oldVal,
                    'new' => (string) $newVal,
                ];
                $dbUpdates[$col] = $inputs[$col];
            }
        }

        // DIRECT DATABASE UPDATE jika record ada di database
        $dbUpdated = false;
        if (!empty($dbUpdates) && $dbRecord && $stagingId !== null) {
            try {
                if (Schema::hasColumn($tableName, 'updated_at')) {
                    $dbUpdates['updated_at'] = now();
                }
                DB::table($tableName)->where($pk, $stagingId)->update($dbUpdates);
                $dbUpdated = true;
            } catch (\Throwable $e) {
                Log::error("Direct database update error on {$tableName} #{$stagingId}: " . $e->getMessage());
                return [
                    'success' => false,
                    'message' => "Gagal update database staging {$tableName}: " . $e->getMessage()
                ];
            }
        }

        // Simpan overlay & riwayat audit ke SatuSehatStorageService
        $repairResult = $this->storage->saveStagingRepair(
            $visitId,
            $resType,
            $tableName,
            $stagingId ?: $visitId,
            $fieldUpdates,
            $reason,
            $user
        );

        $msg = $dbUpdated
            ? "Data pada tabel {$tableName} (ID #{$stagingId}) berhasil diperbarui langsung ke database staging!"
            : "Data perbaikan dicatat ({$repairResult['updated_count']} field).";

        return [
            'success'       => true,
            'message'       => $msg,
            'updated_count' => count($fieldUpdates),
            'proposed_sql'  => $repairResult['proposed_sql'] ?? null,
            'table'         => $tableName,
            'staging_id'    => $stagingId,
            'db_updated'    => $dbUpdated,
        ];
    }

    /**
     * Memperbarui data staging (Kompatibel dengan caller lama dan overlay).
     */
    public function updateStaging(
        $logId,
        array $inputs,
        string $reason,
        string $user = 'Admin SIMRS',
        ?int $stagingId = null
    ): array {
        $item = $this->getFailedItem($logId, $stagingId);
        $tableName = $inputs['staging_table'] ?? ($item['staging_info']['table'] ?? 'visit_encounter');
        $resolvedStagingId = $stagingId ?: ($inputs['staging_id'] ?? ($item['staging_info']['id'] ?? null));
        $resType = $inputs['resource_type'] ?? ($item['staging_info']['type'] ?? 'Encounter');

        return $this->updateStagingDirect(
            $logId,
            $tableName,
            $resolvedStagingId,
            $inputs,
            $reason,
            $user,
            $resType
        );
    }

    /**
     * Menjalankan retry pengiriman dengan payload segar dari staging data + overlay perbaikan.
     */
    public function retrySending($logId): array
    {
        $validation = $this->validateStaging($logId);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Validasi staging gagal: ' . implode(' | ', $validation['errors']),
                'errors'  => $validation['errors'],
            ];
        }

        $item = $this->getFailedItem($logId);
        if (!$item) {
            return ['success' => false, 'message' => 'Log tidak ditemukan.'];
        }

        $visitId = $item['visit_id'];
        $result = $this->bundleService->prepareAndSendBundle($visitId);

        return [
            'success'   => $result['success'] ?? false,
            'status'    => $result['status'] ?? 'UNKNOWN',
            'message'   => $result['message'] ?? 'Pengiriman selesai.',
            'results'   => $result,
        ];
    }
}
