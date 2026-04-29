<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use Illuminate\Support\Str;

class ProsedureBundle
{

    public static function build($visitId)
    {

        $visit = Visit_encounter::with('icd9')
            ->where('visit_id', $visitId)
            ->firstOrFail();

        $entries = [];
        $patientId    = $visit->kode_pasien;
        $encounterId  = $visit->uuid_encounter;
        $performedDateTime = date("Y-m-d\TH:i:sP", strtotime($visit->tgl_kunjung));

        foreach ($visit->icd9 as $idx => $icd9) {

            $procUuid = $icd9->uuid ?: (string) Str::uuid();

            $entries[] = [
                "fullUrl"  => "urn:uuid:" . $procUuid,
                "resource" => [
                    "resourceType" => "Procedure",
                    "id"           => $procUuid,

                    "identifier"   => [
                        [
                            "system" => "https://fhir.kemkes.go.id/id/procedure",
                            "value"  => "$procUuid"."- Procedure",
                        ]
                    ],

                    "status" => "completed",
                    "category" => [
                        "coding" => [
                            [
                                "system"  => "http://snomed.info/sct",
                                "code"    => "103693007",
                                "display" => "Diagnostic procedure"
                            ]
                        ],
                        "text" => "Diagnostic procedure"
                    ],
                    "code"   => [
                        "coding" => [
                            [
                                "system"  => "http://hl7.org/fhir/sid/icd-9-cm",
                                "code"    => $icd9->icd_code,
                                "display" => $icd9->icd_name,
                            ]
                        ],
                        "text" => $icd9->icd_name,
                    ],

                    "subject" => [
                        "reference" => "Patient/" . $patientId,
                    ],

                    "encounter" => [
                        "reference" => "Encounter/" . $encounterId,
                    ],

                    "performedDateTime" => $performedDateTime,
                ],

                "request" => [
                    "method" => "POST",
                    "url"    => "Procedure",
                ],
            ];
        }

        return $entries;

    }
}
