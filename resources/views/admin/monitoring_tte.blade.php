@extends('layouts.admin')

@section('title', 'Monitoring TTE')
@section('page_title', 'Monitoring Layanan Tanda Tangan Elektronik (TTE)')

@section('content')

{{-- HEADER & STATS CARD (MEDBRIDGE DESIGN) --}}
<div class="card-soft mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-pen text-primary me-2"></i>Monitoring Layanan TTE
            </h4>
            <small class="text-muted">
                Pemantauan dokumen rekam medis tersertifikasi TTE BSrE dan riwayat log komunikasi API engine TTE.
            </small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1.5 d-flex align-items-center gap-1">
                <i class="bi bi-check-circle-fill"></i>
                <span class="fw-semibold">{{ number_format($totalSuccess) }} Sukses</span>
            </span>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1.5 d-flex align-items-center gap-1">
                <i class="bi bi-list-check"></i>
                <span class="fw-semibold">{{ number_format($totalLogs) }} Total Log</span>
            </span>
            @if($totalFailed > 0)
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1.5 d-flex align-items-center gap-1">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span class="fw-semibold">{{ number_format($totalFailed) }} Gagal</span>
            </span>
            @endif
        </div>
    </div>
</div>

{{-- TOOLBAR: TABS & SEARCH (MEDBRIDGE DESIGN) --}}
<div class="card-soft mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-pills gap-1" id="tteTab" role="tablist">
            <li class="nav-item">
                <a href="{{ route('admin.tte.index', ['tab' => 'success', 'search' => $search]) }}" 
                   class="nav-link py-1.5 px-3 {{ $tab === 'success' ? 'active' : '' }}" style="font-size: 13px;">
                    <i class="bi bi-file-earmark-check me-1"></i>
                    Dokumen Berhasil ({{ $totalSuccess }})
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.tte.index', ['tab' => 'logs', 'search' => $search]) }}" 
                   class="nav-link py-1.5 px-3 {{ $tab === 'logs' ? 'active' : '' }}" style="font-size: 13px;">
                    <i class="bi bi-clock-history me-1"></i>
                    Log Transaksi ({{ $totalLogs }})
                </a>
            </li>
        </ul>

        <!-- Search Form -->
        <form action="{{ route('admin.tte.index') }}" method="GET" class="d-flex align-items-center gap-2">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="input-group input-group-sm" style="width: 280px;">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" 
                       name="search" 
                       value="{{ $search }}" 
                       class="form-control border-start-0" 
                       placeholder="Cari Visit ID / Doc ID...">
            </div>
            <button type="submit" class="btn btn-primary btn-sm px-3 fw-semibold">
                Cari
            </button>
            @if($search)
                <a href="{{ route('admin.tte.index', ['tab' => $tab]) }}" class="btn btn-outline-secondary btn-sm" title="Reset filter">
                    <i class="bi bi-x-circle"></i>
                </a>
            @endif
        </form>

    </div>
</div>

{{-- TAB CONTENT: DOKUMEN TTE BERHASIL --}}
@if($tab === 'success')
<div class="card-soft p-0 overflow-hidden mb-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Visit ID</th>
                    <th>Doc ID</th>
                    <th>Path / URL File TTE</th>
                    <th>Waktu Tersertifikasi</th>
                    <th class="text-end" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tteSuccessList as $idx => $row)
                    <tr>
                        <td class="text-muted">{{ $tteSuccessList->firstItem() + $idx }}</td>
                        <td class="fw-bold text-dark">{{ $row->visit_id }}</td>
                        <td class="font-monospace text-secondary">{{ $row->doc_id }}</td>
                        <td class="text-truncate font-monospace small text-muted" style="max-width: 320px;" title="{{ $row->path_tte }}">
                            {{ $row->path_tte ?: '-' }}
                        </td>
                        <td class="text-muted small whitespace-nowrap">
                            {{ $row->created_at ? $row->created_at->format('d/m/Y H:i:s') : '-' }}
                        </td>
                        <td class="text-end whitespace-nowrap">
                            @if($row->path_tte)
                                <a href="{{ $row->path_tte }}" target="_blank" class="btn btn-outline-success btn-sm py-1 px-2.5">
                                    <i class="bi bi-file-pdf me-1"></i> Buka PDF
                                    <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 10px;"></i>
                                </a>
                            @else
                                <span class="text-muted small italic">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-folder2-open fs-2 text-muted d-block mb-2"></i>
                            Tidak ada data dokumen TTE yang ditemukan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted">
            Menampilkan {{ $tteSuccessList->firstItem() ?? 0 }} - {{ $tteSuccessList->lastItem() ?? 0 }} dari {{ $tteSuccessList->total() }} entri
        </small>
        <div>
            {{ $tteSuccessList->appends(['tab' => 'success', 'search' => $search])->links() }}
        </div>
    </div>
</div>

{{-- TAB CONTENT: LOG TRANSAKSI TTE --}}
@else
<div class="card-soft p-0 overflow-hidden mb-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
            <thead class="table-light">
                <tr>
                    <th style="width: 170px;">Waktu</th>
                    <th>Visit / FK ID</th>
                    <th>Endpoint</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Pesan Respons</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tteLogsList as $log)
                    @php
                        $code = (int) ($log->response_code ?: $log->status);
                        $badgeClass = ($code >= 200 && $code < 300) 
                            ? 'bg-success-subtle text-success border border-success-subtle' 
                            : (($code >= 400) ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-secondary-subtle text-secondary border');
                    @endphp
                    <tr>
                        <td class="text-muted small whitespace-nowrap">
                            {{ $log->created_at ? $log->created_at->format('d/m/Y H:i:s') : '-' }}
                        </td>
                        <td class="fw-bold font-monospace text-dark">
                            {{ $log->fk_id ?: '-' }}
                        </td>
                        <td class="font-monospace small text-dark">
                            {{ $log->endpoint_url ?: $log->service_name }}
                        </td>
                        <td>
                            <span class="badge bg-light text-secondary border font-monospace text-uppercase">
                                {{ $log->http_method ?: 'POST' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $badgeClass }} px-2 py-1 font-monospace">
                                {{ $code ?: '200' }}
                            </span>
                        </td>
                        <td class="text-truncate text-muted small" style="max-width: 300px;" title="{{ $log->response_message }}">
                            {{ $log->response_message ?: '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-folder2-open fs-2 text-muted d-block mb-2"></i>
                            Tidak ada data log request TTE ditemukan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted">
            Menampilkan {{ $tteLogsList->firstItem() ?? 0 }} - {{ $tteLogsList->lastItem() ?? 0 }} dari {{ $tteLogsList->total() }} entri
        </small>
        <div>
            {{ $tteLogsList->appends(['tab' => 'logs', 'search' => $search])->links() }}
        </div>
    </div>
</div>
@endif

@endsection
