<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use App\Libraries\SatuSehatStorageService;

class ObservationBundle
{
    public static function build($visitId)
    {
        $visit = Visit_encounter::with(['observation.vital'])
            ->where('visit_id', $visitId)
            ->firstOrFail();

        $storage = new SatuSehatStorageService();
        $existingEncounterId = $storage->getSpecificResourceId($visitId, 'Encounter');

        $encounterRef = !empty($existingEncounterId)
            ? "Encounter/{$existingEncounterId}"
            : "urn:uuid:{$visit->uuid_encounter}";

        $bundles = [];

        foreach ($visit->observation as $obs) {
            if (!$obs->vital) continue;

            $issuedDate = $obs->created_at 
                ? (is_object($obs->created_at) && method_exists($obs->created_at, 'format') ? $obs->created_at->format('Y-m-d\TH:i:sP') : date("Y-m-d\TH:i:sP", strtotime($obs->created_at)))
                : date("Y-m-d\TH:i:sP");

            $value = [
                "valueQuantity" => [
                    "value"  => floatval($obs->result),
                    "unit"   => $obs->vital->satuan ?? "",
                    "system" => "http://unitsofmeasure.org",
                    "code"   => $obs->vital->code_satuan ?? ""
                ]
            ];

            $resource = [
                "resourceType" => "Observation",
                "status" => "final",
                "category" => [
                    [
                        "coding" => [
                            [
                                "system"  => "http://terminology.hl7.org/CodeSystem/observation-category",
                                "code"    => "vital-signs",
                                "display" => "Vital Signs"
                            ]
                        ]
                    ]
                ],
                "code" => [
                    "coding" => [
                        [
                            "system"  => $obs->vital->sumber ?? "http://loinc.org",
                            "code"    => $obs->vital->code ?? "",
                            "display" => $obs->vital->display
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
                "effectiveDateTime" => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_dilayani)),
                "issued" => $issuedDate,
                "performer" => [
                    [
                        "reference" => "Practitioner/{$visit->kode_dokter}",
                        "display"   => $visit->dpjp_name
                    ]
                ],
                "valueQuantity" => $value['valueQuantity']
            ];

            $existingObsId = $storage->getSpecificResourceId($visitId, 'Observation_' . $obs->id) 
                ?? $storage->getSpecificResourceId($visitId, 'Observation');

            if (!empty($existingObsId)) {
                $resource["id"] = $existingObsId;
                $request = [
                    "method" => "PUT",
                    "url"    => "Observation/{$existingObsId}"
                ];
            } else {
                $request = [
                    "method" => "POST",
                    "url"    => "Observation"
                ];
            }

            $bundles[] = [
                "fullUrl"  => "urn:uuid:{$obs->uuid_observation}",
                "resource" => $resource,
                "request"  => $request
            ];
        }

        return $bundles;
    }
}
