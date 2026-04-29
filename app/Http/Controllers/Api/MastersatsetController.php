<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Illuminate\Support\Facades\DB;

class MastersatsetController extends BaseApiController
{
    public function search_practitioner($nik)
    {


        $url = "https://api-satusehat.kemkes.go.id/fhir-r4/v1/Practitioner?identifier=https://fhir.kemkes.go.id/id/nik|$nik";
        //dd($url);
        $response = $this->satusehat->connect('get', $url);
        return response()->json($response);
    }

    public function search_patient(Request $request)
    {
        $name = $request->query('name');
        $ttl = $request->query('birthdate');
        $gender = $request->query('gender');
        $nik = $request->query('nik');

        $url = "https://api-satusehat.kemkes.go.id/fhir-r4/v1/Patient?name=" . $name . "&birthdate=" . $ttl . "&gender=" . $gender . "&nik" . $nik;

        $response = $this->satusehat->connect('get', $url);
        return response()->json($response);
    }

    public function serachPxbynik($nik)
    {

        $url = "https://api-satusehat.kemkes.go.id/fhir-r4/v1/Patient?identifier=https://fhir.kemkes.go.id/id/nik|$nik";

        $data = $this->satusehat->connect('get', $url, null);

        return $data;
    }


    public function createlocation(Request $request)
    {
        $url = "https://api-satusehat.kemkes.go.id/fhir-r4/v1/Location";
        $data = [
            "resourceType" => "Location",
            "identifier" => [
                [
                    "system" => "http://sys-ids.kemkes.go.id/location/" . ENV('ORG_ID_PROUD'),
                    "value" => "DRI"
                ]
            ],
            "status" => "active",
            "name" => "DEPO RAWAT INAP",
            "description" => "Ruang DEPO RAWAT INAP",
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
                "longitude" => 112.181044,
                "latitude" => -8.109486,
                "altitude" => 0
            ],
            "managingOrganization" => [
                "reference" => "Organization/" . ENV('ORG_ID_PROUD')
            ]
        ];

        $response = $this->satusehat->connect('post', $url, $data);
        return response()->json($response);
    }
}
