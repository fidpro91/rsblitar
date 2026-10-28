<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use App\Libraries\SatuSehatStorageService;

class RadiologiBundle
{
    public static function build($visitId)
    {
        $encounter = Visit_encounter::with('visit_radiologi.ms_radiologi')
            ->where('visit_id', $visitId)
            ->first();

        if (!$encounter || $encounter->visit_radiologi->isEmpty()) {
            return [];
        }

        $storage = new SatuSehatStorageService();
        $existingEncounterId = $storage->getSpecificResourceId($visitId, 'Encounter');

        $encounterRef = !empty($existingEncounterId)
            ? "Encounter/{$existingEncounterId}"
            : "urn:uuid:{$encounter->uuid_encounter}";

        $orgId = env('ORG_ID_PROUD', '100026368');
        $patientId = $encounter->kode_pasien;

        $entries = [];

        foreach ($encounter->visit_radiologi as $rad) {
            $ms = $rad->ms_radiologi; 
            $serviceRequestId = $rad->uuid_service_request;
            $existingRadSrId = $storage->getSpecificResourceId($visitId, 'ServiceRequest_Rad_' . $rad->id);

            $resource = [
                'resourceType' => 'ServiceRequest',
                'identifier' => [
                    [
                        'system' => "http://sys-ids.kemkes.go.id/servicerequest/{$orgId}",
                        'value' => "{$encounter->visit_id} - RAD - {$rad->id}",
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
                        'system' => "http://sys-ids.kemkes.go.id/acsn/{$orgId}",
                        'value' => $rad->acsn_number ?? "ACSN-{$rad->id}",
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
                            'code' => $ms->loinc_code ?? '36554-4',
                            'display' => $ms->loinc_name ?? ($rad->nama_pemeriksaan ?? 'X-Ray'),
                        ]
                    ],
                    'text' => $rad->nama_pemeriksaan ?? 'Radiology',
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
                    'reference' => "Patient/{$patientId}"
                ],
                'encounter' => [
                    'reference' => $encounterRef
                ],
                'occurrenceDateTime' => $rad->tanggal_order
                    ? date("Y-m-d\TH:i:sP", strtotime($rad->tanggal_order))
                    : date("Y-m-d\TH:i:sP"),
                'requester' => [
                    'reference' => "Practitioner/{$rad->kode_dokter_pengirim}",
                    'display' => $rad->dokter_pengirim ?? $encounter->dpjp_name,
                ],
                'performer' => [
                    [
                        'reference' => "Practitioner/{$rad->kode_dokter_radiologi}",
                        'display' => $rad->dokter_radiologi ?? $encounter->dpjp_name,
                    ]
                ],
                'reasonCode' => [
                    [
                        'text' => $rad->alasan_klinis ?? '-',
                    ]
                ],
            ];

            if (!empty($existingRadSrId)) {
                $resource['id'] = $existingRadSrId;
                $request = ['method' => 'PUT', 'url' => "ServiceRequest/{$existingRadSrId}"];
            } else {
                $request = ['method' => 'POST', 'url' => 'ServiceRequest'];
            }

            $entries[] = [
                'fullUrl'  => "urn:uuid:{$serviceRequestId}",
                'resource' => $resource,
                'request'  => $request,
            ];
        }

        return $entries;
    }
}
