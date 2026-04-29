<?php

namespace App\Http\Controllers\Simrs;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Libraries\SimrsInsert;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SimrsController extends Controller
{
   
    public function get_all()
    {
        set_time_limit(300);
        $simrsData = $this->getSimrsData(false);
        if (!$simrsData || $simrsData->isEmpty()) {
            return response()->json(['message' => 'Tidak ada data ditemukan'], 404);
        }

        $simrsData->chunk(100)->each(function ($simrsChunk) {
            $visitIds = $simrsChunk->pluck('visit_id')->toArray();
            $dataInsert = [
                'visit_encounter' => $simrsChunk->map(fn($row) => (array) $row)->toArray(),
                'diagnosis' => $this->getDiagnosa($visitIds, false)->toArray(),
                'visit_icd9' => $this->getTindakan($visitIds, false)->toArray(),
                'observation' => $this->getVitalSigns($visitIds, false)->toArray(),
                'visit_quisioner' => $this->getQuisioner($visitIds, false)->toArray(),
                'visit_farmasi' => $this->getVisitFarmasi($visitIds, false)->toArray(),
                'visit_lab' => $this->getVisitLab($visitIds, false)->toArray(),
                'visit_allergi' => $this->visitAllergi($visitIds, false)->toArray(),
                'visit_careplan' => $this->visitCareplan($visitIds, false)->toArray(),
                'visit_radiologi' => $this->visitRadiologi($visitIds, false)->toArray(),
            ];

           //  dd($dataInsert);
            SimrsInsert::insert($dataInsert);
        });
   
        return response()->json([
            'message' => 'Data berhasil diinsert (chunked)'
        ]);
    }

    public function getSimrsData($withResponse = true)
    {
        $start = Carbon::now()->subDays(4)->startOfDay();
        $end = Carbon::now()->subDays(4)->endOfDay();

        $simrsData = DB::connection('db_simrs')
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
                's.srv_type as tipe_kunjungan'
            )
            ->where('v.visit_end_date', '>=', $start)
            ->where('v.visit_end_date', '<=', $end)
            ->whereIn('un.unit_type', [21, 23, 67])
            ->whereNull('adm.admission_id')
            ->where('s.srv_status', '!=', 35)
            ->get();

        if ($withResponse) {
            return response()->json(['message' => 'Data berhasil diinsert', 'data' => $simrsData]);
        }
        return $simrsData;
    }
    public function getDiagnosa($visitIds = [], $withResponse = true)
    {
        $query = DB::connection('db_simrs')
            ->table('yanmed.services as s')
            ->join('yanmed.diagnosa as d', 's.srv_id', '=', 'd.srv_id')
            ->join('claim.coding_diagnosa as cd', 'd.diagnosa_id', '=', 'cd.diagnosa_id')
            ->join('yanmed.ms_icd as i', 'cd.icd_id_coding', '=', 'i.icd_id')
            ->select(
                's.visit_id',
                'i.icd_code',
                'i.icd_name',
                'cd.urut'
            );

        if (!empty($visitIds)) {
            $query->whereIn('s.visit_id', $visitIds);
        }

        $diagnosa = $query->orderBy('s.visit_id')
            ->orderBy('cd.urut', 'asc')
            ->get();

        if ($withResponse) {
            return response()->json(['data' => $diagnosa]);
        }
        return $diagnosa;
    }

    public function getTindakan($visitIds = [], $withResponse = true)
    {
        $query = DB::connection('db_simrs')
            ->table('yanmed.services as s')
            ->join('yanmed.billing as b', 's.visit_id', '=', 'b.visit_id')
            ->join('claim.coding_tindakan as ct', 'b.billing_id', '=', 'ct.billing_id')
            ->join('yanmed.ms_icd as i', 'ct.icd_id_coding', '=', 'i.icd_id')
            ->select(
                's.visit_id',
                'i.icd_code',
                'i.icd_name'
            );

        if (!empty($visitIds)) {
            $query->whereIn('s.visit_id', $visitIds);
        }

        $tindakan = $query->get();

        if ($withResponse) {
            return response()->json(['data' => $tindakan]);
        }
        return $tindakan;
    }


    public function getVitalSigns($visitIds = [], $withResponse = true)
    {
        $query = DB::connection('db_simrs')
            ->table('yanmed.services as s')
            ->join('ermgwk.vital_sign as vt', 's.srv_id', '=', 'vt.srv_id')
            ->select(
                's.visit_id',
                'vt.sistol',
                'vt.diastol',
                'vt.nadi',
                'vt.rr',
                'vt.suhu',
                'vt.spo2'
            );


        if (!empty($visitIds)) {
            $query->whereIn('s.visit_id', $visitIds);
        }

        $data = $query->get();
        $results = collect();

        foreach ($data as $row) {
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
                        'visit_id' => $row->visit_id,
                        'PARAMETER' => $param,
                        'nilai' => $nilai,
                        'vital_id' => $vital_id
                    ]);
                }
            }
        }

        if ($withResponse) {
            return response()->json(['data' => $results->values()]);
        }
        return $results->values();
    }

    public function getQuisioner($visitIds = [], $withResponse = true)
    {
        // Pertanyaan statis sesuai query
        $questions = [
            1 => 'Apakah identitas pasien pada resep lengkap?',
            2 => 'Apakah nama obat, dosis, dan aturan pakai sudah jelas?',
            3 => 'Apakah dosis obat sudah sesuai dengan kondisi pasien?',
            4 => 'Apakah terdapat duplikasi terapi obat?',
            5 => 'Apakah terdapat potensi interaksi obat?',
            6 => 'Apakah terdapat kontraindikasi obat pada pasien?',
            7 => 'Apakah obat sesuai dengan indikasi diagnosis?',
        ];

        $query = DB::connection('db_simrs')
            ->table('yanmed.visit as v')
            ->select('v.visit_id', 'visit_date');

        if (!empty($visitIds)) {
            $query->whereIn('v.visit_id', $visitIds);
        }

        $visits = $query->get();
        $result = collect();

        foreach ($visits as $visit) {
            $questionnaire = [];
            foreach ($questions as $kode => $teks) {
                $questionnaire['pertanyaan_' . $kode] = [
                    'teks' => $teks,
                    'jawaban' => true
                ];
            }
            $result->push([
                'visit_id' => $visit->visit_id,
                'visit_date' => $visit->visit_date,
                'questionnaire_telaah_resep' => $questionnaire
            ]);
        }

        if ($withResponse) {
            return response()->json(['data' => $result->values()]);
        }
        return $result->values();
    }

    public function getVisitFarmasi($visitIds = [], $withResponse = true)
    {
        $query = DB::connection('db_simrs')
            ->table('farmasi.sale as s')
            ->join('yanmed.recipe as r', 's.sale_id', '=', 'r.sale_id')
            ->join('farmasi.sale_detail as sd', 's.sale_id', '=', 'sd.sale_id')
            ->join('admin.ms_item as i', 'sd.item_id', '=', 'i.item_id')
            ->join('kemkes.practitioner as p', 's.doctor_id', '=', 'p.par_id')
            ->join('kemkes.location as lo', 's.unit_id', '=', 'lo.unit_id_simrs')
            ->select(

                's.visit_id',
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
            ->distinct();
        if (!empty($visitIds)) {
            $query->whereIn('s.visit_id', $visitIds);
        }

        $data = $query->get();

        if ($withResponse) {
            return response()->json(['data' => $data]);
        }
        return $data;
    }

    public function getVisitLab($visitIds = [], $withResponse = true)
    {
        $query = DB::connection('db_simrs')
            ->table('yanmed.services as s')
            ->join('yanmed.checkup as c', 's.srv_id', '=', 'c.service_id')
            ->join('yanmed.ms_check as mc', 'c.ms_check_id', '=', 'mc.idcheck')
            ->join('kemkes.practitioner as pr', 'c.par_id', '=', 'pr.par_id')
            ->join('kemkes.practitioner as pr2', 'c.par_id_requester', '=', 'pr2.par_id')
            ->select(
                's.visit_id',
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
            ->whereNotNull('mc.kode_satusehat');

        if (!empty($visitIds)) {
            $query->whereIn('s.visit_id', $visitIds);
        }

        $data = $query->get();

        if ($withResponse) {
            return response()->json(['data' => $data]);
        }
        return $data;
    }


    public function visitAllergi($visitIds = [], $withResponse = true)
    {
        $query = DB::connection('db_simrs')
            ->table('yanmed.allergy as a')
            ->join('yanmed.visit as v', 'a.px_id', '=', 'v.px_id')
            ->select(
                'satsetid',
                'visit_id',
                'allergy_date',
                'allergy_desc'
            )
            ->whereNotNull('satsetid');

        if (!empty($visitIds)) {
            $query->whereIn('v.visit_id', $visitIds);
        }

        $data = $query->get();

        if ($withResponse) {
            return response()->json(['data' => $data]);
        }
        return $data;
    }

    public function visitCareplan($visitIds = [], $withResponse = true)
    {

        $query = DB::connection('db_simrs')
            ->table('ermgwk.pemeriksaan_fisik as pf')
            ->join('yanmed.services as s', 'pf.srv_id', '=', 's.srv_id')
            ->select(
                's.visit_id',
                'pf.subtl',
                DB::raw("CASE\n                    WHEN LOWER(pf.subtl) LIKE '%rawat inap%' THEN 'Pasien Melanjutkan rawat inap'\n                    WHEN LOWER(pf.subtl) LIKE '%atas permintaan sendiri%' THEN 'Pasien Meminta pulang atas kemauan sendiri'\n                    WHEN LOWER(pf.subtl) LIKE '%boleh pulang%' AND pf.kontrol = 'Ya' THEN 'Pasien Pulang dengan kontrol kembali'\n                    WHEN LOWER(pf.subtl) LIKE '%boleh pulang%' AND pf.kontrol = 'Tidak' THEN 'Pasien Pulang dengan kondisi baik tidak perlu kontrol'\n                    WHEN LOWER(pf.subtl) LIKE '%rujuk%' OR LOWER(pf.subtl) LIKE '%dirujuk%' OR LOWER(pf.subtl) LIKE '%rujukan%' THEN 'Pasien dirujuk'\n                    ELSE 'Tidak diketahui'\n                END as alasan"),
                DB::raw("CASE\n                    WHEN LOWER(pf.subtl) LIKE '%rujuk%' OR LOWER(pf.subtl) LIKE '%dirujuk%' OR LOWER(pf.subtl) LIKE '%rujukan%' THEN NULLIF(pf.rujukke, '')\n                    ELSE COALESCE(NULLIF(pf.alasanaps, ''), NULLIF(pf.edu, ''))\n                END as keterangan")
            );

        if (!empty($visitIds)) {
            $query->whereIn('s.visit_id', $visitIds);
        }

        $data = $query->get();

        if ($withResponse) {
            return response()->json(['data' => $data]);
        }
        return $data;
    }

    public function visitRadiologi($visitIds = [], $withResponse = true)
    {
        // dd($visitIds);
        $query = DB::connection('db_simrs')
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
            ]);
            $query->where('srv.unit_id', '=', '73');
        if (!empty($visitIds)) {
            $query->whereIn('srv.visit_id', $visitIds);
        }

        $data = $query->get();

        if ($withResponse) {
            return response()->json(['data' => $data]);
        }
        return $data;
    }
}
