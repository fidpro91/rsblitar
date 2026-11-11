<?php

namespace App\Libraries\SatuSehat;

use App\Models\Visit_encounter;
use App\Libraries\SatuSehatStorageService;
use Illuminate\Support\Facades\Log;

class ObservationBundle
{
    public static function build($visitId)
    {
        $visit = Visit_encounter::with(['observation.vital'])
            ->where('visit_id', $visitId)
            ->firstOrFail();

        $storage = new SatuSehatStorageService();

        // Ambil Encounter yang sudah tersimpan di SATUSEHAT
        $existingEncounterId = $storage->getSpecificResourceId(
            $visitId,
            'Encounter'
        );

        $encounterRef = !empty($existingEncounterId)
            ? "Encounter/{$existingEncounterId}"
            : "urn:uuid:{$visit->uuid_encounter}";

        $bundles = [];

        // Menyimpan URL resource untuk mendeteksi duplikasi
        $usedResourceUrls = [];

        foreach ($visit->observation as $obs) {

            // Lewati jika master vital tidak tersedia
            if (!$obs->vital) {
                continue;
            }

            // Validasi UUID Observation
            if (empty($obs->uuid_observation)) {
                Log::warning('UUID Observation kosong', [
                    'visit_id' => $visitId,
                    'observation_id' => $obs->id,
                ]);

                continue;
            }

            // Tanggal issued
            $issuedDate = $obs->created_at
                ? (
                    is_object($obs->created_at)
                    && method_exists($obs->created_at, 'format')
                        ? $obs->created_at->format('Y-m-d\TH:i:sP')
                        : date(
                            'Y-m-d\TH:i:sP',
                            strtotime($obs->created_at)
                        )
                )
                : date('Y-m-d\TH:i:sP');

            // Tanggal pemeriksaan
            $effectiveDate = !empty($visit->tgl_dilayani)
                ? date(
                    'Y-m-d\TH:i:sP',
                    strtotime($visit->tgl_dilayani)
                )
                : $issuedDate;

            // Resource Observation
            $resource = [
                'resourceType' => 'Observation',

                'status' => 'final',

                'category' => [
                    [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                                'code' => 'vital-signs',
                                'display' => 'Vital Signs',
                            ],
                        ],
                    ],
                ],

                'code' => [
                    'coding' => [
                        [
                            'system' => $obs->vital->sumber
                                ?? 'http://loinc.org',
                            'code' => $obs->vital->code ?? '',
                            'display' => $obs->vital->display,
                        ],
                    ],
                ],

                'subject' => [
                    'reference' => "Patient/{$visit->kode_pasien}",
                    'display' => $visit->px_name,
                ],

                'encounter' => [
                    'reference' => $encounterRef,
                ],

                'effectiveDateTime' => $effectiveDate,

                'issued' => $issuedDate,

                'performer' => [
                    [
                        'reference' => "Practitioner/{$visit->kode_dokter}",
                        'display' => $visit->dpjp_name,
                    ],
                ],

                'valueQuantity' => [
                    'value' => floatval($obs->result),
                    'unit' => $obs->vital->satuan ?? '',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => $obs->vital->code_satuan ?? '',
                ],
            ];

            /*
             * Ambil ID Observation berdasarkan ID data lokal.
             *
             * Jangan gunakan fallback 'Observation' karena bisa
             * menyebabkan beberapa Observation memakai ID SATUSEHAT
             * yang sama.
             */
            $existingObsId = $storage->getSpecificResourceId(
                $visitId,
                'Observation_' . $obs->id
            );

            // Tentukan operasi FHIR
            if (!empty($existingObsId)) {

                $resource['id'] = $existingObsId;

                $request = [
                    'method' => 'PUT',
                    'url' => "Observation/{$existingObsId}",
                ];

            } else {

                $request = [
                    'method' => 'POST',
                    'url' => 'Observation',
                ];
            }

            /*
             * Deteksi duplikasi operasi PUT.
             *
             * Beberapa Observation lokal tidak boleh melakukan
             * PUT terhadap resource SATUSEHAT yang sama.
             */
            if ($request['method'] === 'PUT') {

                $resourceUrl = $request['url'];

                if (isset($usedResourceUrls[$resourceUrl])) {

                    Log::warning(
                        'Duplikasi ID Observation dalam Bundle',
                        [
                            'visit_id' => $visitId,
                            'resource_url' => $resourceUrl,
                            'first_observation_id' =>
                                $usedResourceUrls[$resourceUrl],
                            'duplicate_observation_id' => $obs->id,
                        ]
                    );

                    continue;
                }

                $usedResourceUrls[$resourceUrl] = $obs->id;
            }

            // Masukkan ke Bundle
            $bundles[] = [
                'fullUrl' => "urn:uuid:{$obs->uuid_observation}",

                'resource' => $resource,

                'request' => $request,
            ];
        }

        return $bundles;
    }
}
