<?php 

namespace App\Libraries\SatuSehat;

use Illuminate\Support\Str;


class ImunisasiBundle
{
    public static function build($visitId)
    {
        // Load encounter and immunization transaction data
        $encounter = \App\Models\Visit_encounter::where('visit_id', $visitId)->first();
        if (!$encounter) return null;

        $imunisasi = \App\Models\Transaksi_imunisasi::where('visit_id', $visitId)->first();
        if (!$imunisasi) return null;

        $vaksin = $imunisasi->vaksin;

        // Compose the array using data from models
        return [
            "fullUrl" => "urn:uuid:" . Str::uuid(),
            "resource" => [
                "resourceType" => "Immunization",
                "status" => "completed",
                "vaccineCode" => [
                    "coding" => [
                        [
                            "system" => "http://sys-ids.kemkes.go.id/kfa",
                            "code" => $vaksin->kode_vaksin ?? "",
                            "display" => $vaksin->nama_vaksin ?? ""
                        ]
                    ]
                ],
                "patient" => [
                    "reference" => "Patient/" . ($encounter->kode_pasien ?? ""),
                    "display" => $encounter->px_name ?? ""
                ],
                "encounter" => [
                    "reference" => "Encounter/" . ($encounter->uuid_encounter ?? "")
                ],
                "occurrenceDateTime" => date("Y-m-d\TH:i:sP", strtotime($imunisasi->tanggal_imunisasi)),
                "recorded" => date("Y-m-d\TH:i:sP", strtotime($imunisasi->created_at)),
                "primarySource" => true,
                "location" => [
                    "reference" => "Location/" . ($encounter->idunitsatset ?? ""),
                    "display" => $encounter->unit_name ?? ""
                ],
                "lotNumber" => $imunisasi->nomor_batch ?? null,
                "expirationDate" => $imunisasi->tanggal_kadaluarsa ?? null,
                "route" => [
                    "coding" => [
                        [
                            "system" => "http://www.whocc.no/atc",
                            "code" => $imunisasi->cara_pemberian ?? "inj.intramuscular",
                            "display" => "Injection Intramuscular"
                        ]
                    ]
                ],
                "doseQuantity" => [
                    "value" => (int)($imunisasi->dosis_ke ?? 1),
                    "unit" => "mL",
                    "system" => "http://unitsofmeasure.org",
                    "code" => "ml"
                ],
                "performer" => [
                    [
                        "function" => [
                            "coding" => [
                                [
                                    "system" => "http://terminology.hl7.org/CodeSystem/v2-0443",
                                    "code" => "AP",
                                    "display" => "Administering Provider"
                                ]
                            ]
                        ],
                        "actor" => [
                            "reference" => "Practitioner/" . ($encounter->kode_dokter ?? "")
                        ]
                    ],
                    [
                        "actor" => [
                            "reference" => "Organization/". env('ORG_ID_PROUD')
                        ]
                    ]
                ],
                "reasonCode" => [
                    [
                        "coding" => [
                            [
                                "system" => "http://terminology.kemkes.go.id/CodeSystem/immunization-reason",
                                "code" => $vaksin->reason_code ,
                                "display" => $vaksin->reason_display 
                            ],
                            [
                                "system" => "http://terminology.kemkes.go.id/CodeSystem/immunization-routine-timing",
                                "code" => $vaksin->timing_code ,
                                "display" => $vaksin->timing_display 
                            ]
                        ]
                    ]
                ],
                "protocolApplied" => [
                    [
                        "doseNumberPositiveInt" => (int)($imunisasi->dosis_ke ?? 1)
                    ]
                ]
            ],
            "request" => [
                "method" => "POST",
                "url" => "Immunization"
            ]
        ];
    }
}