<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use App\Libraries\SatuSehatStorageService;

class EpisodeOfCareBundle
{
    public static function build($visitId)
    {
        $visit = Visit_encounter::where('visit_id', $visitId)->firstOrFail();

        $storage = new SatuSehatStorageService();
        $existingEocId = $storage->getSpecificResourceId($visitId, 'EpisodeOfCare');
        $draft = $storage->getDraft($visitId);
        $draftFields = $draft['fields'] ?? [];

        $uuidepisode = (string) \Illuminate\Support\Str::uuid();
        $orgId = env('ORG_ID_PROUD', '100026368');

        $tglKunjung = !empty($visit->tgl_kunjung) 
            ? date("Y-m-d\TH:i:sP", strtotime($visit->tgl_kunjung)) 
            : date("Y-m-d\TH:i:sP");

        $isExisting = !empty($existingEocId);
        $hasPulang = !empty($visit->tgl_pulang);

        if (!$isExisting) {
           
            $status = "active";
            $period = [
                "start" => $tglKunjung
            ];
            $request = [
                "method" => "POST",
                "url"    => "EpisodeOfCare"
            ];
        } else {
            // Update resource yang sudah ada (PUT)
            $status = ($hasPulang || ($draftFields['episode_of_care_status'] ?? '') === 'finished') ? "finished" : "active";
            $period = [
                "start" => $tglKunjung
            ];
            if ($status === 'finished' && $hasPulang) {
                $period["end"] = date("Y-m-d\TH:i:sP", strtotime($visit->tgl_pulang));
            }
            $request = [
                "method" => "PUT",
                "url"    => "EpisodeOfCare/{$existingEocId}"
            ];
        }

        $resource = [
            "resourceType" => "EpisodeOfCare",
            "identifier"   => [
                [
                    "system" => "https://fhir.kemkes.go.id/id/episode-of-care",
                    "value"  => (string) $visit->visit_id
                ]
            ],              
            "status"       => $status,
            "patient"      => [
                "reference" => "Patient/" . $visit->kode_pasien
            ],
            "managingOrganization" => [
                "reference" => "Organization/" . $orgId
            ],
            "period"       => $period
        ];

        if ($isExisting) {
            $resource["id"] = $existingEocId;
        }

        return [
            "fullUrl"  => "urn:uuid:" . $uuidepisode,
            "resource" => $resource,
            "request"  => $request
        ];
    }
}
