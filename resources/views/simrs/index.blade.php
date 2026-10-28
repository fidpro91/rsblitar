@extends('satusehat.layout')

@section('title', 'Tarik Data SIMRS')

@section('content')
<div class="container-fluid px-0">
    <!-- HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h4 class="fw-bold mb-1 text-dark">
                <i class="bi bi-cloud-arrow-down text-primary me-2"></i>Tarik Data SIMRS
            </h4>
            <div class="text-muted small">
                Tarik data kunjungan pasien dari SIMRS untuk disiapkan ke SATUSEHAT.
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-info btn-sm" id="btnTestConn">
                <i class="bi bi-broadcast me-1"></i>Uji Koneksi SIMRS
            </button>
            <a href="{{ route('satusehat.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-grid me-1"></i>Dashboard
            </a>
            <a href="{{ route('satusehat.simrs.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i>Muat Ulang
            </a>
        </div>
    </div>

    <!-- 5 SUMMARY METRIC CARDS (MEDBRIDGE DESIGN) -->
    <div class="row g-3 mb-4">
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-primary border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Tarik Data</div>
                        <div class="fs-4 fw-bold text-primary mt-1">{{ number_format($stats['total_runs']) }}</div>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-2 rounded-circle text-primary">
                        <i class="bi bi-journal-text fs-4"></i>
                    </div>
                </div>
                <div class="small text-muted mt-2">Semua proses tarik data</div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-success border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Berhasil</div>
                        <div class="fs-4 fw-bold text-success mt-1">{{ number_format($stats['success_runs']) }}</div>
                    </div>
                    <div class="bg-success bg-opacity-10 p-2 rounded-circle text-success">
                        <i class="bi bi-check-circle fs-4"></i>
                    </div>
                </div>
                <div class="small text-muted mt-2">Data siap dikirim</div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-danger border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Gagal</div>
                        <div class="fs-4 fw-bold text-danger mt-1">{{ number_format($stats['failed_runs']) }}</div>
                    </div>
                    <div class="bg-danger bg-opacity-10 p-2 rounded-circle text-danger">
                        <i class="bi bi-x-circle fs-4"></i>
                    </div>
                </div>
                <div class="small text-muted mt-2">Koneksi terputus / error</div>
            </div>
        </div>

        <div class="col-xl col-md-6 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-info border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Kunjungan</div>
                        <div class="fs-4 fw-bold text-info mt-1">{{ number_format($stats['total_encounters']) }}</div>
                    </div>
                    <div class="bg-info bg-opacity-10 p-2 rounded-circle text-info">
                        <i class="bi bi-people fs-4"></i>
                    </div>
                </div>
                <div class="small text-muted mt-2">Data kunjungan ditarik</div>
            </div>
        </div>

        <div class="col-xl col-md-6 col-sm-6">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-warning border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Rata-rata Waktu</div>
                        <div class="fs-4 fw-bold text-warning mt-1">{{ $stats['avg_duration_sec'] }}s</div>
                    </div>
                    <div class="bg-warning bg-opacity-10 p-2 rounded-circle text-warning">
                        <i class="bi bi-stopwatch fs-4"></i>
                    </div>
                </div>
                <div class="small text-muted mt-2">Waktu per proses</div>
            </div>
        </div>
    </div>

    <!-- CARD FORM PENARIKAN DATA BARU -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="bi bi-cloud-download text-primary me-2"></i>Tarik Data Baru
            </h6>
            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                <i class="bi bi-clock me-1"></i>Otomatis
            </span>
        </div>
        <div class="card-body p-3">
            <form id="formTarikData" action="{{ route('satusehat.simrs.pull') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Dari Tanggal</label>
                        <input type="date" name="start_date" id="inputStartDate" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Sampai Tanggal</label>
                        <input type="date" name="end_date" id="inputEndDate" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Jumlah per Batch</label>
                        <select name="chunk_size" id="inputChunkSize" class="form-select form-select-sm">
                            <option value="25">25 data per batch (Hemat memori)</option>
                            <option value="50" selected>50 data per batch (Rekomendasi)</option>
                            <option value="100">100 data per batch (Cepat)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Batas Maksimal (Opsional)</label>
                        <input type="number" name="limit" id="inputLimit" class="form-control form-control-sm" placeholder="Kosongkan untuk semua data">
                    </div>
                </div>

                <!-- QUICK SHORTCUT BUTTONS -->
                <div class="d-flex flex-wrap align-items-center gap-1 mt-2">
                    <small class="text-muted fw-semibold me-2">Pilihan Cepat:</small>
                    <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-date" data-days="0">Hari Ini</button>
                    <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-date" data-days="1">Kemarin</button>
                    <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-date" data-days="3">3 Hari Lalu</button>
                    <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-date" data-days="7">7 Hari Lalu</button>
                    <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-date" data-days="10">10 Hari Lalu</button>
                </div>

                <!-- ACTIONS -->
                <div class="d-flex justify-content-end align-items-center mt-3 pt-3 border-top">
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold" id="btnSubmitTarik">
                        <i class="bi bi-cloud-arrow-down me-1"></i>Mulai Tarik Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FILTER & SEARCH PANEL LOG -->
    <div class="card border-0 shadow-sm rounded-3 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('satusehat.simrs.index') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Cari Riwayat</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="keyword" class="form-control" placeholder="ID, pesan, atau tanggal..." value="{{ $filters['keyword'] ?? '' }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="ALL">-- Semua Status --</option>
                        <option value="SUCCESS" {{ ($filters['status'] ?? '') === 'SUCCESS' ? 'selected' : '' }}>Berhasil</option>
                        <option value="PARTIAL" {{ ($filters['status'] ?? '') === 'PARTIAL' ? 'selected' : '' }}>Sebagian Berhasil</option>
                        <option value="FAILED" {{ ($filters['status'] ?? '') === 'FAILED' ? 'selected' : '' }}>Gagal</option>
                        <option value="RUNNING" {{ ($filters['status'] ?? '') === 'RUNNING' ? 'selected' : '' }}>Sedang Berjalan</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Dari Tanggal</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $filters['start_date'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $filters['end_date'] ?? '' }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                    <a href="{{ route('satusehat.simrs.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset Filter">
                        <i class="bi bi-x-circle"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- TABEL MONITORING LOG TARIK DATA -->
    <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">
                <i class="bi bi-clock-history text-primary me-2"></i>Riwayat Tarik Data
            </h6>
            <span class="badge bg-light text-muted border">Menampilkan {{ $logs->count() }} dari {{ $logs->total() }} riwayat</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                <thead class="table-light">
                    <tr>
                        <th style="width: 140px;">Waktu & ID</th>
                        <th style="width: 90px;">Dijalankan Oleh</th>
                        <th>Rentang Tanggal</th>
                        <th class="text-center" style="width: 100px;">Status</th>
                        <th>Rincian Data</th>
                        <th class="text-center" style="width: 110px;">Waktu & RAM</th>
                        <th>Keterangan</th>
                        <th class="text-center" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        @php
                            $st = $log['status'] ?? 'UNKNOWN';
                            $badgeMap = [
                                'SUCCESS' => 'badge bg-success',
                                'PARTIAL' => 'badge bg-warning text-dark',
                                'FAILED'  => 'badge bg-danger',
                                'RUNNING' => 'badge bg-primary',
                            ];
                            $badgeCls = $badgeMap[$st] ?? 'badge bg-secondary';
                            $stats = $log['total_stats'] ?? [];
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold text-dark font-monospace" style="font-size: 0.8rem;">
                                    {{ $log['id'] ?? '-' }}
                                </div>
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>{{ isset($log['started_at']) ? \Carbon\Carbon::parse($log['started_at'])->format('d/m/Y H:i:s') : '-' }}
                                </small>
                            </td>
                            <td>
                                <span class="badge {{ ($log['trigger'] ?? '') === 'cli' ? 'bg-dark' : 'bg-light text-dark border' }}">
                                    <i class="bi {{ ($log['trigger'] ?? '') === 'cli' ? 'bi-terminal' : 'bi-person' }} me-1"></i>
                                    {{ ucfirst($log['trigger'] ?? 'manual') }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    {{ $log['start_date'] ?? ($log['tanggal'] ?? '-') }} 
                                    @if(!empty($log['end_date']) && $log['end_date'] !== ($log['start_date'] ?? ''))
                                        s/d {{ $log['end_date'] }}
                                    @endif
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="{{ $badgeCls }} px-2 py-1">
                                    {{ $st }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1" style="font-size: 0.75rem;">
                                    <span class="badge bg-primary bg-opacity-10 text-primary border" title="Encounter">Enc: {{ $stats['encounter'] ?? 0 }}</span>
                                    <span class="badge bg-success bg-opacity-10 text-success border" title="Diagnosa (ICD-10)">Dx: {{ $stats['diagnosis'] ?? 0 }}</span>
                                    <span class="badge bg-info bg-opacity-10 text-dark border" title="Tindakan (ICD-9)">ICD9: {{ $stats['icd9'] ?? 0 }}</span>
                                    <span class="badge bg-warning bg-opacity-10 text-dark border" title="Vital Signs">Obs: {{ $stats['observation'] ?? 0 }}</span>
                                    <span class="badge bg-purple bg-opacity-10 text-dark border" title="Farmasi / Resep">Far: {{ $stats['farmasi'] ?? 0 }}</span>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border" title="Lab">Lab: {{ $stats['lab'] ?? 0 }}</span>
                                </div>
                            </td>
                            <td class="text-center small">
                                <div class="fw-semibold text-dark">
                                    <i class="bi bi-stopwatch text-muted me-1"></i>{{ isset($log['duration_seconds']) ? $log['duration_seconds'] . 's' : '-' }}
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    RAM: {{ $log['memory_peak_mb'] ?? '-' }} MB
                                </div>
                            </td>
                            <td>
                                @if(!empty($log['error_message']))
                                    <div class="text-truncate text-danger small" style="max-width: 200px;" title="{{ $log['error_message'] }}">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>{{ $log['error_message'] }}
                                    </div>
                                @elseif(!empty($log['warnings']))
                                    <div class="text-truncate text-warning small" style="max-width: 200px;" title="{{ implode(', ', $log['warnings']) }}">
                                        <i class="bi bi-exclamation-circle me-1"></i>{{ $log['warnings'][0] ?? 'Peringatan' }}
                                    </div>
                                @else
                                    <div class="text-success small">
                                        <i class="bi bi-check2-all me-1"></i>Selesai
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary btn-detail-log" data-id="{{ $log['id'] }}" title="Lihat detail">
                                        <i class="bi bi-eye"></i> Detail
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-rerun-log" 
                                        data-start="{{ $log['start_date'] ?? $log['tanggal'] }}" 
                                        data-end="{{ $log['end_date'] ?? $log['tanggal'] }}" 
                                        data-chunk="{{ $log['chunk_size'] ?? 50 }}" 
                                        title="Gunakan tanggal ini lagi">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-journal-x fs-1 text-secondary mb-2 d-block"></i>
                                <div class="fw-semibold">Belum ada riwayat tarik data.</div>
                                <small>Pilih tanggal di atas lalu klik "Mulai Tarik Data".</small>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->total() > 0)
            <div class="card-footer bg-white border-top py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="small text-muted">
                    Menampilkan <span class="fw-semibold text-dark">{{ $logs->firstItem() ?? 0 }}</span> - <span class="fw-semibold text-dark">{{ $logs->lastItem() ?? 0 }}</span> dari <span class="fw-semibold text-dark">{{ $logs->total() }}</span> riwayat log
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

<!-- MODAL DETAIL INSPEKSI LOG -->
<div class="modal fade" id="modalDetailSync" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="bi bi-journal-medical text-primary me-2"></i>Detail Tarik Data
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="modalDetailSyncBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="small text-muted mt-2">Memuat data log...</div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Quick Date buttons
    $('.btn-quick-date').on('click', function() {
        const days = parseInt($(this).data('days'));
        const targetDate = new Date();
        targetDate.setDate(targetDate.getDate() - days);
        const yyyy = targetDate.getFullYear();
        const mm = String(targetDate.getMonth() + 1).padStart(2, '0');
        const dd = String(targetDate.getDate()).padStart(2, '0');
        const dateStr = `${yyyy}-${mm}-${dd}`;

        $('#inputStartDate').val(dateStr);
        $('#inputEndDate').val(dateStr);

        $('.btn-quick-date').removeClass('btn-primary text-white').addClass('btn-light text-secondary');
        $(this).removeClass('btn-light text-secondary').addClass('btn-primary text-white');
    });

    // Uji Koneksi SIMRS
    $('#btnTestConn').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Menguji...');

        $.ajax({
            url: "{{ route('satusehat.simrs.test-connection') }}",
            type: "POST",
            data: { _token: "{{ csrf_token() }}" },
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="bi bi-broadcast me-1"></i>Uji Koneksi SIMRS');
                Swal.fire({
                    icon: 'success',
                    title: 'Koneksi Berhasil!',
                    html: `
                        <div class="text-start">
                            <p class="mb-1">${res.message}</p>
                            <span class="badge bg-success">Latency: ${res.ping_ms} ms</span>
                        </div>
                    `,
                });
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="bi bi-broadcast me-1"></i>Uji Koneksi SIMRS');
                const msg = xhr.responseJSON?.message || 'Gagal terhubung ke host database SIMRS.';
                Swal.fire({
                    icon: 'error',
                    title: 'Koneksi Gagal',
                    html: `
                        <div class="text-start text-danger small">
                            <p class="mb-2"><strong>Error:</strong> ${msg}</p>
                            <p class="text-muted mb-0">Pastikan server SIMRS aktif dan jaringan terhubung.</p>
                        </div>
                    `,
                });
            }
        });
    });

    // Submit Form Tarik Data
    $('#formTarikData').on('submit', function(e) {
        e.preventDefault();

        const startDate = $('#inputStartDate').val();
        const endDate = $('#inputEndDate').val();
        const chunkSize = $('#inputChunkSize').val();
        const limit = $('#inputLimit').val();

        Swal.fire({
            title: 'Menarik Data...',
            html: `
                <div class="text-center">
                    <div class="spinner-border text-primary mb-3"></div>
                    <div class="fw-semibold">Mengambil data: ${startDate} s/d ${endDate}</div>
                    <small class="text-muted">Data diproses bertahap (${chunkSize} data per batch).</small>
                </div>
            `,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
        });

        $.ajax({
            url: "{{ route('satusehat.simrs.pull') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                start_date: startDate,
                end_date: endDate,
                tanggal: startDate,
                chunk_size: chunkSize,
                limit: limit,
            },
            success: function(response) {
                const total = response.total || {};
                Swal.fire({
                    icon: 'success',
                    title: response.message || 'Data berhasil ditarik!',
                    html: `
                        <div class="text-start small">
                            <div class="alert alert-light border mb-2">
                                <strong>Log ID:</strong> <code>${response.sync_id}</code><br>
                                <strong>Durasi:</strong> ${response.duration} detik<br>
                                <strong>Batch Diproses:</strong> ${response.chunks_processed || 1} batch
                            </div>
                            <div class="fw-semibold mb-1">Rincian Data:</div>
                            <ul class="list-unstyled mb-0 row g-1">
                                <li class="col-6"><span class="badge bg-primary">Encounter: ${total.encounter || 0}</span></li>
                                <li class="col-6"><span class="badge bg-success">Diagnosis: ${total.diagnosis || 0}</span></li>
                                <li class="col-6"><span class="badge bg-info text-dark">ICD-9 Tindakan: ${total.icd9 || 0}</span></li>
                                <li class="col-6"><span class="badge bg-warning text-dark">Vital Signs: ${total.observation || 0}</span></li>
                                <li class="col-6"><span class="badge bg-secondary">Farmasi: ${total.farmasi || 0}</span></li>
                                <li class="col-6"><span class="badge bg-danger">Lab / Specimen: ${total.lab || 0}</span></li>
                            </ul>
                        </div>
                    `,
                    confirmButtonText: 'OK',
                }).then(() => {
                    window.location.reload();
                });
            },
            error: function(xhr) {
                const err = xhr.responseJSON || {};
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Menarik Data',
                    html: `
                        <div class="text-start text-danger small">
                            <p class="mb-2"><strong>Pesan:</strong> ${err.message || 'Terjadi kesalahan sistem'}</p>
                            ${err.error ? `<div class="p-2 bg-light border text-dark font-monospace mb-2" style="font-size:0.75rem;">${err.error}</div>` : ''}
                            <p class="text-muted mb-0">Log kegagalan telah tercatat di monitoring log.</p>
                        </div>
                    `,
                    confirmButtonText: 'Tutup',
                }).then(() => {
                    window.location.reload();
                });
            }
        });
    });

    // Buka Modal Detail Log
    $('.btn-detail-log').on('click', function() {
        const id = $(this).data('id');
        const modal = new bootstrap.Modal(document.getElementById('modalDetailSync'));
        modal.show();

        $('#modalDetailSyncBody').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="small text-muted mt-2">Memuat data log ${id}...</div>
            </div>
        `);

        $.ajax({
            url: "{{ url('/simrs/logs') }}/" + id,
            type: "GET",
            success: function(res) {
                if (!res.success) {
                    $('#modalDetailSyncBody').html('<div class="alert alert-danger">Log tidak ditemukan.</div>');
                    return;
                }
                const d = res.data;
                const stats = d.total_stats || {};

                let statusBadge = '<span class="badge bg-success">SUCCESS</span>';
                if (d.status === 'FAILED') statusBadge = '<span class="badge bg-danger">FAILED</span>';
                if (d.status === 'PARTIAL') statusBadge = '<span class="badge bg-warning text-dark">PARTIAL</span>';
                if (d.status === 'RUNNING') statusBadge = '<span class="badge bg-primary">RUNNING</span>';

                let html = `
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold mb-0">${d.id}</h6>
                            <small class="text-muted">Mulai: ${d.started_at || '-'} &bull; Selesai: ${d.finished_at || '-'}</small>
                        </div>
                        <div>${statusBadge}</div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-sm-4">
                            <div class="border rounded p-2 text-center bg-light">
                                <div class="text-muted small">Lama Proses</div>
                                <div class="fw-bold">${d.duration_seconds !== null ? d.duration_seconds + ' detik' : '-'}</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded p-2 text-center bg-light">
                                <div class="text-muted small">Penggunaan Memori</div>
                                <div class="fw-bold">${d.memory_peak_mb || '-'} MB</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="border rounded p-2 text-center bg-light">
                                <div class="text-muted small">Batch / Chunk</div>
                                <div class="fw-bold">${d.chunks_processed || 0} batch (${d.chunk_size || 50}/batch)</div>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark mt-3 mb-2">Rincian Data:</h6>
                    <table class="table table-sm table-bordered mb-3">
                        <thead class="table-light">
                            <tr>
                                <th>Modul / Resource</th>
                                <th class="text-center" style="width: 140px;">Jumlah Record</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><i class="bi bi-person-badge text-primary me-2"></i>Kunjungan Pasien</td><td class="text-center fw-bold">${stats.encounter || 0}</td></tr>
                            <tr><td><i class="bi bi-clipboard2-pulse text-success me-2"></i>Diagnosa (ICD-10)</td><td class="text-center fw-bold">${stats.diagnosis || 0}</td></tr>
                            <tr><td><i class="bi bi-activity text-info me-2"></i>Tindakan (ICD-9)</td><td class="text-center fw-bold">${stats.icd9 || 0}</td></tr>
                            <tr><td><i class="bi bi-heart-pulse text-warning me-2"></i>Tanda Vital</td><td class="text-center fw-bold">${stats.observation || 0}</td></tr>
                            <tr><td><i class="bi bi-capsule text-secondary me-2"></i>Obat & Resep</td><td class="text-center fw-bold">${stats.farmasi || 0}</td></tr>
                            <tr><td><i class="bi bi-eyedropper text-danger me-2"></i>Laboratorium</td><td class="text-center fw-bold">${stats.lab || 0}</td></tr>
                            <tr><td><i class="bi bi-shield-exclamation text-dark me-2"></i>Alergi</td><td class="text-center fw-bold">${stats.allergi || 0}</td></tr>
                            <tr><td><i class="bi bi-signpost-split text-primary me-2"></i>Rencana Perawatan</td><td class="text-center fw-bold">${stats.careplan || 0}</td></tr>
                            <tr><td><i class="bi bi-prescription2 text-info me-2"></i>Radiologi</td><td class="text-center fw-bold">${stats.radiologi || 0}</td></tr>
                            <tr><td><i class="bi bi-question-circle text-muted me-2"></i>Telaah Resep</td><td class="text-center fw-bold">${stats.quisioner || 0}</td></tr>
                        </tbody>
                    </table>
                `;

                if (d.error_message) {
                    html += `
                        <div class="alert alert-danger mb-0">
                            <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Pesan Error:</strong>
                            <div class="font-monospace small mt-1">${d.error_message}</div>
                        </div>
                    `;
                }

                $('#modalDetailSyncBody').html(html);
            },
            error: function() {
                $('#modalDetailSyncBody').html('<div class="alert alert-danger">Gagal menghubungi server untuk memuat detail log.</div>');
            }
        });
    });

    // Tombol Re-run Log (isi form)
    $('.btn-rerun-log').on('click', function() {
        const start = $(this).data('start');
        const end = $(this).data('end');
        const chunk = $(this).data('chunk');

        if (start) $('#inputStartDate').val(start);
        if (end) $('#inputEndDate').val(end);
        if (chunk) $('#inputChunkSize').val(chunk);

        $('html, body').animate({
            scrollTop: $('#formTarikData').offset().top - 80
        }, 300);

        Swal.fire({
            icon: 'info',
            title: 'Tanggal Dipilih',
            text: `Tanggal ${start} s/d ${end} sudah diatur. Silakan klik "Mulai Tarik Data".`,
            timer: 1800,
            showConfirmButton: false
        });
    });
});
</script>
@endpush
