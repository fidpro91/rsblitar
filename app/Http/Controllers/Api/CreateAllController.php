<?php

namespace App\Http\Controllers\Api;

use App\Models\Visit_encounter;
use App\Models\Diagnosis;
use App\Models\Visit_alergy;
use App\Models\Visit_careplane;
use App\Models\Visit_lab;
use App\Models\Visit_farmasi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreateAllController extends BaseApiController
{
    /**
     * INSERT:
     * - Visit_encounter
     * - Diagnosis
     * - Allergy
     * - Careplan
     * - Lab
     * - Farmasi
     */
    public function store(Request $request)
    {
        $request->validate([
            'visit_id'     => 'required|string',
            'px_name'      => 'required|string',
            'kode_pasien'  => 'required|string',
            'unit_name'    => 'required|string',
        ]);

        // ================================
        // 1. INSERT / UPDATE VISIT ENCOUNTER
        // ================================
        $visit = Visit_encounter::updateOrCreate(
            ['visit_id' => $request->visit_id],
            [
                'visit_id'            => $request->visit_id,
                'px_name'             => $request->px_name,
                'px_norm'             => $request->px_norm ?? null,
                'no_ktp'              => $request->no_ktp ?? null,
                'kode_pasien'         => $request->kode_pasien,
                'unit_name'           => $request->unit_name,
                'kode_dokter'         => $request->kode_dokter ?? null,
                'dpjp_name'           => $request->dpjp_name ?? null,
                'tgl_kunjung'         => $request->tgl_kunjung ?? now(),
                'tgl_dilayani'        => $request->tgl_dilayani ?? null,
                'tipe_kunjungan'      => $request->tipe_kunjungan ?? null,

                // AUTO GENERATE UUID kalau kosong
                'uuid_encounter'       => $request->uuid_encounter ?? (string) Str::uuid(),
                'uuid_composition'     => $request->uuid_composition ?? (string) Str::uuid(),
                'uuid_clinicalimpresion' => $request->uuid_clinicalimpresion ?? (string) Str::uuid(),
            ]
        );

        // ======================================================
        // 2. INSERT DIAGNOSIS
        // ======================================================
        if ($request->has('diagnosis')) {
            Diagnosis::where('visit_id', $visit->visit_id)->delete(); // clear existing (opsional)

            foreach ($request->diagnosis as $dx) {
                Diagnosis::create([
                    'visit_id' => $visit->visit_id,
                    'uuid'     => (string) Str::uuid(),
                    'rank'     => $dx['rank'],
                    'code'     => $dx['code'],
                    'dx_name'  => $dx['dx_name'],
                ]);
            }
        }

        // ======================================================
        // 3. INSERT ALERGI
        // ======================================================
        if ($request->has('alergy')) {
            Visit_alergy::where('visit_id', $visit->visit_id)->delete();

            foreach ($request->alergy as $alergi) {
                Visit_alergy::create([
                    'visit_id'       => $visit->visit_id,
                    'allergy_id'     => $alergi['allergy_id'],
                    'tanggal_alergi' => $alergi['tanggal_alergi'],
                    'note'           => $alergi['note'],
                    'uuid_allergy'   => (string) Str::uuid(),
                ]);
            }
        }

        // ======================================================
        // 4. INSERT CAREPLAN (1 DATA)
        // ======================================================
        if ($request->has('careplan')) {
            Visit_careplane::updateOrCreate(
                ['visit_id' => $visit->visit_id],
                [
                    'kondisi_pulang' => $request->careplan['kondisi_pulang'],
                    'alasan_pulang'  => $request->careplan['alasan_pulang'],
                    'keterangan'     => $request->careplan['keterangan'],
                    'uuid_careplane' => (string) Str::uuid(),
                ]
            );
        }

        // ======================================================
        // 5. INSERT LAB
        // ======================================================
        if ($request->has('lab')) {
            Visit_lab::where('visit_id', $visit->visit_id)->delete();

            foreach ($request->lab as $lab) {
                Visit_lab::create([
                    'visit_id'            => $visit->visit_id,
                    'nama_pemeriksaan'    => $lab['nama_pemeriksaan'],
                    'dokter_lab'          => $lab['dokter_lab'],
                    'kode_dokter_lab'     => $lab['kode_dokter_lab'],
                    'tgl_periksa'         => $lab['tgl_periksa'],
                    'hasil_lab'           => $lab['hasil_lab'],
                    'map_pemeriksaan_id'  => $lab['map_pemeriksaan_id'],
                    'map_specimen_id'     => $lab['map_specimen_id'],
                    'uuid_specimen'       => (string) Str::uuid(),
                    'uuid_obs'            => (string) Str::uuid(),
                    'uuid_diagnostic'     => (string) Str::uuid(),
                    'uuid_servicereq'     => (string) Str::uuid(),
                ]);
            }
        }

        // ======================================================
        // 6. INSERT FARMASI
        // ======================================================
        if ($request->has('farmasi')) {
            Visit_farmasi::where('visit_id', $visit->visit_id)->delete();

            foreach ($request->farmasi as $far) {
                Visit_farmasi::create([
                    'visit_id'          => $visit->visit_id,
                    'item_id_simrs'     => $far['item_id_simrs'],
                    'item_id_kfa'       => $far['item_id_kfa'],
                    'sale_qty'          => $far['sale_qty'],
                    'racikan'           => $far['racikan'],
                    'dosis'             => $far['dosis'],
                    'dokter_peresep'    => $far['dokter_peresep'],
                    'kode_dokter'       => $far['kode_dokter'],
                    'unit_name'         => $far['unit_name'],
                    'uuid_med'          => (string) Str::uuid(),
                    'uuid_med_request'  => (string) Str::uuid(),
                    'uuid_med_dispen'   => (string) Str::uuid(),
                    'sale_num'          => $far['sale_num'],
                ]);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Visit encounter + seluruh relasi berhasil disimpan',
            'visit_encounter' => $visit,
        ]);
    }


    public function updateuuid($visitId)
    {
        Visit_farmasi::where('visit_id', $visitId)->update([
            'uuid_med'          => (string) Str::uuid(),
            'uuid_med_request'  => (string) Str::uuid(),
            'uuid_med_dispen'   => (string) Str::uuid(),
        ]);

        Diagnosis::where('visit_id', $visitId)->update([
            'uuid'          => (string) Str::uuid()
        ]);

        Visit_encounter::where('visit_id', $visitId)->update([
            'uuid_encounter'       =>  (string) Str::uuid(),
            'uuid_composition'     =>  (string) Str::uuid(),
            'uuid_clinicalimpresion' =>  (string) Str::uuid(),
        ]);
    }
}
