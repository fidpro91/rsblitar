<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use App\Libraries\SatuSehatStorageService;
use Illuminate\Support\Str;

class CompositionBundle
{
    public static function build($visitId)
    {
        $visit = Visit_encounter::where('visit_id', $visitId)->firstOrFail();

        $storage = new SatuSehatStorageService();
        $existingEncounterId = $storage->getSpecificResourceId($visitId, 'Encounter');
        $existingCompId = $storage->getSpecificResourceId($visitId, 'Composition');

        $encounterRef = !empty($existingEncounterId)
            ? "Encounter/{$existingEncounterId}"
            : "urn:uuid:{$visit->uuid_encounter}";

        $draft = $storage->getDraft($visitId);
        $instruksiPulang = $draft['fields']['instruksi_pulang'] ?? ($visit->instruksi_pulang ?? 'Pasien dipulangkan dalam kondisi stabil.');
        $orgId = env('ORG_ID_PROUD', '100026368');

        $compositionUuid = !empty($visit->uuid_composition) ? $visit->uuid_composition : (string) Str::uuid();

        // FHIR Narrative requires valid XHTML
        $escapedDiv = htmlspecialchars($instruksiPulang, ENT_XML1, 'UTF-8');
        $divHtml = "<div xmlns=\"http://www.w3.org/1999/xhtml\">{$escapedDiv}</div>";

        // PERHATIAN: Dalam spesifikasi FHIR R4, Composition.identifier memiliki kardinalitas 0..1 (bukan array).
        // Oleh karena itu, nilainya harus berupa satu Identifier object, bukan array of Identifier.
        $resource = [
            "resourceType" => "Composition",
            "identifier" => [
                "system" => "http://sys-ids.kemkes.go.id/composition/" . $orgId,
                "value"  => "{$compositionUuid}-Composition"
            ],
            "status" => "final",
            "type" => [
                "coding" => [
                    [
                        "system"  => "http://loinc.org",
                        "code"    => "8653-8",
                        "display" => "Hospital discharge instructions"
                    ]
                ]
            ],
            "category" => [
                [
                    "coding" => [
                        [
                            "system"  => "http://loinc.org",
                            "code"    => "LP173421-1",
                            "display" => "Report"
                        ]
                    ]
                ]
            ],
            "subject" => [
                "reference" => "Patient/" . $visit->kode_pasien,
                "display"   => $visit->px_name
            ],
            "encounter" => [
                "reference" => $encounterRef
            ],
            "date" => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_pulang ?? now())),
            "author" => [
                [
                    "reference" => "Practitioner/" . $visit->kode_dokter,
                    "display"   => $visit->dpjp_name
                ]
            ],
            "title" => "Rencana Pemulangan (Discharge Plan)",
            "custodian" => [
                "reference" => "Organization/" . $orgId
            ],
            "section" => [
                [
                    "title" => "Rencana Pemulangan (Discharge Plan)",
                    "code" => [
                        "coding" => [
                            [
                                "system"  => "http://loinc.org",
                                "code"    => "8653-8",
                                "display" => "Hospital discharge instructions"
                            ]
                        ]
                    ],
                    "text" => [
                        "status" => "generated",
                        "div"    => $divHtml
                    ]
                ]
            ]
        ];

        if (!empty($existingCompId)) {
            $resource["id"] = $existingCompId;
            $request = [
                "method" => "PUT",
                "url"    => "Composition/{$existingCompId}"
            ];
        } else {
            $request = [
                "method" => "POST",
                "url"    => "Composition"
            ];
        }

        return [
            "fullUrl"  => "urn:uuid:{$compositionUuid}",
            "resource" => $resource,
            "request"  => $request
        ];
    }
}
