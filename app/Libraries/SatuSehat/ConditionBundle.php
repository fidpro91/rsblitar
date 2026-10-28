<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use App\Libraries\SatuSehatStorageService;

class ConditionBundle
{
    public static function build($visitId)
    {
        $visit = Visit_encounter::with(['diagnossis'])
                    ->where('visit_id', $visitId)
                    ->firstOrFail();

        $storage = new SatuSehatStorageService();
        $existingEncounterId = $storage->getSpecificResourceId($visitId, 'Encounter');

        $encounterRef = !empty($existingEncounterId)
            ? "Encounter/{$existingEncounterId}"
            : "urn:uuid:{$visit->uuid_encounter}";

        $bundles = [];
        foreach ($visit->diagnossis as $diag) {
            $existingCondId = $storage->getSpecificResourceId($visitId, 'Condition_' . $diag->id) 
                ?? $storage->getSpecificResourceId($visitId, 'Condition');

            $resource = [
                "resourceType" => "Condition",
                "clinicalStatus" => [
                    "coding" => [
                        [
                            "system"  => "http://terminology.hl7.org/CodeSystem/condition-clinical",
                            "code"    => "active",
                            "display" => "Active"
                        ]
                    ]
                ],
                "category" => [
                    [
                        "coding" => [
                            [
                                "system"  => "http://terminology.hl7.org/CodeSystem/condition-category",
                                "code"    => "encounter-diagnosis",
                                "display" => "Encounter Diagnosis"
                            ]
                        ]
                    ]
                ],
                "code" => [
                    "coding" => [
                        [
                            "system"  => "http://hl7.org/fhir/sid/icd-10",
                            "code"    => $diag->code,
                            "display" => $diag->dx_name
                        ]
                    ]
                ],
                "subject" => [
                    "reference" => "Patient/{$visit->kode_pasien}",
                    "display"   => $visit->px_name
                ],
                "encounter" => [
                    "reference" => $encounterRef
                ],
                "onsetDateTime" => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_dilayani)),
                "recordedDate"  => date("Y-m-d\TH:i:sP", strtotime($diag->created_at ?? now())),
                "recorder" => [
                    "reference" => "Practitioner/{$visit->kode_dokter}",
                    "display"   => $visit->dpjp_name
                ],
                "note" => [
                    [
                        "text" => ""
                    ]
                ]
            ];

            if (!empty($existingCondId)) {
                $resource["id"] = $existingCondId;
                $request = [
                    "method" => "PUT",
                    "url"    => "Condition/{$existingCondId}"
                ];
            } else {
                $request = [
                    "method" => "POST",
                    "url"    => "Condition"
                ];
            }

            $bundles[] = [
                "fullUrl"  => "urn:uuid:{$diag->uuid}",
                "resource" => $resource,
                "request"  => $request
            ];
        }

        return $bundles;
    }
}
