@extends('layouts.admin')

@section('title', 'Dashboard Administrator')
@section('page_title', 'Dashboard Monitoring Administrator')

@section('content')

{{-- WELCOME & OVERVIEW BANNER (MEDBRIDGE DESIGN) --}}
<div class="card-soft mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                    <i class="bi bi-shield-check me-1"></i> Mode Administrator
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                    <i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i> Sistem Online
                </span>
            </div>
            <h4 class="fw-bold text-dark mb-1">
                Selamat Datang, {{ Auth::user()->name ?? 'Administrator' }}
            </h4>
            <small class="text-muted">
                Pusat pemantauan layanan Tanda Tangan Elektronik (TTE) dan integrasi data Satu Sehat Kemenkes RI.
            </small>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.tte.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pen me-1"></i> Monitoring TTE
            </a>
            <a href="http://localhost:8000/satusehat" class="btn btn-primary btn-sm">
                <i class="bi bi-heart-pulse me-1"></i> Satu Sehat
                <i class="bi bi-box-arrow-up-right ms-1" style="font-size: 11px;"></i>
            </a>
        </div>
    </div>
</div>

{{-- METRIC SUMMARY CARDS (MEDBRIDGE CARD-SOFT) --}}
<div class="row g-3 mb-4">
    
    <!-- Stat 1: TTE Sukses -->
    <div class="col-sm-6 col-xl-3">
        <div class="card-soft h-100 mb-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">TTE Sukses</span>
                    <h3 class="fw-bold text-dark mt-1 mb-0">{{ number_format($totalTteSuccess) }}</h3>
                    <small class="text-success fw-semibold">
                        <i class="bi bi-check-circle-fill me-1"></i> Dokumen TTE
                    </small>
                </div>
                <div class="rounded-3 bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-patch-check fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 2: Total Log Request TTE -->
    <div class="col-sm-6 col-xl-3">
        <div class="card-soft h-100 mb-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Log Request TTE</span>
                    <h3 class="fw-bold text-dark mt-1 mb-0">{{ number_format($totalTteLogs) }}</h3>
                    <small class="text-primary fw-semibold">
                        <i class="bi bi-arrow-left-right me-1"></i> Riwayat API
                    </small>
                </div>
                <div class="rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-clock-history fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 3: Total Gagal / Error -->
    <div class="col-sm-6 col-xl-3">
        <div class="card-soft h-100 mb-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Log Gagal / Error</span>
                    <h3 class="fw-bold text-dark mt-1 mb-0">{{ number_format($failedLogsCount) }}</h3>
                    <small class="text-danger fw-semibold">
                        <i class="bi bi-exclamation-octagon-fill me-1"></i> Perlu dievaluasi
                    </small>
                </div>
                <div class="rounded-3 bg-danger-subtle text-danger d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-bug fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat 4: Satu Sehat Status -->
    <div class="col-sm-6 col-xl-3">
        <div class="card-soft h-100 mb-0">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Integrasi SATUSEHAT</span>
                    <h4 class="fw-bold text-primary mt-1 mb-0">Aktif & Siap</h4>
                    <a href="http://localhost:8000/satusehat" class="small text-decoration-none text-primary fw-semibold d-inline-flex align-items-center gap-1 mt-1">
                        Buka Dashboard <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
                <div class="rounded-3 bg-info-subtle text-info d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-heart-pulse fs-4"></i>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- GRAFIK PEMANTAUAN KONEKSI KE ENDPOINT API_URL (CONFIG/TTE.PHP) --}}
<div class="card-soft mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 pb-3 mb-3 border-bottom">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-activity text-primary fs-5"></i>
                <h5 class="fw-bold text-dark mb-0">Pemantauan Koneksi Endpoint Server TTE</h5>
                <span id="endpointStatusBadge" class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                    <span class="spinner-border spinner-border-sm me-1" style="width: 10px; height: 10px;"></span> Memeriksa...
                </span>
            </div>
            <div class="text-muted small d-flex align-items-center flex-wrap gap-2">
                <span>Target Endpoint:</span>
                <code class="bg-light px-2 py-0.5 rounded text-primary border font-monospace">{{ $tteApiUrl }}</code>
                <span class="text-muted">&bull;</span>
                <span class="text-secondary">Sumber: <code>config/tte.php</code></span>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <!-- Auto-refresh switch -->
            <div class="form-check form-switch me-2 d-none d-sm-flex align-items-center gap-2">
                <input class="form-check-input" type="checkbox" id="autoPingSwitch" checked>
                <label class="form-check-label small text-muted" for="autoPingSwitch">
                    Auto-Ping (15s)
                </label>
            </div>

            <button type="button" id="btnManualPing" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1.5 fw-semibold">
                <i class="bi bi-arrow-clockwise" id="pingIcon"></i>
                <span>Uji Koneksi Sekarang</span>
            </button>
        </div>
    </div>

    <div class="row g-4">
        
        <!-- Chart 1: Real-time Latency (ms) Line Chart -->
        <div class="col-lg-7">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-semibold text-secondary">
                    <i class="bi bi-speedometer me-1 text-primary"></i>Grafik Latensi Koneksi Real-time (ms)
                </span>
                <div class="small text-muted font-monospace" style="font-size: 11px;">
                    Pembaruan: <span id="lastPingTimeText">-</span>
                </div>
            </div>

            <!-- Canvas Container -->
            <div style="position: relative; height: 230px; width: 100%;">
                <canvas id="tteLatencyChart"></canvas>
            </div>

            <!-- Latency Metrics Strip -->
            <div class="row g-2 mt-2 pt-2 border-top text-center">
                <div class="col-4">
                    <small class="text-muted d-block text-uppercase" style="font-size: 10px;">Latensi Terkini</small>
                    <span id="metricCurrentLatency" class="fw-bold text-dark fs-6">-</span>
                </div>
                <div class="col-4">
                    <small class="text-muted d-block text-uppercase" style="font-size: 10px;">Status Terakhir</small>
                    <span id="metricHttpStatus" class="fw-bold text-dark fs-6">-</span>
                </div>
                <div class="col-4">
                    <small class="text-muted d-block text-uppercase" style="font-size: 10px;">Rata-rata Latensi</small>
                    <span id="metricAvgLatency" class="fw-bold text-dark fs-6">-</span>
                </div>
            </div>
        </div>

        <!-- Chart 2: Daily Transaction Volume & Success Rate (7 Hari Terakhir) -->
        <div class="col-lg-5">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="small fw-semibold text-secondary">
                    <i class="bi bi-bar-chart-line me-1 text-primary"></i>Riwayat Transaksi TTE (7 Hari Terakhir)
                </span>
                <span class="badge bg-light text-secondary border">Log HTTP</span>
            </div>

            <!-- Canvas Container -->
            <div style="position: relative; height: 230px; width: 100%;">
                <canvas id="tteDailyTrafficChart"></canvas>
            </div>

            <div class="d-flex justify-content-around mt-2 pt-2 border-top text-center">
                <div>
                    <small class="text-muted d-block text-uppercase" style="font-size: 10px;">Total Percobaan</small>
                    <span class="fw-bold text-dark fs-6">{{ number_format($totalTteLogs) }}</span>
                </div>
                <div>
                    <small class="text-muted d-block text-uppercase" style="font-size: 10px;">Sukses Tersertifikasi</small>
                    <span class="fw-bold text-success fs-6">{{ number_format($totalTteSuccess) }}</span>
                </div>
                <div>
                    <small class="text-muted d-block text-uppercase" style="font-size: 10px;">Gagal / Error</small>
                    <span class="fw-bold text-danger fs-6">{{ number_format($failedLogsCount) }}</span>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- MENU UTAMA MONITORING CARDS --}}
<div class="row g-3 mb-4">
    
    <!-- Menu 1 Card: Monitoring TTE -->
    <div class="col-md-6">
        <div class="card-soft h-100 mb-0">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-3 bg-primary-subtle text-primary p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="bi bi-pen fs-3"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold text-dark mb-1">1. Monitoring TTE</h5>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">BSrE</span>
                    </div>
                    <p class="text-muted small mb-3">
                        Pemantauan penandatanganan dokumen elektronik rekam medis oleh dokter, status sertifikat digital BSrE, serta jejak audit response engine TTE.
                    </p>
                    <a href="{{ route('admin.tte.index') }}" class="btn btn-outline-primary btn-sm fw-semibold">
                        Buka Monitoring TTE <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Menu 2 Card: Satu Sehat -->
    <div class="col-md-6">
        <div class="card-soft h-100 mb-0">
            <div class="d-flex align-items-start gap-3">
                <div class="rounded-3 bg-danger-subtle text-danger p-3 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                    <i class="bi bi-heart-pulse fs-3"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold text-dark mb-1">2. Satu Sehat</h5>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Kemenkes</span>
                    </div>
                    <p class="text-muted small mb-3">
                        Akses modul integrasi SATUSEHAT Kemenkes: penarikan data SIMRS, pengiriman Encounter/Bundle, perbaikan data gagal, dan monitoring aliran resource.
                    </p>
                    <a href="http://localhost:8000/satusehat" class="btn btn-primary btn-sm fw-semibold">
                        Buka Portal Satu Sehat <i class="bi bi-box-arrow-up-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- RECENT ACTIVITY TABLES (MEDBRIDGE DESIGN) --}}
<div class="row g-3">
    
    <!-- Table 1: Dokumen TTE Sukses Terbaru -->
    <div class="col-lg-6">
        <div class="card-soft mb-0">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-check text-success"></i>
                    <span>Dokumen TTE Sukses Terbaru</span>
                </div>
                <a href="{{ route('admin.tte.index', ['tab' => 'success']) }}" class="small text-primary text-decoration-none fw-semibold">
                    Lihat Semua &rarr;
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-light">
                        <tr>
                            <th>Visit ID</th>
                            <th>Doc ID</th>
                            <th>Waktu Selesai</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentSuccess as $item)
                            <tr>
                                <td class="fw-bold text-dark">{{ $item->visit_id }}</td>
                                <td class="text-muted">{{ $item->doc_id }}</td>
                                <td class="text-muted small">
                                    {{ $item->created_at ? $item->created_at->diffForHumans() : '-' }}
                                </td>
                                <td class="text-end">
                                    @if($item->path_tte)
                                        <a href="{{ $item->path_tte }}" target="_blank" class="btn btn-outline-success btn-sm py-0 px-2" style="font-size: 11px;">
                                            <i class="bi bi-file-pdf me-1"></i> PDF
                                        </a>
                                    @else
                                        <span class="text-muted small">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    Belum ada data dokumen TTE sukses.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Table 2: Log Request TTE Terakhir -->
    <div class="col-lg-6">
        <div class="card-soft mb-0">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-primary"></i>
                    <span>Log Aktivitas Transaksi TTE</span>
                </div>
                <a href="{{ route('admin.tte.index', ['tab' => 'logs']) }}" class="small text-primary text-decoration-none fw-semibold">
                    Lihat Semua &rarr;
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                    <thead class="table-light">
                        <tr>
                            <th>Endpoint</th>
                            <th>Status</th>
                            <th>Pesan Respons</th>
                            <th class="text-end">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentLogs as $log)
                            @php
                                $code = (int) ($log->response_code ?: $log->status);
                                $badgeClass = ($code >= 200 && $code < 300) 
                                    ? 'bg-success-subtle text-success border border-success-subtle' 
                                    : (($code >= 400) ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-secondary-subtle text-secondary');
                            @endphp
                            <tr>
                                <td class="font-monospace small text-dark">
                                    {{ $log->endpoint_url ?? $log->service_name }}
                                </td>
                                <td>
                                    <span class="badge {{ $badgeClass }} px-2 py-1">
                                        {{ $code ?: '200' }}
                                    </span>
                                </td>
                                <td class="text-truncate text-muted small" style="max-width: 180px;" title="{{ $log->response_message }}">
                                    {{ $log->response_message ?: '-' }}
                                </td>
                                <td class="text-end text-muted small whitespace-nowrap">
                                    {{ $log->created_at ? $log->created_at->diffForHumans() : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    Belum ada log request TTE.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {
    // -------------------------------------------------------------
    // 1. Inisialisasi Grafik Real-time Latensi Endpoint TTE
    // -------------------------------------------------------------
    const latencyCanvas = document.getElementById('tteLatencyChart');
    if (!latencyCanvas) return;

    const ctxLatency = latencyCanvas.getContext('2d');
    
    // Gradient fill warna tema MedBridge (Primary Blue)
    const gradient = ctxLatency.createLinearGradient(0, 0, 0, 200);
    gradient.addColorStop(0, 'rgba(14, 165, 233, 0.35)');
    gradient.addColorStop(1, 'rgba(14, 165, 233, 0.02)');

    const maxDataPoints = 12;
    const latencyLabels = [];
    const latencyData = [];
    const pointColors = [];

    const latencyChart = new Chart(ctxLatency, {
        type: 'line',
        data: {
            labels: latencyLabels,
            datasets: [{
                label: 'Latensi (ms)',
                data: latencyData,
                borderColor: '#0ea5e9',
                backgroundColor: gradient,
                borderWidth: 2.5,
                fill: true,
                tension: 0.35,
                pointBackgroundColor: pointColors,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return `Latensi: ${context.parsed.y} ms`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    suggestedMax: 3000,
                    grid: {
                        color: 'rgba(226, 232, 240, 0.6)',
                        borderDash: [4, 4]
                    },
                    ticks: {
                        callback: function (val) {
                            return val + ' ms';
                        },
                        font: { size: 11 }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 10 } }
                }
            }
        }
    });

    // -------------------------------------------------------------
    // 2. Inisialisasi Grafik Transaksi TTE Harian (7 Hari Terakhir)
    // -------------------------------------------------------------
    const trafficCanvas = document.getElementById('tteDailyTrafficChart');
    if (trafficCanvas) {
        const ctxTraffic = trafficCanvas.getContext('2d');
        const chartDates = @json($chartDates);
        const chartSuccess = @json($chartSuccess);
        const chartErrors = @json($chartErrors);

        new Chart(ctxTraffic, {
            type: 'bar',
            data: {
                labels: chartDates.length ? chartDates : ['Hari Ini'],
                datasets: [
                    {
                        label: 'Sukses',
                        data: chartSuccess.length ? chartSuccess : [0],
                        backgroundColor: '#10b981',
                        borderRadius: 6,
                        maxBarThickness: 18
                    },
                    {
                        label: 'Gagal / Error',
                        data: chartErrors.length ? chartErrors : [0],
                        backgroundColor: '#f43f5e',
                        borderRadius: 6,
                        maxBarThickness: 18
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            font: { size: 11, weight: 'bold' }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { size: 11 } },
                        grid: {
                            color: 'rgba(226, 232, 240, 0.6)',
                            borderDash: [4, 4]
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 } }
                    }
                }
            }
        });
    }

    // -------------------------------------------------------------
    // 3. Logika AJAX Ping Real-time ke Endpoint TTE
    // -------------------------------------------------------------
    let pingHistory = [];
    const statusBadge = document.getElementById('endpointStatusBadge');
    const metricCurrentLatency = document.getElementById('metricCurrentLatency');
    const metricHttpStatus = document.getElementById('metricHttpStatus');
    const metricAvgLatency = document.getElementById('metricAvgLatency');
    const lastPingTimeText = document.getElementById('lastPingTimeText');
    const btnManualPing = document.getElementById('btnManualPing');
    const pingIcon = document.getElementById('pingIcon');
    const autoPingSwitch = document.getElementById('autoPingSwitch');

    function executePing() {
        if (pingIcon) pingIcon.classList.add('fa-spin', 'text-primary');

        fetch("{{ route('admin.tte.ping') }}")
            .then(res => res.json())
            .then(data => {
                if (pingIcon) pingIcon.classList.remove('fa-spin', 'text-primary');

                const timeLabel = data.time || new Date().toLocaleTimeString();
                const latency = data.latency_ms || 0;
                const isOnline = data.online;
                const httpStatus = data.status || 0;

                // Update badge status
                if (statusBadge) {
                    if (isOnline) {
                        statusBadge.className = 'badge bg-success-subtle text-success border border-success-subtle px-2 py-1';
                        statusBadge.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Online (${httpStatus}) &bull; ${latency} ms`;
                    } else {
                        statusBadge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1';
                        statusBadge.innerHTML = `<i class="bi bi-exclamation-octagon-fill me-1"></i> Terputus (${httpStatus || 'Timeout'})`;
                    }
                }

                // Update info ringkasan
                if (metricCurrentLatency) {
                    metricCurrentLatency.innerHTML = `${latency} <small class="text-muted fw-normal">ms</small>`;
                    metricCurrentLatency.className = isOnline 
                        ? (latency > 2500 ? 'fw-bold text-warning fs-6' : 'fw-bold text-success fs-6') 
                        : 'fw-bold text-danger fs-6';
                }

                if (metricHttpStatus) {
                    metricHttpStatus.innerHTML = isOnline 
                        ? `<span class="badge bg-success-subtle text-success">${httpStatus} OK</span>` 
                        : `<span class="badge bg-danger-subtle text-danger">${httpStatus || 'ERR'}</span>`;
                }

                if (lastPingTimeText) {
                    lastPingTimeText.innerText = data.datetime || timeLabel;
                }

                // Hitung rata-rata
                pingHistory.push(latency);
                if (pingHistory.length > 20) pingHistory.shift();
                const avg = Math.round(pingHistory.reduce((a, b) => a + b, 0) / pingHistory.length);
                if (metricAvgLatency) {
                    metricAvgLatency.innerHTML = `${avg} <small class="text-muted fw-normal">ms</small>`;
                }

                // Update Chart.js dataset
                latencyLabels.push(timeLabel);
                latencyData.push(latency);
                pointColors.push(isOnline ? (latency > 2500 ? '#f59e0b' : '#0ea5e9') : '#f43f5e');

                if (latencyLabels.length > maxDataPoints) {
                    latencyLabels.shift();
                    latencyData.shift();
                    pointColors.shift();
                }

                latencyChart.update();
            })
            .catch(err => {
                if (pingIcon) pingIcon.classList.remove('fa-spin', 'text-primary');
                if (statusBadge) {
                    statusBadge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1';
                    statusBadge.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Error Jaringan';
                }
            });
    }

    // Jalankan ping pertama saat halaman selesai dimuat
    executePing();

    // Auto-ping setiap 15 detik jika switch aktif
    setInterval(() => {
        if (autoPingSwitch && autoPingSwitch.checked) {
            executePing();
        }
    }, 15000);

    // Event handler tombol ping manual
    if (btnManualPing) {
        btnManualPing.addEventListener('click', function () {
            executePing();
        });
    }
});
</script>
@endpush
