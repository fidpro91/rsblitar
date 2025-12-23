<?php

namespace App\Libraries;

use App\Models\Observation;
use App\Models\Visit_encounter;
use App\Models\Visit_farmasi;
use App\Models\Diagnosis;
use App\Models\Visit_icd9;
use App\Models\Visit_lab;
use App\Models\Visit_quisioner;
use Illuminate\Support\Str;

class SimrsInsert
{

    public static function insert(array $data)
    {
       
        if (!empty($data['visit_encounter'])) {
            $rows = $data['visit_encounter'];
            if (isset($rows[0])) {
                foreach ($rows as $row) {
                    if ($row instanceof \stdClass) $row = (array)$row;
                    $insert = [
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
                    Visit_encounter::create($insert);
                }
            } else {
                $row = $rows;
                if ($row instanceof \stdClass) $row = (array)$row;
                $insert = [
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
                Visit_encounter::create($insert);
            }
        }
        if (!empty($data['observation']) && is_array($data['observation'])) {
            foreach ($data['observation'] as $item) {
                if ($item instanceof \stdClass) $item = (array)$item;
                $insert = [
                    'visit_id' => $item['visit_id'] ?? null,
                    'observation_name' => $item['PARAMETER'] ?? null,
                    'result' => $item['nilai'] ?? null,
                    'vital_id' => $item['vital_id'] ?? null,
                    'uuid_observation' => $item['uuid_observation'] ?? Str::uuid(),
                ];
                Observation::create($insert);
            }
        }
        if (!empty($data['diagnosis']) && is_array($data['diagnosis'])) {
            foreach ($data['diagnosis'] as $item) {
                if ($item instanceof \stdClass) $item = (array)$item;
                $insert = [
                    'visit_id' => $item['visit_id'] ?? null,
                    'code' => $item['icd_code'] ?? null,
                    'dx_name' => $item['icd_name'] ?? null,
                    'rank' => $item['urut'] ?? null,
                    'uuid' => $item['uuid'] ?? Str::uuid(),
                ];
                Diagnosis::create($insert);
            }
        }
        if (!empty($data['visit_icd9']) && is_array($data['visit_icd9'])) {
            foreach ($data['visit_icd9'] as $item) {
                if ($item instanceof \stdClass) $item = (array)$item;
                $insert = [
                    'visit_id' => $item['visit_id'] ?? null,
                    'icd_code' => $item['icd_code'] ?? null,
                    'icd_name' => $item['icd_name'] ?? null,
                    'uuid' => $item['uuid'] ?? Str::uuid(),
                ];
                Visit_icd9::create($insert);
            }
        }
        if (!empty($data['visit_lab']) && is_array($data['visit_lab'])) {
            foreach ($data['visit_lab'] as $item) {
                if ($item instanceof \stdClass) $item = (array)$item;
                $insert = [
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
                Visit_lab::create($insert);
            }
        }
        if (!empty($data['visit_farmasi']) && is_array($data['visit_farmasi'])) {
            foreach ($data['visit_farmasi'] as $item) {
                if ($item instanceof \stdClass) $item = (array)$item;
                $sale_qty = $item['sale_qty'] ?? null;
                if (!is_null($sale_qty)) {
                    $sale_qty = (int)$sale_qty;
                }
                $insert = [
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
                Visit_farmasi::create($insert);
            }
        }
        if (!empty($data['visit_quisioner']) && is_array($data['visit_quisioner'])) {
            foreach ($data['visit_quisioner'] as $item) {
                if ($item instanceof \stdClass) $item = (array)$item;
                $insert = [
                    'visit_id' => $item['visit_id'] ?? null,
                    'data_quisioner' => $item['questionnaire_telaah_resep'] ?? null,
                    'uuid_quisioner' => $item['uuid_quisioner'] ?? Str::uuid(),
                    'tgl_quisioner' => $item['visit_date'] ?? null,
                ];
                Visit_quisioner::create($insert);
            }
        }
    }
}
