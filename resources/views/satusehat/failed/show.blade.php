@extends('satusehat.layout')

@section('title', 'Detail Data Gagal')

@section('content')
<div class="container-fluid p-0">
    <!-- Breadcrumb & Back Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <a href="{{ route('satusehat.failed.index') }}" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left me-1"></i> Kembali
            </a>
            <h3 class="fw-bold text-dark mb-0">
                Detail Data Gagal #{{ Str::limit($log->id ?? $log->log_id, 14) }} — <span class="text-primary">{{ $log->resource_type ?? 'Encounter' }}</span>
            </h3>
            <span class="text-muted small">ID Kunjungan: <code class="text-dark fw-bold">{{ $visitId }}</code></span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('satusehat.failed.repair', ['id' => $log->id ?? $log->log_id, 'staging_id' => $stagingInfo['id'] ?? null]) }}" class="btn btn-warning fw-semibold text-dark shadow-sm">
                <i class="bi bi-tools me-1"></i> Perbaiki Data
            </a>
            <form method="POST" action="{{ route('satusehat.failed.retry', $log->id ?? $log->log_id) }}">
                @csrf
                <button type="submit" class="btn btn-success fw-semibold shadow-sm" onclick="return confirm('Kirim ulang data ini sekarang?');">
                    <i class="bi bi-arrow-repeat me-1"></i> Kirim Ulang
                </button>
            </form>
        </div>
    </div>

    <!-- Flash Alerts -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-x-circle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Proposed SQL Box (DBA Action - Compliant with READ-ONLY Database rule) --}}
    @if(session('proposed_sql'))
        <div class="card shadow-sm border-0 border-start border-4 border-warning mb-4">
            <div class="card-header bg-warning bg-opacity-10 py-2 border-0 d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark small">
                    <i class="bi bi-database me-1 text-warning"></i> Query Database (Opsional)
                </span>
                <span class="badge bg-warning text-dark font-monospace">READ-ONLY SAFE</span>
            </div>
            <div class="card-body p-3">
                <p class="small text-muted mb-2">
                    Perbaikan sudah tersimpan untuk pengiriman ke SATUSEHAT. Jika database lokal ingin diperbarui permanen, jalankan query berikut:
                </p>
                <pre class="bg-dark text-warning p-3 rounded small font-monospace mb-0" style="white-space: pre-wrap;">{{ session('proposed_sql') }}</pre>
            </div>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <!-- Error Status Card -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Status Kendala</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm align-middle mb-0">
                        <tr>
                            <td class="text-muted" style="width: 140px;">Status:</td>
                            <td>
                                <span class="badge {{ $classification['badge'] }} px-3 py-1 fs-6 font-monospace">
                                    {{ $classification['label'] }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Kategori:</td>
                            <td><span class="badge bg-light text-dark border font-monospace">{{ $classification['category'] }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Kode HTTP:</td>
                            <td><span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace fs-6">HTTP {{ $log->http_status ?? '500' }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Percobaan:</td>
                            <td><span class="fw-bold">{{ $log->retry_count ?? 0 }}</span> / 3 kali</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Waktu:</td>
                            <td>{{ $log->created_at ?? '-' }}</td>
                        </tr>
                    </table>

                    <div class="mt-3 p-3 bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded text-danger small font-monospace">
                        <strong>Pesan Error:</strong><br>
                        {{ $log->error_message ?? 'Tidak ada detail pesan error' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Dependency & Validation Status -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-shield-check me-2 text-primary"></i>Status Validasi & Prasyarat</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="small text-muted fw-bold d-block mb-1">Status Kunjungan (Encounter):</label>
                        <div>
                            @if($is_encounter_success)
                                <span class="badge bg-success px-3 py-1 fs-6"><i class="bi bi-check-circle me-1"></i>Kunjungan Terkirim (#{{ $encounter->id ?? $visitId }})</span>
                            @else
                                <span class="badge bg-danger px-3 py-1 fs-6"><i class="bi bi-x-circle me-1"></i>Kunjungan Belum Terkirim (#{{ $encounter->id ?? '-' }})</span>
                                <small class="d-block text-danger mt-1">Data ini baru bisa dikirim setelah kunjungan utama terkirim.</small>
                            @endif
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small text-muted fw-bold d-block mb-1">Hasil Validasi Data:</label>
                        <div>
                            @if($validation['valid'])
                                <span class="badge bg-success px-3 py-1 fs-6"><i class="bi bi-check2-circle me-1"></i>Siap Dikirim Ulang</span>
                            @else
                                <span class="badge bg-warning text-dark px-3 py-1 fs-6"><i class="bi bi-tools me-1"></i>Perlu Perbaikan Data</span>
                            @endif
                        </div>
                    </div>

                    @if(!empty($validation['errors']))
                        <div class="p-3 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded text-dark small">
                            <strong>Data yang Perlu Dilengkapi:</strong>
                            <ul class="mb-0 ps-3 mt-1 text-danger">
                                @foreach($validation['errors'] as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="p-2 bg-light rounded small mt-3">
                        <div class="fw-bold text-dark mb-1">Informasi Data:</div>
                        <div>Tabel: <code class="text-primary">{{ $stagingInfo['table'] ?? '-' }}</code></div>
                        <div>Tipe: <span class="badge bg-info-subtle text-info-emphasis">{{ $stagingInfo['type'] ?? '-' }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Patient Context Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-0">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-person-vcard text-info me-2"></i>Data Pasien & Dokter</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <small class="text-muted d-block">Nama Pasien</small>
                    <div class="fw-bold text-dark">{{ $encounter->px_name ?? 'Pasien ' . $visitId }}</div>
                    <small class="text-secondary font-monospace">No RM: {{ $encounter->px_norm ?? '-' }}</small>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">ID SATUSEHAT Pasien</small>
                    <div>NIK: <span class="font-monospace text-dark">{{ $encounter->no_ktp ?? '-' }}</span></div>
                    <div>IHS Pasien: <span class="font-monospace text-primary">{{ $encounter->kode_pasien ?? '-' }}</span></div>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">Dokter (DPJP)</small>
                    <div class="fw-bold text-dark">{{ $encounter->dpjp_name ?? '-' }}</div>
                    <div>IHS DPJP: <span class="font-monospace text-primary">{{ $encounter->kode_dokter ?? '-' }}</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attempt History Table -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2 text-secondary"></i>Riwayat Percobaan Pengiriman</h6>
            <span class="badge bg-light text-secondary border font-monospace">{{ count($allAttemptLogs) }} Percobaan</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 150px;">Waktu</th>
                        <th style="width: 110px;">Status</th>
                        <th style="width: 120px;">HTTP Code</th>
                        <th style="width: 140px;">Jenis Error</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($allAttemptLogs as $att)
                        <tr>
                            <td class="ps-3 font-monospace text-secondary" style="font-size: 11px;">
                                {{ $att['timestamp'] ?? ($att['logged_at'] ?? ($att['created_at'] ?? '-')) }}
                            </td>
                            <td>
                                <span class="badge {{ ($att['status'] ?? '') === 'SUCCESS' ? 'bg-success' : 'bg-danger' }}">
                                    {{ $att['status'] ?? 'UNKNOWN' }}
                                </span>
                            </td>
                            <td class="font-monospace">HTTP {{ $att['http_code'] ?? ($att['http_status'] ?? '-') }}</td>
                            <td><span class="badge bg-light text-dark border font-monospace">{{ $att['error_type'] ?? '-' }}</span></td>
                            <td class="text-secondary">{{ $att['message'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Belum ada riwayat percobaan tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Staging Repair History (Audit Trail) -->
    @if(count($repairHistories) > 0)
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0">
                <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clipboard-check text-success me-2"></i>Riwayat Perbaikan Data</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Waktu</th>
                            <th>Tabel</th>
                            <th>Kolom</th>
                            <th>Nilai Lama</th>
                            <th>Nilai Baru</th>
                            <th>Alasan</th>
                            <th>Petugas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($repairHistories as $rh)
                            <tr>
                                <td class="ps-3 font-monospace" style="font-size: 11px;">{{ $rh['repaired_at'] ?? '-' }}</td>
                                <td><code>{{ $rh['staging_table'] ?? '-' }}</code></td>
                                <td><span class="fw-bold font-monospace text-primary">{{ $rh['field_name'] ?? '-' }}</span></td>
                                <td><span class="text-danger font-monospace">{{ $rh['old_value'] ?: '(kosong)' }}</span></td>
                                <td><span class="text-success font-monospace">{{ $rh['new_value'] }}</span></td>
                                <td>{{ $rh['reason'] ?? '-' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $rh['repaired_by'] ?? 'Petugas' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
