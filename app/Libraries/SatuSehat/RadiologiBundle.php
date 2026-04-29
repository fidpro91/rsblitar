<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;

class RadiologiBundle
{
    public static function build($visitId)
    {
        $encounter = Visit_encounter::with('visit_radiologi.ms_radiologi')
            ->where('visit_id', $visitId)
            ->firstOrFail();

        $orgId = env('ORG_ID_PROUD');
        $patientId = $encounter->kode_pasien;
        $encounterId = $encounter->uuid_encounter;

        $entries = [];

        foreach ($encounter->visit_radiologi as $rad) {

            $ms = $rad->ms_radiologi; 

            $serviceRequestId = $rad->uuid_service_request ;
            $observationId    = $rad->uuid_observation ;
            $diagnosticId     = $rad->uuid_diagnostic_report ;            

            /**
             * =========================
             * SERVICE REQUEST
             * =========================
             */
            $entries[] = [
                'fullUrl' => "urn:uuid:$serviceRequestId",
                'resource' => [
                    'resourceType' => 'ServiceRequest',
                    'identifier' => [
                        [
                            'system' => "http://sys-ids.kemkes.go.id/servicerequest/$orgId",
                            'value' => $encounter->visit_id . '-RAD-' . $rad->id,
                        ],
                        [
                            'use' => 'usual',
                            'type' => [
                                'coding' => [
                                    [
                                        'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                                        'code' => 'ACSN',
                                    ]
                                ]
                            ],
                            'system' => "http://sys-ids.kemkes.go.id/acsn/$orgId",
                            'value' => $rad->acsn_number,
                        ]
                    ],
                    'status' => 'active',
                    'intent' => 'original-order',
                    'category' => [
                        [
                            'coding' => [
                                [
                                    'system' => 'http://snomed.info/sct',
                                    'code' => '363679005',
                                    'display' => 'Imaging',
                                ]
                            ]
                        ]
                    ],
                    'priority' => $rad->prioritas ?? 'routine',
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://loinc.org',
                                'code' => $ms->loinc_code,
                                'display' => $ms->loinc_name,
                            ]
                        ],
                        'text' => $rad->nama_pemeriksaan,
                    ],
                    'orderDetail' => [
                        [
                            'coding' => [
                                [
                                    'system' => 'http://dicom.nema.org/resources/ontology/DCM',
                                    'code' => $ms->modality ?? 'DX',
                                ]
                            ],
                            'text' => 'Modality Code: ' . ($ms->modality ?? 'DX'),
                        ],
                    ],
                    'subject' => [
                        'reference' => "Patient/$patientId"
                    ],
                    'encounter' => [
                        'reference' => "urn:uuid:$encounterId"
                    ],
                    'occurrenceDateTime' => $rad->tanggal_order
                        ? date("Y-m-d\TH:i:sP", strtotime($rad->tanggal_order))
                        : null,
                    'requester' => [
                        'reference' => "Practitioner/$rad->kode_dokter_pengirim",
                        'display' => $rad->dokter_pengirim,
                    ],
                    'performer' => [
                        [
                            'reference' => "Practitioner/$rad->kode_dokter_radiologi",
                            'display' => $rad->dokter_radiologi,
                        ]
                    ],
                    'reasonCode' => [
                        [
                            'text' => $rad->alasan_klinis ?? '-',
                        ]
                    ],
                ],
                'request' => [
                    'method' => 'POST',
                    'url' => 'ServiceRequest',
                ]
            ];

            /**
             * =========================
             * OBSERVATION
             * =========================
             */
            // $entries[] = [
            //     'fullUrl' => "urn:uuid:$observationId",
            //     'resource' => [
            //         'resourceType' => 'Observation',
            //         'basedOn' => [
            //             [
            //                 'reference' => "urn:uuid:$serviceRequestId"
            //             ]
            //         ],
            //         'status' => 'final',
            //         'category' => [
            //             [
            //                 'coding' => [
            //                     [
            //                         'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
            //                         'code' => 'imaging',
            //                         'display' => 'Imaging'
            //                     ]
            //                 ]
            //             ]
            //         ],
            //         'code' => [
            //             'coding' => [
            //                 [
            //                     'system' => 'http://loinc.org',
            //                     'code' => $ms->loinc_code,
            //                     'display' => $ms->loinc_name,
            //                 ]
            //             ]
            //         ],
            //         'subject' => [
            //             'reference' => "Patient/$patientId"
            //         ],
            //         'encounter' => [
            //             'reference' => "urn:uuid:$encounterId"
            //         ],
            //         'effectiveDateTime' => $rad->tanggal_pemeriksaan
            //             ? date("Y-m-d\TH:i:sP", strtotime($rad->tanggal_pemeriksaan))
            //             : null,
            //         'issued' => $rad->tanggal_hasil
            //             ? date("Y-m-d\TH:i:sP", strtotime($rad->tanggal_hasil))
            //             : null,
            //         'performer' => [
            //             [
            //                 'reference' => "Practitioner/$rad->kode_dokter_radiologi",
            //                 'display' => $rad->dokter_radiologi,
            //             ]
            //         ],
            //         'valueString' => $rad->hasil_pemeriksaan ?? '-',
            //     ],
            //     'request' => [
            //         'method' => 'POST',
            //         'url' => 'Observation',
            //     ]
            // ];

            /**
             * =========================
             * DIAGNOSTIC REPORT
             * =========================
             */
        //     $entries[] = [
        //         'fullUrl' => "urn:uuid:$diagnosticId",
        //         'resource' => [
        //             'resourceType' => 'DiagnosticReport',
        //             'identifier' => [
        //                 [
        //                     'system' => "http://sys-ids.kemkes.go.id/diagnostic/$orgId/rad",
        //                     'value' => $encounter->visit_id . '-DR-' . $rad->id,
        //                 ]
        //             ],
        //             'basedOn' => [
        //                 [
        //                     'reference' => "urn:uuid:$serviceRequestId"
        //                 ]
        //             ],
        //             'status' => 'final',
        //             'category' => [
        //                 [
        //                     'coding' => [
        //                         [
        //                             'system' => 'http://terminology.hl7.org/CodeSystem/v2-0074',
        //                             'code' => 'RAD',
        //                             'display' => 'Radiology'
        //                         ]
        //                     ]
        //                 ]
        //             ],
        //             'code' => [
        //                 'coding' => [
        //                     [
        //                         'system' => 'http://loinc.org',
        //                         'code' => $ms->loinc_code,
        //                         'display' => $ms->loinc_name,
        //                     ]
        //                 ]
        //             ],
        //             'subject' => [
        //                 'reference' => "Patient/$patientId"
        //             ],
        //             'encounter' => [
        //                 'reference' => "urn:uuid:$encounterId"
        //             ],
        //             'effectiveDateTime' => $rad->tanggal_pemeriksaan
        //                 ? date("Y-m-d\TH:i:sP", strtotime($rad->tanggal_pemeriksaan))
        //                 : null,
        //             'issued' => $rad->tanggal_hasil
        //                 ? date("Y-m-d\TH:i:sP", strtotime($rad->tanggal_hasil))
        //                 : null,
        //             'performer' => [
        //                 [
        //                     'reference' => "Practitioner/$rad->kode_dokter_radiologi",
        //                 ],
        //                 [
        //                     'reference' => "Organization/$orgId"
        //                 ]
        //             ],
        //             'result' => [
        //                 [
        //                     'reference' => "urn:uuid:$observationId"
        //                 ]
        //             ],
        //             'conclusion' => $rad->hasil_pemeriksaan ?? '-',
        //         ],
        //         'request' => [
        //             'method' => 'POST',
        //             'url' => 'DiagnosticReport',
        //         ]
        //     ];
         }

        return $entries;
    }
}
