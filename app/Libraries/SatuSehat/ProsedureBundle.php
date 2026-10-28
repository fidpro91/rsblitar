<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use Illuminate\Support\Str;
use App\Libraries\SatuSehatStorageService;

class ProsedureBundle
{
    public static function build($visitId)
    {
        $visit = Visit_encounter::with('icd9')
            ->where('visit_id', $visitId)
            ->firstOrFail();

        $storage = new SatuSehatStorageService();
        $existingEncounterId = $storage->getSpecificResourceId($visitId, 'Encounter');

        $encounterRef = !empty($existingEncounterId)
            ? "Encounter/{$existingEncounterId}"
            : "urn:uuid:{$visit->uuid_encounter}";

        $entries = [];
        $patientId = $visit->kode_pasien;
        $performedDateTime = date("Y-m-d\TH:i:sP", strtotime($visit->tgl_kunjung));

        foreach ($visit->icd9 as $idx => $icd9) {
            $procUuid = $icd9->uuid ?: (string) Str::uuid();
            $existingProcId = $storage->getSpecificResourceId($visitId, 'Procedure_' . $icd9->id)
                ?? $storage->getSpecificResourceId($visitId, 'Procedure');

            $resource = [
                "resourceType" => "Procedure",
                "identifier"   => [
                    [
                        "system" => "https://fhir.kemkes.go.id/id/procedure",
                        "value"  => "{$procUuid} - Procedure",
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
                    "reference" => $encounterRef,
                ],
                "performedDateTime" => $performedDateTime,
            ];

            if (!empty($existingProcId)) {
                $resource["id"] = $existingProcId;
                $request = [
                    "method" => "PUT",
                    "url"    => "Procedure/{$existingProcId}"
                ];
            } else {
                $request = [
                    "method" => "POST",
                    "url"    => "Procedure"
                ];
            }

            $entries[] = [
                "fullUrl"  => "urn:uuid:" . $procUuid,
                "resource" => $resource,
                "request"  => $request,
            ];
        }

        return $entries;
    }
}
