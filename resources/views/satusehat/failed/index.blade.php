@extends('satusehat.layout')

@section('title', 'Perbaikan Data Gagal')

@section('content')
<div class="container-fluid px-0">
    <!-- HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark">
                <i class="bi bi-tools text-danger me-2"></i>Perbaikan Data Gagal
            </h4>
            <div class="text-muted small">
                Daftar data yang gagal dikirim ke SATUSEHAT dan perlu diperbaiki.
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('satusehat.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Dashboard
            </a>
            <a href="{{ route('satusehat.failed.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i>Muat Ulang
            </a>
        </div>
    </div>

    <!-- 5 SUMMARY METRIC CARDS (MEDBRIDGE CONCEPT) -->
    <div class="row g-3 mb-4">
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-danger border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Gagal</div>
                        <div class="fs-4 fw-bold text-danger mt-1">{{ number_format($totalFailed) }}</div>
                    </div>
                    <div class="bg-danger bg-opacity-10 p-2 rounded-circle text-danger">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                </div>
                <div class="small text-muted mt-2">Semua data gagal</div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-warning border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Perlu Perbaikan</div>
                        <div class="fs-4 fw-bold text-warning mt-1">{{ number_format($needRepair) }}</div>
                    </div>
                    <div class="bg-warning bg-opacity-10 p-2 rounded-circle text-warning">
                        <i class="bi bi-tools fs-4"></i>
                    </div>
                </div>
                <div class="small text-muted mt-2">Perlu perbaikan data</div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-success border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Bisa Dikirim Ulang</div>
                        <div class="fs-4 fw-bold text-success mt-1">{{ number_format($retryable) }}</div>
                    </div>
                    <div class="bg-success bg-opacity-10 p-2 rounded-circle text-success">
                        <i class="bi bi-arrow-repeat fs-4"></i>
                    </div>
                </div>
                <div class="small text-muted mt-2">Gangguan server / jaringan</div>
            </div>
        </div>

        <div class="col-xl col-md-6 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-secondary border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Menunggu Kunjungan</div>
                        <div class="fs-4 fw-bold text-secondary mt-1">{{ number_format($blocked) }}</div>
                    </div>
                    <div class="bg-secondary bg-opacity-10 p-2 rounded-circle text-secondary">
                        <i class="bi bi-lock fs-4"></i>
                    </div>
                </div>
                <div class="small text-muted mt-2">Data kunjungan belum terkirim</div>
            </div>
        </div>

        <div class="col-xl col-md-6 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-dark border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Batas Percobaan</div>
                        <div class="fs-4 fw-bold text-dark mt-1">{{ number_format($maxRetry) }}</div>
                    </div>
                    <div class="bg-dark bg-opacity-10 p-2 rounded-circle text-dark">
                        <i class="bi bi-shield-exclamation fs-4"></i>
                    </div>
                </div>
                <div class="small text-muted mt-2">Sudah dicoba 3x atau lebih</div>
            </div>
        </div>
    </div>

    <!-- FILTER & SEARCH PANEL -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('satusehat.failed.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Cari Data</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="keyword" class="form-control" placeholder="ID Kunjungan, No. RM, error..." value="{{ request('keyword') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Jenis Data</label>
                    <select name="resource_type" class="form-select form-select-sm">
                        <option value="all">-- Semua Jenis Data --</option>
                        @foreach($availableResources as $res)
                            <option value="{{ $res }}" {{ request('resource_type') == $res ? 'selected' : '' }}>{{ $res }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Dari Tanggal</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-funnel me-1"></i>Cari
                    </button>
                    <a href="{{ route('satusehat.failed.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset filter">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- TABEL LOG KEGAGALAN DENGAN KLASIFIKASI & AKSI -->
    <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="bi bi-list-task me-2 text-primary"></i>Daftar Data Gagal
            </h6>
            <span class="badge bg-light text-muted border">Menampilkan {{ $logs->count() }} dari {{ $logs->total() }} entri</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                <thead class="table-light">
                    <tr>
                        <th style="width: 130px;">Waktu</th>
                        <th>Pasien</th>
                        <th>Jenis Data</th>
                        <th>Status</th>
                        <th>Kode</th>
                        <th>Keterangan</th>
                        <th class="text-center">Percobaan</th>
                        <th class="text-center" style="width: 170px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $cls = $log->classification ?? [
                                'status' => 'NEED_REPAIR',
                                'label'  => 'Perlu Perbaikan',
                                'badge'  => 'bg-warning text-dark'
                            ];
                            $visitId = $log->visit_id ?? '-';
                        @endphp
                        <tr>
                            <td class="text-muted small">
                                <i class="bi bi-clock me-1"></i>{{ isset($log->created_at) ? \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td>
                                <div class="fw-bold text-dark">Visit #{{ $visitId }}</div>
                                <div class="small text-muted">
                                    {{ $log->px_name ?? 'Pasien' }} 
                                    @if(!empty($log->px_norm)) (RM: {{ $log->px_norm }}) @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info bg-opacity-10 text-primary border border-info border-opacity-25 px-2 py-1">
                                    {{ $log->resource_type ?? 'Encounter' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $cls['badge'] }} px-2 py-1">
                                    {{ $cls['label'] }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $httpCode = (int) ($log->http_status ?? 0);
                                    $badgeColor = ($httpCode >= 500) ? 'bg-danger' : (($httpCode >= 400) ? 'bg-warning text-dark' : 'bg-secondary');
                                @endphp
                                <span class="badge {{ $badgeColor }} font-monospace">
                                    {{ $httpCode > 0 ? $httpCode : 'ERR' }}
                                </span>
                            </td>
                            <td>
                                <div class="text-truncate text-secondary" style="max-width: 280px;" title="{{ $log->error_message ?? '-' }}">
                                    {{ $log->error_message ?? '-' }}
                                </div>
                            </td>
                            <td class="text-center">
                                @php
                                    $retries = (int) ($log->retry_count ?? 0);
                                @endphp
                                <span class="badge {{ $retries >= 3 ? 'bg-danger' : ($retries > 0 ? 'bg-warning text-dark' : 'bg-light text-secondary border') }}">
                                    {{ $retries }}x
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    {{-- Tombol Detail / Investigasi --}}
                                    <a href="{{ route('satusehat.failed.show', $log->id ?? $log->log_id) }}" class="btn btn-outline-secondary" title="Lihat detail">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    {{-- Tombol Form Perbaikan MedBridge --}}
                                    <a href="{{ route('satusehat.failed.repair', $log->id ?? $log->log_id) }}" class="btn btn-warning text-dark" title="Perbaiki data">
                                        <i class="bi bi-tools"></i>
                                    </a>

                                    {{-- Tombol Retry Cerdas --}}
                                    @if($cls['status'] !== 'BLOCKED')
                                        <form method="POST" action="{{ route('satusehat.failed.retry', $log->id ?? $log->log_id) }}" class="d-inline" onsubmit="return confirm('Kirim ulang data ini sekarang?')">
                                            @csrf
                                            <button type="submit" class="btn btn-success" title="Kirim ulang">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                    @else
                                        <button class="btn btn-outline-secondary disabled" title="Data kunjungan belum terkirim" disabled>
                                            <i class="bi bi-lock"></i>
                                        </button>
                                    @endif

                                    {{-- Riwayat Audit --}}
                                    <a href="{{ route('satusehat.failed.history', $log->id ?? $log->log_id) }}" class="btn btn-outline-info" title="Riwayat perbaikan">
                                        <i class="bi bi-clock-history"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle fs-1 text-success mb-2 d-block"></i>
                                <div class="fw-semibold">Tidak ada data gagal.</div>
                                <small>Semua data berhasil terkirim atau filter tidak cocok.</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->total() > 0)
            <div class="card-footer bg-white border-top py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="small text-muted">
                    Menampilkan <span class="fw-semibold text-dark">{{ $logs->firstItem() ?? 0 }}</span> - <span class="fw-semibold text-dark">{{ $logs->lastItem() ?? 0 }}</span> dari <span class="fw-semibold text-dark">{{ $logs->total() }}</span> data
                </div>
                @if($logs->hasPages())
                    <div>
                        {{ $logs->withQueryString()->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
