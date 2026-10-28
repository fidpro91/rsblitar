@extends('satusehat.layout')

@section('title', 'Monitoring Aliran Data Resource')

@section('styles')
<style>
    .kpi-card {
        border-radius: 12px;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }
    .resource-card {
        border-radius: 12px;
        transition: all .2s ease;
        border: 1px solid var(--border);
        background: var(--card);
    }
    .resource-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.08);
        border-color: var(--primary);
    }
    .resource-icon-wrap {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
    .dual-progress {
        height: 7px;
        border-radius: 4px;
        background-color: #f1f5f9;
        overflow: hidden;
        display: flex;
    }
    .dual-progress-bar-success {
        background-color: #10b981;
    }
    .dual-progress-bar-failed {
        background-color: #ef4444;
    }
    .pulse-live {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
        animation: pulse 1.8s infinite;
    }
    @keyframes pulse {
        0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.6); }
        70% { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    .log-table th {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--muted);
        font-weight: 600;
        background: var(--card);
    }
    .log-table td {
        vertical-align: middle;
        font-size: 13.5px;
    }
    .json-code-box {
        background: #0f172a;
        color: #e2e8f0;
        font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 12px;
        padding: 14px;
        border-radius: 8px;
        max-height: 400px;
        overflow: auto;
    }
</style>
@endsection

@section('content')
<div class="container-fluid p-0">

    {{-- HEADER & REAL-TIME CONTROLS --}}
    <div class="card-soft mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-activity text-primary"></i>
                    Monitoring Aliran Data Resource SATUSEHAT
                </h4>
                <p class="text-muted small mb-0">
                    Pemantauan transmisi data, deteksi kegagalan, dan throughput setiap jenis resource FHIR secara real-time.
                </p>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="badge bg-light text-dark border px-3 py-2 d-flex align-items-center gap-2">
                    <span class="pulse-live"></span>
                    <span class="small fw-semibold" id="lblLiveStatus">Live Transmisi</span>
                </div>

                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle fw-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-clock-history me-1"></i> Auto-Refresh: <span id="lblRefreshRate">Off</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item active opt-refresh" href="#" data-seconds="0">Nonaktif (Manual)</a></li>
                        <li><a class="dropdown-item opt-refresh" href="#" data-seconds="15">Setiap 15 Detik</a></li>
                        <li><a class="dropdown-item opt-refresh" href="#" data-seconds="30">Setiap 30 Detik</a></li>
                        <li><a class="dropdown-item opt-refresh" href="#" data-seconds="60">Setiap 1 Menit</a></li>
                    </ul>
                </div>

                <button class="btn btn-primary btn-sm fw-semibold" id="btnManualRefresh" onclick="window.location.reload()">
                    <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    {{-- FILTER BAR --}}
    <div class="card-soft mb-3">
        <form method="GET" action="{{ route('satusehat.resource-monitor.index') }}" id="filterForm">
            <div class="row g-2 align-items-end">
                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Dari Tanggal</label>
                    <input type="date" name="start_date" id="startDate" class="form-control form-control-sm" value="{{ $filters['start_date'] ?? '' }}">
                </div>

                <div class="col-md-3 col-sm-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" id="endDate" class="form-control form-control-sm" value="{{ $filters['end_date'] ?? '' }}">
                </div>

                <div class="col-md-2 col-sm-4">
                    <label class="form-label small fw-semibold text-muted mb-1">Resource</label>
                    <select name="resource_type" class="form-select form-select-sm">
                        <option value="ALL">Semua Resource ({{ count($resourceDefinitions) }})</option>
                        @foreach($resourceDefinitions as $rk => $rdef)
                            <option value="{{ $rk }}" {{ ($filters['resource_type'] ?? '') === $rk ? 'selected' : '' }}>
                                {{ $rk }} ({{ $rdef['label'] }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 col-sm-4">
                    <label class="form-label small fw-semibold text-muted mb-1">Status Transmisi</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="ALL" {{ ($filters['status'] ?? 'ALL') === 'ALL' ? 'selected' : '' }}>Semua Status</option>
                        <option value="SUCCESS" {{ ($filters['status'] ?? '') === 'SUCCESS' ? 'selected' : '' }}>Berhasil (201 Created)</option>
                        <option value="FAILED" {{ ($filters['status'] ?? '') === 'FAILED' ? 'selected' : '' }}>Gagal / Error</option>
                    </select>
                </div>

                <div class="col-md-2 col-sm-4 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill fw-semibold">
                        <i class="bi bi-funnel me-1"></i> Terapkan
                    </button>
                    <a href="{{ route('satusehat.resource-monitor.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </div>

            {{-- QUICK SHORTCUTS --}}
            <div class="d-flex align-items-center gap-1 mt-2 flex-wrap">
                <small class="text-muted fw-semibold me-2" style="font-size: 11px;">Pintas Waktu:</small>
                <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-filter" data-start="" data-end="">Semua</button>
                <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-filter" data-start="{{ date('Y-m-d') }}" data-end="{{ date('Y-m-d') }}">Hari Ini</button>
                <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-filter" data-start="{{ date('Y-m-d', strtotime('-1 day')) }}" data-end="{{ date('Y-m-d', strtotime('-1 day')) }}">Kemarin</button>
                <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-filter" data-start="{{ date('Y-m-d', strtotime('-7 day')) }}" data-end="{{ date('Y-m-d') }}">7 Hari Terakhir</button>
                <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-filter" data-start="{{ date('Y-m-01') }}" data-end="{{ date('Y-m-d') }}">Bulan Ini</button>
            </div>
        </form>
    </div>

    {{-- TOP KPI METRICS ROW --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card-soft kpi-card p-3 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small text-muted text-uppercase fw-semibold" style="font-size: 11px;">Total Transaksi Aliran</div>
                        <h3 class="mb-0 fw-bold text-dark mt-1" id="kpiTotal">{{ number_format($summary['total'] ?? 0) }}</h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3">
                        <i class="bi bi-arrow-left-right fs-4"></i>
                    </div>
                </div>
                <div class="small text-muted mt-2">
                    <span class="text-primary fw-semibold">{{ count($resourceDefinitions) }}</span> resource FHIR terpantau
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card-soft kpi-card p-3 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small text-success text-uppercase fw-semibold" style="font-size: 11px;">Resource Berhasil</div>
                        <h3 class="mb-0 fw-bold text-success mt-1" id="kpiSuccess">{{ number_format($summary['success'] ?? 0) }}</h3>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success p-2 rounded-3">
                        <i class="bi bi-check-circle fs-4"></i>
                    </div>
                </div>
                <div class="small text-muted mt-2">
                    Status: <span class="badge bg-success bg-opacity-10 text-success border border-success">201 Created</span>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card-soft kpi-card p-3 border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small text-danger text-uppercase fw-semibold" style="font-size: 11px;">Resource Gagal</div>
                        <h3 class="mb-0 fw-bold text-danger mt-1" id="kpiFailed">{{ number_format($summary['failed'] ?? 0) }}</h3>
                    </div>
                    <div class="bg-danger bg-opacity-10 text-danger p-2 rounded-3">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                </div>
                <div class="small text-muted mt-2">
                    Status: <span class="badge bg-danger bg-opacity-10 text-danger border border-danger">Error / Outcome</span>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card-soft kpi-card p-3 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="small text-info text-uppercase fw-semibold" style="font-size: 11px;">Kelancaran Aliran</div>
                        <h3 class="mb-0 fw-bold text-info mt-1" id="kpiRate">{{ $summary['success_rate'] ?? 100 }}%</h3>
                    </div>
                    <div class="bg-info bg-opacity-10 text-info p-2 rounded-3">
                        <i class="bi bi-pie-chart fs-4"></i>
                    </div>
                </div>
                <div class="mt-2">
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-info" role="progressbar" style="width: {{ $summary['success_rate'] ?? 100 }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- CHART VISUALIZATION --}}
    <div class="card-soft mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="fw-bold text-dark fs-6 d-flex align-items-center gap-2">
                <i class="bi bi-bar-chart-line text-primary"></i>
                Grafik Aliran Resource: Terkirim Berhasil vs Gagal
            </div>
            <div class="d-flex align-items-center gap-3 small">
                <span class="d-flex align-items-center gap-1"><span class="badge bg-success p-1 rounded-circle" style="width:10px;height:10px;"></span> Berhasil</span>
                <span class="d-flex align-items-center gap-1"><span class="badge bg-danger p-1 rounded-circle" style="width:10px;height:10px;"></span> Gagal</span>
            </div>
        </div>
        <div style="height: 240px; position: relative;">
            <canvas id="resourceFlowChart"></canvas>
        </div>
    </div>

    {{-- PER-RESOURCE STREAM MATRIX CARDS --}}
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-grid-3x3-gap text-secondary"></i>
            Matriks Pemantauan Aliran Data Setiap Resource
        </h6>
        <small class="text-muted">Menampilkan rincian status untuk 15 resource FHIR</small>
    </div>

    <div class="row g-3 mb-4" id="resourceMatrixGrid">
        @foreach($resourceStats as $item)
            @php
                $tot = $item['total'];
                $succ = $item['success'];
                $fail = $item['failed'];
                $rate = $item['success_rate'];
                $health = $item['health'];
                $succPct = $tot > 0 ? ($succ / $tot) * 100 : 100;
                $failPct = $tot > 0 ? ($fail / $tot) * 100 : 0;
            @endphp
            <div class="col-xl-3 col-lg-4 col-md-6 col-12">
                <div class="resource-card p-3 h-100 d-flex flex-column justify-content-between">
                    <div>
                        {{-- CARD HEADER --}}
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="resource-icon-wrap" style="background-color: {{ $item['color'] }}15; color: {{ $item['color'] }};">
                                    <i class="bi {{ $item['icon'] }}"></i>
                                </div>
                                <div class="lh-sm">
                                    <span class="fw-bold text-dark d-block">{{ $item['name'] }}</span>
                                    <small class="text-muted" style="font-size: 11px;">{{ $item['label'] }}</small>
                                </div>
                            </div>

                            @if($health === 'OPTIMAL')
                                <span class="badge bg-success bg-opacity-10 text-success border border-success" title="Transmisi lancar tanpa kegagalan signifikan">
                                    <i class="bi bi-shield-check me-1"></i>Optimal
                                </span>
                            @elseif($health === 'WARNING')
                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning" title="Ada beberapa kegagalan pengiriman">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Perhatian
                                </span>
                            @else
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger" title="Tingkat kegagalan tinggi">
                                    <i class="bi bi-x-circle me-1"></i>Kritis
                                </span>
                            @endif
                        </div>

                        {{-- DUAL PROGRESS BAR --}}
                        <div class="dual-progress my-2" title="Berhasil: {{ number_format($succ) }} ({{ round($succPct, 1) }}%) | Gagal: {{ number_format($fail) }} ({{ round($failPct, 1) }}%)">
                            <div class="dual-progress-bar-success" style="width: {{ $succPct }}%;"></div>
                            <div class="dual-progress-bar-failed" style="width: {{ $failPct }}%;"></div>
                        </div>

                        {{-- METRICS BREAKDOWN --}}
                        <div class="row g-1 text-center my-2">
                            <div class="col-4">
                                <small class="text-muted d-block" style="font-size: 10px;">TOTAL</small>
                                <span class="fw-bold text-dark small">{{ number_format($tot) }}</span>
                            </div>
                            <div class="col-4">
                                <small class="text-success d-block" style="font-size: 10px;">BERHASIL</small>
                                <span class="fw-bold text-success small">{{ number_format($succ) }}</span>
                            </div>
                            <div class="col-4">
                                <small class="text-danger d-block" style="font-size: 10px;">GAGAL</small>
                                <span class="fw-bold text-danger small">{{ number_format($fail) }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- CARD FOOTER --}}
                    <div class="pt-2 border-top mt-2 d-flex justify-content-between align-items-center">
                        <small class="text-muted font-monospace" style="font-size: 11px;">
                            Sukses: <strong class="{{ $rate >= 90 ? 'text-success' : 'text-danger' }}">{{ $rate }}%</strong>
                        </small>

                        <a href="{{ route('satusehat.resource-monitor.index', array_merge(request()->query(), ['resource_type' => $item['name']])) }}" 
                           class="btn btn-outline-primary btn-sm py-0 px-2" 
                           style="font-size: 11px;">
                            <i class="bi bi-filter me-1"></i>Log
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- RECENT TRANSMISSION STREAM TABLE --}}
    <div class="card-soft">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-primary"></i>
                    Log Aliran Data Transmisi SATUSEHAT
                </h6>
                <small class="text-muted">
                    Menampilkan {{ $paginator->firstItem() ?? 0 }} - {{ $paginator->lastItem() ?? 0 }} dari total {{ number_format($paginator->total()) }} interaksi
                </small>
            </div>

            <div class="d-flex align-items-center gap-2">
                <form method="GET" action="{{ route('satusehat.resource-monitor.index') }}" class="d-flex gap-1">
                    @foreach($filters as $fk => $fv)
                        @if($fk !== 'search' && !empty($fv))
                            <input type="hidden" name="{{ $fk }}" value="{{ $fv }}">
                        @endif
                    @endforeach
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari Visit ID / NIK / Status..." value="{{ $filters['search'] ?? '' }}" style="width: 220px;">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover log-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width: 150px;">Waktu Transmisi</th>
                        <th style="width: 160px;">Resource FHIR</th>
                        <th style="width: 130px;">ID Kunjungan</th>
                        <th style="width: 130px;">Pasien / NIK</th>
                        <th style="width: 140px;">Status SATUSEHAT</th>
                        <th>Keterangan / Diagnostik</th>
                        <th style="width: 120px;" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>
                                <div class="fw-semibold text-dark small">
                                    {{ $log->tgl_kirim ? \Carbon\Carbon::parse($log->tgl_kirim)->format('d/m/Y H:i:s') : '-' }}
                                </div>
                                <small class="text-muted" style="font-size: 11px;">
                                    {{ $log->tgl_kirim_human }}
                                </small>
                            </td>

                            <td>
                                <span class="badge px-2 py-1 text-dark border d-inline-flex align-items-center gap-1"
                                      style="background-color: {{ $log->resource_color }}18; border-color: {{ $log->resource_color }}40 !important;">
                                    <i class="bi {{ $log->resource_icon }}" style="color: {{ $log->resource_color }};"></i>
                                    <strong>{{ $log->resourcetype }}</strong>
                                </span>
                            </td>

                            <td>
                                @if(!empty($log->visit_id))
                                    <a href="{{ route('satusehat.detail', $log->visit_id) }}" class="fw-bold font-monospace text-primary text-decoration-none" title="Lihat Detail Encounter">
                                        {{ $log->visit_id }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>

                            <td>
                                <span class="font-monospace text-secondary small">
                                    {{ $log->pasien_id ?: '-' }}
                                </span>
                            </td>

                            <td>
                                @if($log->is_success)
                                    <span class="badge bg-success font-monospace px-2 py-1">
                                        <i class="bi bi-check2-circle me-1"></i>{{ $log->status }}
                                    </span>
                                @else
                                    <span class="badge bg-danger font-monospace px-2 py-1">
                                        <i class="bi bi-exclamation-octagon me-1"></i>{{ $log->status }}
                                    </span>
                                @endif
                            </td>

                            <td>
                                <div class="small text-truncate" style="max-width: 380px;" title="{{ $log->diagnostic_text }}">
                                    @if($log->is_success)
                                        <span class="text-muted font-monospace" style="font-size: 12px;">{{ $log->diagnostic_text }}</span>
                                    @else
                                        <span class="text-danger fw-semibold">{{ $log->diagnostic_text }}</span>
                                    @endif
                                </div>
                            </td>

                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" 
                                            class="btn btn-outline-secondary btn-inspect-json" 
                                            data-id="{{ $log->id_respon }}" 
                                            title="Lihat Raw JSON Respon SATUSEHAT">
                                        <i class="bi bi-code-slash"></i>
                                    </button>

                                    @if(!empty($log->visit_id))
                                        <a href="{{ route('satusehat.detail', $log->visit_id) }}" 
                                           class="btn btn-outline-primary" 
                                           title="Buka Encounter Pasien">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>

                                        @if(!$log->is_success)
                                            <a href="{{ route('satusehat.failed.show', $log->visit_id) }}" 
                                               class="btn btn-outline-danger" 
                                               title="Perbaiki Data di Failed Center">
                                                <i class="bi bi-tools"></i>
                                            </a>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                                Belum ada log transmisi aliran data yang sesuai filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- PAGINATION --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">
            <div class="small text-muted">
                Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}
            </div>
            <div>
                {{ $paginator->links() }}
            </div>
        </div>
    </div>

</div>

{{-- MODAL INSPEKSI RESPON JSON --}}
<div class="modal fade" id="modalInspectJson" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-code-square text-primary"></i>
                    Inspeksi Respons SATUSEHAT
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Resource Type</small>
                        <strong id="modalResourceType" class="font-monospace text-primary">-</strong>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">Status HTTP</small>
                        <span id="modalStatusBadge" class="badge bg-secondary font-monospace">-</span>
                    </div>
                    <div class="col-sm-4">
                        <small class="text-muted d-block">ID Kunjungan</small>
                        <strong id="modalVisitId" class="font-monospace text-dark">-</strong>
                    </div>
                </div>

                {{-- ERROR CLASSIFICATION ALERT (JIKA ERROR) --}}
                <div id="modalErrorAlert" class="alert alert-danger d-none p-2 mb-3 small">
                    <div class="fw-bold d-flex align-items-center gap-1" id="modalErrorCategory">
                        <i class="bi bi-shield-exclamation"></i> Kategori Masalah
                    </div>
                    <div id="modalErrorDesc" class="mt-1"></div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold text-muted font-monospace">Payload Respons JSON:</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btnCopyJson">
                        <i class="bi bi-clipboard me-1"></i> Salin JSON
                    </button>
                </div>

                <pre class="json-code-box mb-0"><code id="modalJsonContent">// Memuat payload...</code></pre>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div id="modalFooterLinks" class="d-flex gap-2"></div>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<!-- Chart.js UMD -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
$(document).ready(function() {
    // 1. Inisialisasi Grafik Chart.js
    const chartLabels = {!! json_encode($chartData['labels']) !!};
    const chartSuccess = {!! json_encode($chartData['success_data']) !!};
    const chartFailed  = {!! json_encode($chartData['failed_data']) !!};

    const ctx = document.getElementById('resourceFlowChart').getContext('2d');
    const flowChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [
                {
                    label: 'Berhasil (201 Created)',
                    data: chartSuccess,
                    backgroundColor: '#10b981',
                    borderRadius: 4,
                    barPercentage: 0.7,
                },
                {
                    label: 'Gagal / Outcome',
                    data: chartFailed,
                    backgroundColor: '#ef4444',
                    borderRadius: 4,
                    barPercentage: 0.7,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.parsed.y || 0;
                            return `${label}: ${value.toLocaleString()} interaksi`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { size: 11, family: 'ui-sans-serif, system-ui' }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9' },
                    ticks: {
                        callback: function(val) {
                            return val >= 1000 ? (val / 1000) + 'k' : val;
                        }
                    }
                }
            }
        }
    });

    // 2. Pintas filter tanggal cepat
    $('.btn-quick-filter').on('click', function() {
        const start = $(this).data('start');
        const end = $(this).data('end');
        $('#startDate').val(start);
        $('#endDate').val(end);
        $('#filterForm').submit();
    });

    // 3. Inspeksi Respon JSON Modal
    const inspectModal = new bootstrap.Modal(document.getElementById('modalInspectJson'));
    let currentRawJson = '';

    $(document).on('click', '.btn-inspect-json', function() {
        const id = $(this).data('id');
        $('#modalJsonContent').text('// Mengambil detail respons dari server...');
        $('#modalErrorAlert').addClass('d-none');
        inspectModal.show();

        $.getJSON(`{{ url('/satusehat/monitoring-resource/log') }}/${id}`)
            .done(function(res) {
                if (res.success && res.log) {
                    const l = res.log;
                    $('#modalResourceType').text(l.resourcetype || '-');
                    $('#modalVisitId').text(l.visit_id || '-');

                    const isSucc = (l.status || '').includes('201') || l.status === 'SUCCESS';
                    $('#modalStatusBadge')
                        .attr('class', isSucc ? 'badge bg-success font-monospace' : 'badge bg-danger font-monospace')
                        .text(l.status || '-');

                    currentRawJson = JSON.stringify(l.respon_all || {}, null, 2);
                    $('#modalJsonContent').text(currentRawJson);

                    // Analisis error jika ada
                    if (l.error_analysis) {
                        $('#modalErrorAlert').removeClass('d-none');
                        $('#modalErrorCategory').html(`<i class="bi bi-shield-exclamation"></i> ${l.error_analysis.label || 'Kategori Error'}`);
                        $('#modalErrorDesc').text(l.error_analysis.description || '');
                    }

                    // Tautan aksi footer
                    let footerHtml = '';
                    if (l.detail_visit_url) {
                        footerHtml += `<a href="${l.detail_visit_url}" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>Encounter Detail</a>`;
                    }
                    if (l.repair_url && !isSucc) {
                        footerHtml += `<a href="${l.repair_url}" class="btn btn-danger btn-sm"><i class="bi bi-tools me-1"></i>Perbaiki Data</a>`;
                    }
                    $('#modalFooterLinks').html(footerHtml);
                }
            })
            .fail(function() {
                $('#modalJsonContent').text('// Gagal mengambil respons JSON dari server.');
            });
    });

    // Salin JSON ke Clipboard
    $('#btnCopyJson').on('click', function() {
        if (!currentRawJson) return;
        navigator.clipboard.writeText(currentRawJson).then(() => {
            const originalText = $(this).html();
            $(this).html('<i class="bi bi-check2 text-success me-1"></i> Tersalin!');
            setTimeout(() => $(this).html(originalText), 2000);
        });
    });

    // 4. Auto-Refresh Polling Logic
    let refreshInterval = null;
    let refreshSeconds = 0;

    $('.opt-refresh').on('click', function(e) {
        e.preventDefault();
        $('.opt-refresh').removeClass('active');
        $(this).addClass('active');

        refreshSeconds = parseInt($(this).data('seconds'), 10);
        if (refreshInterval) clearInterval(refreshInterval);

        if (refreshSeconds > 0) {
            $('#lblRefreshRate').text(`${refreshSeconds}s`);
            $('#lblLiveStatus').text(`Live (${refreshSeconds}s)`);
            refreshInterval = setInterval(fetchLiveStreamData, refreshSeconds * 1000);
        } else {
            $('#lblRefreshRate').text('Off');
            $('#lblLiveStatus').text('Live Transmisi');
        }
    });

    function fetchLiveStreamData() {
        const start = $('#startDate').val();
        const end = $('#endDate').val();

        $.getJSON(`{{ route('satusehat.resource-monitor.stream') }}`, { start_date: start, end_date: end })
            .done(function(res) {
                if (res.success) {
                    // Update KPI Cards
                    if (res.summary) {
                        $('#kpiTotal').text(Number(res.summary.total).toLocaleString());
                        $('#kpiSuccess').text(Number(res.summary.success).toLocaleString());
                        $('#kpiFailed').text(Number(res.summary.failed).toLocaleString());
                        $('#kpiRate').text(`${res.summary.success_rate}%`);
                    }

                    // Update Chart
                    if (res.chart && flowChart) {
                        flowChart.data.datasets[0].data = res.chart.success_data;
                        flowChart.data.datasets[1].data = res.chart.failed_data;
                        flowChart.update();
                    }
                }
            });
    }
});
</script>
@endsection
