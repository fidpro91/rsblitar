<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use App\Libraries\SatuSehatStorageService;

class AllergyBundle
{
    public static function build($visitId)
    {
        $visit = Visit_encounter::with(['visit_allergy.master'])
            ->where('visit_id', $visitId)
            ->first();

        if (!$visit || $visit->visit_allergy->isEmpty()) {
            return [];
        }

        $storage = new SatuSehatStorageService();
        $existingEncounterId = $storage->getSpecificResourceId($visitId, 'Encounter');

        $encounterRef = !empty($existingEncounterId)
            ? "Encounter/{$existingEncounterId}"
            : "urn:uuid:{$visit->uuid_encounter}";

        $orgId = env('ORG_ID_PROUD', '100026368');
        $entries = [];

        foreach ($visit->visit_allergy as $item) {
            $master = $item->master;
            $uuid = $item->uuid_allergy;
            $existingAllergyId = $storage->getSpecificResourceId($visitId, 'AllergyIntolerance_' . $item->id)
                ?? $storage->getSpecificResourceId($visitId, 'AllergyIntolerance');

            $resource = [
                "resourceType" => "AllergyIntolerance",
                "identifier" => [
                    [
                        "use" => "official",
                        "system" => "http://sys-ids.kemkes.go.id/allergy/{$orgId}",
                        "value" => "{$item->id} - AllergyIntolerance"
                    ]
                ],
                "clinicalStatus" => [
                    "coding" => [
                        [
                            "system"  => "http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical",
                            "code"    => "active",
                            "display" => "Active"
                        ]
                    ]
                ],
                "verificationStatus" => [
                    "coding" => [
                        [
                            "system"  => "http://terminology.hl7.org/CodeSystem/allergyintolerance-verification",
                            "code"    => "confirmed",
                            "display" => "Confirmed"
                        ]
                    ]
                ],
                "category" => [
                    $master->category ?? "medication"
                ],
                "criticality" => $master->criticality ?? "low",
                "code" => [
                    "coding" => [
                        [
                            "system"  => "http://sys-ids.kemkes.go.id/kfa",
                            "code"    => $master->substance_code ?? '0',
                            "display" => $master->substance_display ?? ($master->description ?? 'Allergy substance')
                        ]
                    ],
                    "text" => $master->description ?? 'Alergi'
                ],
                "patient" => [
                    "reference" => "Patient/" . $visit->kode_pasien,
                    "display"   => $visit->px_name
                ],
                "encounter" => [
                    "reference" => $encounterRef,
                    "display"   => "Kunjungan Pasien " . $visit->px_name
                ],
                "recordedDate" => date("Y-m-d\TH:i:sP", strtotime($item->tanggal_alergi ?? now())),
                "recorder" => [
                    "reference" => "Practitioner/" . $visit->kode_dokter,
                    "display"   => $visit->dpjp_name
                ]
            ];

            if (!empty($existingAllergyId)) {
                $resource["id"] = $existingAllergyId;
                $request = [
                    "method" => "PUT",
                    "url"    => "AllergyIntolerance/{$existingAllergyId}"
                ];
            } else {
                $request = [
                    "method" => "POST",
                    "url"    => "AllergyIntolerance"
                ];
            }

            $entries[] = [
                "fullUrl"  => "urn:uuid:{$uuid}",
                "resource" => $resource,
                "request"  => $request
            ];
        }

        return $entries;
    }
}
