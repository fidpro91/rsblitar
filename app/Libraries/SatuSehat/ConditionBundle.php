<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;

class ConditionBundle
{
    public static function build($visitId)
    {
        $visit = Visit_encounter::with(['diagnossis'])
                    ->where('visit_id', $visitId)
                    ->firstOrFail();

        $bundles = [];
        foreach ($visit->diagnossis as $diag) {
           
            $bundles[] = [
                "fullUrl" => "urn:uuid:{$diag->uuid}",
                "resource" => [
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
                        "reference" => "urn:uuid:{$visit->uuid_encounter}"
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
                ],
                "request" => [
                    "method" => "POST",
                    "url"    => "Condition"
                ]
            ];
        }

        return $bundles;
    }
}
