<?php

namespace App\Libraries;

use App\Models\Observation;
use App\Models\Visit_encounter;
use App\Models\Visit_farmasi;
use App\Models\Diagnosis;
use App\Models\Visit_icd9;
use App\Models\Visit_lab;
use App\Models\Visit_quisioner;
use App\Models\Visit_alergy;
use App\Models\Visit_careplane;
use App\Models\Visit_radiologi;
use Illuminate\Support\Str;

class SimrsInsert
{

    public static function insert(array $data, int $chunkSize = 100)
    {
        
        $chunkArray = function($array, $size) {
            if (!is_array($array)) return [$array];
            return array_chunk($array, $size);
        };

        if (!empty($data['visit_encounter'])) {
            $rows = $data['visit_encounter'];
            foreach ($chunkArray($rows, $chunkSize) as $chunk) {
                $bulk = [];
                foreach ($chunk as $row) {
                    if ($row instanceof \stdClass) $row = (array)$row;
                    $bulk[] = [
                        'visit_id' => $row['visit_id'] ?? null,
                        'no_ktp' => $row['px_noktp'] ?? null,
                        'px_norm' => $row['px_norm'] ?? null,
                        'px_name' => $row['px_name'] ?? null,
                        'unit_name' => $row['location_name'] ?? null,
                        'idunitsatset' => $row['idunitsatset'] ?? null,
                        'dpjp_name' => $row['dpjp'] ?? null,
                        'kode_dokter' => $row['kode_dokter'] ?? null,
                        'tgl_kunjung' => $row['tgl_kunjung'] ?? null,
                        'tgl_dilayani' => $row['tgl_dilayani'] ?? null,
                        'tgl_selesai_dilayani' => $row['tgl_selesai_dilayani'] ?? null,
                        'tgl_pulang' => $row['tgl_pulang'] ?? null,
                        'tipe_kunjungan' => $row['tipe_kunjungan'] ?? null,
                        'is_send' => $row['is_send'] ?? false,
                        'uuid_encounter' => $row['uuid_encounter'] ?? Str::uuid(),
                        'kode_pasien' => $row['patient_id_kemkes'] ?? null,
                        'instruksi_pulang' => $row['instruksi_pulang'] ?? null,
                        'uuid_composition' => $row['uuid_composition'] ?? Str::uuid(),
                        'uuid_clinicalimpresion' => $row['uuid_clinicalimpresion'] ?? Str::uuid(),
                    ];
                }
                if (!empty($bulk)) foreach ($bulk as $row) Visit_encounter::create($row);
            }
        }
        if (!empty($data['observation']) && is_array($data['observation'])) {
            foreach ($chunkArray($data['observation'], $chunkSize) as $chunk) {
                $bulk = [];
                foreach ($chunk as $item) {
                    if ($item instanceof \stdClass) $item = (array)$item;
                    $bulk[] = [
                        'visit_id' => $item['visit_id'] ?? null,
                        'observation_name' => $item['PARAMETER'] ?? null,
                        'result' => $item['nilai'] ?? null,
                        'vital_id' => $item['vital_id'] ?? null,
                        'uuid_observation' => $item['uuid_observation'] ?? Str::uuid(),
                    ];
                }
                if (!empty($bulk)) foreach ($bulk as $row) Observation::create($row);
            }
        }
        if (!empty($data['diagnosis']) && is_array($data['diagnosis'])) {
            foreach ($chunkArray($data['diagnosis'], $chunkSize) as $chunk) {
                $bulk = [];
                foreach ($chunk as $item) {
                    if ($item instanceof \stdClass) $item = (array)$item;
                    $bulk[] = [
                        'visit_id' => $item['visit_id'] ?? null,
                        'code' => $item['icd_code'] ?? null,
                        'dx_name' => $item['icd_name'] ?? null,
                        'rank' => $item['urut'] ?? null,
                        'uuid' => $item['uuid'] ?? Str::uuid(),
                    ];
                }
                if (!empty($bulk)) foreach ($bulk as $row) Diagnosis::create($row);
            }
        }
        if (!empty($data['visit_icd9']) && is_array($data['visit_icd9'])) {
            foreach ($chunkArray($data['visit_icd9'], $chunkSize) as $chunk) {
                $bulk = [];
                foreach ($chunk as $item) {
                    if ($item instanceof \stdClass) $item = (array)$item;
                    $bulk[] = [
                        'visit_id' => $item['visit_id'] ?? null,
                        'icd_code' => $item['icd_code'] ?? null,
                        'icd_name' => $item['icd_name'] ?? null,
                        'uuid' => $item['uuid'] ?? Str::uuid(),
                    ];
                }
                if (!empty($bulk)) foreach ($bulk as $row) Visit_icd9::create($row);
            }
        }
        if (!empty($data['visit_lab']) && is_array($data['visit_lab'])) {
            foreach ($chunkArray($data['visit_lab'], $chunkSize) as $chunk) {
                $bulk = [];
                foreach ($chunk as $item) {
                    if ($item instanceof \stdClass) $item = (array)$item;
                    $bulk[] = [
                        'visit_id' => $item['visit_id'] ?? null,
                        'nama_pemeriksaan' => $item['namecheck'] ?? null,
                        'dokter_lab' => $item['dokter_lab'] ?? null,
                        'kode_dokter_lab' => $item['kode_dokter_lab'] ?? null,
                        'tgl_ambil_sample' => $item['tgl_periksa'] ?? null,
                        'tgl_periksa' => $item['tgl_periksa'] ?? null,
                        'tgl_selesai' => $item['tgl_selesai'] ?? null,
                        'dokter_pengirim' => $item['dokter_pengirim'] ?? null,
                        'kode_pengirim' => $item['kode_pengirim'] ?? null,
                        'hasil_lab' => $item['result'] ?? null,
                        'map_pemeriksaan_id' => $item['kode_satusehat'] ?? null,
                        'map_specimen_id' => $item['map_specimen_id'] ?? 1,
                        'uuid_specimen' => $item['uuid_specimen'] ?? Str::uuid(),
                        'uuid_obs' => $item['uuid_obs'] ?? Str::uuid(),
                        'uuid_diagnostic' => $item['uuid_diagnostic'] ?? Str::uuid(),
                        'uuid_servicereq' => $item['uuid_servicereq'] ?? Str::uuid(),
                        'jml_sample' => $item['jml_sample'] ?? 1,
                        'satuan_sample' => $item['satuan_sample'] ?? null,
                        'status_normal' => $item['status_normal'] ?? null,

                    ];
                }
                if (!empty($bulk)) foreach ($bulk as $row) Visit_lab::create($row);
            }
        }
        if (!empty($data['visit_farmasi']) && is_array($data['visit_farmasi'])) {
            foreach ($chunkArray($data['visit_farmasi'], $chunkSize) as $chunk) {
                $bulk = [];
                foreach ($chunk as $item) {
                    if ($item instanceof \stdClass) $item = (array)$item;
                    $sale_qty = $item['sale_qty'] ?? null;
                    if (!is_null($sale_qty)) {
                        $sale_qty = (int)$sale_qty;
                    }
                    $bulk[] = [
                        'item_id_simrs' => $item['item_id_simrs'] ?? $item['item_id'] ?? null,
                        'item_id_kfa' => $item['kode_satusehat'] ?? null,
                        'visit_id' => $item['visit_id'] ?? null,
                        'sale_qty' => $sale_qty,
                        'racikan' => $item['racikan'] ?? null,
                        'dosis' => $item['dosis'] ?? null,
                        'waktu_resep_dibuat' => $item['rcp_date'] ?? null,
                        'waktu_resep_diterima' => $item['rcp_date'] ?? null,
                        'waktu_resep_diproses' => $item['date_act'] ?? null,
                        'waktu_diserahkan' => $item['date_act'] ?? null,
                        'waktu_selesai' => $item['date_act'] ?? null,
                        'dokter_peresep' => $item['dokter_peresep'] ?? $item['practitioner_name'] ?? null,
                        'kode_dokter' => $item['kode_dokter'] ?? $item['practitioner_id'] ?? null,
                        'unit_name' => $item['unit_name'] ?? $item['location_name'] ?? null,
                        'uuid_unit' => $item['location_id_kemkes'] ?? null,
                        'uuid_med' => $item['uuid_med'] ?? Str::uuid(),
                        'uuid_med_request' => $item['uuid_med_request'] ?? Str::uuid(),
                        'uuid_med_dispen' => $item['uuid_med_dispen'] ?? Str::uuid(),
                        'sale_num' => $item['sale_num'] ?? null,
                    ];
                }
                if (!empty($bulk)) foreach ($bulk as $row) Visit_farmasi::create($row);
            }
        }
        if (!empty($data['visit_quisioner']) && is_array($data['visit_quisioner'])) {
            foreach ($chunkArray($data['visit_quisioner'], $chunkSize) as $chunk) {
                $bulk = [];
                foreach ($chunk as $item) {
                    if ($item instanceof \stdClass) $item = (array)$item;
                    $bulk[] = [
                        'visit_id' => $item['visit_id'] ?? null,
                        'kode_apoteker' => $item['kode_apoteker'] ?? 10002580986,
                        'nama_apoteker' => $item['nama_apoteker'] ?? 'Tita Sugesti',
                        'data_quisioner' => $item['questionnaire_telaah_resep'] ?? null,
                        'uuid_quisioner' => $item['uuid_quisioner'] ?? Str::uuid(),
                        'tgl_quisioner' => $item['visit_date'] ?? null,
                    ];
                }
                if (!empty($bulk)) foreach ($bulk as $row) Visit_quisioner::create($row);
            }
        }

        if (!empty($data['visit_allergi']) && is_array($data['visit_allergi'])) {
            foreach ($chunkArray($data['visit_allergi'], $chunkSize) as $chunk) {
                $bulk = [];
                foreach ($chunk as $item) {
                    if ($item instanceof \stdClass) $item = (array)$item;
                    $bulk[] = [
                        'visit_id' => $item['visit_id'] ?? null,
                        'tanggal_alergi' => $item['allergy_date'] ?? null,
                        'note' => $item['allergy_desc'] ?? null,
                        'allergy_id' => $item['satsetid'] ?? null,
                        'uuid_allergy' => $item['uuid_allergy'] ?? Str::uuid(),
                    ];
                }
                if (!empty($bulk)) foreach ($bulk as $row) Visit_alergy::create($row);
            }
        }

        if (!empty($data['visit_careplan']) && is_array($data['visit_careplan'])) {
            foreach ($chunkArray($data['visit_careplan'], $chunkSize) as $chunk) {
                $bulk = [];
                foreach ($chunk as $item) {
                    if ($item instanceof \stdClass) $item = (array)$item;
                    $bulk[] = [
                        'visit_id' => $item['visit_id'] ?? null,
                        'kondisi_pulang' => $item['subtl'] ?? null,
                        'alasan_pulang' => $item['alasan'] ?? null,
                        'keterangan' => $item['keterangan'] ?? null,
                        'uuid_careplane' => $item['uuid_careplane'] ?? Str::uuid(),
                    ];
                }
                if (!empty($bulk)) foreach ($bulk as $row) Visit_careplane::create($row);
            }
        }


        if (!empty($data['visit_radiologi']) && is_array($data['visit_radiologi'])) {
            foreach ($chunkArray($data['visit_radiologi'], $chunkSize) as $chunk) {
                $bulk = [];
                foreach ($chunk as $item) {
                    if ($item instanceof \stdClass) $item = (array)$item;
                    $bulk[] = [
                        'visit_id' => $item['visit_id'],
                        'srv_id' => $item['srv_id'],
                        'tanggal_order' => $item['tanggal_order'],
                        'nama_pemeriksaan' => $item['nama_pemeriksaan'],
                        'dokter_pengirim' => $item['dokter_pengirim'],
                        'kode_dokter_pengirim' => $item['kode_dokter_pengirim'],
                        'tanggal_pemeriksaan' => $item['tanggal_pemeriksaan'],
                        'tanggal_hasil' => $item['tanggal_hasil'],
                        'dokter_radiologi' => $item['dokter_radiologi'],
                        'kode_dokter_radiologi' => $item['kode_dokter_radiologi'],
                        'hasil_pemeriksaan' => $item['hasil_pemeriksaan'],
                        'uuid_service_request' => Str::uuid(),
                        'uuid_observation' => Str::uuid(),
                        'uuid_diagnostic_report' => Str::uuid(),
                        'code_map_rad' => $item['map_radiologi'],
                        'acsn_number' => $item['assesion_number'],

                    ];
                }
                if (!empty($bulk)) foreach ($bulk as $row) Visit_radiologi::create($row);
            }
        }
    }
}
