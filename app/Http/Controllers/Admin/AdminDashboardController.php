<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Log_http;
use App\Models\Tte_successfully;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AdminDashboardController extends Controller
{
    /**
     * Tampilkan halaman Dashboard Administrator.
     */
    public function index()
    {
        $tteApiUrl = config('tte.api_url', 'https://signed.blitarkota.go.id/');
        $totalTteSuccess = Tte_successfully::count();
        $totalTteLogs = Log_http::where('service_name', 'like', '%TTE%')->count();
        $recentSuccess = Tte_successfully::latest()->take(5)->get();
        $recentLogs = Log_http::where('service_name', 'like', '%TTE%')->latest()->take(5)->get();

        // Ambil riwayat log TTE untuk diproses statistik (aman untuk PostgreSQL & MariaDB)
        $tteLogsForStats = Log_http::where('service_name', 'like', '%TTE%')
            ->latest()
            ->take(300)
            ->get();

        $failedLogsCount = $tteLogsForStats->filter(function ($log) {
            $code = (int) ($log->response_code ?: $log->status);
            return $code >= 400;
        })->count();

        // Kelompokkan per tanggal secara aman via Laravel Collection
        $dailyGrouped = $tteLogsForStats->groupBy(function ($item) {
            return $item->created_at ? $item->created_at->format('Y-m-d') : date('Y-m-d');
        })->take(7)->reverse();

        $chartDates = [];
        $chartSuccess = [];
        $chartErrors = [];

        foreach ($dailyGrouped as $dateKey => $group) {
            $chartDates[] = date('d M', strtotime($dateKey));
            $chartSuccess[] = $group->filter(function ($log) {
                $code = (int) ($log->response_code ?: $log->status);
                return $code >= 200 && $code < 300;
            })->count();
            $chartErrors[] = $group->filter(function ($log) {
                $code = (int) ($log->response_code ?: $log->status);
                return $code >= 400;
            })->count();
        }

        return view('admin.dashboard', compact(
            'tteApiUrl',
            'totalTteSuccess',
            'totalTteLogs',
            'failedLogsCount',
            'recentSuccess',
            'recentLogs',
            'chartDates',
            'chartSuccess',
            'chartErrors'
        ));
    }

    /**
     * Endpoint API untuk pengujian koneksi & latensi real-time ke api_url TTE.
     */
    public function pingTteEndpoint()
    {
        $url = config('tte.api_url', 'https://signed.blitarkota.go.id/');
        $startTime = microtime(true);

        try {
            $response = Http::timeout(8)->withoutVerifying()->get($url);
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->status();
            $isOnline = ($statusCode >= 200 && $statusCode < 400);

            return response()->json([
                'success'    => true,
                'online'     => $isOnline,
                'url'        => $url,
                'status'     => $statusCode,
                'latency_ms' => $latencyMs,
                'time'       => now()->format('H:i:s'),
                'datetime'   => now()->format('d M Y, H:i:s'),
                'message'    => $isOnline ? "Terhubung (HTTP {$statusCode})" : "Status respons {$statusCode}",
            ]);
        } catch (\Exception $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return response()->json([
                'success'    => false,
                'online'     => false,
                'url'        => $url,
                'status'     => 0,
                'latency_ms' => $latencyMs,
                'time'       => now()->format('H:i:s'),
                'datetime'   => now()->format('d M Y, H:i:s'),
                'message'    => "Gagal terhubung: " . $e->getMessage(),
            ]);
        }
    }
}
