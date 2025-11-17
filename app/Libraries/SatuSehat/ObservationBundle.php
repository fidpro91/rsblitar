<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;

class ObservationBundle
{
    public static function build($visitId)
    {

        $visit = Visit_encounter::with(['observation.vital'])
            ->where('visit_id', $visitId)
            ->firstOrFail();

        $bundles = [];

        foreach ($visit->observation as $obs) {
            if (!$obs->vital) continue;
            $value = [
                "valueQuantity" => [
                    "value"  => floatval($obs->result),
                    "unit"   => $obs->vital->satuan ?? "",
                    "system" => "http://unitsofmeasure.org",
                    "code"   => $obs->vital->code_satuan ?? ""
                ]
            ];

            $bundles[] = [
                "fullUrl" => "urn:uuid:{$obs->uuid_observation}",
                "resource" => [
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
                        "reference" => "urn:uuid:{$visit->uuid_encounter}"
                    ],
                    "effectiveDateTime" => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_dilayani)),
                    "issued" => $obs->created_at->format('Y-m-d\TH:i:sP'),
                    "performer" => [
                        [
                            "reference" => "Practitioner/{$visit->kode_dokter}",
                            "display"   => $visit->dpjp_name
                        ]
                    ],
                    "valueQuantity" => $value['valueQuantity']
                ],
                "request" => [
                    "method" => "POST",
                    "url"    => "Observation"
                ]
            ];
        }

        return $bundles;
    }
}
