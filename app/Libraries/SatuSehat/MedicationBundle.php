<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_farmasi;
use App\Models\Visit_encounter;
use App\Libraries\SatuSehatStorageService;

class MedicationBundle
{
    public static function build($visitId)
    {
        $medication = Visit_farmasi::with(['ms_kfa'])
            ->where('visit_id', $visitId)
            ->get();

        if ($medication->isEmpty()) {
            return [];
        }

        $visit = Visit_encounter::where('visit_id', $visitId)->firstOrFail();

        $storage = new SatuSehatStorageService();
        $existingEncounterId = $storage->getSpecificResourceId($visitId, 'Encounter');

        $encounterRef = !empty($existingEncounterId)
            ? "Encounter/{$existingEncounterId}"
            : "urn:uuid:{$visit->uuid_encounter}";

        $orgId = env('ORG_ID_PROUD', '100026368');
        $bundleEntries = [];

        foreach ($medication as $item) {
            $kfa = $item->ms_kfa;
            $existingMedId = $storage->getSpecificResourceId($visitId, 'Medication_' . $item->id)
                ?? $storage->getSpecificResourceId($visitId, 'Medication');

            $medRef = !empty($existingMedId)
                ? "Medication/{$existingMedId}"
                : "urn:uuid:{$item->uuid_med}";

            $medResource = [
                'resourceType' => 'Medication',
                'meta' => [
                    'profile' => [
                        'https://fhir.kemkes.go.id/r4/StructureDefinition/Medication'
                    ]
                ],
                'extension' => [
                    [
                        'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType',
                        'valueCodeableConcept' => [
                            'coding' => [
                                [
                                    'system' => 'http://terminology.kemkes.go.id/CodeSystem/medication-type',
                                    'code' => ($item->racikan ? 'N' : 'NC'),
                                    'display' => ($item->racikan ? 'Compound' : 'Non-compound'),
                                ]
                            ]
                        ]
                    ]
                ],
                'identifier' => [
                    [
                        'use' => 'official',
                        'system' => "http://sys-ids.kemkes.go.id/medication/{$orgId}",
                        'value' => "{$item->id} - medication"
                    ]
                ],
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://sys-ids.kemkes.go.id/kfa',
                            'code' => $kfa->code_kfa ?? ($item->kode_satusehat ?? '0'),
                            'display' => $kfa->nama_kfa ?? ($item->item_name ?? 'Medication')
                        ]
                    ]
                ],
                'status' => 'active',
            ];

            $ingredientArray = [];
            if (!empty($kfa->ingredients)) {
                $ing = $kfa->ingredients;
                if (is_string($ing)) {
                    $decoded = json_decode($ing, true);
                    if (json_last_error() === JSON_ERROR_NONE) $ing = $decoded;
                }
                if (is_array($ing)) {
                    foreach ($ing as $ingItem) {
                        $codingCode = $ingItem['code'] ?? $ingItem['kfa_code'] ?? ($ingItem['itemCodeableConcept']['coding'][0]['code'] ?? null);
                        $display = $ingItem['display'] ?? ($ingItem['name'] ?? ($ingItem['zat_aktif']) ?? ($ingItem['itemCodeableConcept']['coding'][0]['display'] ?? null));

                        $ingredientArray[] = [
                            'itemCodeableConcept' => [
                                'coding' => [
                                    [
                                        'system' => 'http://sys-ids.kemkes.go.id/kfa',
                                        'code' => $codingCode,
                                        'display' => $display
                                    ]
                                ]
                            ],
                            'isActive' => $ingItem['isActive'] ?? true
                        ];
                    }
                }
            }
            if (empty($ingredientArray)) {
                $ingredientArray[] = [
                    'itemCodeableConcept' => [
                        'coding' => [
                            [
                                'system' => 'http://sys-ids.kemkes.go.id/kfa',
                                'code' => $kfa->code_kfa ?? ($item->kode_satusehat ?? '0'),
                                'display' => $kfa->nama_kfa ?? ($item->item_name ?? 'Medication')
                            ]
                        ]
                    ],
                    'isActive' => true
                ];
            }
            $medResource['ingredient'] = $ingredientArray;

            if (!empty($existingMedId)) {
                $medResource['id'] = $existingMedId;
                $medRequest = ['method' => 'PUT', 'url' => "Medication/{$existingMedId}"];
            } else {
                $medRequest = ['method' => 'POST', 'url' => 'Medication'];
            }

            $bundleEntries[] = [
                'fullUrl'  => "urn:uuid:{$item->uuid_med}",
                'resource' => $medResource,
                'request'  => $medRequest
            ];

            // MedicationRequest
            $authoredOn = date("Y-m-d\TH:i:sP", strtotime($item->waktu_resep_dibuat ?? $item->rcp_date ?? now()));
            $existingMedReqId = $storage->getSpecificResourceId($visitId, 'MedicationRequest_' . $item->id)
                ?? $storage->getSpecificResourceId($visitId, 'MedicationRequest');

            $medReqResource = [
                'resourceType' => 'MedicationRequest',
                'identifier' => [
                    [
                        'use' => 'official',
                        'system' => "http://sys-ids.kemkes.go.id/prescription/{$orgId}",
                        'value' => "{$item->sale_num} - MedicationRequest"
                    ],
                    [
                        'use' => 'official',
                        'system' => "http://sys-ids.kemkes.go.id/prescription-item/{$orgId}",
                        'value' => "{$item->id} - MedicationRequest"
                    ]
                ],
                'status' => 'completed',
                'intent' => 'order',
                'category' => [
                    [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/medicationrequest-category',
                                'code' => 'outpatient',
                                'display' => 'Outpatient'
                            ]
                        ]
                    ]
                ],
                'priority' => 'routine',
                'medicationReference' => [
                    'reference' => $medRef,
                    'display' => $kfa->nama_kfa ?? ($item->item_name ?? 'Medication')
                ],
                'subject' => [
                    'reference' => "Patient/{$visit->kode_pasien}",
                    'display' => $visit->px_name
                ],
                'encounter' => [
                    'reference' => $encounterRef
                ],
                'authoredOn' => $authoredOn,
                'requester' => [
                    'reference' => "Practitioner/{$item->kode_dokter}",
                    'display' => $item->dokter_peresep ?? $visit->dpjp_name
                ]
            ];

            if (!empty($existingMedReqId)) {
                $medReqResource['id'] = $existingMedReqId;
                $medReqAction = ['method' => 'PUT', 'url' => "MedicationRequest/{$existingMedReqId}"];
            } else {
                $medReqAction = ['method' => 'POST', 'url' => 'MedicationRequest'];
            }

            $bundleEntries[] = [
                'fullUrl'  => "urn:uuid:{$item->uuid_med_request}",
                'resource' => $medReqResource,
                'request'  => $medReqAction
            ];

            // MedicationStatement
            $waktupembuatan = date("Y-m-d\TH:i:sP", strtotime($item->waktu_resep_diproses ?? $authoredOn));
            $obat_diberikan = date("Y-m-d\TH:i:sP", strtotime($item->waktu_diserahkan ?? $authoredOn));           
            $uuidMedStatement = (string) \Illuminate\Support\Str::uuid();

            $bundleEntries[] = [
                'fullUrl' => 'urn:uuid:' . $uuidMedStatement,
                'resource' => [
                    'resourceType' => 'MedicationStatement',
                    'meta' => [
                        'profile' => [
                            'https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationStatement'
                        ]
                    ],
                    'status' => 'active',
                    'medicationReference' => [
                        'reference' => $medRef,
                        'display' => $item->item_name ?? ($kfa->nama_kfa ?? 'Medication'),
                    ],
                    'subject' => [
                        'reference' => 'Patient/' . $visit->kode_pasien,
                        'display' => $visit->px_name,
                    ],
                    'context' => [
                        'reference' => $encounterRef,
                    ],                  
                    'effectiveDateTime' => $obat_diberikan,                   
                    'dateAsserted' => $waktupembuatan,
                    'informationSource' => [
                        'reference' => 'Practitioner/' . $item->kode_dokter,
                        'display' => $item->dokter_peresep ?? $visit->dpjp_name,
                    ],
                    'note' => [
                        [
                            'text' => 'Informasi penggunaan obat dinyatakan oleh pasien.'
                        ]
                    ],
                ],
                'request' => [
                    'method' => 'POST',
                    'url' => 'MedicationStatement'
                ]
            ];
            
            // MedicationDispense
            $authorizingReqRef = !empty($existingMedReqId)
                ? "MedicationRequest/{$existingMedReqId}"
                : "urn:uuid:{$item->uuid_med_request}";

            $bundleEntries[] = [
                'fullUrl' => 'urn:uuid:' . $item->uuid_med_dispen,
                'resource' => [
                    'resourceType' => 'MedicationDispense',
                    'identifier' => [
                        [
                            'system' => "http://sys-ids.kemkes.go.id/prescription/{$orgId}",
                            'use' => 'official',
                            'value' => "{$item->sale_num} - MedicationDispense"
                        ],
                        [
                            'system' => "http://sys-ids.kemkes.go.id/prescription-item/{$orgId}",
                            'use' => 'official',
                            'value' => "{$item->id} - MedicationDispense"
                        ]
                    ],
                    'status' => 'completed',
                    'category' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/fhir/CodeSystem/medicationdispense-category',
                                'code' => 'outpatient',
                                'display' => 'Outpatient'
                            ]
                        ]
                    ],
                    'medicationReference' => [
                        'reference' => $medRef,
                        'display' => $item->item_name ?? ($kfa->nama_kfa ?? 'Medication')
                    ],
                    'subject' => [
                        'reference' => 'Patient/' . $visit->kode_pasien,
                        'display' => $visit->px_name
                    ],
                    'context' => [
                        'reference' => $encounterRef
                    ],
                    'performer' => [
                        [
                            'actor' => [
                                'reference' => 'Practitioner/' . $item->kode_dokter,
                                'display' => $item->dokter_peresep ?? $visit->dpjp_name
                            ]
                        ]
                    ],
                    'location' => [
                        'reference' => 'Location/' . ($item->uuid_unit ?? $visit->idunitsatset),
                        'display' => $item->unit_name ?? $visit->unit_name
                    ],
                    'authorizingPrescription' => [
                        [
                            'reference' => $authorizingReqRef
                        ]
                    ],
                    'whenPrepared' => $waktupembuatan,
                    'whenHandedOver' => $obat_diberikan,
                ],
                'request' => [
                    'method' => 'POST',
                    'url' => 'MedicationDispense'
                ]
            ];
        }

        return $bundleEntries;
    }
}
