<?php

namespace App\Http\Controllers\Api;

use App\Models\Kfa_master;
use App\Models\Ms_allergy;
use App\Models\Ms_pemeriksaan;
use App\Models\Ms_vital_sign;
use Illuminate\Http\Request;

class MappingSatsetController
{
    public function get_kfa() {
        $data = Kfa_master::select([
            'id',
            'code_kfa',
            'nama_kfa',
            'type',
            'status',
            'harga_obat',
            'waktu_update',
            'berat_bersih_uom',
            'volume_uom',
        ])->get();

        return response()->json([
            "code"      => "200",
            "message"   => "OK",
            "response"  => [
                "data"  => $data
            ]
        ]);
    }

    public function get_pemeriksaan() {
        $data = Ms_pemeriksaan::select([
            'code_value',
            'code_display',
            'category',
            'priority',
            'unit',
            'normal_low',
            'normal_high',
            'normal_text',
            'critical_low',
            'critical_high',
        ])->get();

        return response()->json([
            "code"      => "200",
            "message"   => "OK",
            "response"  => [
                "data"  => $data
            ]
        ]);
    }

    public function get_vital_sign() {
        $data = Ms_vital_sign::select([
            'code',
            'name',
            'display'
        ])->get();

        return response()->json([
            "code"      => "200",
            "message"   => "OK",
            "response"  => [
                "data"  => $data
            ]
        ]);
    }

    public function get_alergi() {
        $data = Ms_allergy::select([
            'substance_code',
            'substance_display',
            'category',
            'criticality',
            'description'
        ])->get();

        return response()->json([
            "code"      => "200",
            "message"   => "OK",
            "response"  => [
                "data"  => $data
            ]
        ]);
    }
}
