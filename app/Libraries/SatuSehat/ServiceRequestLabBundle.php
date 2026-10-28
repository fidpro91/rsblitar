<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use App\Libraries\SatuSehatStorageService;

class ServiceRequestLabBundle
{
    public static function build($visitId)
    {
        $encounter = Visit_encounter::with([
            'visit_lab.pemeriksaan',
            'visit_lab.specimen',
            'diagnossis'
        ])->where('visit_id', $visitId)->first();

        if (!$encounter || $encounter->visit_lab->isEmpty()) {
            return [];
        }

        $storage = new SatuSehatStorageService();
        $existingEncounterId = $storage->getSpecificResourceId($visitId, 'Encounter');

        $encounterRef = !empty($existingEncounterId)
            ? "Encounter/{$existingEncounterId}"
            : "urn:uuid:{$encounter->uuid_encounter}";

        $orgId = env('ORG_ID_PROUD', '100026368');
        $entries = [];

        foreach ($encounter->visit_lab as $lab) {
            $conditionRef = [];
            if ($encounter->diagnossis->isNotEmpty()) {
                foreach ($encounter->diagnossis as $condition) {
                    $existingCondId = $storage->getSpecificResourceId($visitId, 'Condition_' . $condition->id)
                        ?? $storage->getSpecificResourceId($visitId, 'Condition');

                    $conditionRef[] = [
                        "reference" => !empty($existingCondId) ? "Condition/{$existingCondId}" : "urn:uuid:{$condition->uuid}"
                    ];
                }
            }

            $serviceReqRef = "urn:uuid:{$lab->uuid_servicereq}";
            $specimenRef = "urn:uuid:{$lab->uuid_specimen}";
            $obsRef = "urn:uuid:{$lab->uuid_obs}";

            // === ServiceRequest ===
            $existingServiceReqId = $storage->getSpecificResourceId($visitId, 'ServiceRequest_' . $lab->id)
                ?? $storage->getSpecificResourceId($visitId, 'ServiceRequest');

            $srResource = [
                "resourceType" => "ServiceRequest",
                "identifier" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/servicerequest/{$orgId}",
                        "value" => "{$lab->id} - ServiceRequest"
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
                            "code" => $lab->pemeriksaan->code_value ?? "Unknown",
                            "display" => $lab->pemeriksaan->code_display ?? ($lab->nama_pemeriksaan ?? 'Pemeriksaan Lab')
                        ]
                    ],
                    "text" => $lab->nama_pemeriksaan ?? 'Pemeriksaan Lab'
                ],
                "subject" => [
                    "reference" => "Patient/{$encounter->kode_pasien}"
                ],
                "encounter" => [
                    "reference" => $encounterRef,
                    "display" => "Permintaan " . ($lab->nama_pemeriksaan ?? 'Lab')
                ],
                "occurrenceDateTime" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_periksa ?? now())),
                "authoredOn" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_periksa ?? now())),
                "requester" => [
                    "reference" => "Practitioner/{$lab->kode_pengirim}",
                    "display" => $lab->dokter_pengirim ?? $encounter->dpjp_name
                ],
                "performer" => [
                    [
                        "reference" => "Practitioner/{$lab->kode_dokter_lab}",
                        "display" => $lab->dokter_lab ?? $encounter->dpjp_name
                    ]
                ],
                "reasonReference" => $conditionRef,
            ];

            if (!empty($existingServiceReqId)) {
                $srResource["id"] = $existingServiceReqId;
                $srRequest = ["method" => "PUT", "url" => "ServiceRequest/{$existingServiceReqId}"];
                $serviceReqRef = "ServiceRequest/{$existingServiceReqId}";
            } else {
                $srRequest = ["method" => "POST", "url" => "ServiceRequest"];
            }

            $entries[] = [
                "fullUrl" => "urn:uuid:{$lab->uuid_servicereq}",
                "resource" => $srResource,
                "request" => $srRequest
            ];

            // === Specimen ===
            $existingSpecimenId = $storage->getSpecificResourceId($visitId, 'Specimen_' . $lab->id)
                ?? $storage->getSpecificResourceId($visitId, 'Specimen');

            $specResource = [
                "resourceType" => "Specimen",
                "identifier" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/specimen/{$orgId}",
                        "value" => "{$lab->id} - Specimen"
                    ]
                ],
                "status" => "available",
                "type" => [
                    "coding" => [
                        [
                            "system" => $lab->specimen->code_system ?? "http://snomed.info/sct",
                            "code" => $lab->specimen->code_value ?? "119297000",
                            "display" => $lab->specimen->code_display ?? "Blood specimen"
                        ]
                    ]
                ],
                "subject" => [
                    "reference" => "Patient/{$encounter->kode_pasien}"
                ],
                "receivedTime" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_selesai ?? now())),
                "request" => [
                    [
                        "reference" => $serviceReqRef
                    ]
                ],
                "collection" => [
                    "collector" => [
                        "reference" => "Practitioner/{$lab->kode_dokter_lab}",
                        "display" => $lab->dokter_lab ?? $encounter->dpjp_name
                    ],
                    "collectedDateTime" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_ambil_sample ?? now())),
                    "quantity" => [
                        "value" => $lab->jumlah_sample ?? 1,
                        "unit" => $lab->satuan_sample ?? "mL"
                    ]
                ]
            ];

            if (!empty($existingSpecimenId)) {
                $specResource["id"] = $existingSpecimenId;
                $specRequest = ["method" => "PUT", "url" => "Specimen/{$existingSpecimenId}"];
                $specimenRef = "Specimen/{$existingSpecimenId}";
            } else {
                $specRequest = ["method" => "POST", "url" => "Specimen"];
            }

            $entries[] = [
                "fullUrl" => "urn:uuid:{$lab->uuid_specimen}",
                "resource" => $specResource,
                "request" => $specRequest
            ];

            // === Observation ===
            $interpretation = [];
            switch (strtoupper($lab->status_normal ?? 'N')) {
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

            $referenceRange = [];
            if (!empty($lab->pemeriksaan)) {
                $referenceRange[] = [
                    "low" => [
                        "value" => (float)($lab->pemeriksaan->normal_low ?? 0),
                        "unit" => $lab->pemeriksaan->unit ?? '',
                        "system" => "http://unitsofmeasure.org",
                        "code" => $lab->pemeriksaan->unit ?? ''
                    ],
                    "high" => [
                        "value" => (float)($lab->pemeriksaan->normal_high ?? 0),
                        "unit" => $lab->pemeriksaan->unit ?? '',
                        "system" => "http://unitsofmeasure.org",
                        "code" => $lab->pemeriksaan->unit ?? ''
                    ],
                    "text" => $lab->pemeriksaan->normal_text ?? ''
                ];
            }

            $existingObsId = $storage->getSpecificResourceId($visitId, 'Observation_Lab_' . $lab->id);

            $obsResource = [
                "resourceType" => "Observation",
                "status" => "final",
                "identifier" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/observation/{$orgId}",
                        "value" => "{$lab->id} - Observation"
                    ]
                ],
                "basedOn" => [
                    [
                        "reference" => $serviceReqRef
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
                            "code" => $lab->pemeriksaan->code_value ?? "Unknown",
                            "display" => $lab->pemeriksaan->code_display ?? ($lab->nama_pemeriksaan ?? 'Lab')
                        ]
                    ]
                ],
                "subject" => [
                    "reference" => "Patient/{$encounter->kode_pasien}"
                ],
                "encounter" => [
                    "reference" => $encounterRef
                ],
                "effectiveDateTime" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_selesai ?? now())),
                "issued" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_selesai ?? now())),
                "performer" => [
                    [
                        "reference" => "Practitioner/{$lab->kode_dokter_lab}"
                    ],
                    [
                        "reference" => "Organization/{$orgId}"
                    ]
                ],
                "valueQuantity" => [
                    "value" => (float)($lab->hasil_lab ?? 0),
                    "unit" => $lab->pemeriksaan->unit ?? '',
                    "system" => "http://unitsofmeasure.org",
                    "code" => $lab->pemeriksaan->unit ?? ''
                ],
                "interpretation" => $interpretation,
                "specimen" => [
                    "reference" => $specimenRef
                ],
                "referenceRange" => $referenceRange
            ];

            if (!empty($existingObsId)) {
                $obsResource["id"] = $existingObsId;
                $obsRequest = ["method" => "PUT", "url" => "Observation/{$existingObsId}"];
                $obsRef = "Observation/{$existingObsId}";
            } else {
                $obsRequest = ["method" => "POST", "url" => "Observation"];
            }

            $entries[] = [
                "fullUrl" => "urn:uuid:{$lab->uuid_obs}",
                "resource" => $obsResource,
                "request" => $obsRequest
            ];

            // === DiagnosticReport ===
            $existingDrId = $storage->getSpecificResourceId($visitId, 'DiagnosticReport_' . $lab->id)
                ?? $storage->getSpecificResourceId($visitId, 'DiagnosticReport');

            $drResource = [
                "resourceType" => "DiagnosticReport",
                "identifier" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/diagnostic/{$orgId}/lab",
                        "value" => "{$lab->id} - DiagnosticReport"
                    ]
                ],
                "basedOn" => [
                    [
                        "reference" => $serviceReqRef
                    ]
                ],
                "status" => "final",
                "code" => [
                    "coding" => [
                        [
                            "system" => $lab->pemeriksaan->code_system ?? "http://loinc.org",
                            "code" => $lab->pemeriksaan->code_value ?? "Unknown",
                            "display" => $lab->pemeriksaan->code_display ?? ($lab->nama_pemeriksaan ?? 'Lab')
                        ]
                    ]
                ],
                "subject" => [
                    "reference" => "Patient/{$encounter->kode_pasien}"
                ],
                "encounter" => [
                    "reference" => $encounterRef
                ],
                "effectiveDateTime" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_selesai ?? now())),
                "issued" => date("Y-m-d\TH:i:sP", strtotime($lab->tgl_selesai ?? now())),
                "performer" => [
                    [
                        "reference" => "Practitioner/{$lab->kode_dokter_lab}"
                    ]
                ],
                "specimen" => [
                    [
                        "reference" => $specimenRef
                    ]
                ],
                "result" => [
                    [
                        "reference" => $obsRef
                    ]
                ]
            ];

            if (!empty($existingDrId)) {
                $drResource["id"] = $existingDrId;
                $drRequest = ["method" => "PUT", "url" => "DiagnosticReport/{$existingDrId}"];
            } else {
                $drRequest = ["method" => "POST", "url" => "DiagnosticReport"];
            }

            $entries[] = [
                "fullUrl" => "urn:uuid:{$lab->uuid_diagnostic}",
                "resource" => $drResource,
                "request" => $drRequest
            ];
        }

        return $entries;
    }
}
