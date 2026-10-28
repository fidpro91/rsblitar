<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use App\Libraries\SatuSehatStorageService;

class ClinicalImpressionBundle
{
    public static function build($visitId)
    {
        $visit = Visit_encounter::with(['diagnossis', 'visit_lab'])
            ->where('visit_id', $visitId)
            ->firstOrFail();

        $storage = new SatuSehatStorageService();
        $existingEncounterId = $storage->getSpecificResourceId($visitId, 'Encounter');
        $existingCiId = $storage->getSpecificResourceId($visitId, 'ClinicalImpression');

        $encounterRef = !empty($existingEncounterId)
            ? "Encounter/{$existingEncounterId}"
            : "urn:uuid:{$visit->uuid_encounter}";

        $clinicalImpressionUuid = $visit->uuid_clinicalimpresion;
        $diagnoses = $visit->diagnossis;
        $primaryDiagnosis = $diagnoses->where('rank', 1)->first();
        $secondaryDiagnoses = $diagnoses->where('rank', '!=', 1);

        $patientId   = $visit->kode_pasien;
        $patientName = $visit->px_name;
        $orgId       = env('ORG_ID_PROUD', '100026368');

        $effectiveDateTime = date("Y-m-d\TH:i:sP", strtotime($visit->tgl_dilayani));
        $dateClinicalImpression = date("Y-m-d\TH:i:sP", strtotime($visit->tgl_selesai_dilayani ?? now()));

        $investigationItems = [];
        foreach ($visit->visit_lab as $lab) {
            if (!empty($lab->uuid_diagnostic)) {
                $existingDrId = $storage->getSpecificResourceId($visitId, 'DiagnosticReport_' . $lab->id)
                    ?? $storage->getSpecificResourceId($visitId, 'DiagnosticReport');

                $investigationItems[] = [
                    'reference' => !empty($existingDrId) ? "DiagnosticReport/{$existingDrId}" : 'urn:uuid:' . $lab->uuid_diagnostic,
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
            $existingCondId = $storage->getSpecificResourceId($visitId, 'Condition_' . $diag->id)
                ?? $storage->getSpecificResourceId($visitId, 'Condition');

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
                    'reference' => !empty($existingCondId) ? "Condition/{$existingCondId}" : 'urn:uuid:' . ($diag->uuid ?? ''),
                ],
            ];
        }

        $summary = 'Prognosis terhadap ' . ($primaryDiagnosis->dx_name ?? 'diagnosis utama');
        if ($secondaryDiagnoses->isNotEmpty()) {
            $secondaryTexts = $secondaryDiagnoses->pluck('dx_name')->filter()->values()->all();
            if (!empty($secondaryTexts)) {
                $summary .= ', disertai adanya ' . implode('; ', $secondaryTexts);
            }
        }

        $description = $patientName . ' terdiagnosa ' . ($primaryDiagnosis->dx_name ?? '...');
        if ($secondaryDiagnoses->isNotEmpty()) {
            $secondaryTexts = $secondaryDiagnoses->pluck('dx_name')->filter()->values()->all();
            if (!empty($secondaryTexts)) {
                $description .= ', dan adanya ' . implode('; ', $secondaryTexts);
            }
        }

        $problem = [];
        if ($primaryDiagnosis) {
            $existingPrimCondId = $storage->getSpecificResourceId($visitId, 'Condition_' . $primaryDiagnosis->id)
                ?? $storage->getSpecificResourceId($visitId, 'Condition');

            $problem[] = [
                'reference' => !empty($existingPrimCondId) ? "Condition/{$existingPrimCondId}" : 'urn:uuid:' . ($primaryDiagnosis->uuid ?? ''),
            ];
        }

        $resource = [
            'resourceType' => 'ClinicalImpression',
            'identifier'   => [
                [
                    'use'    => 'official',
                    'system' => "http://sys-ids.kemkes.go.id/clinicalimpression/{$orgId}",
                    'value'  => "{$clinicalImpressionUuid} - ClinicalImpression",
                ],
            ],
            'status'      => 'completed',
            'description' => $description,
            'subject' => [
                'reference' => 'Patient/' . $patientId,
                'display'   => $patientName,
            ],
            'encounter' => [
                'reference' => $encounterRef,
                'display'   => 'Kunjungan ' . $patientName . ' di ' . $visit->unit_name,
            ],
            'effectiveDateTime' => $effectiveDateTime,
            'date'              => $dateClinicalImpression,
            'assessor' => [                    
                'reference' => 'Practitioner/' . $visit->kode_dokter,
            ],
            'problem' => $problem,
            'investigation' => $investigations,
            'summary'       => $summary,
            'finding'       => $findings,
        ];

        if (!empty($existingCiId)) {
            $resource['id'] = $existingCiId;
            $request = [
                'method' => 'PUT',
                'url'    => "ClinicalImpression/{$existingCiId}",
            ];
        } else {
            $request = [
                'method' => 'POST',
                'url'    => 'ClinicalImpression',
            ];
        }

        return [
            'fullUrl'  => 'urn:uuid:' . $clinicalImpressionUuid,
            'resource' => $resource,
            'request'  => $request,
        ];
    }
}
