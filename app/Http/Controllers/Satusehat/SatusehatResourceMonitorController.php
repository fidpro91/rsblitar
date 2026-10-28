<?php

namespace App\Http\Controllers\Satusehat;

use App\Http\Controllers\Controller;
use App\Models\Respon_satusehat;
use App\Libraries\SatuSehat\ErrorClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SatusehatResourceMonitorController extends Controller
{
    /**
     * Definisi metadata resource FHIR SATUSEHAT
     */
    protected array $resourceDefinitions = [
        'Encounter' => [
            'name'        => 'Encounter',
            'label'       => 'Kunjungan Pasien',
            'icon'        => 'bi-hospital',
            'color'       => '#0ea5e9',
            'description' => 'Pencatatan kunjungan, registrasi, kontak medis, dan rujukan pasien.',
        ],
        'Condition' => [
            'name'        => 'Condition',
            'label'       => 'Diagnosis (ICD-10)',
            'icon'        => 'bi-heart-pulse',
            'color'       => '#f59e0b',
            'description' => 'Diagnosis primer, sekunder, dan riwayat keluhan medis pasien.',
        ],
        'Observation' => [
            'name'        => 'Observation',
            'label'       => 'Tanda Vital & Lab',
            'icon'        => 'bi-clipboard2-pulse',
            'color'       => '#10b981',
            'description' => 'Tanda-tanda vital (TTV), antropometri, dan hasil pemeriksaan klinis.',
        ],
        'Procedure' => [
            'name'        => 'Procedure',
            'label'       => 'Tindakan Medis (ICD-9)',
            'icon'        => 'bi-bandaid',
            'color'       => '#8b5cf6',
            'description' => 'Tindakan medis, operasi bedah, dan intervensi terapeutik.',
        ],
        'Medication' => [
            'name'        => 'Medication',
            'label'       => 'Master Obat',
            'icon'        => 'bi-capsule',
            'color'       => '#ec4899',
            'description' => 'Katalog dan referensi sediaan obat berkode KFA SATUSEHAT.',
        ],
        'MedicationRequest' => [
            'name'        => 'MedicationRequest',
            'label'       => 'Resep / Instruksi Obat',
            'icon'        => 'bi-prescription2',
            'color'       => '#06b6d4',
            'description' => 'Instruksi peresepan obat dari DPJP untuk pasien.',
        ],
        'MedicationDispense' => [
            'name'        => 'MedicationDispense',
            'label'       => 'Dispensing Farmasi',
            'icon'        => 'bi-box-seam',
            'color'       => '#3b82f6',
            'description' => 'Penyerahan dan realisasi obat oleh instalasi farmasi.',
        ],
        'CarePlan' => [
            'name'        => 'CarePlan',
            'label'       => 'Rencana Asuhan Medis',
            'icon'        => 'bi-calendar2-check',
            'color'       => '#6366f1',
            'description' => 'Rencana perawatan, target terapi, dan tindak lanjut pasien.',
        ],
        'Composition' => [
            'name'        => 'Composition',
            'label'       => 'Resume Medis & Diet',
            'icon'        => 'bi-file-earmark-medical',
            'color'       => '#14b8a6',
            'description' => 'Dokumen klinis, ringkasan kepulangan, dan asuhan nutrisi.',
        ],
        'ClinicalImpression' => [
            'name'        => 'ClinicalImpression',
            'label'       => 'Kajian Medis DPJP',
            'icon'        => 'bi-person-badge',
            'color'       => '#f97316',
            'description' => 'Prognosis, asesmen klinis, dan evaluasi kondisi pasien.',
        ],
        'EpisodeOfCare' => [
            'name'        => 'EpisodeOfCare',
            'label'       => 'Masa Perawatan',
            'icon'        => 'bi-clock-history',
            'color'       => '#64748b',
            'description' => 'Periode episode perawatan rawat inap / rawat jalan berkesinambungan.',
        ],
        'ServiceRequest' => [
            'name'        => 'ServiceRequest',
            'label'       => 'Order Penunjang / Lab',
            'icon'        => 'bi-send-check',
            'color'       => '#84cc16',
            'description' => 'Permintaan rujukan laboratorium, radiologi, atau konsul.',
        ],
        'DiagnosticReport' => [
            'name'        => 'DiagnosticReport',
            'label'       => 'Hasil Diagnostik',
            'icon'        => 'bi-file-earmark-text',
            'color'       => '#a855f7',
            'description' => 'Laporan interpretasi hasil pemeriksaan penunjang/lab.',
        ],
        'Specimen' => [
            'name'        => 'Specimen',
            'label'       => 'Spesimen Lab',
            'icon'        => 'bi-eyedropper',
            'color'       => '#e11d48',
            'description' => 'Sampel darah, urin, atau jaringan biologis laboratorium.',
        ],
        'QuestionnaireResponse' => [
            'name'        => 'QuestionnaireResponse',
            'label'       => 'Kuesioner / Skrining',
            'icon'        => 'bi-ui-checks',
            'color'       => '#d97706',
            'description' => 'Jawaban skrining awal, triase, atau asesmen keperawatan.',
        ],
    ];

    /**
     * Halaman Dashboard Monitoring Aliran Data Resource
     */
    public function index(Request $request)
    {
        $startDate    = $request->query('start_date');
        $endDate      = $request->query('end_date');
        $resourceType = $request->query('resource_type', 'ALL');
        $statusFilter = strtoupper($request->query('status', 'ALL'));
        $search       = trim($request->query('search', ''));
        $perPage      = max(10, min(100, (int) $request->query('per_page', 20)));

        // 1. Hitung ringkasan statistik per-resource
        $resourceStats = $this->calculateResourceStats($startDate, $endDate);

        // 2. Hitung KPI keseluruhan
        $totalProcessed = array_sum(array_column($resourceStats, 'total'));
        $totalSuccess   = array_sum(array_column($resourceStats, 'success'));
        $totalFailed    = array_sum(array_column($resourceStats, 'failed'));
        $successRate    = $totalProcessed > 0 ? round(($totalSuccess / $totalProcessed) * 100, 2) : 100.0;

        $overallSummary = [
            'total'        => $totalProcessed,
            'success'      => $totalSuccess,
            'failed'       => $totalFailed,
            'success_rate' => $successRate,
        ];

        // 3. Query daftar log aliran data real-time (tabel stream)
        $streamQuery = DB::table('respon_satusehat');

        if (!empty($startDate)) {
            if (!empty($endDate)) {
                $streamQuery->whereDate('tgl_kirim', '>=', $startDate)
                            ->whereDate('tgl_kirim', '<=', $endDate);
            } else {
                $streamQuery->whereDate('tgl_kirim', $startDate);
            }
        } elseif (!empty($endDate)) {
            $streamQuery->whereDate('tgl_kirim', '<=', $endDate);
        }

        if ($resourceType !== 'ALL' && !empty($resourceType)) {
            $streamQuery->where('resourcetype', $resourceType);
        }

        if ($statusFilter === 'SUCCESS') {
            $streamQuery->where(function ($q) {
                $q->where('status', 'like', '%201%')
                  ->orWhere('status', 'SUCCESS');
            });
        } elseif ($statusFilter === 'FAILED') {
            $streamQuery->where(function ($q) {
                $q->where('status', 'not like', '%201%')
                  ->where('status', '!=', 'SUCCESS');
            });
        }

        if (!empty($search)) {
            $streamQuery->where(function ($q) use ($search) {
                $q->where('visit_id', 'like', "%{$search}%")
                  ->orWhere('pasien_id', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhere('resourcetype', 'ilike', "%{$search}%");
            });
        }

        $paginator = $streamQuery->orderBy('id_respon', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // 4. Dekorasi setiap baris log untuk UI yang kaya
        $decoratedLogs = collect($paginator->items())->map(function ($row) {
            $isSuccess = str_contains($row->status ?? '', '201') || ($row->status ?? '') === 'SUCCESS';
            $def = $this->resourceDefinitions[$row->resourcetype] ?? null;

            // Ekstraksi pesan dan diagnostics dari respon_all
            $diagnosticText = '-';
            $issueDetails = [];
            if (!empty($row->respon_all)) {
                $decoded = is_string($row->respon_all) ? json_decode($row->respon_all, true) : $row->respon_all;
                if (is_array($decoded)) {
                    if (isset($decoded['issue']) && is_array($decoded['issue'])) {
                        foreach ($decoded['issue'] as $iss) {
                            $text = $iss['details']['text'] ?? $iss['diagnostics'] ?? null;
                            if ($text) {
                                $issueDetails[] = $text;
                            }
                        }
                    }
                    if (empty($issueDetails)) {
                        $diagnosticText = $decoded['message'] ?? $decoded['error'] ?? ($decoded['text']['status'] ?? '-');
                    } else {
                        $diagnosticText = implode('; ', $issueDetails);
                    }
                }
            }

            if ($isSuccess && empty($issueDetails)) {
                $diagnosticText = !empty($row->resourceid)
                    ? 'ID: ' . $row->resourceid
                    : 'Resource berhasil dibuat di SATUSEHAT.';
            }

            return (object) [
                'id_respon'       => $row->id_respon,
                'visit_id'        => $row->visit_id,
                'pasien_id'       => $row->pasien_id,
                'resourcetype'    => $row->resourcetype,
                'resource_label'  => $def['label'] ?? $row->resourcetype,
                'resource_icon'   => $def['icon'] ?? 'bi-file-earmark',
                'resource_color'  => $def['color'] ?? '#6b7280',
                'resourceid'      => $row->resourceid,
                'status'          => $row->status,
                'is_success'      => $isSuccess,
                'metode'          => $row->metode ?? 'POST',
                'tgl_kirim'       => $row->tgl_kirim,
                'tgl_kirim_human' => $row->tgl_kirim ? Carbon::parse($row->tgl_kirim)->diffForHumans() : '-',
                'diagnostic_text' => $diagnosticText,
                'has_raw_respon'  => !empty($row->respon_all),
            ];
        });

        // Bentuk data untuk grafik visual (Chart.js)
        $chartData = [
            'labels'       => array_keys($resourceStats),
            'success_data' => array_column($resourceStats, 'success'),
            'failed_data'  => array_column($resourceStats, 'failed'),
        ];

        return view('satusehat.resource_monitor', [
            'summary'             => $overallSummary,
            'resourceStats'       => $resourceStats,
            'resourceDefinitions' => $this->resourceDefinitions,
            'logs'                => $decoratedLogs,
            'paginator'           => $paginator,
            'chartData'           => $chartData,
            'filters'             => [
                'start_date'    => $startDate,
                'end_date'      => $endDate,
                'resource_type' => $resourceType,
                'status'        => $statusFilter,
                'search'        => $search,
                'per_page'      => $perPage,
            ],
        ]);
    }

    /**
     * Hitung statistik berhasil vs gagal per resource
     */
    protected function calculateResourceStats(?string $startDate = null, ?string $endDate = null): array
    {
        $stats = [];
        foreach ($this->resourceDefinitions as $key => $def) {
            $stats[$key] = [
                'key'           => $key,
                'name'          => $def['name'],
                'label'         => $def['label'],
                'icon'          => $def['icon'],
                'color'         => $def['color'],
                'description'   => $def['description'],
                'total'         => 0,
                'success'       => 0,
                'failed'        => 0,
                'success_rate'  => 100.0,
                'health'        => 'OPTIMAL', // OPTIMAL | WARNING | CRITICAL
                'last_activity' => null,
            ];
        }

        // 1. Hitung dari respon_satusehat untuk resource bertipe langsung
        $query = DB::table('respon_satusehat')
            ->select(
                'resourcetype',
                DB::raw("COUNT(CASE WHEN status LIKE '%201%' OR status = 'SUCCESS' THEN 1 END) as success_count"),
                DB::raw("COUNT(CASE WHEN status NOT LIKE '%201%' AND status != 'SUCCESS' THEN 1 END) as failed_count"),
                DB::raw("MAX(tgl_kirim) as last_activity")
            )
            ->whereIn('resourcetype', array_keys($this->resourceDefinitions));

        if (!empty($startDate)) {
            if (!empty($endDate)) {
                $query->whereDate('tgl_kirim', '>=', $startDate)->whereDate('tgl_kirim', '<=', $endDate);
            } else {
                $query->whereDate('tgl_kirim', $startDate);
            }
        } elseif (!empty($endDate)) {
            $query->whereDate('tgl_kirim', '<=', $endDate);
        }

        $directRows = $query->groupBy('resourcetype')->get();

        foreach ($directRows as $r) {
            $k = $r->resourcetype;
            if (isset($stats[$k])) {
                $stats[$k]['success']       = (int) $r->success_count;
                $stats[$k]['failed']        += (int) $r->failed_count;
                $stats[$k]['total']         = $stats[$k]['success'] + $stats[$k]['failed'];
                $stats[$k]['last_activity'] = $r->last_activity;
            }
        }

        // 2. Hubungkan kegagalan dari bundle / OperationOutcome ke resource terkait
        $outcomeQuery = DB::table('respon_satusehat')
            ->whereIn('resourcetype', ['OperationOutcome', 'BundleResponse'])
            ->where('status', 'not like', '%201%')
            ->where('status', '!=', 'SUCCESS');

        if (!empty($startDate)) {
            if (!empty($endDate)) {
                $outcomeQuery->whereDate('tgl_kirim', '>=', $startDate)->whereDate('tgl_kirim', '<=', $endDate);
            } else {
                $outcomeQuery->whereDate('tgl_kirim', $startDate);
            }
        } elseif (!empty($endDate)) {
            $outcomeQuery->whereDate('tgl_kirim', '<=', $endDate);
        }

        // Ambil sampel transaksi outcome error (maks 500 baris terbaru untuk efisiensi)
        $outcomeRows = $outcomeQuery->select('respon_all', 'tgl_kirim')->take(500)->get();

        foreach ($outcomeRows as $item) {
            if (empty($item->respon_all)) continue;
            $decoded = is_string($item->respon_all) ? json_decode($item->respon_all, true) : $item->respon_all;
            if (!is_array($decoded) || !isset($decoded['issue']) || !is_array($decoded['issue'])) continue;

            foreach ($decoded['issue'] as $issue) {
                $exprList = (array) ($issue['expression'] ?? []);
                $text     = (string) ($issue['details']['text'] ?? ($issue['diagnostics'] ?? ''));

                foreach ($this->resourceDefinitions as $rk => $rdef) {
                    $matched = false;
                    foreach ($exprList as $expr) {
                        if (str_contains($expr, $rk)) {
                            $matched = true;
                            break;
                        }
                    }
                    if ($matched || str_contains($text, $rk)) {
                        $stats[$rk]['failed']++;
                        $stats[$rk]['total']++;
                        if (!$stats[$rk]['last_activity'] || $item->tgl_kirim > $stats[$rk]['last_activity']) {
                            $stats[$rk]['last_activity'] = $item->tgl_kirim;
                        }
                    }
                }
            }
        }

        // 3. Hitung success_rate & health level
        foreach ($stats as $k => &$item) {
            $tot = $item['total'];
            $succ = $item['success'];
            $fail = $item['failed'];

            if ($tot > 0) {
                $rate = round(($succ / $tot) * 100, 2);
                $item['success_rate'] = $rate;

                if ($fail === 0 && $succ > 0) {
                    $item['health'] = 'OPTIMAL';
                } elseif ($rate >= 90.0) {
                    $item['health'] = 'WARNING';
                } else {
                    $item['health'] = 'CRITICAL';
                }
            } else {
                $item['success_rate'] = 100.0;
                $item['health'] = 'OPTIMAL';
            }
        }
        unset($item);

        return $stats;
    }

    /**
     * API JSON Live Polling untuk Stream Aliran Resource
     */
    public function streamData(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate   = $request->query('end_date');

        $stats = $this->calculateResourceStats($startDate, $endDate);

        $totalProcessed = array_sum(array_column($stats, 'total'));
        $totalSuccess   = array_sum(array_column($stats, 'success'));
        $totalFailed    = array_sum(array_column($stats, 'failed'));
        $rate           = $totalProcessed > 0 ? round(($totalSuccess / $totalProcessed) * 100, 2) : 100.0;

        // Ambil 10 log transmisi terbaru
        $recentLogs = DB::table('respon_satusehat')
            ->orderBy('id_respon', 'desc')
            ->take(10)
            ->get()
            ->map(function ($r) {
                $def = $this->resourceDefinitions[$r->resourcetype] ?? null;
                return [
                    'id_respon'    => $r->id_respon,
                    'visit_id'     => $r->visit_id,
                    'pasien_id'    => $r->pasien_id,
                    'resourcetype' => $r->resourcetype,
                    'resource_icon'=> $def['icon'] ?? 'bi-file-earmark',
                    'status'       => $r->status,
                    'is_success'   => str_contains($r->status ?? '', '201') || ($r->status ?? '') === 'SUCCESS',
                    'tgl_kirim'    => $r->tgl_kirim,
                    'tgl_human'    => $r->tgl_kirim ? Carbon::parse($r->tgl_kirim)->diffForHumans() : '-',
                ];
            });

        return response()->json([
            'success'   => true,
            'timestamp' => now()->toIso8601String(),
            'summary'   => [
                'total'        => $totalProcessed,
                'success'      => $totalSuccess,
                'failed'       => $totalFailed,
                'success_rate' => $rate,
            ],
            'resources' => array_values($stats),
            'chart'     => [
                'labels'       => array_keys($stats),
                'success_data' => array_column($stats, 'success'),
                'failed_data'  => array_column($stats, 'failed'),
            ],
            'recent_logs' => $recentLogs,
        ]);
    }

    /**
     * API JSON Detail Log Transaksi (Modal Inspeksi Respon SATUSEHAT)
     */
    public function getLogDetail($id)
    {
        $log = DB::table('respon_satusehat')->where('id_respon', $id)->first();

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Data log transmisi tidak ditemukan.'
            ], 404);
        }

        $decodedRespon = is_string($log->respon_all) ? json_decode($log->respon_all, true) : $log->respon_all;
        $def = $this->resourceDefinitions[$log->resourcetype] ?? null;

        // Analisis error jika status bukan 201
        $errorClassification = null;
        if (!str_contains($log->status ?? '', '201') && ($log->status ?? '') !== 'SUCCESS') {
            $msg = '';
            if (is_array($decodedRespon)) {
                $msg = $decodedRespon['message'] ?? $decodedRespon['error'] ?? '';
                if (isset($decodedRespon['issue'][0]['details']['text'])) {
                    $msg = $decodedRespon['issue'][0]['details']['text'];
                }
            }
            $httpCode = (int) ($decodedRespon['_http_status'] ?? (preg_match('/(\d{3})/', $log->status, $m) ? $m[1] : 400));
            $errorClassification = ErrorClassifier::classify($httpCode, $msg);
        }

        return response()->json([
            'success' => true,
            'log'     => [
                'id_respon'          => $log->id_respon,
                'visit_id'           => $log->visit_id,
                'pasien_id'          => $log->pasien_id,
                'resourcetype'       => $log->resourcetype,
                'resource_label'     => $def['label'] ?? $log->resourcetype,
                'resourceid'         => $log->resourceid,
                'status'             => $log->status,
                'metode'             => $log->metode ?? 'POST',
                'tgl_kirim'          => $log->tgl_kirim,
                'respon_all'         => $decodedRespon,
                'error_analysis'     => $errorClassification,
                'detail_visit_url'   => !empty($log->visit_id) ? route('satusehat.detail', $log->visit_id) : null,
                'repair_url'         => !empty($log->visit_id) ? route('satusehat.failed.show', $log->visit_id) : null,
            ]
        ]);
    }
}
