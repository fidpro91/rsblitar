<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use App\Models\Configs;

class EncounterBundle
{
    public static function build($visitId)
    {        
        
        $visit = Visit_encounter::with(['diagnossis'])
                    ->where('visit_id', $visitId)
                    ->firstOrFail();

        $diagnosisArray = [];
        foreach ($visit->diagnossis as $diag) {
            $diagnosisArray[] = [
                "condition" => [
                    "reference" => "urn:uuid:" . $diag->uuid,
                    "display"   => $diag->dx_name
                ],
                "use" => [
                    "coding" => [
                        [
                            "system"  => "http://terminology.hl7.org/CodeSystem/diagnosis-role",
                            "code"    => "DD",
                            "display" => "Discharge diagnosis"
                        ]
                    ]
                ],
                "rank" => $diag->rank
            ];
        }

        $mapClass = [
            'RJ'  => ['code' => 'AMB',   'display' => 'ambulatory'],
            'RI'  => ['code' => 'IMP',   'display' => 'inpatient'],
            'IGD' => ['code' => 'EMER',  'display' => 'emergency'],
        ];

        $class = $mapClass[$visit->tipe_kunjungan] ?? ['code' => 'AMB', 'display' => 'ambulatory'];

        // Build Encounter bundle
        $encounterBundle = [
            "fullUrl" => "urn:uuid:{$visit->uuid_encounter}",
            "resource" => [
                "resourceType" => "Encounter",
                "identifier" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/encounter/".env('ORG_ID_PROUD'),
                        "value"  => "$visit->id - encounter"
                    ]
                ],
                "status" => "finished",
                "statusHistory" => array_values(array_filter([
                    (strtotime($visit->tgl_kunjung) && strtotime($visit->tgl_dilayani) && strtotime($visit->tgl_kunjung) <= strtotime($visit->tgl_dilayani)) ? [
                        "status" => "arrived",
                        "period" => [
                            "start" => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_kunjung)),
                            "end"   => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_dilayani))
                        ]
                    ] : null,
                    (strtotime($visit->tgl_dilayani) && strtotime($visit->tgl_selesai_dilayani) && strtotime($visit->tgl_dilayani) <= strtotime($visit->tgl_selesai_dilayani)) ? [
                        "status" => "in-progress",
                        "period" => [
                            "start" => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_dilayani)),
                            "end"   => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_selesai_dilayani))
                        ]
                    ] : null,
                    (strtotime($visit->tgl_selesai_dilayani) && strtotime($visit->tgl_pulang) && strtotime($visit->tgl_selesai_dilayani) <= strtotime($visit->tgl_pulang)) ? [
                        "status" => "finished",
                        "period" => [
                            "start" => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_selesai_dilayani)),
                            "end"   => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_pulang))
                        ]
                    ] : null
                ])),
                "class" => [
                    "system" => "http://terminology.hl7.org/CodeSystem/v3-ActCode",
                    "code"   => $class['code'],
                    "display" => $class['display']
                ],
                "subject" => [
                    "reference" => "Patient/{$visit->kode_pasien}",
                    "display"   => $visit->px_name
                ],
                "participant" => [
                    [
                        "type" => [
                            [
                                "coding" => [
                                    [
                                        "system"  => "http://terminology.hl7.org/CodeSystem/v3-ParticipationType",
                                        "code"    => "ATND",
                                        "display" => "attender"
                                    ]
                                ]
                            ]
                        ],
                        "individual" => [
                            "reference" => "Practitioner/{$visit->kode_dokter}",
                            "display"   => $visit->dpjp_name
                        ]
                    ]
                ],
                "period" => [
                    "start" => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_kunjung)),
                    "end"   => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_pulang))
                ],
                "diagnosis" => $diagnosisArray,
                "hospitalization" => [
                    "dischargeDisposition" => [
                        "coding" => [
                            [
                                "system"  => "http://terminology.hl7.org/CodeSystem/discharge-disposition",
                                "code"    => "self-care",
                                "display" => "Self care"
                            ]
                        ],
                        "text" => "Atas Izin Dokter"
                    ]
                ],
                "location" => [
                    [
                        "location" => [
                            "reference" => "Location/{$visit->idunitsatset}",
                            "display"   => $visit->unit_name
                        ],
                        "period" => [
                            "start" => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_kunjung)),
                            "end"   => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_pulang))
                        ]
                    ]
                ],
                "serviceProvider" => [
                    "reference" => "Organization/".env('ORG_ID_PROUD')
                ]
            ],
            "request" => [
                "method" => "POST",
                "url"    => "Encounter"
            ]
        ];

        return $encounterBundle;
    }
}
