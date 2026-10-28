<?php

namespace App\Http\Controllers\Satusehat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Visit_encounter;
use App\Models\Respon_satusehat;
use App\Models\Configs;
use App\ServicesBundle\SendAllSatusehatService;
use App\Libraries\SatuSehatStorageService;
use App\Libraries\SatuSehatService;
use App\Libraries\SatuSehat\ErrorClassifier;
use App\Libraries\SatuSehat\RepairService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendAllSatusehatJob;
use App\Jobs\SendSatusehatBundleJob;

class SatusehatDashboardController extends Controller
{
    protected SatuSehatStorageService $storage;
    protected SendAllSatusehatService $bundleService;
    protected RepairService $repairService;

    public function __construct()
    {
        $this->storage = new SatuSehatStorageService();
        $this->bundleService = new SendAllSatusehatService();
        $this->repairService = new RepairService();
    }

    /**
     * Dashboard pemantauan dan tabel daftar pengiriman SATUSEHAT.
     */
    public function index(Request $request)
    {
        $statusFilter = $request->query('status', 'ALL');
        $search = trim($request->query('search', ''));
        // Auto-set default tanggal hari ini jika belum ditentukan
        $startDate = $request->has('start_date') ? $request->query('start_date') : date('Y-m-d');
        $endDate = $request->query('end_date');
        $perPage = max(5, min(100, (int) $request->query('per_page', 15)));

        $dbEncountersAvailable = false;
        $encounters = collect();
        $paginator = null;
        $metrics = [
            'total'       => 0,
            'success'     => 0,
            'failed'      => 0,
            'pending'     => 0,
            'in_progress' => 0,
        ];

        // 1. Memeriksa ketersediaan tabel visit_encounter di DB secara READ-ONLY
        try {
            $query = Visit_encounter::with(['diagnossis']);

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('px_name', 'ilike', "%{$search}%")
                      ->orWhere('px_norm', 'like', "%{$search}%")
                      ->orWhere('visit_id', 'like', "%{$search}%")
                      ->orWhere('unit_name', 'ilike', "%{$search}%");
                });
            }

            if (!empty($startDate)) {
                if (!empty($endDate)) {
                    $query->whereDate('tgl_kunjung', '>=', $startDate)->whereDate('tgl_kunjung', '<=', $endDate);
                } else {
                    $query->whereDate('tgl_kunjung', $startDate);
                }
            }
            if (empty($startDate) && !empty($endDate)) {
                $query->whereDate('tgl_kunjung', '<=', $endDate);
            }

            // Metrik disesuaikan dengan filter yang sedang aktif
            $totalFiltered = (clone $query)->count();
            $successCount = (clone $query)->where(function ($q) {
                $q->where('bundle_status', 'SUCCESS')
                  ->orWhere('is_send', true);
            })->count();

            $failedCount = (clone $query)->where('bundle_status', 'FAILED')
                ->where(function ($q) {
                    $q->where('is_send', false)->orWhereNull('is_send');
                })->count();

            $pendingCount = max(0, $totalFiltered - $successCount - $failedCount);

            $metrics['total']   = $totalFiltered;
            $metrics['success'] = $successCount;
            $metrics['failed']  = $failedCount;
            $metrics['pending'] = $pendingCount;

            // Filter status untuk pagination
            if ($statusFilter === 'SUCCESS') {
                $query->where(function ($q) {
                    $q->where('bundle_status', 'SUCCESS')
                      ->orWhere('is_send', true);
                });
            } elseif ($statusFilter === 'FAILED') {
                $query->where('bundle_status', 'FAILED')
                      ->where(function ($q) {
                          $q->where('is_send', false)->orWhereNull('is_send');
                      });
            } elseif ($statusFilter === 'DRAFT' || $statusFilter === 'PENDING') {
                $query->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->whereNull('bundle_status')->orWhere('bundle_status', 'DRAFT')->orWhere('bundle_status', 'PENDING');
                    })->where(function ($q3) {
                        $q3->where('is_send', false)->orWhereNull('is_send');
                    });
                });
            }

            $paginator = $query->orderBy('tgl_kunjung', 'desc')->orderBy('id', 'desc')->paginate($perPage)->withQueryString();
            $encounters = collect($paginator->items());
            $dbEncountersAvailable = true;
        } catch (\Throwable $e) {
            Log::info("Visit_encounter table not available in current database: " . $e->getMessage());
            $dbEncountersAvailable = false;
        }

        // 2. Jika database lokal kosong atau belum ada tabel, periksa apakah ada data respon_satusehat atau storage
        $storedMappings = $this->storage->getAllResourceMappings();
        $storedLogs = $this->storage->getLogs(null, 500);

        // Jika tidak ada data dari Visit_encounter, kita bentuk daftar dari riwayat respon_satusehat / storage
        if (!$dbEncountersAvailable) {
            $fallbackEncounters = $this->loadFallbackEncountersFromStorageOrRespon($search, $statusFilter, $startDate, $endDate);
            $totalFiltered = count($fallbackEncounters);
            $metrics['total'] = $totalFiltered;
            $metrics['success'] = count(array_filter($fallbackEncounters, fn($e) => in_array($e['status'] ?? '', ['SUCCESS', '201 Created'], true)));
            $metrics['failed'] = count(array_filter($fallbackEncounters, fn($e) => in_array($e['status'] ?? '', ['FAILED', 'EXCEPTION'], true)));
            $metrics['pending'] = max(0, $totalFiltered - $metrics['success'] - $metrics['failed']);
            
            // Buat paginator manual
            $page = max(1, (int) $request->query('page', 1));
            $slice = array_slice($fallbackEncounters, ($page - 1) * $perPage, $perPage);
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $slice,
                count($fallbackEncounters),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            $encounters = collect($slice);
        }

        // 3. Gabungkan status pengiriman aktual dan resource_id dari Storage
        $decoratedEncounters = $encounters->map(function ($enc) use (&$metrics) {
            $visitId = (string) ($enc->visit_id ?? $enc['visit_id'] ?? '');
            $storageResources = $this->storage->getResourceIds($visitId);
            $lastLog = $this->storage->getLastLog($visitId);
            $draft = $this->storage->getDraft($visitId);

            $encounterResourceId = $storageResources['Encounter']['resource_id'] ?? null;
            $hasDbSend = is_object($enc) ? (bool) ($enc->is_send ?? false) : (bool) ($enc['is_send'] ?? false);

            // Tentukan status tampilan
            if (!empty($encounterResourceId)) {
                $computedStatus = 'SUCCESS';
            } elseif ($lastLog && ($lastLog['status'] ?? '') === 'SUCCESS') {
                $computedStatus = 'SUCCESS';
            } elseif ($lastLog && in_array($lastLog['status'] ?? '', ['FAILED', 'EXCEPTION', 'TIMEOUT'], true)) {
                $computedStatus = 'FAILED';
            } elseif ($hasDbSend) {
                $computedStatus = 'SUCCESS';
            } else {
                $computedStatus = 'DRAFT';
            }

// Status sudah dihitung pada metrik filter

            return (object) [
                'id'                  => is_object($enc) ? ($enc->id ?? null) : ($enc['id'] ?? null),
                'visit_id'            => $visitId,
                'px_norm'             => is_object($enc) ? ($enc->px_norm ?? '-') : ($enc['px_norm'] ?? '-'),
                'px_name'             => is_object($enc) ? ($enc->px_name ?? 'Pasien') : ($enc['px_name'] ?? 'Pasien'),
                'kode_pasien'         => is_object($enc) ? ($enc->kode_pasien ?? '-') : ($enc['kode_pasien'] ?? '-'),
                'dpjp_name'           => is_object($enc) ? ($enc->dpjp_name ?? '-') : ($enc['dpjp_name'] ?? '-'),
                'unit_name'           => is_object($enc) ? ($enc->unit_name ?? '-') : ($enc['unit_name'] ?? '-'),
                'tgl_kunjung'         => is_object($enc) ? ($enc->tgl_kunjung ?? null) : ($enc['tgl_kunjung'] ?? null),
                'tgl_dilayani'        => is_object($enc) ? ($enc->tgl_dilayani ?? null) : ($enc['tgl_dilayani'] ?? null),
                'tgl_pulang'          => is_object($enc) ? ($enc->tgl_pulang ?? null) : ($enc['tgl_pulang'] ?? null),
                'computed_status'     => $computedStatus,
                'encounter_satusehat' => $encounterResourceId,
                'resources_count'     => count($storageResources),
                'last_error'          => $lastLog['message'] ?? null,
                'error_type'          => $lastLog['error_type'] ?? null,
                'last_sent_at'        => $lastLog['timestamp'] ?? null,
                'has_draft'           => !empty($draft),
            ];
        });

        // Terapkan filter status jika dispesifikasikan (kecuali ALL)
        if ($statusFilter !== 'ALL') {
            $filteredItems = $decoratedEncounters->filter(function ($item) use ($statusFilter) {
                if ($statusFilter === 'DRAFT' || $statusFilter === 'PENDING') {
                    return in_array($item->computed_status, ['DRAFT', 'PENDING'], true);
                }
                return $item->computed_status === $statusFilter;
            })->values();
        } else {
            $filteredItems = $decoratedEncounters;
        }

        // Metrik tambahan jika database memiliki riwayat
        try {
            $responTotal = Respon_satusehat::count();
            $respon201 = Respon_satusehat::where('status', 'like', '%201%')->count();
            $responErr = Respon_satusehat::where('status', '!=', '201 Created')->count();
            $dbResponStats = [
                'total_responses' => $responTotal,
                'created_201'     => $respon201,
                'errors'          => $responErr,
            ];
        } catch (\Throwable $e) {
            $dbResponStats = null;
        }

        return view('satusehat.dashboard', [
            'encounters'        => $filteredItems,
            'paginator'         => $paginator,
            'metrics'           => $metrics,
            'dbResponStats'     => $dbResponStats,
            'filters'           => [
                'status'     => $statusFilter,
                'search'     => $search,
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'per_page'   => $perPage,
            ],
            'dbAvailable'       => $dbEncountersAvailable,
        ]);
    }

    /**
     * Halaman detail encounter, inspeksi payload FHIR, log respons, dan perbaikan data draft.
     * @param string|int $visitId
     */
    public function detail($visitId)
    {
        $visitId = (string) $visitId;

        // Ambil data encounter dari DB secara READ-ONLY
        $encounter = null;
        try {
            $encounter = Visit_encounter::with([
                'diagnossis',
                'observation.vital',
                'visit_allergy.master',
                'visit_lab.pemeriksaan',
                'visit_lab.specimen',
                'icd9',
                'visit_radiologi.ms_radiologi'
            ])->where('visit_id', $visitId)->first();
        } catch (\Throwable $e) {
            Log::info("Could not fetch Visit_encounter in detail: " . $e->getMessage());
        }

        // Ambil data dari Storage (Non-Database)
        $storageResources = $this->storage->getResourceIds($visitId);
        $logs = $this->storage->getLogs($visitId, 20);
        $draft = $this->storage->getDraft($visitId);

        // Ambil preview FHIR Bundle
        $bundlePreview = null;
        $previewError = null;
        try {
            $bundlePreview = $this->bundleService->buildBundlePreview($visitId);
        } catch (\Throwable $e) {
            $previewError = $e->getMessage();
        }

        // Ambil log dari tabel respon_satusehat (READ-ONLY) jika tabel tersedia
        $dbResponses = collect();
        try {
            $dbResponses = Respon_satusehat::where('visit_id', $visitId)
                ->orderBy('id_respon', 'desc')
                ->take(30)
                ->get();
        } catch (\Throwable $e) {
            // Abaikan jika tabel tidak ada
        }

        // Sanitasi identitas pasien untuk keamanan privasi (masking KTP)
        if ($encounter && !empty($encounter->no_ktp)) {
            $ktpStr = (string) $encounter->no_ktp;
            $encounter->no_ktp_masked = strlen($ktpStr) >= 8 
                ? substr($ktpStr, 0, 4) . '******' . substr($ktpStr, -4) 
                : '******';
        }

        return view('satusehat.detail', [
            'visitId'          => $visitId,
            'encounter'        => $encounter,
            'storageResources' => $storageResources,
            'logs'             => $logs,
            'draft'            => $draft,
            'bundlePreview'    => $bundlePreview,
            'previewError'     => $previewError,
            'dbResponses'      => $dbResponses,
        ]);
    }

    /**
     * Menyimpan draft koreksi data secara NON-DATABASE (tidak melakukan UPDATE ke database).
     * @param string|int $visitId
     */
    public function saveDraft(Request $request, $visitId)
    {
        $visitId = (string) $visitId;

        $validated = $request->validate([
            'instruksi_pulang' => 'nullable|string|max:2000',
            'idunitsatset'     => 'nullable|string|max:100',
            'kondisi_pulang'   => 'nullable|string|max:100',
            'alasan_pulang'    => 'nullable|string|max:1000',
        ]);

        $saved = $this->storage->saveDraft($visitId, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => $saved,
                'message' => $saved ? 'Draft koreksi berhasil disimpan (tersimpan di penyimpanan aplikasi aman tanpa mengubah database).' : 'Gagal menyimpan draft.',
                'draft'   => $this->storage->getDraft($visitId),
            ]);
        }

        return redirect()->route('satusehat.detail', $visitId)
            ->with($saved ? 'success' : 'error', $saved ? 'Draft perbaikan data berhasil disimpan secara aman.' : 'Gagal menyimpan draft.');
    }

    /**
     * Menghapus draft koreksi data.
     * @param string|int $visitId
     */
    public function clearDraft(Request $request, $visitId)
    {
        $visitId = (string) $visitId;
        $this->storage->clearDraft($visitId);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Draft koreksi telah dibersihkan.',
            ]);
        }

        return redirect()->route('satusehat.detail', $visitId)
            ->with('info', 'Draft perbaikan data telah dibersihkan.');
    }

    /**
     * Mengirim ulang / Retry pengiriman data SATUSEHAT.
     * Mengirim hanya ke API SATUSEHAT tanpa mengubah database lokal.
     * @param string|int $visitId
     */
    public function resend(Request $request, $visitId)
    {
        $visitId = (string) $visitId;

        // Validasi CSRF dan akses
        if (!$request->isMethod('post')) {
            return response()->json([
                'success' => false,
                'message' => 'Method tidak diizinkan. Gunakan POST.',
            ], 405);
        }

        // Lakukan pengiriman dengan CronsendBundle
        $result = $this->bundleService->prepareAndSendBundle($visitId);

        $httpCode = $result['success'] ? 200 : ($result['http_status'] ?? 400);

        return response()->json([
            'success'      => $result['success'],
            'status'       => $result['status'],
            'message'      => $result['message'],
            'error_type'   => $result['error_type'] ?? null,
            'captured_ids' => $result['captured_ids'] ?? [],
            'issues'       => $result['issues'] ?? [],
            'created_count'=> $result['created_count'] ?? 0,
        ], $httpCode >= 200 && $httpCode <= 500 ? $httpCode : 200);
    }

    /**
     * Ekspor aman data mapping resource ID SATUSEHAT untuk audit.
     * @param string|int $visitId
     */
    public function exportMapping($visitId)
    {
        $visitId = (string) $visitId;
        $data = [
            'visit_id'         => $visitId,
            'exported_at'      => now()->toIso8601String(),
            'storage_resources'=> $this->storage->getResourceIds($visitId),
            'logs'             => $this->storage->getLogs($visitId),
            'draft'            => $this->storage->getDraft($visitId),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => "attachment; filename=\"satusehat_mapping_{$visitId}.json\"",
        ]);
    }

    /**
     * Helper pemuatan data fallback dari Storage atau DB jika visit_encounter kosong.
     */
    protected function loadFallbackEncountersFromStorageOrRespon(string $search, string $statusFilter, ?string $startDate, ?string $endDate): array
    {
        $list = [];
        $seenVisitIds = [];

        // 1. Coba baca dari tabel respon_satusehat (READ-ONLY) jika tabel ada
        try {
            $query = Respon_satusehat::select('visit_id', 'pasien_id', 'tgl_kirim', 'status')
                ->whereNotNull('visit_id')
                ->orderBy('id_respon', 'desc');

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('visit_id', 'like', "%{$search}%")
                      ->orWhere('pasien_id', 'like', "%{$search}%");
                });
            }

            $rows = $query->take(200)->get();
            foreach ($rows as $r) {
                $vid = (string) $r->visit_id;
                if (!isset($seenVisitIds[$vid])) {
                    $seenVisitIds[$vid] = true;
                    $list[] = [
                        'visit_id'    => $vid,
                        'px_norm'     => '-',
                        'px_name'     => $r->pasien_id ?? 'Pasien ' . $vid,
                        'kode_pasien' => $r->pasien_id ?? '-',
                        'dpjp_name'   => '-',
                        'unit_name'   => 'Rawat Jalan',
                        'tgl_kunjung' => $r->tgl_kirim,
                        'is_send'     => str_contains($r->status, '201'),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // respon_satusehat tidak ada
        }

        // 2. Gabungkan dengan data dari storage non-database jika ada
        $allMappings = $this->storage->getAllResourceMappings();
        foreach ($allMappings as $vid => $val) {
            $vid = (string) $vid;
            if (!isset($seenVisitIds[$vid])) {
                $seenVisitIds[$vid] = true;
                $list[] = [
                    'visit_id'    => $vid,
                    'px_norm'     => '-',
                    'px_name'     => 'Pasien ' . $vid,
                    'kode_pasien' => '-',
                    'dpjp_name'   => '-',
                    'unit_name'   => 'Rawat Jalan',
                    'tgl_kunjung' => $val['updated_at'] ?? now()->toIso8601String(),
                    'is_send'     => true,
                ];
            }
        }

        return $list;
    }

    /**
     * API pengembalian riwayat log dan status encounter untuk modal log MedBridge.
     */
    public function getEncounterLogs($visitId)
    {
        $visitId = (string) $visitId;
        $logs = $this->storage->getLogs($visitId);
        $resources = $this->storage->getResourceIds($visitId);

        $totalResource = count($resources);
        $berhasil = 0;
        foreach ($resources as $res) {
            if (!empty($res['resource_id'])) $berhasil++;
        }

        $lastLog = $this->storage->getLastLog($visitId);
        $gagal = ($lastLog && in_array($lastLog['status'] ?? '', ['FAILED', 'EXCEPTION', 'TIMEOUT'], true)) ? 1 : 0;

        if ($gagal === 0 && $berhasil > 0) {
            $status = 'SUCCESS';
        } elseif ($berhasil > 0 && $gagal > 0) {
            $status = 'PARTIAL_SUCCESS';
        } else {
            $status = $gagal > 0 ? 'FAILED' : 'DRAFT';
        }

        $failedResources = [];
        if ($gagal > 0 && $lastLog) {
            $failedResources[] = [
                'resource_type' => $lastLog['resource_type'] ?? ($lastLog['error_type'] ?? 'Encounter'),
                'error_message' => $lastLog['message'] ?? 'Error pengiriman',
                'failed_at'     => $lastLog['logged_at'] ?? now()->toIso8601String(),
            ];
        }

        return response()->json([
            'success'   => true,
            'visit_id'  => $visitId,
            'summary'   => [
                'total_resource'   => $totalResource,
                'berhasil'         => $berhasil,
                'gagal'            => $gagal,
                'status'           => $status,
                'failed_resources' => $failedResources,
                'logs'             => $logs,
            ]
        ]);
    }

    
    /**
     * Memulai pengiriman batch seluruh data kunjungan ke SATUSEHAT melalui Laravel Job Queue.
     */
    public function sendAll(Request $request)
    {
        $filters = [
            'start_date' => $request->input('start_date') ?: date('Y-m-d'),
            'end_date'   => $request->input('end_date'),
            'status'     => $request->input('status', 'ALL'),
            'search'     => $request->input('search'),
            'visit_ids'  => $request->input('visit_ids', []),
            'chunk_size' => $request->input('chunk_size', 50),
        ];

        // Estimasi jumlah data yang akan diproses
        $count = 0;
        try {
            $query = Visit_encounter::query();
            if (!empty($filters['visit_ids']) && is_array($filters['visit_ids'])) {
                $query->whereIn('visit_id', $filters['visit_ids']);
            } else {
                if (!empty($filters['start_date'])) {
                    if (!empty($filters['end_date'])) {
                        $query->whereDate('tgl_kunjung', '>=', $filters['start_date'])
                              ->whereDate('tgl_kunjung', '<=', $filters['end_date']);
                    } else {
                        $query->whereDate('tgl_kunjung', $filters['start_date']);
                    }
                } elseif (!empty($filters['end_date'])) {
                    $query->whereDate('tgl_kunjung', '<=', $filters['end_date']);
                }

                if (!empty($filters['search'])) {
                    $search = trim($filters['search']);
                    $query->where(function ($q) use ($search) {
                        $q->where('px_name', 'ilike', "%{$search}%")
                          ->orWhere('px_norm', 'like', "%{$search}%")
                          ->orWhere('visit_id', 'like', "%{$search}%")
                          ->orWhere('unit_name', 'ilike', "%{$search}%");
                    });
                }

                $st = strtoupper($filters['status'] ?? 'ALL');
                if ($st === 'FAILED') {
                    $query->where('bundle_status', 'FAILED');
                } elseif ($st === 'DRAFT' || $st === 'PENDING') {
                    $query->where(function ($q) {
                        $q->whereNull('bundle_status')
                          ->orWhereIn('bundle_status', ['DRAFT', 'PENDING']);
                    })->where(function ($q) {
                        $q->where('is_send', false)->orWhereNull('is_send');
                    });
                } elseif ($st === 'SUCCESS') {
                    $query->where(function ($q) {
                        $q->where('bundle_status', 'SUCCESS')
                          ->orWhere('is_send', true);
                    });
                } else {
                    // ALL: Data kunjungan yang belum terkirim sukses
                    $query->where(function ($q) {
                        $q->whereNull('bundle_status')
                          ->orWhere('bundle_status', '!=', 'SUCCESS');
                    })->where(function ($q) {
                        $q->where('is_send', false)->orWhereNull('is_send');
                    });
                }
            }

            $count = $query->count();
        } catch (\Throwable $e) {
            Log::info("Tidak dapat menghitung estimasi encounter: " . $e->getMessage());
        }

        // Dispatch SendAllSatusehatJob ke Background Queue
        SendAllSatusehatJob::dispatch($filters);

        $message = $count > 0
            ? "Pengiriman {$count} data kunjungan ke SATUSEHAT telah dijadwalkan ke queue job. Proses berjalan di background."
            : "Pengiriman semua data ke SATUSEHAT dijadwalkan ke antrian (Job Queue). Proses berjalan di background.";

        return response()->json([
            'success'      => true,
            'message'      => $message,
            'total_queued' => $count,
            'filters'      => $filters,
        ], 202);
    }

    
    public function failedIndex(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $resourceType = $request->query('resource_type', 'all');
        $keyword = trim($request->query('keyword', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 15;

        // Ambil log kegagalan dari SatuSehatStorageService
        $failedLogs = $this->storage->getFailedLogs($resourceType, $startDate, $endDate, $keyword);

        // Hanya membaca log kegagalan aktual dari storage aplikasi

        // Klasifikasikan semua item untuk menghitung ringkasan metric MedBridge
        $totalFailed = count($failedLogs);
        $retryable   = 0;
        $needRepair  = 0;
        $blocked     = 0;
        $maxRetry    = 0;

        $classifiedItems = [];
        foreach ($failedLogs as $item) {
            $obj = (object) $item;
            $visitId = (string) ($obj->visit_id ?? '');

            // Periksa dependency Encounter
            $resourceIds = $this->storage->getResourceIds($visitId);
            $encounterSuccess = !empty($resourceIds['Encounter']['resource_id']);

            $cls = ErrorClassifier::classify(
                isset($obj->http_status) ? (int) $obj->http_status : 500,
                $obj->error_message ?? null,
                in_array(($obj->resource_type ?? ''), ['Encounter', 'EpisodeOfCare'], true) ? true : $encounterSuccess,
                (int) ($obj->retry_count ?? 0)
            );

            $obj->classification = $cls;
            $classifiedItems[] = $obj;

            if ($cls['status'] === ErrorClassifier::STATUS_RETRYABLE) $retryable++;
            if ($cls['status'] === ErrorClassifier::STATUS_NEED_REPAIR) $needRepair++;
            if ($cls['status'] === ErrorClassifier::STATUS_BLOCKED) $blocked++;
            if ($cls['status'] === ErrorClassifier::STATUS_MAX_RETRY) $maxRetry++;
        }

        // Pagination
        $slice = array_slice($classifiedItems, ($page - 1) * $perPage, $perPage);
        $logs = new LengthAwarePaginator(
            $slice,
            $totalFailed,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $availableResources = ['Encounter', 'EpisodeOfCare', 'Condition', 'Observation', 'Medication', 'Procedure', 'ServiceRequest', 'Radiologi', 'Allergy'];

        return view('satusehat.failed.index', compact(
            'logs',
            'totalFailed',
            'retryable',
            'needRepair',
            'blocked',
            'maxRetry',
            'availableResources',
            'request'
        ));
    }

    /**
     * Halaman detail satu kegagalan di Failed Center.
     */
    public function failedShow(Request $request, $id)
    {
        $stagingId = $request->filled('staging_id') ? (int) $request->staging_id : null;
        $item = $this->repairService->getFailedItem($id, $stagingId);

        if (!$item) {
            return redirect()->route('satusehat.failed.index')->with('error', 'Log kegagalan tidak ditemukan.');
        }

        $log = $item['log'];
        $visitId = $item['visit_id'];
        $encounter = $item['encounter'];
        $stagingInfo = $item['staging_info'];
        $classification = $item['classification'];
        $is_encounter_success = $item['is_encounter_success'] ?? false;
        $validation = $this->repairService->validateStaging($id, $stagingId);

        $repairHistories = $this->storage->getRepairHistories($visitId);
        $allAttemptLogs = $this->storage->getLogs($visitId);

        return view('satusehat.failed.show', compact(
            'log',
            'visitId',
            'encounter',
            'stagingInfo',
            'classification',
            'is_encounter_success',
            'validation',
            'repairHistories',
            'allAttemptLogs'
        ));
    }

    /**
     * Form interaktif perbaikan tabel staging.
     */
    public function failedRepairForm(Request $request, $id)
    {
        $stagingId = $request->filled('staging_id') ? (int) $request->staging_id : null;
        $tableName = $request->input('table');
        $activeTab = $request->input('tab');

        $item = $this->repairService->getFailedItem($id, $stagingId, $tableName);

        if (!$item) {
            return redirect()->route('satusehat.failed.index')->with('error', 'Log atau data kunjungan tidak ditemukan.');
        }

        $log = $item['log'];
        $visitId = $item['visit_id'];
        $encounter = $item['encounter'];
        $allStaging = $item['all_staging'] ?? $this->repairService->getAllStagingData($visitId);
        $stagingInfo = $item['staging_info'];
        $is_encounter_success = $item['is_encounter_success'] ?? false;
        $validation = $this->repairService->validateStaging($id, $stagingId, $tableName);

        if (!$activeTab) {
            if ($tableName) {
                $tableToTab = [
                    'visit_encounter' => 'encounter',
                    'diagnosis'       => 'diagnosis',
                    'visit_farmasi'   => 'medication',
                    'visit_icd9'      => 'procedure',
                    'observation'     => 'observation',
                    'visit_lab'       => 'lab',
                    'visit_careplane' => 'careplan',
                    'visit_radiologi' => 'radiologi',
                    'visit_quisioner' => 'quisioner',
                    'visit_alergy'    => 'allergy',
                ];
                $activeTab = $tableToTab[$tableName] ?? 'encounter';
            } else {
                $resType = strtolower($log->resource_type ?? 'encounter');
                $resToTab = [
                    'encounter'             => 'encounter',
                    'episodeofcare'         => 'encounter',
                    'condition'             => 'diagnosis',
                    'diagnosa'              => 'diagnosis',
                    'medication'            => 'medication',
                    'farmasi'               => 'medication',
                    'procedure'             => 'procedure',
                    'icd9'                  => 'procedure',
                    'observation'           => 'observation',
                    'servicerequest'        => 'lab',
                    'lab'                   => 'lab',
                    'careplan'              => 'careplan',
                    'radiologi'             => 'radiologi',
                    'questionnaireresponse' => 'quisioner',
                    'allergy'               => 'allergy',
                ];
                $activeTab = $resToTab[$resType] ?? 'encounter';
            }
        }

        return view('satusehat.failed.repair', compact(
            'log',
            'visitId',
            'encounter',
            'allStaging',
            'activeTab',
            'stagingInfo',
            'stagingId',
            'is_encounter_success',
            'validation'
        ));
    }

    /**
     * Endpoint AJAX live validasi staging data.
     */
    public function failedValidateStaging(Request $request, $id)
    {
        $stagingId = $request->filled('staging_id') ? (int) $request->staging_id : null;
        $tableName = $request->input('table');
        $res = $this->repairService->validateStaging($id, $stagingId, $tableName);
        return response()->json($res);
    }

    /**
     * Menyimpan perbaikan form langsung ke database staging dan riwayat audit, serta opsi kirim ulang otomatis.
     */
    public function failedSaveRepair(Request $request, $id)
    {
        $stagingTable = $request->input('staging_table');
        $stagingId = $request->filled('staging_id') ? (int) $request->staging_id : null;
        $activeTab = $request->input('active_tab');
        $reason = $request->input('reason') ?: 'Perbaikan data staging SATUSEHAT';
        $user = auth()->user()->name ?? 'Petugas SIMRS';

        $inputs = $request->except([
            '_token', 'reason', 'submit_type', 'staging_id', 'staging_table',
            'resource_type', 'active_tab', 'redirect_to'
        ]);

        if ($stagingTable) {
            $result = $this->repairService->updateStagingDirect(
                $id,
                $stagingTable,
                $stagingId,
                $inputs,
                $reason,
                $user,
                $request->input('resource_type')
            );
        } else {
            $result = $this->repairService->updateStaging($id, $inputs, $reason, $user, $stagingId);
        }

        if (!$result['success']) {
            return redirect()->back()->with('error', 'Gagal update staging: ' . $result['message'])->withInput();
        }

        $redirectTo = $request->input('redirect_to');
        $routeParams = ['id' => $id];
        if ($activeTab) {
            $routeParams['tab'] = $activeTab;
        }
        if ($stagingId) {
            $routeParams['staging_id'] = $stagingId;
        }
        if ($stagingTable) {
            $routeParams['table'] = $stagingTable;
        }

        $destRoute = ($redirectTo === 'repair') ? 'satusehat.failed.repair' : 'satusehat.failed.show';

        if ($request->input('submit_type') === 'save_and_retry') {
            $retryResult = $this->repairService->retrySending($id);
            if ($retryResult['success']) {
                return redirect()->route($destRoute, $routeParams)
                    ->with('success', $result['message'] . ' Data berhasil dikirim ulang ke SATUSEHAT API!')
                    ->with('proposed_sql', $result['proposed_sql'] ?? null);
            } else {
                return redirect()->route($destRoute, $routeParams)
                    ->with('warning', $result['message'] . ' Namun pengiriman ulang belum berhasil: ' . ($retryResult['message'] ?? ''))
                    ->with('proposed_sql', $result['proposed_sql'] ?? null);
            }
        }

        return redirect()->route($destRoute, $routeParams)
            ->with('success', $result['message'])
            ->with('proposed_sql', $result['proposed_sql'] ?? null);
    }

    /**
     * Retry pengiriman satu item secara langsung.
     */
    public function failedRetrySingle($id)
    {
        $result = $this->repairService->retrySending($id);
        if ($result['success']) {
            return redirect()->route('satusehat.failed.show', $id)
                ->with('success', $result['message']);
        }

        return redirect()->route('satusehat.failed.show', $id)
            ->with('error', $result['message']);
    }

    /**
     * Penjadwalan retry otomatis ke background queue.
     */
    public function failedRetryQueue($id)
    {
        $item = $this->repairService->getFailedItem($id);
        $visitId = $item['visit_id'] ?? $id;

        SendSatusehatBundleJob::dispatch($visitId);

        return redirect()->route('satusehat.failed.show', $id)
            ->with('info', "Pengiriman ulang (Job Queue) untuk kunjungan #{$visitId} telah dijadwalkan di background.");
    }

    /**
     * Menampilkan riwayat perbaikan staging untuk audit trail.
     */
    public function failedHistory($id)
    {
        $item = $this->repairService->getFailedItem($id);
        if (!$item) {
            return redirect()->route('satusehat.failed.index')->with('error', 'Log tidak ditemukan.');
        }

        $log = $item['log'];
        $visitId = $item['visit_id'];
        $histories = $this->storage->getRepairHistories($visitId);

        return view('satusehat.failed.history', compact('log', 'visitId', 'histories'));
    }
}
