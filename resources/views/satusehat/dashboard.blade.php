@extends('satusehat.layout')

@section('title', 'Kirim Data')

@section('content')

{{-- HEADER & FILTER CARD (MEDBRIDGE DESIGN) --}}
<div class="card-soft mb-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1 fw-bold text-dark">
                <i class="bi bi-send text-primary me-2"></i>Kirim Data ke SATUSEHAT
            </h4>
            <small class="text-muted">
                Pilih tanggal dan status, lalu kirim data kunjungan.
            </small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('satusehat.failed.index') }}" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-tools me-1"></i> Perbaikan Data Gagal
            </a>
            <a href="{{ route('satusehat.dashboard') }}" class="btn btn-outline-secondary btn-sm" title="Muat ulang">
                <i class="bi bi-arrow-clockwise me-1"></i> Muat Ulang
            </a>
        </div>
    </div>

    {{-- FILTER FORM --}}
    <form method="GET" action="{{ route('satusehat.dashboard') }}" id="filterForm">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-secondary">Tanggal Kunjungan</label>
                <input
                    type="date"
                    id="tanggal"
                    name="start_date"
                    class="form-control"
                    value="{{ $filters['start_date'] ?? request('start_date', date('Y-m-d')) }}">
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-secondary">Status Pengiriman</label>
                <select id="status" name="status" class="form-select">
                    <option value="ALL" {{ request('status', 'ALL') == 'ALL' ? 'selected' : '' }}>Semua Status</option>
                    <option value="SUCCESS" {{ request('status') == 'SUCCESS' ? 'selected' : '' }}>Berhasil</option>
                    <option value="FAILED" {{ request('status') == 'FAILED' ? 'selected' : '' }}>Gagal</option>
                    <option value="DRAFT" {{ request('status') == 'DRAFT' ? 'selected' : '' }}>Belum Dikirim</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label small fw-semibold text-secondary">Cari Pasien</label>
                <input
                    type="text"
                    id="keyword"
                    name="search"
                    class="form-control"
                    placeholder="Nama, No. RM, atau ID kunjungan"
                    value="{{ request('search') }}">
            </div>

            <div class="col-md-2 d-flex align-items-end gap-1">
                <button
                    type="submit"
                    id="btnFilter"
                    class="btn btn-primary flex-fill fw-semibold">
                    <i class="bi bi-search me-1"></i> Cari
                </button>
                <a href="{{ route('satusehat.dashboard') }}" class="btn btn-outline-secondary" title="Reset filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </a>
            </div>
        </div>

        {{-- QUICK DATE SHORTCUTS --}}
        <div class="d-flex flex-wrap align-items-center gap-1 mt-2">
            <small class="text-muted fw-semibold me-2">Tanggal:</small>
            <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-date-ss" data-date="">Semua</button>
            <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-date-ss" data-date="{{ date('Y-m-d') }}">Hari Ini</button>
            <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-date-ss" data-date="{{ date('Y-m-d', strtotime('-1 day')) }}">Kemarin</button>
            <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-date-ss" data-date="{{ date('Y-m-d', strtotime('-2 day')) }}">2 Hari Lalu</button>
            <button type="button" class="btn btn-light btn-sm py-0 px-2 border text-secondary btn-quick-date-ss" data-date="{{ date('Y-m-d', strtotime('-7 day')) }}">7 Hari Lalu</button>
        </div>
    </form>
</div>

{{-- SEND ALL ACTION (MEDBRIDGE DESIGN) --}}
<div class="card-soft mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="form-check">
            <input
                class="form-check-input"
                type="checkbox"
                id="checkAll">
            <label class="form-check-label small fw-semibold text-dark" for="checkAll">
                Kirim semua data sesuai filter
            </label>
        </div>

        <button
            class="btn btn-success fw-semibold"
            id="btnSendAll"
            disabled
            type="button"
            onclick="triggerSendAll()">
            <i class="bi bi-send me-1"></i> Kirim Semua
        </button>
    </div>
</div>

{{-- SUMMARY METRICS ROW (MEDBRIDGE METRIC STYLE) --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card-soft p-3 text-center border-start border-3 border-primary">
            <div class="small text-muted text-uppercase fw-semibold" style="font-size: 11px;">Total Kunjungan</div>
            <h3 class="mb-0 fw-bold text-dark mt-1">{{ number_format($metrics['total'] ?? 0) }}</h3>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card-soft p-3 text-center border-start border-3 border-success">
            <div class="small text-success text-uppercase fw-semibold" style="font-size: 11px;">Berhasil</div>
            <h3 class="mb-0 fw-bold text-success mt-1">{{ number_format($metrics['success'] ?? 0) }}</h3>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card-soft p-3 text-center border-start border-3 border-danger">
            <div class="small text-danger text-uppercase fw-semibold" style="font-size: 11px;">Gagal</div>
            <h3 class="mb-0 fw-bold text-danger mt-1">{{ number_format($metrics['failed'] ?? 0) }}</h3>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card-soft p-3 text-center border-start border-3 border-secondary">
            <div class="small text-secondary text-uppercase fw-semibold" style="font-size: 11px;">Belum Dikirim</div>
            <h3 class="mb-0 fw-bold text-secondary mt-1">{{ number_format($metrics['pending'] ?? 0) }}</h3>
        </div>
    </div>
</div>

{{-- ENCOUNTER CARD LIST (MEDBRIDGE SATUSEHAT LIST DESIGN) --}}
<div id="listEncounter">
    @forelse($encounters as $item)
        @php
            $itemObj = (object) $item;
            $visitId = (string) ($itemObj->visit_id ?? '');
            $pxName = strtoupper($itemObj->px_name ?? 'PASIEN ' . $visitId);
            $pxNorm = $itemObj->px_norm ?? '-';
            $unitName = $itemObj->unit_name ?? '-';
            $doctorName = $itemObj->dpjp_name ?? '-';
            $tglKunjung = $itemObj->tgl_kunjung ?? null;
            $status = $itemObj->computed_status ?? 'DRAFT';
            $hasResourceId = !empty($itemObj->encounter_satusehat);
        @endphp

        <div class="card mb-2 shadow-sm border-0 border-start border-4 {{ $status === 'SUCCESS' ? 'border-success' : ($status === 'FAILED' ? 'border-danger' : 'border-secondary') }}">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h6 class="mb-1 fw-bold text-dark">{{ $pxName }}</h6>
                        <div class="small text-muted font-monospace">
                            <span>No RM: <strong>{{ $pxNorm }}</strong></span>
                            <span class="mx-2">&bull;</span>
                            <span>ID Kunjungan: <strong>{{ $visitId }}</strong></span>
                            @if(!empty($tglKunjung))
                                <span class="mx-2">&bull;</span>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-calendar3 me-1"></i>{{ \Carbon\Carbon::parse($tglKunjung)->format('d/m/Y H:i') }}
                                </span>
                            @endif
                        </div>
                        <div class="small text-secondary mt-1">
                            <span><i class="bi bi-hospital me-1"></i>{{ $unitName }}</span>
                            <span class="mx-2">&bull;</span>
                            <span><i class="bi bi-person me-1"></i>{{ $doctorName }}</span>
                        </div>
                    </div>

                    <div class="text-end">
                        @if($status === 'SUCCESS')
                            <span class="badge bg-success px-3 py-1 font-monospace">Berhasil</span>
                        @elseif($status === 'PARTIAL_SUCCESS')
                            <span class="badge bg-warning text-dark px-3 py-1 font-monospace">Sebagian Berhasil</span>
                        @elseif($status === 'FAILED')
                            <span class="badge bg-danger px-3 py-1 font-monospace">Gagal</span>
                        @else
                            <span class="badge bg-secondary px-3 py-1 font-monospace">Belum Dikirim</span>
                        @endif

                        @if(!empty($itemObj->encounter_satusehat))
                            <small class="d-block text-muted font-monospace mt-1" style="font-size: 11px;">
                                ID: {{ Str::limit($itemObj->encounter_satusehat, 18) }}
                            </small>
                        @endif
                    </div>
                </div>

                <div class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
                    <div class="d-flex gap-2 flex-wrap">
                        @if($status === 'SUCCESS')
                            <button class="btn btn-success btn-sm fw-semibold" disabled>
                                <i class="bi bi-check-circle me-1"></i> Terkirim
                            </button>
                        @elseif($status === 'FAILED')
                            <button class="btn btn-warning btn-sm fw-semibold btn-send" onclick="sendOne('{{ $visitId }}', this)">
                                <i class="bi bi-arrow-repeat me-1"></i> Kirim Ulang
                            </button>
                            <a href="{{ route('satusehat.failed.repair', $visitId) }}" class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-tools me-1"></i> Perbaiki Data
                            </a>
                        @else
                            <button class="btn btn-primary btn-sm fw-semibold btn-send" onclick="sendOne('{{ $visitId }}', this)">
                                <i class="bi bi-send me-1"></i> Kirim
                            </button>
                        @endif

                        <button class="btn btn-outline-info btn-sm" onclick="showDetailLog('{{ $visitId }}')">
                            <i class="bi bi-card-checklist me-1"></i> Riwayat
                        </button>
                    </div>

                    <div>
                        <a href="{{ route('satusehat.detail', $visitId) }}" class="btn btn-light btn-sm border text-secondary" title="Lihat detail kunjungan">
                            <i class="bi bi-eye me-1"></i> Detail
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="card-soft text-center py-5">
            <i class="bi bi-inbox fs-1 text-secondary"></i>
            <div class="mt-3 text-muted fw-semibold">
                Data kunjungan tidak ditemukan.
            </div>
            <div class="text-secondary small mb-3">
                Tarik data dari SIMRS atau ubah filter.
            </div>
            <div>
                <a href="{{ route('satusehat.simrs.index') }}" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-cloud-arrow-down me-1"></i>Tarik Data SIMRS
                </a>
            </div>
        </div>
    @endforelse
</div>

{{-- PAGINATION --}}
@if(isset($paginator) && $paginator->hasPages())
    <div class="d-flex justify-content-between align-items-center mt-3 pt-2">
        <small class="text-muted">
            Menampilkan {{ $paginator->firstItem() ?? 0 }} - {{ $paginator->lastItem() ?? 0 }} dari {{ $paginator->total() }} kunjungan
        </small>
        <div>
            {{ $paginator->links() }}
        </div>
    </div>
@endif

{{-- MODAL DETAIL LOG (MEDBRIDGE COMPATIBLE) --}}
<div class="modal fade" id="modalDetailLog" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Riwayat Pengiriman</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="modalLogContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <div class="small text-muted mt-2">Memuat...</div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Check All Checkbox Handler
    $("#checkAll").on("change", function() {
        $("#btnSendAll").prop("disabled", !this.checked);
    });

    // Quick Date filter click handler
    $('.btn-quick-date-ss').on('click', function() {
        const dateVal = $(this).data('date');
        $('#tanggal').val(dateVal);
        $('#filterForm').submit();
    });

    // Single Encounter Send
    function sendOne(visitId, btn) {
        Swal.fire({
            title: "Kirim Data?",
            text: `Kunjungan ${visitId} akan dikirim ke SATUSEHAT.`,
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#0ea5e9",
            cancelButtonColor: "#64748b",
            confirmButtonText: "Kirim",
            cancelButtonText: "Batal"
        }).then((result) => {
            if (!result.isConfirmed) return;

            if (btn) {
                $(btn).prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span> Mengirim...');
            }

            Swal.fire({
                title: "Mengirim...",
                html: "Mohon tunggu.",
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            $.ajax({
                url: `{{ url('/satusehat/send') }}/${visitId}`,
                type: "POST",
                data: {
                    _token: $('meta[name="csrf-token"]').attr("content")
                },
                success: function(res) {
                    if (res.success) {
                        Swal.fire({
                            icon: "success",
                            title: "Berhasil!",
                            text: res.message || "Data berhasil dikirim.",
                            confirmButtonColor: "#0ea5e9"
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: "warning",
                            title: "Sebagian Gagal",
                            text: res.message || "Sebagian data gagal dikirim. Lihat Riwayat untuk detailnya.",
                            confirmButtonColor: "#0ea5e9"
                        }).then(() => {
                            window.location.reload();
                        });
                    }
                },
                error: function(xhr) {
                    let msg = xhr.responseJSON?.message || "Tidak dapat terhubung ke server.";
                    Swal.fire({
                        icon: "error",
                        title: "Gagal",
                        text: msg,
                        confirmButtonColor: "#0ea5e9"
                    });
                },
                complete: function() {
                    if (btn) {
                        $(btn).prop("disabled", false).html('<i class="bi bi-send me-1"></i> Kirim');
                    }
                }
            });
        });
    }

    // Trigger Send All (Best Effort Pipeline)
    function triggerSendAll() {
        Swal.fire({
            title: "Kirim Semua Data?",
            text: "Semua data sesuai filter akan dikirim di latar belakang.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#10b981",
            cancelButtonColor: "#64748b",
            confirmButtonText: "Kirim Semua",
            cancelButtonText: "Batal"
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: "{{ route('satusehat.send.all') }}",
                type: "POST",
                data: {
                    start_date: $("#tanggal").val(),
                    status: $("#status").val(),
                    search: $("#keyword").val(),
                    _token: $('meta[name="csrf-token"]').attr("content")
                },
                success: function(res) {
                    Swal.fire({
                        icon: "success",
                        title: "Sedang Diproses",
                        text: res.message,
                        confirmButtonColor: "#10b981"
                    });
                },
                error: function(xhr) {
                    Swal.fire("Gagal", xhr.responseJSON?.message || "Gagal memulai pengiriman.", "error");
                }
            });
        });
    }

    // Show Detail Log Modal (Identical to MedBridge showDetailLog)
    function showDetailLog(visitId) {
        $("#modalDetailLog").modal("show");
        $("#modalLogContent").html('<div class="text-center py-4"><div class="spinner-border text-primary"></div><div class="small text-muted mt-2">Memuat...</div></div>');

        $.ajax({
            url: `{{ url('/satusehat/encounter-logs') }}/${visitId}`,
            type: "GET",
            success: function(res) {
                if (!res.success) {
                    $("#modalLogContent").html('<div class="alert alert-danger">Gagal memuat riwayat.</div>');
                    return;
                }

                let s = res.summary;
                let statusBadge = s.status === 'SUCCESS' ? '<span class="badge bg-success">Berhasil</span>'
                    : (s.status === 'PARTIAL_SUCCESS' ? '<span class="badge bg-warning text-dark">Sebagian Berhasil</span>'
                    : '<span class="badge bg-danger">Gagal</span>');

                let html = `
                    <div class="row text-center mb-4 g-2">
                        <div class="col-4">
                            <div class="card-soft py-2 mb-0">
                                <small class="text-muted d-block text-uppercase" style="font-size: 11px;">Total Data</small>
                                <h4 class="mb-0 fw-bold">${s.total_resource}</h4>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card-soft py-2 mb-0 border-start border-3 border-success">
                                <small class="text-success d-block text-uppercase" style="font-size: 11px;">Berhasil</small>
                                <h4 class="text-success mb-0 fw-bold">${s.berhasil}</h4>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card-soft py-2 mb-0 border-start border-3 border-danger">
                                <small class="text-danger d-block text-uppercase" style="font-size: 11px;">Gagal</small>
                                <h4 class="text-danger mb-0 fw-bold">${s.gagal}</h4>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                        <div>
                            <span class="text-muted small">Status:</span>
                            ${statusBadge}
                        </div>
                        <div>
                            <span class="text-muted small">ID Kunjungan:</span>
                            <span class="fw-bold font-monospace">${res.visit_id}</span>
                        </div>
                    </div>
                `;

                if (s.failed_resources && s.failed_resources.length > 0) {
                    html += `
                        <div class="mb-3">
                            <h6 class="fw-bold text-danger mb-2">Data yang Gagal</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Jenis Data</th>
                                            <th>Keterangan</th>
                                            <th>Waktu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                    `;
                    s.failed_resources.forEach(item => {
                        html += `
                            <tr>
                                <td class="fw-semibold text-danger">${item.resource_type}</td>
                                <td class="small">${item.error_message}</td>
                                <td class="small font-monospace">${item.failed_at || '-'}</td>
                            </tr>
                        `;
                    });
                    html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                }

                if (s.logs && s.logs.length > 0) {
                    html += `
                        <div>
                            <h6 class="fw-bold mb-2">Riwayat Pengiriman</h6>
                            <div class="list-group list-group-flush small">
                    `;
                    s.logs.forEach(log => {
                        let isSuccess = log.status === 'SUCCESS';
                        let badge = isSuccess ? '<span class="badge bg-success">Berhasil</span>' : '<span class="badge bg-danger">Gagal</span>';
                        html += `
                            <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold">${log.message || log.resource_type || 'Aktivitas'}</div>
                                    <small class="text-muted font-monospace">${log.timestamp || log.logged_at || '-'}</small>
                                </div>
                                <div>${badge}</div>
                            </div>
                        `;
                    });
                    html += `
                            </div>
                        </div>
                    `;
                }

                $("#modalLogContent").html(html);
            },
            error: function() {
                $("#modalLogContent").html('<div class="alert alert-danger">Gagal memuat riwayat.</div>');
            }
        });
    }
</script>
@endpush
