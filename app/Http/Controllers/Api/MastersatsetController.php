<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Illuminate\Support\Facades\DB;

class MastersatsetController extends BaseApiController
{
    public function search_practitioner()
    {
        $url = "https://api-satusehat.kemkes.go.id/fhir-r4/v1//Location?name=depo";
        $response = $this->satusehat->connect('get', $url);
        return response()->json($response);
    }

    public function createlocation(Request $request)
    {
        $url = "https://api-satusehat.kemkes.go.id/fhir-r4/v1/Location";
        $data = [
            "resourceType" => "Location",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/location/".ENV('ORG_ID_PROUD'),
                    "value" => "DIGD"
                ]
            ],
            "status" => "active",
            "name" => "DEPO IGD",
            "description" => "Ruang DEPO IGD",
            "mode" => "instance",
            "telecom" => [
               
                [
                    "system" => "fax",
                    "value" => "(0342) 801118",
                    "use" => "work"
                ],
                [
                    "system" => "email",
                    "value" => "rsudmardiwaluyo@yahoo.com"
                ],
                [
                    "system" => "url",
                    "value" => "www.rsmardiwaluyo.com",
                    "use" => "work"
                ]
            ],
            "address" => [
                "use" => "work",
                "line" => [
                    "Jl. Kalimantan 113 Kota Blitar"
                ],
                "city" => "Blitar",
                "postalCode" => "12950",
                "country" => "ID",
                "extension" => [
                    [
                        "url" => "https://fhir.kemkes.go.id/r4/StructureDefinition/administrativeCode",
                        "extension" => [
                            [
                                "url" => "province",
                                "valueCode" => "35"
                            ],
                            [
                                "url" => "city",
                                "valueCode" => "3572"
                            ],
                            [
                                "url" => "district",
                                "valueCode" => "357203"
                            ],
                            [
                                "url" => "village",
                                "valueCode" => "3572031004"
                            ]
                        ]
                    ]
                ]
            ],
            "physicalType" => [
                "coding" => [
                    [
                        "system" => "http://terminology.hl7.org/CodeSystem/location-physical-type",
                        "code" => "ca",
                        "display" => "Cabinet"
                    ]
                ]
            ],
            "position" => [
                "longitude" => 112.614799,
                "latitude" => -7.816594,
                "altitude" => 0
            ],
            "managingOrganization" => [
                "reference" => "Organization/". ENV('ORG_ID_PROUD')
            ]
        ];

        $response = $this->satusehat->connect('post', $url, $data);
        return response()->json($response);
    }


}
