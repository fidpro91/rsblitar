<?php

namespace App\ServicesBundle;

use App\Libraries\SatuSehat\AllergyBundle;
use App\Libraries\SatuSehat\EncounterBundle;
use App\Libraries\SatuSehat\ConditionBundle;
use App\Libraries\SatuSehat\ObservationBundle;
use App\Libraries\SatuSehat\CompositionBundle;
use App\Libraries\SatuSehat\MedicationBundle;
use App\Libraries\SatuSehat\QuisionerResponsBundle;
use App\Libraries\SatuSehat\ServiceRequestLabBundle;
use App\Libraries\SatuSehat\CarePlanBundle;
use App\Libraries\SatuSehat\ClinicalImpressionBundle;
use App\Libraries\SatuSehat\EpisodeOfCareBundle;
use App\Libraries\SatuSehat\ProsedureBundle;
use App\Libraries\SatuSehatService;
use App\Libraries\SatuSehat\ImunisasiBundle;
use App\Libraries\SatuSehat\RadiologiBundle;
use App\Models\Respon_satusehat;


class CronsendBundle
{
    protected $satusehat;

    public function __construct()
    {
        $this->satusehat = new SatuSehatService();
    }

    public function prepareAndSendBundle($visitId)
    {
        $url = "https://api-satusehat.kemkes.go.id/fhir-r4/v1/";
        $builders = [
            EncounterBundle::class,
            ConditionBundle::class,
            ObservationBundle::class,
            AllergyBundle::class,
            CompositionBundle::class,
            ServiceRequestLabBundle::class,
            MedicationBundle::class,
            QuisionerResponsBundle::class,
            CarePlanBundle::class,
            ClinicalImpressionBundle::class,
            EpisodeOfCareBundle::class,
            ProsedureBundle::class,
           // ImunisasiBundle::class,
            RadiologiBundle::class
        ];

        try {
            $entries = [];
            foreach ($builders as $builderClass) {
                $entries = array_merge($entries, $this->safeBuild([$builderClass, 'build'], $visitId));
            }

            $entries = array_filter($entries, fn($e) => !empty($e));
            $entries = array_values($entries);

            if (empty($entries)) {
                throw new \Exception('Tidak ada entry bundle untuk visitId ' . $visitId);
            }

            $combinedBundle = [
                "resourceType" => "Bundle",
                "type"         => "transaction",
                "entry"        => $entries
            ];
          //    return response()->json($combinedBundle);die;
            // kirim ke SatuSehat
            $response = $this->satusehat->connect('post', $url, $combinedBundle);
            $pasienId = null;
            if (!empty($entries)) {
                foreach ($entries as $entry) {
                    if (isset($entry['resource']['resourceType']) && $entry['resource']['resourceType'] === 'Encounter') {
                        $pasienId = $entry['resource']['subject']['reference'] ?? null;
                        break;
                    }
                }
                if ($pasienId === null && isset($entries[0]['resource']['subject']['reference'])) {
                    $pasienId = $entries[0]['resource']['subject']['reference'];
                }
            }

            if (isset($response['entry']) && is_array($response['entry'])) {
                foreach ($response['entry'] as $item) {
                    if (!isset($item['response'])) continue;

                    $resp = $item['response'];

                    Respon_satusehat::create([
                        'status'        => $resp['status'] ?? 'unknown',
                        'resourcetype'  => $resp['resourceType'] ?? null,
                        'resourceid'    => $resp['resourceID'] ?? ($resp['id'] ?? null),
                        'metode'        => 'POST',
                        'tgl_kirim'     => now(),
                        'pasien_id'     => $pasienId,
                        'visit_id'      => $visitId,
                        'respon_all'    => $resp,
                    ]);
                }
            } else {
                Respon_satusehat::create([
                    'status'        => 'error',
                    'resourcetype'  => 'BundleResponse',
                    'resourceid'    => $response['id'] ?? null,
                    'metode'        => 'POST',
                    'tgl_kirim'     => now(),
                    'pasien_id'     => $pasienId,
                    'visit_id'      => $visitId,
                    'respon_all'    => $response,
                ]);
            }

            return [
                "success" => true,
                "message" => "Bundle berhasil dikirim",
                "data"    => $response
            ];
        } catch (\Throwable $e) {
            Respon_satusehat::create([
                'status'        => 'Exception',
                'resourcetype'  => 'Exception',
                'resourceid'    => null,
                'metode'        => 'POST',
                'tgl_kirim'     => now(),
                'visit_id'      => $visitId,
                'respon_all'    => ['error' => $e->getMessage()],
            ]);

            return [
                "success" => false,
                "message" => "Exception saat mengirim bundle",
                "error"   => $e->getMessage()
            ];
        }
    }

    private function safeBuild(callable $builderCallable, $visitId): array
    {
        try {
            $result = call_user_func($builderCallable, $visitId);
            return $this->cekBundle($result);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function cekBundle($bundle): array
    {
        if ($bundle === null) return [];
        if ($bundle === false) return [];
        if (is_object($bundle)) {
            if (method_exists($bundle, 'toArray')) {
                $bundle = $bundle->toArray();
            } else {
                $bundle = (array) $bundle;
            }
        }
        if (is_array($bundle) && isset($bundle[0]) && is_array($bundle[0])) {
            return $bundle;
        }

        if (is_array($bundle) && (isset($bundle['resource']) || isset($bundle['resourceType']))) {
            if (isset($bundle['resourceType']) && $bundle['resourceType'] === 'Bundle' && isset($bundle['entry']) && is_array($bundle['entry'])) {
                return $bundle['entry'];
            }
            if (isset($bundle['resource'])) {
                return [$bundle];
            }
            if (isset($bundle['resourceType'])) {
                return [$bundle];
            }
        }
        return [];
    }
}
