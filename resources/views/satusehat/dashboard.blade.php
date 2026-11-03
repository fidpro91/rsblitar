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
            <button type="button" id="btnRefreshDashboard" class="btn btn-outline-secondary btn-sm" title="Muat ulang">
                <i class="bi bi-arrow-clockwise me-1"></i> Muat Ulang
            </button>
        </div>
    </div>

    {{-- FILTER FORM --}}
    <form method="GET" action="{{ route('satusehat.dashboard') }}" id="filterForm">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-secondary">Tanggal Kunjungan</label>
                <input
                    type="date"
                    id="tanggal"
                    name="start_date"
                    class="form-control"
                    value="{{ $filters['start_date'] ?? request('start_date', date('Y-m-d')) }}">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-secondary">Sampai Tanggal</label>
                <input
                    type="date"
                    id="tanggalAkhir"
                    name="end_date"
                    class="form-control"
                    value="{{ $filters['end_date'] ?? request('end_date', '') }}">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold text-secondary">Status Pengiriman</label>
                <select id="status" name="status" class="form-select">
                    <option value="ALL" {{ request('status', 'ALL') == 'ALL' ? 'selected' : '' }}>Semua Status</option>
                    <option value="SUCCESS" {{ request('status') == 'SUCCESS' ? 'selected' : '' }}>Berhasil</option>
                    <option value="FAILED" {{ request('status') == 'FAILED' ? 'selected' : '' }}>Gagal</option>
                    <option value="DRAFT" {{ request('status') == 'DRAFT' ? 'selected' : '' }}>Belum Dikirim</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold text-secondary">Cari Pasien</label>
                <input
                    type="text"
                    id="keyword"
                    name="search"
                    class="form-control"
                    placeholder="Nama, No. RM, atau ID kunjungan"
                    value="{{ request('search') }}">
            </div>

            <div class="col-md-3 d-flex align-items-end gap-1">
                <button
                    type="submit"
                    id="btnFilter"
                    class="btn btn-primary flex-fill fw-semibold">
                    <i class="bi bi-search me-1"></i> Cari
                </button>
                <button type="button" id="btnResetFilter" class="btn btn-outline-secondary" title="Reset filter">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
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
<div class="row g-3 mb-3" id="metricsContainer">
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
            $pxName = strtoupper(trim((string) ($itemObj->px_name ?? ''))) ?: 'NAMA PASIEN TIDAK TERSEDIA';
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
                            <button class="btn btn-warning btn-sm fw-semibold btn-send" onclick="sendOne(@js($visitId), this)">
                                <i class="bi bi-arrow-repeat me-1"></i> Kirim Ulang
                            </button>
                            <a href="{{ route('satusehat.failed.repair', $visitId) }}" class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-tools me-1"></i> Perbaiki Data
                            </a>
                        @else
                            <button class="btn btn-primary btn-sm fw-semibold btn-send" onclick="sendOne(@js($visitId), this)">
                                <i class="bi bi-send me-1"></i> Kirim
                            </button>
                        @endif

                        <button class="btn btn-outline-info btn-sm" onclick="showDetailLog(@js($visitId))">
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
<div id="paginationContainer">
    @if(isset($paginator) && $paginator->hasPages())
        <div class="d-flex justify-content-between align-items-center mt-3 pt-2">
            <small class="text-muted">
                Menampilkan {{ $paginator->firstItem() ?? 0 }} - {{ $paginator->lastItem() ?? 0 }} dari {{ $paginator->total() }} kunjungan
            </small>
            <div>
                {{ $paginator->links() }}
            </div>
        </div>
    @elseif(isset($paginator))
        <div class="mt-3 small text-muted">Menampilkan {{ $paginator->total() }} kunjungan</div>
    @endif
</div>

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
    // Escape data API sebelum dimasukkan ke HTML modal untuk mencegah HTML injection.
    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    /**
     * Filter dashboard dengan Fetch API (tanpa submit/navigasi dokumen).
     * Event dipasang pada document dalam capture phase agar submit form
     * tetap dicegat meskipun ada handler lain pada elemen form.
     */
    let dashboardAbortController = null;

    function setDashboardLoading(loading) {
        const ids = ['btnFilter', 'btnRefreshDashboard', 'btnResetFilter'];
        ids.forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.disabled = loading;
        });

        ['listEncounter', 'paginationContainer'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('opacity-50', loading);
        });

        const filterButton = document.getElementById('btnFilter');
        if (filterButton) {
            filterButton.innerHTML = loading
                ? '<span class="spinner-border spinner-border-sm me-1"></span> Memuat...'
                : '<i class="bi bi-search me-1"></i> Cari';
        }
    }

    function getDashboardUrl() {
        const form = document.getElementById('filterForm');
        const url = new URL(form.action, window.location.origin);
        const formData = new FormData(form);
        url.search = new URLSearchParams(formData).toString();
        return url;
    }

    async function applyDashboardFilters(updateUrl = true, targetUrl = null) {
        const requestUrl = targetUrl
            ? new URL(targetUrl, window.location.origin)
            : getDashboardUrl();

        if (dashboardAbortController) dashboardAbortController.abort();
        dashboardAbortController = new AbortController();
        setDashboardLoading(true);

        try {
            const response = await fetch(requestUrl.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                },
                credentials: 'same-origin',
                signal: dashboardAbortController.signal
            });

            if (!response.ok) {
                throw new Error('Server mengembalikan HTTP ' + response.status);
            }

            const html = await response.text();
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            const newList = parsed.querySelector('#listEncounter');
            const newMetrics = parsed.querySelector('#metricsContainer');
            const newPagination = parsed.querySelector('#paginationContainer');

            if (!newList || !newMetrics || !newPagination) {
                throw new Error('Elemen hasil dashboard tidak ditemukan pada respons server.');
            }

            document.getElementById('listEncounter').innerHTML = newList.innerHTML;
            document.getElementById('metricsContainer').innerHTML = newMetrics.innerHTML;
            document.getElementById('paginationContainer').innerHTML = newPagination.innerHTML;

            // Sinkronkan filter dari URL (terutama saat berpindah halaman).
            ['start_date', 'end_date', 'status', 'search'].forEach(function(key) {
                const value = requestUrl.searchParams.get(key);
                const selector = {
                    start_date: '#tanggal',
                    end_date: '#tanggalAkhir',
                    status: '#status',
                    search: '#keyword'
                }[key];
                const field = document.querySelector(selector);
                if (field && value !== null) field.value = value;
                if (field && value === null && key !== 'status') field.value = '';
            });

            const checkAll = document.getElementById('checkAll');
            const sendAll = document.getElementById('btnSendAll');
            if (checkAll) checkAll.checked = false;
            if (sendAll) sendAll.disabled = true;

            if (updateUrl) {
                window.history.pushState({}, '', requestUrl.toString());
            }
        } catch (error) {
            if (error.name !== 'AbortError') {
                console.error('Dashboard AJAX error:', error);
                if (window.Swal) {
                    Swal.fire('Gagal Memuat Data', error.message || 'Tidak dapat memuat data.', 'error');
                } else {
                    alert(error.message || 'Tidak dapat memuat data.');
                }
            }
        } finally {
            setDashboardLoading(false);
            dashboardAbortController = null;
        }
    }

    // Capture phase: cegah browser melakukan submit GET/reload halaman.
    document.addEventListener('submit', function(event) {
        if (event.target && event.target.id === 'filterForm') {
            event.preventDefault();
            event.stopPropagation();
            applyDashboardFilters(true);
        }
    }, true);

    // Fallback eksplisit untuk klik tombol Cari.
    document.addEventListener('click', function(event) {
        const filterButton = event.target.closest('#btnFilter');
        if (filterButton) {
            event.preventDefault();
            event.stopPropagation();
            applyDashboardFilters(true);
            return;
        }

        const refreshButton = event.target.closest('#btnRefreshDashboard');
        if (refreshButton) {
            event.preventDefault();
            applyDashboardFilters(false, window.location.href);
            return;
        }

        const resetButton = event.target.closest('#btnResetFilter');
        if (resetButton) {
            event.preventDefault();
            document.getElementById('tanggal').value = '';
            document.getElementById('tanggalAkhir').value = '';
            document.getElementById('status').value = 'ALL';
            document.getElementById('keyword').value = '';
            applyDashboardFilters(true);
            return;
        }

        const quickDate = event.target.closest('.btn-quick-date-ss');
        if (quickDate) {
            event.preventDefault();
            document.getElementById('tanggal').value = quickDate.dataset.date || '';
            document.getElementById('tanggalAkhir').value = '';
            applyDashboardFilters(true);
            return;
        }

        const pageLink = event.target.closest('#paginationContainer .pagination a');
        if (pageLink) {
            event.preventDefault();
            const href = pageLink.getAttribute('href');
            if (href) applyDashboardFilters(true, href);
        }
    }, true);

    // Ubah status langsung menerapkan filter tanpa menekan tombol Cari.
    document.getElementById('status')?.addEventListener('change', function() {
        applyDashboardFilters(true);
    });

    // Back/Forward browser tetap menggunakan AJAX.
    window.addEventListener('popstate', function() {
        applyDashboardFilters(false, window.location.href);
    });

    // Check All Checkbox Handler
    $("#checkAll").on("change", function() {
        $("#btnSendAll").prop("disabled", !this.checked);
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
                url: `{{ url('/satusehat/send') }}/${encodeURIComponent(visitId)}`,
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
                            applyDashboardFilters(false);
                        });
                    } else {
                        Swal.fire({
                            icon: "warning",
                            title: "Sebagian Gagal",
                            text: res.message || "Sebagian data gagal dikirim. Lihat Riwayat untuk detailnya.",
                            confirmButtonColor: "#0ea5e9"
                        }).then(() => {
                            applyDashboardFilters(false);
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

            const sendAllButton = $("#btnSendAll");
            sendAllButton.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menjadwalkan...');
            $.ajax({
                url: "{{ route('satusehat.send.all') }}",
                type: "POST",
                data: {
                    start_date: $("#tanggal").val(),
                    end_date: $("#tanggalAkhir").val(),
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
                },
                complete: function() {
                    sendAllButton.prop("disabled", !$("#checkAll").is(":checked"))
                        .html('<i class="bi bi-send me-1"></i> Kirim Semua');
                }
            });
        });
    }

    // Show Detail Log Modal (Identical to MedBridge showDetailLog)
    function showDetailLog(visitId) {
        $("#modalDetailLog").modal("show");
        $("#modalLogContent").html('<div class="text-center py-4"><div class="spinner-border text-primary"></div><div class="small text-muted mt-2">Memuat...</div></div>');

        $.ajax({
            url: `{{ url('/satusehat/encounter-logs') }}/${encodeURIComponent(visitId)}`,
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
                                <h4 class="mb-0 fw-bold">${escapeHtml(s.total_resource)}</h4>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card-soft py-2 mb-0 border-start border-3 border-success">
                                <small class="text-success d-block text-uppercase" style="font-size: 11px;">Berhasil</small>
                                <h4 class="text-success mb-0 fw-bold">${escapeHtml(s.berhasil)}</h4>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card-soft py-2 mb-0 border-start border-3 border-danger">
                                <small class="text-danger d-block text-uppercase" style="font-size: 11px;">Gagal</small>
                                <h4 class="text-danger mb-0 fw-bold">${escapeHtml(s.gagal)}</h4>
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
                            <span class="fw-bold font-monospace">${escapeHtml(res.visit_id)}</span>
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
                                <td class="fw-semibold text-danger">${escapeHtml(item.resource_type)}</td>
                                <td class="small">${escapeHtml(item.error_message)}</td>
                                <td class="small font-monospace">${escapeHtml(item.failed_at || '-')}</td>
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
                                    <div class="fw-semibold">${escapeHtml(log.message || log.resource_type || 'Aktivitas')}</div>
                                    <small class="text-muted font-monospace">${escapeHtml(log.timestamp || log.logged_at || '-')}</small>
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
