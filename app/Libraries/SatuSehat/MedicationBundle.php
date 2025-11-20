<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_farmasi;
use App\Models\Visit_encounter;

class MedicationBundle
{

    public static function build($visitId)
    {
        $medication = Visit_farmasi::with(['ms_kfa'])
            ->where('visit_id', $visitId)
            ->get();
        $visit = Visit_encounter::where('visit_id', $visitId)->firstOrFail();

        $bundleEntries = [];

        foreach ($medication as $item) {
            $kfa = $item->ms_kfa;

            $medicationResource = [
                'fullUrl' => 'urn:uuid:' . $item->uuid_med,
                'resource' => [
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
                            'system' => "http://sys-ids.kemkes.go.id/medication/" . env('ORG_ID_DEV'),
                            'value' => "$visit->visit_id"
                        ]
                    ],
                    'code' => [
                        'coding' => [
                            [
                                'system' => 'http://sys-ids.kemkes.go.id/kfa',
                                'code' => $kfa->code_kfa,
                                'display' => $kfa->nama_kfa ?? null
                            ]
                        ]
                    ],
                    'status' => 'active',
                ],
                'request' => [
                    'method' => 'POST',
                    'url' => 'Medication'
                ]
            ];

            // ingredient mapping (jika ada)
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
                        $strengthNumerator = $ingItem['strength']['numerator'] ?? null;
                        $strengthDenominator = $ingItem['strength']['denominator'] ?? null;
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
                                'code' => $kfa->code_kfa ?? ($item->item_id_kfa ?? null),
                                'display' => $kfa->nama_kfa ?? null
                            ]
                        ]
                    ],
                    'isActive' => true
                ];
            }
            $medicationResource['resource']['ingredient'] = $ingredientArray;

            // tambahkan Medication
            $bundleEntries[] = $medicationResource;

            // ------ MedicationRequest resource ------
            $authoredOn = date("Y-m-d\TH:i:sP", strtotime($item->waktu_resep_dibuat));


            $medRequestResource = [
                'fullUrl' => 'urn:uuid:' . $item->uuid_med_request,
                'resource' => [
                    'resourceType' => 'MedicationRequest',
                    'identifier' => [
                        [
                            'use' => 'official',
                            'system' => "http://sys-ids.kemkes.go.id/prescription/" . env('ORG_ID_DEV'),
                            'value' => $item->sale_num
                        ],
                        [
                            'use' => 'official',
                            'system' => "http://sys-ids.kemkes.go.id/prescription-item/" . env('ORG_ID_DEV'),
                            'value' => $item->sale_num
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
                        'reference' => 'Medication/' . $item->uuid_med,
                        'display' => $kfa->nama_kfa
                    ],
                    'subject' => [
                        'reference' => "Patient/{$visit->kode_pasien}",
                        'display' => $visit->px_name
                    ],
                    'encounter' => [
                        'reference' => 'urn:uuid:' . $visit->uuid_encounter
                    ],
                    'authoredOn' => $authoredOn,
                    'requester' => [
                        'reference' => "Practitioner/{$item->kode_dokter}",
                        'display' => $item->dokter_peresep
                    ],
                    'dosageInstruction' => [
                        [
                            'sequence' => 1,
                            'patientInstruction' => $item->dosis ?? ($kfa->dose_per_unit ?? null),
                            'timing' => [
                                'repeat' => [
                                    'frequency' => 1,
                                    'period' => 1,
                                    'periodUnit' => 'd'
                                ]
                            ],
                            'route' => [
                                'coding' => [
                                    [
                                        'system' => 'http://www.whocc.no/atc',
                                        'code' => 'O',
                                        'display' => 'Oral'
                                    ]
                                ]
                            ],
                            'doseAndRate' => [
                                [
                                    'type' => [
                                        'coding' => [
                                            [
                                                'system' => 'http://terminology.hl7.org/CodeSystem/dose-rate-type',
                                                'code' => 'ordered',
                                                'display' => 'Ordered'
                                            ]
                                        ]
                                    ],
                                    'doseQuantity' => [
                                        'value' => (float) ($item->sale_qty ?? 1),
                                        'unit' => $kfa->dose_per_unit ?? ($item->dosis ?? 'TAB'),
                                        'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm',
                                        'code' => $kfa->dose_per_unit ?? 'TAB'
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'dispenseRequest' => [
                        'dispenseInterval' => [
                            'value' => 1,
                            'unit' => 'days',
                            'system' => 'http://unitsofmeasure.org',
                            'code' => 'd'
                        ],
                        'numberOfRepeatsAllowed' => 0,
                        'quantity' => [
                            'value' => (float) ($item->sale_qty ?? 1),
                            'unit' => $kfa->dose_per_unit ?? 'TAB',
                            'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm',
                            'code' => $kfa->dose_per_unit ?? 'TAB'
                        ],
                        'expectedSupplyDuration' => [
                            'value' => 30,
                            'unit' => 'days',
                            'system' => 'http://unitsofmeasure.org',
                            'code' => 'd'
                        ],
                        'performer' => [
                            'reference' => 'Organization/' . env('ORG_ID_DEV')
                        ]
                    ]
                ],
                'request' => [
                    'method' => 'POST',
                    'url' => 'MedicationRequest'
                ]
            ];

            $bundleEntries[] = $medRequestResource;

            $waktupembuatan =  date("Y-m-d\TH:i:sP", strtotime($item->waktu_resep_diproses));
            $obat_diberikan =  date("Y-m-d\TH:i:sP", strtotime($item->waktu_diserahkan));
            $uom = json_decode($kfa->dosis_form, true);
            $medicationDispenseResource = [
                'fullUrl' => 'urn:uuid:' . $item->uuid_med_dispen,
                'resource' => [
                    'resourceType' => 'MedicationDispense',
                    'identifier' => [
                        [
                            'system' => "http://sys-ids.kemkes.go.id/prescription/" . env('ORG_ID_DEV'),
                            'use' => 'official',
                            'value' => $item->sale_num
                        ],
                        [
                            'system' => "http://sys-ids.kemkes.go.id/prescription-item/" . env('ORG_ID_DEV'),
                            'use' => 'official',
                            'value' => $item->sale_num
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
                        'reference' => 'Medication/' . $item->uuid_med,
                        'display' => $item->item_name ?? ($kfa->nama_kfa ?? null)
                    ],
                    'subject' => [
                        'reference' => 'Patient/' . $visit->kode_pasien,
                        'display' => $visit->px_name
                    ],
                    'context' => [
                        'reference' => 'urn:uuid:' . $visit->uuid_encounter
                    ],
                    'performer' => [
                        [
                            'actor' => [
                                'reference' => 'Practitioner/' . $item->kode_dokter,
                                'display' => $item->dokter_peresep
                            ]
                        ]
                    ],
                    'location' => [
                        'reference' => 'Location/' . $item->uuid_unit,
                        'display' => $item->unit_name
                    ],
                    'authorizingPrescription' => [
                        [
                            'reference' => 'MedicationRequest/' . $item->uuid_med_request
                        ]
                    ],

                    'quantity' => [
                        'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm',
                        'code' => $uom['name'] ?? 'TAB',
                        'value' => (float) ($item->sale_qty ?? 1)
                    ],
                    'whenPrepared' => $waktupembuatan,
                    'whenHandedOver' => $obat_diberikan,
                ],
                'request' => [
                    'method' => 'POST',
                    'url' => 'MedicationDispense'
                ]
            ];

            $bundleEntries[] = $medicationDispenseResource;
        }

        return  $bundleEntries;
    }
}
