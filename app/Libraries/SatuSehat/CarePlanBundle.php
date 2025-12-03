<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_careplane;

class CarePlanBundle
{
    public static function build($visitId)
    {
        $careplane = Visit_careplane::with('visit_encounter')
            ->where('visit_id', $visitId)
            ->firstOrFail();

        $encounter = $careplane->visit_encounter;
        $cpUuid = $careplane->uuid_careplane;
        $patientRef  = 'Patient/' . $encounter->kode_pasien;
        $patientName = $encounter->px_name;

        $encounterRef = 'urn:uuid:' . $encounter->uuid_encounter;
        $created = date("Y-m-d\TH:i:sP", strtotime($encounter->tgl_pulang));
        $authorRef  = 'Practitioner/' . $encounter->kode_dokter;
        $authorName = $encounter->dpjp_name;
        $title = ($careplane->kondisi_pulang ?? 'Pulang');
        $description = $careplane->keterangan
            ?? $careplane->alasan_pulang
            ?? 'Rencana pulang pasien.';

        $careplaneBundle = 
                [
                    'fullUrl'  => "urn:uuid:{$cpUuid}",
                    'resource' => [
                        'resourceType' => 'CarePlan',
                        'status'       => 'active',
                        'intent'       => 'plan',
                        'category'     => [
                            [
                                'coding' => [
                                    [
                                        'system'  => 'http://snomed.info/sct',
                                        'code'    => '734163000',
                                        'display' => 'Discharge planning',
                                    ],
                                ],
                            ],
                        ],

                        'title'       => $title,
                        'description' => $description,

                        'subject' => [
                            'reference' => $patientRef,
                            'display'   => $patientName,
                        ],
                        'encounter' => [
                            'reference' => $encounterRef,
                        ],
                        'created'  => $created,
                        'author'   => [
                            'reference' => $authorRef,
                            'display'   => $authorName,
                        ],
                    ],
                    'request' => [
                        'method' => 'POST',
                        'url'    => 'CarePlan',
                    ],
                ];
            
        
        return $careplaneBundle;
    }
}
