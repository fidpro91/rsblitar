<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Log_http;
use App\Models\Tte_successfully;
use Illuminate\Http\Request;

class MonitoringTteController extends Controller
{
    /**
     * Tampilkan halaman Monitoring TTE.
     */
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'success'); // 'success' or 'logs'
        $search = $request->get('search');

        $querySuccess = Tte_successfully::query()->latest();
        if ($search) {
            $querySuccess->where(function ($q) use ($search) {
                $q->where('visit_id', 'like', "%{$search}%")
                  ->orWhere('doc_id', 'like', "%{$search}%")
                  ->orWhere('path_tte', 'like', "%{$search}%");
            });
        }
        $tteSuccessList = $querySuccess->paginate(15, ['*'], 'success_page');

        $queryLogs = Log_http::where('service_name', 'like', '%TTE%')->latest();
        if ($search) {
            $queryLogs->where(function ($q) use ($search) {
                $q->where('fk_id', 'like', "%{$search}%")
                  ->orWhere('response_message', 'like', "%{$search}%")
                  ->orWhere('response_body', 'like', "%{$search}%")
                  ->orWhere('endpoint_url', 'like', "%{$search}%");
            });
        }
        $tteLogsList = $queryLogs->paginate(15, ['*'], 'logs_page');

        $totalSuccess = Tte_successfully::count();
        $totalLogs = Log_http::where('service_name', 'like', '%TTE%')->count();
        $totalFailed = Log_http::where('service_name', 'like', '%TTE%')
            ->where(function ($q) {
                $q->where('response_code', '>=', 400)
                  ->orWhere('status', '>=', 400);
            })->count();

        return view('admin.monitoring_tte', compact(
            'tteSuccessList',
            'tteLogsList',
            'tab',
            'search',
            'totalSuccess',
            'totalLogs',
            'totalFailed'
        ));
    }
}

