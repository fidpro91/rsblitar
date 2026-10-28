<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_quisioner;
use App\Libraries\SatuSehatStorageService;

class QuisionerResponsBundle
{
    public static function build($visitId)
    {
        $quisioner = Visit_quisioner::with(['visit_encounter'])
            ->where('visit_id', $visitId)
            ->first();

        if (!$quisioner || !$quisioner->visit_encounter) {
            return [];
        }

        $encounter = $quisioner->visit_encounter;
        $qrUuid = $quisioner->uuid_quisioner;
        $data = (array) $quisioner->data_quisioner;
        $items = [];
        for ($i = 1; $i <= 7; $i++) {
            $key = 'pertanyaan_' . $i;

            if (!isset($data[$key])) {
                continue;
            }

            $row = $data[$key];

            $items[] = [
                'linkId' => (string) $i,
                'text'   => $row['teks'] ?? '',
                'answer' => [
                    [
                        'valueBoolean' => (bool) ($row['jawaban'] ?? false),
                    ],
                ],
            ];
        }

        $storage = new SatuSehatStorageService();
        $existingEncounterId = $storage->getSpecificResourceId($visitId, 'Encounter');
        $existingQrId = $storage->getSpecificResourceId($visitId, 'QuestionnaireResponse');

        $encounterRef = !empty($existingEncounterId)
            ? "Encounter/{$existingEncounterId}"
            : "urn:uuid:{$encounter->uuid_encounter}";

        $patientRef   = 'Patient/' . $encounter->kode_pasien;
        $patientName  = $encounter->px_name;
        $authored     = date("Y-m-d\TH:i:sP", strtotime($quisioner->tgl_quisioner ?? now()));
        $authorRef    = 'Practitioner/' . ($quisioner->kode_apoteker ?? $encounter->kode_dokter);
        $authorName   = $quisioner->nama_apoteker ?? $encounter->dpjp_name;

        $resource = [
            'resourceType'  => 'QuestionnaireResponse',
            'questionnaire' => 'https://fhir.kemkes.go.id/Questionnaire/Q0007',
            'status'        => 'completed',
            'subject'       => [
                'reference' => $patientRef,
                'display'   => $patientName,
            ],
            'encounter'     => [
                'reference' => $encounterRef,
            ],
            'authored'      => $authored,
            'author'        => [
                'reference' => $authorRef,
                'display'   => $authorName,
            ],
            'source'        => [
                'reference' => $patientRef,
            ],
            'item'          => $items,
        ];

        if (!empty($existingQrId)) {
            $resource['id'] = $existingQrId;
            $request = [
                'method' => 'PUT',
                'url'    => "QuestionnaireResponse/{$existingQrId}",
            ];
        } else {
            $request = [
                'method' => 'POST',
                'url'    => 'QuestionnaireResponse',
            ];
        }

        return [
            'fullUrl'  => "urn:uuid:{$qrUuid}",
            'resource' => $resource,
            'request'  => $request,
        ];
    }
}
