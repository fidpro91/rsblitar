<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ClinicalImpressionBundle
{
    public static function build($visitId)
    {
        $visit = Visit_encounter::with(['diagnossis', 'visit_lab'])
            ->where('visit_id', $visitId)
            ->firstOrFail();

        $clinicalImpressionUuid = $visit->uuid_clinicalimpresion;
        $identifierId = $visit->visit_id;
        $diagnoses = $visit->diagnossis;
        $primaryDiagnosis = $diagnoses->where('rank', 1)->first();
        $secondaryDiagnoses = $diagnoses->where('rank', '!=', 1);


        $patientId   = $visit->kode_pasien;
        $patientName = $visit->px_name;
        $encounterId = $visit->uuid_encounter;

        $effectiveDateTime = date("Y-m-d\TH:i:sP", strtotime($visit->tgl_dilayani));
        $dateClinicalImpression = date("Y-m-d\TH:i:sP", strtotime($visit->tgl_selesai_dilayani));
      

        $investigationItems = [];

        foreach ($visit->visit_lab as $lab) {
            if (!empty($lab->uuid_diagnostic)) {
                $investigationItems[] = [
                    'reference' => 'urn:uuid:' . $lab->uuid_diagnostic,
                ];
            }
        }

        $investigations = [];
        if (!empty($investigationItems)) {
            $investigations[] = [
                'code' => [                    
                    'text' => 'Pemeriksaan penunjang',
                ],
                'item' => $investigationItems,
            ];
        }

        $findings = [];

        foreach ($diagnoses as $diag) {
            $findings[] = [
                'itemCodeableConcept' => [
                    'coding' => [
                        [
                            'system'  => 'http://hl7.org/fhir/sid/icd-10',
                            'code'    => $diag->code ?? null,
                            'display' => $diag->dx_name ?? null,
                        ],
                    ],
                ],
                'itemReference' => [
                    'reference' => 'urn:uuid:' . ($diag->uuid ?? ''),
                ],
            ];
        }

    
        $summary = 'Prognosis terhadap ' . ($primaryDiagnosis->dx_name ?? 'diagnosis utama');

        if ($secondaryDiagnoses->isNotEmpty()) {
            $secondaryTexts = $secondaryDiagnoses
                ->pluck('dx_name')
                ->filter()
                ->values()
                ->all();

            if (!empty($secondaryTexts)) {
                $summary .= ', disertai adanya ' . implode('; ', $secondaryTexts);
            }
        }

        $description = $patientName . ' terdiagnosa ' . ($primaryDiagnosis->dx_name ?? '...');

        if ($secondaryDiagnoses->isNotEmpty()) {
            $secondaryTexts = $secondaryDiagnoses
                ->pluck('dx_name')
                ->filter()
                ->values()
                ->all();

            if (!empty($secondaryTexts)) {
                $description .= ', dan adanya ' . implode('; ', $secondaryTexts);
            }
        }


        $entry = [
            'fullUrl'  => 'urn:uuid:' . $clinicalImpressionUuid,
            'resource' => [
                'resourceType' => 'ClinicalImpression',
                'identifier'   => [
                    [
                        'use'    => 'official',
                        'system' => 'http://sys-ids.kemkes.go.id/clinicalimpression/' . env('ORG_ID_PROUD'),
                        'value'  => "$clinicalImpressionUuid"."- ClinicalImpression",
                    ],
                ],
                'status'      => 'completed',
                'description' => $description,

                'subject' => [
                    'reference' => 'Patient/' . $patientId,
                    'display'   => $patientName,
                ],

                'encounter' => [
                    'reference' => 'urn:uuid:' . $encounterId,
                    'display'   => 'Kunjungan ' . $patientName . ' di ' . $visit->unit_name,
                ],

                'effectiveDateTime' => $effectiveDateTime,
                'date'              => $dateClinicalImpression,

                'assessor' => [                    
                    'reference' => 'Practitioner/' . $visit->kode_dokter,
                ],

                'problem' => $primaryDiagnosis ? [[
                    'reference' => 'urn:uuid:' . ($primaryDiagnosis->uuid ?? ''),
                ]] : [],

                'investigation' => $investigations,
                'summary'       => $summary,
                'finding'       => $findings,
            ],
            'request' => [
                'method' => 'POST',
                'url'    => 'ClinicalImpression',
            ],
        ];

        return $entry;
    }
}
