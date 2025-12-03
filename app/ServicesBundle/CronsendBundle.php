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
      
        $url = "https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/";

        try {

            $encounterBundle          = $this->cekBundle(EncounterBundle::build($visitId));
            $conditionBundles         = $this->cekBundle(ConditionBundle::build($visitId));
            $observationBundle        = $this->cekBundle(ObservationBundle::build($visitId));
            $allergyBundle            = $this->cekBundle(AllergyBundle::build($visitId));
            $compositionBundle        = $this->cekBundle(CompositionBundle::build($visitId));
            $serviceReqBundle         = $this->cekBundle(ServiceRequestLabBundle::build($visitId));
            $medicationBundle         = $this->cekBundle(MedicationBundle::build($visitId));
            $quisionserBundel         = $this->cekBundle(QuisionerResponsBundle::build($visitId));
            $careplaneBundle          = $this->cekBundle(CarePlanBundle::build($visitId));
            $clinicalimpresionBundle  = $this->cekBundle(ClinicalImpressionBundle::build($visitId));
            $episodeofcareBundle      = $this->cekBundle(EpisodeOfCareBundle::build($visitId));
            $prosedurBundle           = $this->cekBundle(ProsedureBundle::build($visitId));

            $entries = array_merge(
                $encounterBundle,
                $conditionBundles,
                $observationBundle,
                $allergyBundle,
                $compositionBundle,
                $serviceReqBundle,
                $medicationBundle,
                $quisionserBundel,
                $careplaneBundle,
                $clinicalimpresionBundle,
                $episodeofcareBundle,
                $prosedurBundle
            );
              

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
            

            $response = $this->satusehat->connect('post', $url, $combinedBundle);
          

            $pasienId = $encounterBundle[0]['resource']['subject']['reference'] ?? null;

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
            }

            return [
                "success" => true,
                "message" => "Bundle berhasil dikirim",
                "data"    => $response
            ];
        } catch (\Exception $e) {

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

    private function cekBundle($bundle): array
    {
        
        if ($bundle === null) return [];
        if (isset($bundle[0]) && is_array($bundle[0])) return $bundle;
        if (is_array($bundle) && isset($bundle['resource'])) return [$bundle];
        return [];
    }
}
