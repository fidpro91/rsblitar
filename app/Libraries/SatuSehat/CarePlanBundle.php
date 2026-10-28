<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_careplane;
use App\Libraries\SatuSehatStorageService;

class CarePlanBundle
{
    public static function build($visitId)
    {
        $careplane = Visit_careplane::with('visit_encounter')
            ->where('visit_id', $visitId)
            ->first();

        if (!$careplane || !$careplane->visit_encounter) {
            return [];
        }

        $encounter = $careplane->visit_encounter;
        $storage = new SatuSehatStorageService();
        $existingEncounterId = $storage->getSpecificResourceId($visitId, 'Encounter');
        $existingCpId = $storage->getSpecificResourceId($visitId, 'CarePlan');

        $encounterRef = !empty($existingEncounterId)
            ? "Encounter/{$existingEncounterId}"
            : "urn:uuid:{$encounter->uuid_encounter}";

        $draft = $storage->getDraft($visitId);
        $draftFields = $draft['fields'] ?? [];

        $cpUuid = $careplane->uuid_careplane;
        $patientRef  = 'Patient/' . $encounter->kode_pasien;
        $patientName = $encounter->px_name;
        $created = date("Y-m-d\TH:i:sP", strtotime($encounter->tgl_pulang ?? now()));
        $authorRef  = 'Practitioner/' . $encounter->kode_dokter;
        $authorName = $encounter->dpjp_name;

        $title = $draftFields['kondisi_pulang'] ?? ($careplane->kondisi_pulang ?? 'Pulang');
        $description = $draftFields['alasan_pulang']
            ?? ($careplane->keterangan
            ?? $careplane->alasan_pulang
            ?? 'Rencana pulang pasien.');

        $resource = [
            'resourceType' => 'CarePlan',
            'status'       => 'active',
            'intent'       => 'plan',
            'category'     => [
                [
                    'coding' => [
                        [
                            'system'  => 'http://snomed.info/sct',
                            'code'    => '734163000',
                            'display' => 'Discharge planning',
                        ],
                    ],
                ],
            ],
            'title'       => $title,
            'description' => $description,
            'subject' => [
                'reference' => $patientRef,
                'display'   => $patientName,
            ],
            'encounter' => [
                'reference' => $encounterRef,
            ],
            'created'  => $created,
            'author'   => [
                'reference' => $authorRef,
                'display'   => $authorName,
            ],
        ];

        if (!empty($existingCpId)) {
            $resource['id'] = $existingCpId;
            $request = [
                'method' => 'PUT',
                'url'    => "CarePlan/{$existingCpId}",
            ];
        } else {
            $request = [
                'method' => 'POST',
                'url'    => 'CarePlan',
            ];
        }

        return [
            'fullUrl'  => "urn:uuid:{$cpUuid}",
            'resource' => $resource,
            'request'  => $request,
        ];
    }
}
