<?php

namespace App\Http\Controllers\Simrs;

use App\Http\Controllers\Controller;
use App\Libraries\Simrs\SimrsSyncService;
use App\Libraries\Simrs\SimrsSyncStorageService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SimrsSyncController extends Controller
{
    protected SimrsSyncService $syncService;
    protected SimrsSyncStorageService $storage;

    public function __construct()
    {
        $this->storage     = new SimrsSyncStorageService();
        $this->syncService = new SimrsSyncService($this->storage);
    }

    /**
     * Halaman Utama Sinkronisasi SIMRS & Monitoring Log
     */
    public function index(Request $request)
    {
        $filters = [
            'status'     => $request->query('status', 'ALL'),
            'keyword'    => trim($request->query('keyword', '')),
            'start_date' => $request->query('start_date', ''),
            'end_date'   => $request->query('end_date', ''),
        ];

        $page    = max(1, (int) $request->query('page', 1));
        $perPage = 15;

        // Ambil data log & ringkasan metrik dari storage
        $allLogs = $this->storage->getLogs($filters);
        $totalLogs = count($allLogs);

        $slice = array_slice($allLogs, ($page - 1) * $perPage, $perPage);
        $logs = new LengthAwarePaginator(
            $slice,
            $totalLogs,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $stats = $this->storage->getStatsSummary($filters);

        return view('simrs.index', compact('logs', 'stats', 'filters'));
    }

    /**
     * Endpoint API / AJAX untuk mengeksekusi penarikan data SIMRS
     */
    public function pull(Request $request)
    {
        $request->validate([
            'tanggal'    => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
            'chunk_size' => 'nullable|integer|min:10|max:200',
            'limit'      => 'nullable|integer|min:1',
            'dry_run'    => 'nullable|boolean',
            'simulate'   => 'nullable|boolean',
        ]);

        $options = [
            'tanggal'    => $request->input('tanggal', date('Y-m-d')),
            'start_date' => $request->input('start_date', $request->input('tanggal', date('Y-m-d'))),
            'end_date'   => $request->input('end_date', $request->input('tanggal', date('Y-m-d'))),
            'chunk_size' => (int) $request->input('chunk_size', 50),
            'limit'      => $request->filled('limit') ? (int) $request->input('limit') : null,
            'dry_run'    => $request->boolean('dry_run'),
            'simulate'   => $request->boolean('simulate'),
            'trigger'    => 'manual',
        ];

        $result = $this->syncService->sync($options);

        $statusCode = $result['success'] ? 200 : ($result['status'] === 'FAILED' ? 500 : 207);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($result, $statusCode);
        }

        return redirect()->route('satusehat.simrs.index')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Ambil rincian satu log untuk Modal Inspeksi Monitoring
     */
    public function logDetail(string $id)
    {
        $log = $this->storage->getLogById($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Log penarikan data tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $log
        ]);
    }

    /**
     * Uji koneksi ke database SIMRS (Fast-fail check)
     */
    public function testConnection()
    {
        $res = $this->syncService->testConnection();
        $code = $res['success'] ? 200 : 503;

        return response()->json($res, $code);
    }
}
