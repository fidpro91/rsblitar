<?php

namespace App\Libraries\Simrs;

use App\Libraries\SimrsInsert;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SimrsSyncService
{
    protected SimrsSyncStorageService $storage;
    protected string $connection = 'db_simrs';

    public function __construct(?SimrsSyncStorageService $storage = null)
    {
        $this->storage = $storage ?? new SimrsSyncStorageService();
    }

    /**
     * Uji konektivitas ke database SIMRS (Fast Fail Check)
     */
    public function testConnection(): array
    {
        $startTime = microtime(true);
        try {
            // Gunakan query sederhana untuk menguji handshake database
            $result = DB::connection($this->connection)->select("SELECT 1 AS ping");
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'success'  => true,
                'message'  => "Koneksi ke database SIMRS ({$this->connection}) berhasil terhubung.",
                'ping_ms'  => $duration,
            ];
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $msg = $e->getMessage();

            return [
                'success'  => false,
                'message'  => "Gagal terhubung ke database SIMRS: {$msg}",
                'ping_ms'  => $duration,
                'error'    => $msg
            ];
        }
    }

    /**
     * Tarik data SIMRS dengan mitigasi bottleneck (Chunking, Memory Control, Idempotent)
     *
     * @param array $options [
     *    'tanggal'     => '2026-10-23',
     *    'start_date'  => '2026-10-23',
     *    'end_date'    => '2026-10-23',
     *    'chunk_size'  => 50,
     *    'limit'       => null,
     *    'dry_run'     => false,
     *    'unit_types'  => [21, 23, 67],
     *    'trigger'     => 'manual'|'cli'|'scheduler',
     *    'simulate'    => false
     * ]
     * @return array
     */
    public function sync(array $options = []): array
    {
        $startExecTime = microtime(true);

        // 1. Normalisasi parameter tanggal
        $tanggal   = $options['tanggal'] ?? date('Y-m-d');
        $startDate = $options['start_date'] ?? $tanggal;
        $endDate   = $options['end_date'] ?? $tanggal;
        $chunkSize = max(10, min(200, (int) ($options['chunk_size'] ?? 50))); // default 50 untuk menjaga memory aman
        $limit     = !empty($options['limit']) ? (int) $options['limit'] : null;
        $dryRun    = !empty($options['dry_run']);
        $unitTypes = $options['unit_types'] ?? [21, 23, 67];
        $trigger   = $options['trigger'] ?? 'manual';
        $simulate  = !empty($options['simulate']);

        // 2. Mulai pencatatan Log Monitoring
        $logEntry = $this->storage->startLog([
            'trigger'    => $trigger,
            'tanggal'    => $tanggal,
            'start_date' => $startDate,
            'end_date'   => $endDate,
            'chunk_size' => $chunkSize,
            'limit'      => $limit,
            'dry_run'    => $dryRun,
        ]);
        $syncId = $logEntry['id'];

        $stats = [
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
        ];
        $chunksProcessed = 0;
        $warnings = [];

        try {
            // Mode Simulasi (untuk environment dev / testing offline jika SIMRS LAN tidak aktif)
            if ($simulate) {
                return $this->handleSimulation($syncId, $startDate, $endDate, $dryRun, $startExecTime);
            }

            // 3. Fast-Fail Connection Check
            $connCheck = $this->testConnection();
            if (!$connCheck['success']) {
                throw new \RuntimeException(
                    "Koneksi database SIMRS offline/timeout: " . $connCheck['message'] . 
                    " (Periksa konfigurasi DB_SIMRS_HOST di file .env dan pastikan jaringan LAN RS / VPN aktif)."
                );
            }

            // 4. Ambil master encounters dasar dari SIMRS
            $encounters = $this->fetchEncounters($startDate, $endDate, $unitTypes, $limit);

            if ($encounters->isEmpty()) {
                $duration = round(microtime(true) - $startExecTime, 2);
                $this->storage->finishLog($syncId, [
                    'status'           => 'SUCCESS',
                    'duration_seconds' => $duration,
                    'total_stats'      => $stats,
                    'chunks_processed' => 0,
                    'warnings'         => ['Tidak ada kunjungan pasien yang ditemukan pada rentang tanggal ' . $startDate . ' s/d ' . $endDate],
                ]);

                return [
                    'success'  => true,
                    'sync_id'  => $syncId,
                    'status'   => 'SUCCESS',
                    'message'  => "Tidak ada data kunjungan ditemukan untuk tanggal {$startDate} s/d {$endDate}",
                    'total'    => $stats,
                    'duration' => $duration,
                    'dry_run'  => $dryRun,
                ];
            }

            $totalFoundEncounters = $encounters->count();
            $stats['encounter'] = $totalFoundEncounters;

            // 5. Eksekusi proses secara Chunker untuk mengeliminasi memory spike & subquery bottleneck
            $encounterChunks = $encounters->chunk($chunkSize);

            foreach ($encounterChunks as $chunkIndex => $simrsChunk) {
                $chunkVisitIds = $simrsChunk->pluck('visit_id')->filter()->unique()->values()->toArray();

                if (empty($chunkVisitIds)) {
                    continue;
                }

                // Ambil data relasi terperinci HANYA untuk chunk visit_id saat ini
                $diagnosis   = $this->fetchDiagnosis($chunkVisitIds);
                $icd9        = $this->fetchTindakan($chunkVisitIds);
                $observation = $this->fetchVitalSigns($chunkVisitIds);
                $quisioner   = $this->fetchQuisioner($chunkVisitIds);
                $farmasi     = $this->fetchVisitFarmasi($chunkVisitIds);
                $lab         = $this->fetchVisitLab($chunkVisitIds);
                $allergi     = $this->fetchVisitAllergi($chunkVisitIds);
                $careplan    = $this->fetchVisitCareplan($chunkVisitIds);
                $radiologi   = $this->fetchVisitRadiologi($chunkVisitIds);

                // Akumulasikan counter
                $stats['diagnosis']   += $diagnosis->count();
                $stats['icd9']        += $icd9->count();
                $stats['observation'] += $observation->count();
                $stats['quisioner']   += $quisioner->count();
                $stats['farmasi']     += $farmasi->count();
                $stats['lab']         += $lab->count();
                $stats['allergi']     += $allergi->count();
                $stats['careplan']    += $careplan->count();
                $stats['radiologi']   += $radiologi->count();

                // 6. Simpan ke database staging jika bukan mode dry_run
                if (!$dryRun) {
                    $insertData = [
                        'visit_encounter' => $simrsChunk->map(fn($x) => (array) $x)->toArray(),
                        'diagnosis'       => $diagnosis->map(fn($x) => (array) $x)->toArray(),
                        'visit_icd9'      => $icd9->map(fn($x) => (array) $x)->toArray(),
                        'observation'     => $observation->map(fn($x) => (array) $x)->toArray(),
                        'visit_quisioner' => $quisioner->map(fn($x) => (array) $x)->toArray(),
                        'visit_farmasi'   => $farmasi->map(fn($x) => (array) $x)->toArray(),
                        'visit_lab'       => $lab->map(fn($x) => (array) $x)->toArray(),
                        'visit_allergi'   => $allergi->map(fn($x) => (array) $x)->toArray(),
                        'visit_careplan'  => $careplan->map(fn($x) => (array) $x)->toArray(),
                        'visit_radiologi' => $radiologi->map(fn($x) => (array) $x)->toArray(),
                    ];

                    $inserted = SimrsInsert::insert($insertData, $chunkSize);
                    if (!$inserted) {
                        $warnings[] = "Gagal melakukan insert staging untuk batch #" . ($chunkIndex + 1);
                    }
                }

                $chunksProcessed++;

                // Bersihkan variabel memori lokal per chunk untuk mencegah bottleneck memory leak
                unset($diagnosis, $icd9, $observation, $quisioner, $farmasi, $lab, $allergi, $careplan, $radiologi, $insertData);
            }

            $duration = round(microtime(true) - $startExecTime, 2);
            $finalStatus = empty($warnings) ? 'SUCCESS' : 'PARTIAL';

            $this->storage->finishLog($syncId, [
                'status'           => $finalStatus,
                'duration_seconds' => $duration,
                'total_stats'      => $stats,
                'chunks_processed' => $chunksProcessed,
                'warnings'         => $warnings,
            ]);

            return [
                'success'          => true,
                'sync_id'          => $syncId,
                'status'           => $finalStatus,
                'message'          => $dryRun ? "Dry-Run berhasil: {$totalFoundEncounters} kunjungan teranalisis." : "Berhasil menarik data {$totalFoundEncounters} kunjungan dari SIMRS.",
                'total'            => $stats,
                'chunks_processed' => $chunksProcessed,
                'duration'         => $duration,
                'warnings'         => $warnings,
                'dry_run'          => $dryRun,
            ];

        } catch (\Throwable $e) {
            $duration = round(microtime(true) - $startExecTime, 2);
            $errMsg = $e->getMessage();

            Log::error("SIMRS Sync Pipeline Failed [{$syncId}]: " . $errMsg, [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->storage->finishLog($syncId, [
                'status'           => 'FAILED',
                'duration_seconds' => $duration,
                'error_message'    => $errMsg,
                'chunks_processed' => $chunksProcessed,
                'total_stats'      => $stats,
            ]);

            return [
                'success'  => false,
                'sync_id'  => $syncId,
                'status'   => 'FAILED',
                'message'  => "Proses tarik data gagal: {$errMsg}",
                'total'    => $stats,
                'duration' => $duration,
                'error'    => $errMsg,
            ];
        }
    }

    /**
     * Mode simulasi / dry-run lokal ketika database remote SIMRS tidak dapat diakses
     */
    protected function handleSimulation(string $syncId, string $startDate, string $endDate, bool $dryRun, float $startExecTime): array
    {
        $mockCount = rand(5, 20);
        $stats = [
            'encounter'   => $mockCount,
            'diagnosis'   => $mockCount * rand(1, 2),
            'icd9'        => $mockCount,
            'observation' => $mockCount * 5,
            'farmasi'     => $mockCount * rand(1, 3),
            'lab'         => rand(1, 5),
            'allergi'     => rand(0, 2),
            'careplan'    => $mockCount,
            'radiologi'   => rand(0, 3),
            'quisioner'   => $mockCount,
        ];

        $duration = round(microtime(true) - $startExecTime, 2);

        $this->storage->finishLog($syncId, [
            'status'           => 'SUCCESS',
            'duration_seconds' => $duration,
            'total_stats'      => $stats,
            'chunks_processed' => 1,
            'warnings'         => ['Mode simulasi pengujian (Demo Mode).'],
        ]);

        return [
            'success'          => true,
            'sync_id'          => $syncId,
            'status'           => 'SUCCESS',
            'message'          => "Simulasi penarikan data SIMRS berhasil ({$mockCount} data teruji)",
            'total'            => $stats,
            'chunks_processed' => 1,
            'duration'         => $duration,
            'dry_run'          => $dryRun,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SUMBER SIMRS (KONSEP ASLI DIPERTAHANKAN DENGAN OPTIMASI QUERY)
    |--------------------------------------------------------------------------
    */

    public function fetchEncounters(string $startDate, string $endDate, array $unitTypes = [21, 23, 67], ?int $limit = null)
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->endOfDay();

        $query = DB::connection($this->connection)
            ->table('yanmed.visit as v')
            ->join('yanmed.services as s', 'v.visit_id', '=', 's.visit_id')
            ->join('yanmed.patient as px', 'v.px_id', '=', 'px.px_id')
            ->join('kemkes.patient as p', 'px.px_id', '=', 'p.px_id_simrs')
            ->join('kemkes.location as lo', 's.unit_id', '=', 'lo.unit_id_simrs')
            ->join('kemkes.practitioner as pr', 's.par_id', '=', 'pr.par_id')
            ->join('admin.ms_unit as un', 'un.unit_id', '=', 's.unit_id')
            ->leftJoin('yanmed.admission as adm', 'adm.visit_id', '=', 'v.visit_id')
            ->select(
                'v.visit_id',
                'px.px_noktp',
                'px.px_norm',
                'px.px_name',
                'p.patient_id_kemkes',
                'lo.location_name',
                'lo.location_id_kemkes as idunitsatset',
                'pr.practitioner_name as dpjp',
                'pr.practitioner_id as kode_dokter',
                'v.visit_date as tgl_kunjung',
                's.srv_in as tgl_dilayani',
                's.srv_out as tgl_selesai_dilayani',
                'v.visit_end_date as tgl_pulang',
                's.srv_type as tipe_kunjungan',
                's.srv_id'
            )
            ->where('v.visit_end_date', '>=', $start)
            ->where('v.visit_end_date', '<=', $end)
            ->whereIn('un.unit_type', $unitTypes)
            ->whereNull('adm.admission_id')
            ->where('s.srv_status', '!=', 35)
            ->where('before_srv_id', '=', '0');

        if ($limit && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function fetchDiagnosis(array $visitIds)
    {
        if (empty($visitIds)) return collect();

        return DB::connection($this->connection)
            ->table('yanmed.services as s')
            ->join('yanmed.diagnosa as d', 's.srv_id', '=', 'd.srv_id')
            ->join('claim.coding_diagnosa as cd', 'd.diagnosa_id', '=', 'cd.diagnosa_id')
            ->join('yanmed.ms_icd as i', 'cd.icd_id_coding', '=', 'i.icd_id')
            ->select(
                's.visit_id',
                'i.icd_code',
                'i.icd_name',
                'cd.urut',
                's.srv_id'
            )
            ->whereIn('s.visit_id', $visitIds)
            ->orderBy('s.visit_id')
            ->orderBy('cd.urut', 'asc')
            ->get();
    }

    public function fetchTindakan(array $visitIds)
    {
        if (empty($visitIds)) return collect();

        return DB::connection($this->connection)
            ->table('yanmed.services as s')
            ->join('yanmed.billing as b', 's.srv_id', '=', 'b.srv_id')
            ->join('claim.coding_tindakan as ct', 'b.billing_id', '=', 'ct.billing_id')
            ->join('yanmed.ms_icd as i', 'ct.icd_id_coding', '=', 'i.icd_id')
            ->select(
                's.visit_id',
                'i.icd_code',
                'i.icd_name',
                's.srv_id'
            )
            ->whereIn('s.visit_id', $visitIds)
            ->distinct()
            ->get();
    }

    public function fetchVitalSigns(array $visitIds)
    {
        if (empty($visitIds)) return collect();

        $rows = DB::connection($this->connection)
            ->table('yanmed.services as s')
            ->join('ermgwk.vital_sign as vt', 's.srv_id', '=', 'vt.srv_id')
            ->select(
                's.visit_id',
                'vt.sistol',
                'vt.diastol',
                'vt.nadi',
                'vt.rr',
                'vt.suhu',
                'vt.spo2',
                's.srv_id'
            )
            ->whereIn('s.visit_id', $visitIds)
            ->get();

        $results = collect();
        foreach ($rows as $row) {
            $params = [
                ['sistol', $row->sistol, 1],
                ['diastol', $row->diastol, 2],
                ['nadi', $row->nadi, 3],
                ['rr', $row->rr, 4],
                ['suhu', $row->suhu, 5],
                ['spo2', $row->spo2, 6]
            ];
            foreach ($params as [$param, $nilai, $vital_id]) {
                if ($nilai !== null && $nilai !== '') {
                    $results->push([
                        'visit_id'  => $row->visit_id,
                        'PARAMETER' => $param,
                        'nilai'     => $nilai,
                        'vital_id'  => $vital_id,
                        'srv_id'    => $row->srv_id
                    ]);
                }
            }
        }

        return $results;
    }

    public function fetchQuisioner(array $visitIds)
    {
        if (empty($visitIds)) return collect();

        $questions = [
            1 => 'Apakah identitas pasien pada resep lengkap?',
            2 => 'Apakah nama obat, dosis, dan aturan pakai sudah jelas?',
            3 => 'Apakah dosis obat sudah sesuai dengan kondisi pasien?',
            4 => 'Apakah terdapat duplikasi terapi obat?',
            5 => 'Apakah terdapat potensi interaksi obat?',
            6 => 'Apakah terdapat kontraindikasi obat pada pasien?',
            7 => 'Apakah obat sesuai dengan indikasi diagnosis?',
        ];

        $visits = DB::connection($this->connection)
            ->table('yanmed.visit as v')
            ->join('farmasi.sale as s', 'v.visit_id', '=', 's.visit_id')
            ->select(
                'v.visit_id',
                's.date_act',
                's.service_id as srv_id'
            )
            ->whereNotNull('s.service_id')
            ->whereIn('v.visit_id', $visitIds)
            ->groupBy('v.visit_id', 's.date_act', 's.service_id')
            ->get();

        $result = collect();
        foreach ($visits as $visit) {
            $questionnaire = [];
            foreach ($questions as $kode => $teks) {
                $questionnaire['pertanyaan_' . $kode] = [
                    'teks'    => $teks,
                    'jawaban' => true
                ];
            }
            $result->push([
                'visit_id'                   => $visit->visit_id,
                'srv_id'                     => $visit->srv_id,
                'visit_date'                 => $visit->date_act,
                'questionnaire_telaah_resep' => $questionnaire
            ]);
        }

        return $result;
    }

    public function fetchVisitFarmasi(array $visitIds)
    {
        if (empty($visitIds)) return collect();

        return DB::connection($this->connection)
            ->table('farmasi.sale as s')
            ->join('yanmed.recipe as r', 's.sale_id', '=', 'r.sale_id')
            ->join('farmasi.sale_detail as sd', 's.sale_id', '=', 'sd.sale_id')
            ->join('admin.ms_item as i', 'sd.item_id', '=', 'i.item_id')
            ->join('kemkes.practitioner as p', 's.doctor_id', '=', 'p.par_id')
            ->join('kemkes.location as lo', 's.unit_id', '=', 'lo.unit_id_simrs')
            ->select(
                's.visit_id',
                's.service_id as srv_id',
                'i.item_id',
                'i.kode_satusehat',
                'r.rcp_date',
                's.date_act',
                'p.practitioner_name',
                'p.practitioner_id',
                's.sale_num',
                'sd.sale_qty',
                'sd.racikan',
                'sd.dosis',
                'lo.location_name',
                'lo.location_id_kemkes'
            )
            ->whereNotNull('i.kode_satusehat')
            ->whereIn('s.visit_id', $visitIds)
            ->distinct()
            ->get();
    }

    public function fetchVisitLab(array $visitIds)
    {
        if (empty($visitIds)) return collect();

        return DB::connection($this->connection)
            ->table('yanmed.services as s')
            ->join('yanmed.checkup as c', 's.srv_id', '=', 'c.service_id')
            ->join('yanmed.ms_check as mc', 'c.ms_check_id', '=', 'mc.idcheck')
            ->join('kemkes.practitioner as pr', 'c.par_id', '=', 'pr.par_id')
            ->join('kemkes.practitioner as pr2', 'c.par_id_requester', '=', 'pr2.par_id')
            ->select(
                's.visit_id',
                's.before_srv_id as srv_id',
                'mc.namecheck',
                'pr.practitioner_name as dokter_lab',
                'pr.practitioner_id as kode_dokter_lab',
                'c.scheduled_at as tgl_periksa',
                'c.done_at as tgl_selesai',
                'pr2.practitioner_name as dokter_pengirim',
                'pr2.practitioner_id as kode_pengirim',
                'c.result',
                'mc.kode_satusehat'
            )
            ->whereNotNull('mc.kode_satusehat')
            ->whereIn('s.visit_id', $visitIds)
            ->get();
    }

    public function fetchVisitAllergi(array $visitIds)
    {
        if (empty($visitIds)) return collect();

        return DB::connection($this->connection)
            ->table('yanmed.allergy as a')
            ->join('yanmed.visit as v', 'a.px_id', '=', 'v.px_id')
            ->join('yanmed.services as s', 'v.visit_id', '=', 's.visit_id')
            ->select(
                'satsetid',
                'v.visit_id',
                'allergy_date',
                'allergy_desc',
                's.before_srv_id as srv_id'
            )
            ->whereNotNull('satsetid')
            ->where('before_srv_id', '=', '0')
            ->whereIn('v.visit_id', $visitIds)
            ->get();
    }

    public function fetchVisitCareplan(array $visitIds)
    {
        if (empty($visitIds)) return collect();

        return DB::connection($this->connection)
            ->table('ermgwk.pemeriksaan_fisik as pf')
            ->join('yanmed.services as s', 'pf.srv_id', '=', 's.srv_id')
            ->select(
                's.visit_id',
                's.srv_id',
                'pf.subtl',
                DB::raw("CASE
                    WHEN LOWER(pf.subtl) LIKE '%rawat inap%' THEN 'Pasien Melanjutkan rawat inap'
                    WHEN LOWER(pf.subtl) LIKE '%atas permintaan sendiri%' THEN 'Pasien Meminta pulang atas kemauan sendiri'
                    WHEN LOWER(pf.subtl) LIKE '%boleh pulang%' AND pf.kontrol = 'Ya' THEN 'Pasien Pulang dengan kontrol kembali'
                    WHEN LOWER(pf.subtl) LIKE '%boleh pulang%' AND pf.kontrol = 'Tidak' THEN 'Pasien Pulang dengan kondisi baik tidak perlu kontrol'
                    WHEN LOWER(pf.subtl) LIKE '%rujuk%' OR LOWER(pf.subtl) LIKE '%dirujuk%' OR LOWER(pf.subtl) LIKE '%rujukan%' THEN 'Pasien dirujuk'
                    ELSE 'Tidak diketahui'
                END as alasan"),
                DB::raw("CASE
                    WHEN LOWER(pf.subtl) LIKE '%rujuk%' OR LOWER(pf.subtl) LIKE '%dirujuk%' OR LOWER(pf.subtl) LIKE '%rujukan%' THEN NULLIF(pf.rujukke, '')
                    ELSE COALESCE(NULLIF(pf.alasanaps, ''), NULLIF(pf.edu, ''))
                END as keterangan")
            )
            ->whereIn('s.visit_id', $visitIds)
            ->get();
    }

    public function fetchVisitRadiologi(array $visitIds)
    {
        if (empty($visitIds)) return collect();

        return DB::connection($this->connection)
            ->table('yanmed.checkup as ck')
            ->join('yanmed.services as srv', 'srv.srv_id', '=', 'ck.service_id')
            ->join('yanmed.ms_check as mc', 'ck.ms_check_id', '=', 'mc.idcheck')
            ->join('kemkes.practitioner as e', 'ck.par_id', '=', 'e.par_id')
            ->join('yanmed.services as srv2', 'srv2.srv_id', '=', 'srv.before_srv_id')
            ->leftJoin('kemkes.practitioner as e1', 'e1.par_id', '=', 'srv2.par_id')
            ->select([
                DB::raw("CONCAT('RAD-', ck.id) AS assesion_number"),
                'srv.visit_id',
                'srv.srv_id',
                'srv.srv_date_only AS tanggal_order',
                'mc.namecheck AS nama_pemeriksaan',
                'e.practitioner_name AS dokter_radiologi',
                'e.practitioner_id AS kode_dokter_radiologi',
                'e1.practitioner_name AS dokter_pengirim',
                'e1.practitioner_id AS kode_dokter_pengirim',
                'ck.scheduled_at AS tanggal_pemeriksaan',
                'ck.done_at AS tanggal_hasil',
                'ck.result AS hasil_pemeriksaan',
                'srv.before_srv_id',
                'srv2.par_id',
                'map_radiologi'
            ])
            ->where('srv.unit_id', '=', '73')
            ->whereIn('srv.visit_id', $visitIds)
            ->get();
    }
}
