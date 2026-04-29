<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use Illuminate\Support\Str;

class AllergyBundle
{
    public static function build($visitId)
    {
        $visit = Visit_encounter::with(['visit_allergy.master'])
            ->where('visit_id', $visitId)
            ->first();

        if (!$visit) {
            return [];
        }

        $entries = [];

        foreach ($visit->visit_allergy as $item) {
            $master = $item->master;
            $uuid = $item->uuid_allergy;
            $entries[] = [
                "fullUrl" => "urn:uuid:$uuid",
                "resource" => [
                    "resourceType" => "AllergyIntolerance",
                    "identifier" => [
                        [
                            "use" => "official",
                            "system" => "http://sys-ids.kemkes.go.id/allergy/" . env('ORG_ID_PROUD'),
                            "value" => "$item->id"."- AllergyIntolerance"
                        ]
                    ],

                    "clinicalStatus" => [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical",
                                "code" => "active",
                                "display" => "Active"
                            ]
                        ]
                    ],

                    "verificationStatus" => [
                        "coding" => [
                            [
                                "system" => "http://terminology.hl7.org/CodeSystem/allergyintolerance-verification",
                                "code" => "confirmed",
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
                                "system" => "http://sys-ids.kemkes.go.id/kfa",
                                "code" => $master->substance_code,
                                "display" => $master->substance_display
                            ]
                        ],
                        "text" => $master->description
                    ],

                    "patient" => [
                        "reference" => "Patient/" . $visit->kode_pasien,
                        "display" => $visit->px_name
                    ],

                    "encounter" => [
                        "reference" => "urn:uuid:" . $visit->uuid_encounter,
                        "display" => "Kunjungan Pasien " . $visit->px_name
                    ],

                    "recordedDate" => date("Y-m-d\TH:i:sP", strtotime($item->tanggal_alergi)),

                    "recorder" => [
                        "reference" => "Practitioner/" . $visit->kode_dokter,
                        "display" => $visit->dpjp_name
                    ]
                ],

                "request" => [
                    "method" => "POST",
                    "url" => "AllergyIntolerance"
                ]
            ];
        }

        return $entries;
    }
}
