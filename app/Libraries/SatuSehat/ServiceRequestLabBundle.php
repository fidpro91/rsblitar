<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;

class ServiceRequestLabBundle
{
    public static function build($visitId)
    {

        $encounter = Visit_encounter::with([
            'visit_lab.pemeriksaan',
            'visit_lab.specimen',
            'diagnossis'
        ])->where('visit_id', $visitId)->firstOrFail();

        $entries = [];

        foreach ($encounter->visit_lab as $lab) {

            $conditionRef = [];
            if ($encounter->diagnossis->isNotEmpty()) {
                foreach ($encounter->diagnossis as $condition) {
                    $conditionRef[] = [
                        "reference" => "Condition/{$condition->uuid}"
                    ];
                }
            }

            // === ServiceRequest ===
            $entries[] = [
                "fullUrl" => "urn:uuid:{$lab->uuid_servicereq}",
                "resource" => [
                    "resourceType" => "ServiceRequest",
                    "identifier" => [
                        [
                            "system" => "http://sys-ids.kemkes.go.id/servicerequest/" . env('ORG_ID_PROUD'),
                            "value" => "$lab->visit_id"
                        ]
                    ],
                    "status" => "active",
                    "intent" => "original-order",
                    "priority" => "routine",
                    "category" => [
                        [
                            "coding" => [
                                [
                                    "system" => "http://snomed.info/sct",
                                    "code" => "108252007",
                                    "display" => "Laboratory procedure"
                                ]
                            ]
                        ]
                    ],
                    "code" => [
                        "coding" => [
                            [
                                "system" => $lab->pemeriksaan->code_system ?? "http://loinc.org",
                                "code" => $lab->pemeriksaan->code_value ?? "",
                                "display" => $lab->pemeriksaan->code_display ?? $lab->nama_pemeriksaan
                            ]
                        ],
                        "text" => $lab->nama_pemeriksaan
                    ],
                    "subject" => [
                        "reference" => "Patient/{$encounter->kode_pasien}"
                    ],
                    "encounter" => [
                        "reference" => "Encounter/{$encounter->uuid_encounter}",
                        "display" => "Permintaan {$lab->nama_pemeriksaan}"
                    ],
                    "occurrenceDateTime" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_periksa)),
                    "authoredOn" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_periksa)),
                    "requester" => [
                        "reference" => "Practitioner/{$lab->kode_pengirim}",
                        "display" => $lab->dokter_pengirim
                    ],
                    "performer" => [
                        [
                            "reference" => "Practitioner/{$lab->kode_dokter_lab}",
                            "display" => $lab->dokter_lab
                        ]
                    ],
                    "reasonReference" => $conditionRef,

                    "note" => [
                        [
                            "text" => ""
                        ]
                    ]
                ],
                "request" => [
                    "method" => "POST",
                    "url" => "ServiceRequest"
                ]
            ];

            // === Specimen ===
            $entries[] = [
                "fullUrl" => "urn:uuid:{$lab->uuid_specimen}",
                "resource" => [
                    "resourceType" => "Specimen",
                    "identifier" => [
                        [
                            "system" => "http://sys-ids.kemkes.go.id/specimen/" . env('ORG_ID_PROUD'),
                            "value" => "$lab->visit_id"
                        ]
                    ],
                    "status" => "available",
                    "type" => [
                        "coding" => [
                            [
                                "system" => $lab->specimen->code_system ?? "http://snomed.info/sct",
                                "code" => $lab->specimen->code_value ?? "",
                                "display" => $lab->specimen->code_display ?? "Unknown"
                            ]
                        ]
                    ],
                    "subject" => [
                        "reference" => "Patient/{$encounter->kode_pasien}"
                    ],
                    "receivedTime" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_selesai)),
                    "request" => [
                        [
                            "reference" => "ServiceRequest/{$lab->uuid_servicereq}"
                        ]
                    ],
                    "collection" => [
                        "collector" => [
                            "reference" => "Practitioner/{$lab->kode_dokter_lab}",
                            "display" => $lab->dokter_lab
                        ],
                        "collectedDateTime" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_ambil_sample)),
                        "quantity" => [
                            "value" => $lab->jumlah_sample ?? 1,
                            "unit" => $lab->satuan_sample ?? "mL"
                        ]
                    ],
                    "condition" => [
                        [
                            "text" => ""
                        ]
                    ]
                ],
                "request" => [
                    "method" => "POST",
                    "url" => "Specimen"
                ]
            ];

            
            $interpretation = [];
            switch (strtoupper($lab->status_normal)) {
                case 'H':
                    $interpretation[] = [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation",
                                "code" => "H",
                                "display" => "High"
                            ]
                        ]
                    ];
                    break;
                case 'L':
                    $interpretation[] = [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation",
                                "code" => "L",
                                "display" => "Low"
                            ]
                        ]
                    ];
                    break;
                case 'N':
                default:
                    $interpretation[] = [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/v3-ObservationInterpretation",
                                "code" => "N",
                                "display" => "Normal"
                            ]
                        ]
                    ];
                    break;
            }

            // Reference range dari ms_pemeriksaan
            $referenceRange = [
                [
                    "low" => [
                        "value" => (float)$lab->pemeriksaan->normal_low,
                        "unit" => $lab->pemeriksaan->unit,
                        "system" => "http://unitsofmeasure.org",
                        "code" => $lab->pemeriksaan->unit
                    ],
                    "high" => [
                        "value" => (float)$lab->pemeriksaan->normal_high,
                        "unit" => $lab->pemeriksaan->unit,
                        "system" => "http://unitsofmeasure.org",
                        "code" => $lab->pemeriksaan->unit
                    ],
                    "text" => $lab->pemeriksaan->normal_text
                ]
            ];

            // Observation 
            $entries[] = [
                "fullUrl" => "urn:uuid:{$lab->uuid_obs}",
                "resource" => [
                    "resourceType" => "Observation",
                    "status" => "final",
                    "identifier" => [
                        [
                            "system" => "http://sys-ids.kemkes.go.id/observation/" . env('ORG_ID_PROUD'),
                            "value" => "$lab->visit_id"
                        ]
                    ],
                    "basedOn" => [
                        [
                            "reference" => "ServiceRequest/{$lab->uuid_servicereq}"
                        ]
                    ],
                    "category" => [
                        [
                            "coding" => [
                                [
                                    "system" => "http://terminology.hl7.org/CodeSystem/observation-category",
                                    "code" => "laboratory",
                                    "display" => "Laboratory"
                                ]
                            ]
                        ]
                    ],
                    "code" => [
                        "coding" => [
                            [
                                "system" => $lab->pemeriksaan->code_system ?? "http://loinc.org",
                                "code" => $lab->pemeriksaan->code_value,
                                "display" => $lab->pemeriksaan->code_display ?? $lab->nama_pemeriksaan
                            ]
                        ]
                    ],
                    "subject" => [
                        "reference" => "Patient/{$encounter->kode_pasien}"
                    ],
                    "encounter" => [
                        "reference" => "Encounter/{$encounter->uuid_encounter}"
                    ],
                    "effectiveDateTime" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_selesai)),
                    "issued" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_selesai)),
                    "performer" => [
                        [
                            "reference" => "Practitioner/{$lab->kode_dokter_lab}"
                        ],
                        [
                            "reference" => "Organization/" . env('ORG_ID_PROUD')
                        ]
                    ],
                    "valueQuantity" => [
                        "value" => (float)$lab->hasil_lab,
                        "unit" => $lab->pemeriksaan->unit,
                        "system" => "http://unitsofmeasure.org",
                        "code" => $lab->pemeriksaan->unit
                    ],
                    "interpretation" => $interpretation,
                    "specimen" => [
                        "reference" => "Specimen/{$lab->uuid_specimen}"
                    ],
                    "referenceRange" => $referenceRange
                ],
                "request" => [
                    "method" => "POST",
                    "url" => "Observation"
                ]
            ];


            // === DiagnosticReport ===
            $entries[] = [
                "fullUrl" => "urn:uuid:{$lab->uuid_diagnostic}",
                "resource" => [
                    "resourceType" => "DiagnosticReport",
                    "identifier" => [
                        [
                            "system" => "http://sys-ids.kemkes.go.id/diagnostic/" . env('ORG_ID_PROUD') . "/lab",
                            "value" => "$lab->visit_id"
                        ]
                    ],
                    "basedOn" => [
                        [
                            "reference" => "ServiceRequest/{$lab->uuid_servicereq}"
                        ]
                    ],
                    "status" => "final",
                    "code" => [
                        "coding" => [
                            [
                                "system" => $lab->pemeriksaan->code_system ?? "http://loinc.org",
                                "code" => $lab->pemeriksaan->code_value ?? "",
                                "display" => $lab->pemeriksaan->code_display ?? $lab->nama_pemeriksaan
                            ]
                        ]
                    ],
                    "subject" => [
                        "reference" => "Patient/{$encounter->kode_pasien}"
                    ],
                    "encounter" => [
                        "reference" => "Encounter/{$encounter->uuid_encounter}"
                    ],
                    "effectiveDateTime" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_selesai)),
                    "issued" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_selesai)),
                    "performer" => [
                        [
                            "reference" => "Practitioner/{$lab->kode_dokter_lab}"
                        ]
                    ],
                    "specimen" => [
                        [
                            "reference" => "Specimen/{$lab->uuid_specimen}"
                        ]
                    ],
                    "result" => [
                        [
                            "reference" => "Observation/{$lab->uuid_obs}"
                        ]
                    ]
                ],
                "request" => [
                    "method" => "POST",
                    "url" => "DiagnosticReport"
                ]
            ];
        }

        return $entries;
    }
}
