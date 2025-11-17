<?php

namespace App\Http\Controllers\Api;

use App\Libraries\SatuSehat\AllergyBundle;
use App\Libraries\SatuSehat\EncounterBundle;
use App\Libraries\SatuSehat\ConditionBundle;
use App\Libraries\SatuSehat\ObservationBundle;

class BundleRawatjalanController extends BaseApiController
{

    public function search_patient($nik)
    {
        $method = 'GET';
        $url = "https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Patient?identifier=https://fhir.kemkes.go.id/id/nik|$nik";

        $response = $this->satusehat->connect($method, $url, null);
        if (!empty($response['entry'][0]['resource']['id'])) {
            $result = [
                "kode_patient" => $response['entry'][0]['resource']['id']
            ];
        } else {
            $result = [
                "kode_patient" => null
            ];
        }
        return $result;
    }

    public function create_location()
    {
         $url = "https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/Location";
        $location = [
            "resourceType" => "Location",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/location/" . env('ORG_ID_DEV'),
                    "value"  => "DEPO RAJAL"
                ]
            ],
            "status" => "active",
            "name" => "DEPO RAJAL",
            "description" => "DEPO RAJAL",
            "mode" => "instance",

            "telecom" => [
                [
                    "system" => "phone",
                    "value"  => "+621500567",
                    "use"    => "work"
                ],
                [
                    "system" => "email",
                    "value"  => "pkm-testing@gmail.com",
                    "use"    => "work"
                ],
                [
                    "system" => "url",
                    "value"  => "dto.kemkes.go.id",
                    "use"    => "work"
                ]
            ],

            "physicalType" => [
                "coding" => [
                    [
                        "system"  => "http://terminology.hl7.org/CodeSystem/location-physical-type",
                        "code"    => "ro",
                        "display" => "Room"
                    ]
                ]
            ],

            "position" => [
                "longitude" => -6.23115426275766,
                "latitude"  => 106.83239885393944,
                "altitude"  => 0
            ],

            "managingOrganization" => [
                "reference" => "Organization/" . env('ORG_ID_DEV') 
            ]
        ];
         $response = $this->satusehat->connect('post', $url, $location);
          return response()->json($response, 200);
    }

    public function prepare_bundle($visitId)
    {
        $url = "https://api-satusehat-stg.dto.kemkes.go.id/fhir-r4/v1/";
        $encounterBundle = EncounterBundle::build($visitId);
        $conditionBundles = ConditionBundle::build($visitId);
        $observationBundle = ObservationBundle::build($visitId);
        $allergyBundle = AllergyBundle::build($visitId);
        $combinedBundle = [
            "resourceType" => "Bundle",
            "type" => "transaction",
            "entry" => array_merge([$encounterBundle], $conditionBundles, $observationBundle, $allergyBundle)
        ];

        $response = $this->satusehat->connect('post', $url, $combinedBundle);

        return response()->json($response, 200);
    }

    public function encounter($visitId)
    {
        $bundle = EncounterBundle::build($visitId);
        return response()->json($bundle, 200);
    }
}
