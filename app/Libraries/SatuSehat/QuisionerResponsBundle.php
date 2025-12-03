<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_quisioner;

class QuisionerResponsBundle
{
    public static function build($visitId)
    {
        $quisioner = Visit_quisioner::with(['visit_encounter'])
            ->where('visit_id', $visitId)
            ->first();

        $encounter = $quisioner->visit_encounter;
        $qrUuid = $quisioner->uuid_quisioner;
        $data = (array) $quisioner->data_quisioner;
        $items = [];
        for ($i = 1; $i <= 5; $i++) {
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
        $patientRef   = 'Patient/' . $encounter->kode_pasien;
        $patientName  = $encounter->px_name;
        $encounterRef = 'urn:uuid:' . $encounter->uuid_encounter;
        $authored =  date("Y-m-d\TH:i:sP", strtotime($quisioner->tgl_quisioner));
        $authorRef  = 'Practitioner/' . $quisioner->kode_apoteker;
        $authorName = $quisioner->nama_apoteker;

        $quisionerBundel = [
                'fullUrl'  => "urn:uuid:{$qrUuid}",
                'resource' => [
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
                ],
                'request' => [
                    'method' => 'POST',
                    'url'    => 'QuestionnaireResponse',
                ],
            ];


        return $quisionerBundel;
    }
}
