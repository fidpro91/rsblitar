<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class LogResponseController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('respon_satusehat as r')
            ->join('visit_encounter as ve', 'r.visit_id', '=', 've.visit_id')
            ->select([
                've.px_norm',
                've.px_name',
                've.tgl_dilayani',
                've.unit_name',
                'r.resourcetype',
                'r.tgl_kirim',
                'r.respon_all',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Filter Periode
        |--------------------------------------------------------------------------
        */
        if ($request->filled('tgl_mulai') && $request->filled('tgl_akhir')) {

            $query->whereBetween(
                DB::raw('DATE(r.tgl_kirim)'),
                [
                    $request->tgl_mulai,
                    $request->tgl_akhir
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Filter Resource Type
        |--------------------------------------------------------------------------
        */
        if ($request->filled('resourcetype')) {

            $query->whereRaw(
                'LOWER(r.resourcetype) LIKE ?',
                ['%' . strtolower($request->resourcetype) . '%']
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */
        $start = $request->input('start', 0);

        $length = $request->input('length', 10);

        $recordsFiltered = $query->count();

        $data = $query
            ->skip($start)
            ->take($length)
            ->get();

        return response()->json([
            'status' => true,
            'draw' => intval($request->input('draw')),
            'recordsFiltered' => $recordsFiltered,
            'data' => $data
        ]);
    }
}
