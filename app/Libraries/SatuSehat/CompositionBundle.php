<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use Illuminate\Support\Str;

class CompositionBundle
{
    public static function build($visitId)
    {
        $visit = Visit_encounter::where('visit_id', $visitId)->firstOrFail();

        $compositionEntry = [
            "fullUrl" => "urn:uuid:" . $visit->uuid_composition,

            "resource" => [
                "resourceType" => "Composition",

                "identifier" => [
                    [
                        "system" => "http://sys-ids.kemkes.go.id/composition/" . env('ORG_ID_DEV'),
                        "value"  => $visit->visit_id
                    ]
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
                    "reference" => "urn:uuid:" . $visit->uuid_encounter
                ],

                "date" => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_pulang)),
                "author" => [
                    [
                        "reference" => "Practitioner/" . $visit->kode_dokter,
                        "display"   => $visit->dpjp_name
                    ]
                ],
                "title" => "Rencana Pemulangan (Discharge Plan)",
                "custodian" => [
                    "reference" => "Organization/" . env('ORG_ID_DEV')
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
                            "div"    => "$visit->instruksi_pulang"
                        ]
                    ]
                ]
            ],
            "request" => [
                "method" => "POST",
                "url"    => "Composition"
            ]
        ];

        return $compositionEntry;
    }
}
