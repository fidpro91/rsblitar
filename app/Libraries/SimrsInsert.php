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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SimrsInsert
{
    private array $visitCache = [];
    private int $chunkSize = 100;

    public static function insert(array $data, int $chunkSize = 100): bool
    {
        $self = new self();
        $self->chunkSize = max(10, $chunkSize);

        DB::beginTransaction();

        try {
            /*
            |--------------------------------------------------------------------------
            | ENCOUNTER (MASTER CACHE & IDEMPOTENT INSERTION)
            |--------------------------------------------------------------------------
            */
            $encounters = [];

            if (!empty($data['visit_encounter'])) {
                foreach ($data['visit_encounter'] as $row) {
                    $row = (array) $row;
                    $encounters[] = [
                        'visit_id'              => $row['visit_id'] ?? null,
                        'no_ktp'                => $row['px_noktp'] ?? ($row['no_ktp'] ?? null),
                        'px_norm'               => $row['px_norm'] ?? null,
                        'px_name'               => $row['px_name'] ?? null,
                        'unit_name'             => $row['location_name'] ?? ($row['unit_name'] ?? null),
                        'idunitsatset'          => $row['idunitsatset'] ?? null,
                        'dpjp_name'             => $row['dpjp'] ?? ($row['dpjp_name'] ?? null),
                        'kode_dokter'           => $row['kode_dokter'] ?? null,
                        'tgl_kunjung'           => $row['tgl_kunjung'] ?? null,
                        'tgl_dilayani'          => $row['tgl_dilayani'] ?? null,
                        'tgl_selesai_dilayani'  => $row['tgl_selesai_dilayani'] ?? null,
                        'tgl_pulang'            => $row['tgl_pulang'] ?? null,
                        'tipe_kunjungan'        => $row['tipe_kunjungan'] ?? null,
                        'is_send'               => $row['is_send'] ?? false,
                        'uuid_encounter'        => $row['uuid_encounter'] ?? (string) Str::uuid(),
                        'kode_pasien'           => $row['patient_id_kemkes'] ?? ($row['kode_pasien'] ?? null),
                        'instruksi_pulang'      => $row['instruksi_pulang'] ?? null,
                        'uuid_composition'      => $row['uuid_composition'] ?? (string) Str::uuid(),
                        'uuid_clinicalimpresion' => $row['uuid_clinicalimpresion'] ?? (string) Str::uuid(),
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ];
                }

                $visitIds = array_filter(array_column($encounters, 'visit_id'));

                // Cek data existing untuk mencegah duplikasi data
                $existingEncounters = Visit_encounter::whereIn('visit_id', $visitIds)
                    ->select(['id', 'visit_id'])
                    ->get();

                $existingVisitIds = [];
                foreach ($existingEncounters as $ex) {
                    $existingVisitIds[$ex->visit_id] = $ex->id;
                    $self->visitCache[$ex->visit_id] = $ex->id;
                }

                // Filter hanya encounter yang belum ada di database
                $newEncounters = array_filter($encounters, function ($row) use ($existingVisitIds) {
                    return !isset($existingVisitIds[$row['visit_id']]);
                });

                if (!empty($newEncounters)) {
                    foreach (array_chunk($newEncounters, $self->chunkSize) as $chunk) {
                        Visit_encounter::insert($chunk);
                    }

                    $freshEncounters = Visit_encounter::whereIn('visit_id', array_column($newEncounters, 'visit_id'))
                        ->select(['id', 'visit_id'])
                        ->get();

                    foreach ($freshEncounters as $enc) {
                        $self->visitCache[$enc->visit_id] = $enc->id;
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | OBSERVATION (VITAL SIGNS - CHUNKED)
            |--------------------------------------------------------------------------
            */
            if (!empty($data['observation'])) {
                $insert = [];
                foreach ($data['observation'] as $item) {
                    $item = (array) $item;
                    $vId = $item['visit_id'] ?? '';
                    if (!isset($self->visitCache[$vId])) continue;

                    $insert[] = [
                        'visit_id'         => $vId,
                        'observation_name' => $item['PARAMETER'] ?? null,
                        'result'           => $item['nilai'] ?? null,
                        'vital_id'         => $item['vital_id'] ?? null,
                        'uuid_observation' => $item['uuid_observation'] ?? (string) Str::uuid(),
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ];
                }

                foreach (array_chunk($insert, $self->chunkSize) as $chunk) {
                    Observation::insert($chunk);
                }
                unset($insert);
            }

            /*
            |--------------------------------------------------------------------------
            | DIAGNOSIS (ICD-10 - CHUNKED)
            |--------------------------------------------------------------------------
            */
            if (!empty($data['diagnosis'])) {
                $insert = [];
                foreach ($data['diagnosis'] as $item) {
                    $item = (array) $item;
                    $vId = $item['visit_id'] ?? '';
                    if (!isset($self->visitCache[$vId])) continue;

                    $insert[] = [
                        'visit_id'   => $vId,
                        'code'       => $item['icd_code'] ?? null,
                        'dx_name'    => $item['icd_name'] ?? null,
                        'rank'       => $item['urut'] ?? null,
                        'uuid'       => $item['uuid'] ?? (string) Str::uuid(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                foreach (array_chunk($insert, $self->chunkSize) as $chunk) {
                    Diagnosis::insert($chunk);
                }
                unset($insert);
            }

            /*
            |--------------------------------------------------------------------------
            | VISIT ICD9 (TINDAKAN / PROSEDUR - CHUNKED)
            |--------------------------------------------------------------------------
            */
            if (!empty($data['visit_icd9'])) {
                $insert = [];
                foreach ($data['visit_icd9'] as $item) {
                    $item = (array) $item;
                    $vId = $item['visit_id'] ?? '';
                    if (!isset($self->visitCache[$vId])) continue;

                    $insert[] = [
                        'visit_id' => $vId,
                        'icd_code' => $item['icd_code'] ?? null,
                        'icd_name' => $item['icd_name'] ?? null,
                        'uuid'     => $item['uuid'] ?? (string) Str::uuid(),
                    ];
                }

                foreach (array_chunk($insert, $self->chunkSize) as $chunk) {
                    Visit_icd9::insert($chunk);
                }
                unset($insert);
            }

            /*
            |--------------------------------------------------------------------------
            | VISIT LAB (SERVICE REQUEST & OBSERVATION - CHUNKED)
            |--------------------------------------------------------------------------
            */
            if (!empty($data['visit_lab'])) {
                $insert = [];
                foreach ($data['visit_lab'] as $item) {
                    $item = (array) $item;
                    $vId = $item['visit_id'] ?? '';
                    if (!isset($self->visitCache[$vId])) continue;

                    $insert[] = [
                        'visit_id'           => $vId,
                        'nama_pemeriksaan'   => $item['namecheck'] ?? null,
                        'dokter_lab'         => $item['dokter_lab'] ?? null,
                        'kode_dokter_lab'    => $item['kode_dokter_lab'] ?? null,
                        'tgl_ambil_sample'   => $item['tgl_periksa'] ?? null,
                        'tgl_periksa'        => $item['tgl_periksa'] ?? null,
                        'tgl_selesai'        => $item['tgl_selesai'] ?? null,
                        'dokter_pengirim'    => $item['dokter_pengirim'] ?? null,
                        'kode_pengirim'      => $item['kode_pengirim'] ?? null,
                        'hasil_lab'          => $item['result'] ?? null,
                        'map_pemeriksaan_id' => $item['kode_satusehat'] ?? null,
                        'map_specimen_id'    => $item['map_specimen_id'] ?? 1,
                        'uuid_specimen'      => $item['uuid_specimen'] ?? (string) Str::uuid(),
                        'uuid_obs'           => $item['uuid_obs'] ?? (string) Str::uuid(),
                        'uuid_diagnostic'    => $item['uuid_diagnostic'] ?? (string) Str::uuid(),
                        'uuid_servicereq'    => $item['uuid_servicereq'] ?? (string) Str::uuid(),
                        'jml_sample'         => $item['jml_sample'] ?? 1,
                        'satuan_sample'      => $item['satuan_sample'] ?? null,
                        'status_normal'      => $item['status_normal'] ?? null,
                    ];
                }

                foreach (array_chunk($insert, $self->chunkSize) as $chunk) {
                    Visit_lab::insert($chunk);
                }
                unset($insert);
            }

            /*
            |--------------------------------------------------------------------------
            | FARMASI (MEDICATION & DISPENSE - CHUNKED)
            |--------------------------------------------------------------------------
            */
            if (!empty($data['visit_farmasi'])) {
                $insert = [];
                foreach ($data['visit_farmasi'] as $item) {
                    $item = (array) $item;
                    $vId = $item['visit_id'] ?? '';
                    if (!isset($self->visitCache[$vId])) continue;

                    $sale_qty = $item['sale_qty'] ?? null;
                    if (!is_null($sale_qty)) {
                        $sale_qty = (int) $sale_qty;
                    }

                    $insert[] = [
                        'item_id_simrs'        => $item['item_id_simrs'] ?? $item['item_id'] ?? null,
                        'item_id_kfa'          => $item['kode_satusehat'] ?? null,
                        'visit_id'             => $vId,
                        'sale_qty'             => $sale_qty,
                        'racikan'              => $item['racikan'] ?? null,
                        'dosis'                => $item['dosis'] ?? null,
                        'waktu_resep_dibuat'   => $item['rcp_date'] ?? null,
                        'waktu_resep_diterima' => $item['rcp_date'] ?? null,
                        'waktu_resep_diproses' => $item['date_act'] ?? null,
                        'waktu_diserahkan'     => $item['date_act'] ?? null,
                        'waktu_selesai'        => $item['date_act'] ?? null,
                        'dokter_peresep'       => $item['dokter_peresep'] ?? $item['practitioner_name'] ?? null,
                        'kode_dokter'          => $item['kode_dokter'] ?? $item['practitioner_id'] ?? null,
                        'unit_name'            => $item['unit_name'] ?? $item['location_name'] ?? null,
                        'uuid_unit'            => $item['location_id_kemkes'] ?? null,
                        'uuid_med'             => $item['uuid_med'] ?? (string) Str::uuid(),
                        'uuid_med_request'     => $item['uuid_med_request'] ?? (string) Str::uuid(),
                        'uuid_med_dispen'      => $item['uuid_med_dispen'] ?? (string) Str::uuid(),
                        'sale_num'             => $item['sale_num'] ?? null,
                    ];
                }

                foreach (array_chunk($insert, $self->chunkSize) as $chunk) {
                    Visit_farmasi::insert($chunk);
                }
                unset($insert);
            }

            /*
            |--------------------------------------------------------------------------
            | QUISIONER (QUESTIONNAIRE TELAAH RESEP - CHUNKED)
            |--------------------------------------------------------------------------
            */
            if (!empty($data['visit_quisioner'])) {
                $insert = [];
                foreach ($data['visit_quisioner'] as $item) {
                    $item = (array) $item;
                    $vId = $item['visit_id'] ?? '';
                    if (!isset($self->visitCache[$vId])) continue;

                    $insert[] = [
                        'visit_id'       => $vId,
                        'kode_apoteker'  => $item['kode_apoteker'] ?? 10002580986,
                        'nama_apoteker'  => $item['nama_apoteker'] ?? 'Tita Sugesti',
                        'data_quisioner' => json_encode($item['questionnaire_telaah_resep'] ?? []),
                        'uuid_quisioner' => $item['uuid_quisioner'] ?? (string) Str::uuid(),
                        'tgl_quisioner'  => $item['visit_date'] ?? null,
                    ];
                }

                foreach (array_chunk($insert, $self->chunkSize) as $chunk) {
                    Visit_quisioner::insert($chunk);
                }
                unset($insert);
            }

            /*
            |--------------------------------------------------------------------------
            | ALERGI (ALLERGY INTOLERANCE - CHUNKED)
            |--------------------------------------------------------------------------
            */
            if (!empty($data['visit_allergi'])) {
                $insert = [];
                foreach ($data['visit_allergi'] as $item) {
                    $item = (array) $item;
                    $vId = $item['visit_id'] ?? '';
                    if (!isset($self->visitCache[$vId])) continue;

                    $insert[] = [
                        'visit_id'       => $vId,
                        'tanggal_alergi' => $item['allergy_date'] ?? null,
                        'note'           => $item['allergy_desc'] ?? null,
                        'allergy_id'     => $item['satsetid'] ?? null,
                        'uuid_allergy'   => $item['uuid_allergy'] ?? (string) Str::uuid(),
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];
                }

                foreach (array_chunk($insert, $self->chunkSize) as $chunk) {
                    Visit_alergy::insert($chunk);
                }
                unset($insert);
            }

            /*
            |--------------------------------------------------------------------------
            | CAREPLAN (CARE PLAN - CHUNKED)
            |--------------------------------------------------------------------------
            */
            if (!empty($data['visit_careplan'])) {
                $insert = [];
                foreach ($data['visit_careplan'] as $item) {
                    $item = (array) $item;
                    $vId = $item['visit_id'] ?? '';
                    if (!isset($self->visitCache[$vId])) continue;

                    $insert[] = [
                        'visit_id'       => $vId,
                        'kondisi_pulang' => $item['subtl'] ?? null,
                        'alasan_pulang'  => $item['alasan'] ?? null,
                        'keterangan'     => $item['keterangan'] ?? null,
                        'uuid_careplane' => $item['uuid_careplane'] ?? (string) Str::uuid(),
                    ];
                }

                foreach (array_chunk($insert, $self->chunkSize) as $chunk) {
                    Visit_careplane::insert($chunk);
                }
                unset($insert);
            }

            /*
            |--------------------------------------------------------------------------
            | RADIOLOGI (DIAGNOSTIC REPORT & OBSERVATION - CHUNKED)
            |--------------------------------------------------------------------------
            */
            if (!empty($data['visit_radiologi'])) {
                $insert = [];
                foreach ($data['visit_radiologi'] as $item) {
                    $item = (array) $item;
                    $vId = $item['visit_id'] ?? '';
                    if (!isset($self->visitCache[$vId])) continue;

                    $insert[] = [
                        'visit_id'               => $vId,
                        'srv_id'                 => $item['srv_id'] ?? null,
                        'tanggal_order'          => $item['tanggal_order'] ?? null,
                        'nama_pemeriksaan'       => $item['nama_pemeriksaan'] ?? null,
                        'dokter_pengirim'        => $item['dokter_pengirim'] ?? null,
                        'kode_dokter_pengirim'   => $item['kode_dokter_pengirim'] ?? null,
                        'tanggal_pemeriksaan'    => $item['tanggal_pemeriksaan'] ?? null,
                        'tanggal_hasil'          => $item['tanggal_hasil'] ?? null,
                        'dokter_radiologi'       => $item['dokter_radiologi'] ?? null,
                        'kode_dokter_radiologi'  => $item['kode_dokter_radiologi'] ?? null,
                        'hasil_pemeriksaan'      => $item['hasil_pemeriksaan'] ?? null,
                        'uuid_service_request'   => (string) Str::uuid(),
                        'uuid_observation'       => (string) Str::uuid(),
                        'uuid_diagnostic_report' => (string) Str::uuid(),
                        'code_map_rad'           => $item['map_radiologi'] ?? null,
                        'acsn_number'            => $item['assesion_number'] ?? null,
                    ];
                }

                foreach (array_chunk($insert, $self->chunkSize) as $chunk) {
                    Visit_radiologi::insert($chunk);
                }
                unset($insert);
            }

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('SIMRS Insert Error: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);

            return false;
        }
    }
}
