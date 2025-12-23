<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use App\Models\Configs;

class EpisodeOfCareBundle
{
    public static function build($visitId)
    {

        $visit = Visit_encounter::where('visit_id', $visitId)
            ->firstOrFail();
        $uuidepisode = (string) \Illuminate\Support\Str::uuid();
        $episodeOfCare = [
            "fullUrl" => "urn:uuid:" . $uuidepisode,
            "resource" => [
                "resourceType" => "EpisodeOfCare",
                "identifier"   => [
                    [
                        "system" => "https://fhir.kemkes.go.id/id/episode-of-care",
                        "value"  => "$visit->visit_id"."EpisodeOfcare"
                    ]
                ],              

                "status"       => "finished",
                "patient"      => [
                    "reference" => "Patient/" . $visit->kode_pasien
                ],
                "managingOrganization" => [
                    "reference" => "Organization/" . env('ORG_ID_PROUD')
                ],
                "period" => [
                    "start" => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_kunjung)),
                    "end"   => date("Y-m-d\TH:i:sP", strtotime($visit->tgl_pulang))
                ]

            ],
            "request" => [
                "method" => "POST",
                "url"    => "EpisodeOfCare"
            ]
        ];
        return $episodeOfCare;
    }
}
