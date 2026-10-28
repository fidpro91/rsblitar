@extends('satusehat.layout')

@section('title', 'Detail Kunjungan #' . $visitId)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="{{ route('satusehat.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h4 class="fw-bold mb-0 text-dark">
                Detail Kunjungan #{{ $visitId }}
            </h4>
        </div>
        <small class="text-muted ms-4 ps-2">
            Data kunjungan, ID SATUSEHAT, dan riwayat pengiriman.
        </small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('satusehat.failed.repair', $visitId) }}" class="btn btn-warning btn-sm text-dark fw-semibold shadow-sm" title="Perbaiki data yang gagal dikirim">
            <i class="bi bi-tools me-1"></i> Perbaiki Data
        </a>
        <a href="{{ route('satusehat.export', $visitId) }}" class="btn btn-outline-secondary btn-sm shadow-sm" title="Unduh daftar ID SATUSEHAT">
            <i class="bi bi-box-arrow-up-right me-1"></i> Ekspor JSON
        </a>
        <button type="button" class="btn btn-primary btn-sm shadow-sm fw-semibold" onclick="showRetryModal()">
            <i class="bi bi-send me-1"></i> Kirim Ulang
        </button>
    </div>
</div>

<!-- Informasi Pasien Header (MedBridge Style) -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                        <i class="bi bi-person-circle fs-3"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1 text-dark">{{ $encounter->px_name ?? 'Pasien #' . $visitId }}</h5>
                        <div class="text-muted small d-flex flex-wrap gap-3">
                            <span><i class="bi bi-card-text me-1 text-secondary"></i>No. RM: <strong>{{ $encounter->px_norm ?? '-' }}</strong></span>
                            <span><i class="bi bi-person-vcard me-1 text-secondary"></i>NIK: <code>{{ $encounter->no_ktp_masked ?? '******' }}</code></span>
                            @if(!empty($encounter->kode_pasien))
                                <span><i class="bi bi-hospital me-1 text-secondary"></i>IHS Pasien: <span class="badge bg-light text-dark border">{{ $encounter->kode_pasien }}</span></span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-md-end">
                @php
                    $encSatsetId = $storageResources['Encounter']['resource_id'] ?? null;
                @endphp
                @if(!empty($encSatsetId))
                    <span class="badge bg-success px-3 py-2 fs-6">
                        <i class="bi bi-check-circle-fill me-1"></i> Terkirim
                    </span>
                    <div class="text-muted small mt-1 font-monospace" style="font-size: 11px;">ID SATUSEHAT: {{ $encSatsetId }}</div>
                @else
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6">
                        <i class="bi bi-clock-history me-1"></i> Belum Dikirim
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Tabs Navigasi Detail (MedBridge Design) -->
<ul class="nav nav-tabs nav-fill mb-4" id="detailTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-semibold" id="tab-res-btn" data-bs-toggle="tab" data-bs-target="#tab-resources" type="button" role="tab">
            <i class="bi bi-diagram-3 me-1"></i> ID SATUSEHAT
            <span class="badge bg-primary ms-1">{{ count($storageResources) }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-enc-btn" data-bs-toggle="tab" data-bs-target="#tab-encounter" type="button" role="tab">
            <i class="bi bi-clipboard2-pulse me-1"></i> Data Kunjungan
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-payload-btn" data-bs-toggle="tab" data-bs-target="#tab-payload" type="button" role="tab">
            <i class="bi bi-code-square me-1"></i> Data JSON
            <span class="badge bg-secondary ms-1">{{ $bundlePreview['total_entries'] ?? 0 }}</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-draft-btn" data-bs-toggle="tab" data-bs-target="#tab-draft" type="button" role="tab">
            <i class="bi bi-pencil-square me-1"></i> Koreksi Data
            @if(!empty($draft))
                <span class="badge bg-warning text-dark ms-1">Aktif</span>
            @endif
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="tab-logs-btn" data-bs-toggle="tab" data-bs-target="#tab-logs" type="button" role="tab">
            <i class="bi bi-journal-text me-1"></i> Riwayat Pengiriman
            <span class="badge bg-secondary ms-1">{{ count($logs) }}</span>
        </button>
    </li>
</ul>

<div class="tab-content" id="detailTabsContent">
    <!-- TAB 1: Resource ID SATUSEHAT Terpetakan -->
    <div class="tab-pane fade show active" id="tab-resources" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-card-checklist text-primary me-2"></i>ID SATUSEHAT
                    </h6>
                    <small class="text-muted">ID dari SATUSEHAT untuk setiap data yang berhasil dikirim.</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Jenis Data</th>
                                <th>ID SATUSEHAT</th>
                                <th>Status</th>
                                <th>Lokasi</th>
                                <th>Waktu</th>
                                <th class="text-center pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($storageResources as $resType => $resItem)
                            <tr>
                                <td class="ps-3">
                                    <span class="badge bg-primary bg-opacity-10 text-primary fw-bold px-2 py-1 font-monospace">
                                        {{ $resType }}
                                    </span>
                                </td>
                                <td>
                                    <code class="fw-bold text-dark font-monospace">{{ $resItem['resource_id'] }}</code>
                                </td>
                                <td>
                                    <span class="badge bg-success bg-opacity-10 text-success">
                                        <i class="bi bi-check me-1"></i> {{ $resItem['status'] ?? '201 Created' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted small font-monospace">
                                        {{ $resItem['location'] ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted small">
                                        {{ !empty($resItem['captured_at']) ? date('d/m/Y H:i:s', strtotime($resItem['captured_at'])) : '-' }}
                                    </span>
                                </td>
                                <td class="text-center pe-3">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyToClipboard('{{ $resItem['resource_id'] }}', this)">
                                        <i class="bi bi-clipboard me-1"></i> Salin ID
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-database fs-2 mb-2 text-secondary opacity-50 d-block"></i>
                                    <h6>Belum ada data yang terkirim</h6>
                                    <p class="small mb-0">Klik <strong>Kirim Ulang</strong> untuk mengirim data.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: Ringkasan Pelayanan -->
    <div class="tab-pane fade" id="tab-encounter" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-calendar-check text-primary me-2"></i>Data Kunjungan
                </h6>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <table class="table table-bordered table-sm mb-0 small">
                            <tbody>
                                <tr>
                                    <th class="bg-light text-muted" style="width: 35%;">Poli / Ruangan</th>
                                    <td><strong>{{ $encounter->unit_name ?? '-' }}</strong></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted">ID Lokasi SATUSEHAT</th>
                                    <td><code>{{ $encounter->idunitsatset ?? '-' }}</code></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted">DPJP</th>
                                    <td>{{ $encounter->dpjp_name ?? '-' }} (IHS: <code>{{ $encounter->kode_dokter ?? '-' }}</code>)</td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted">Tipe Kunjungan</th>
                                    <td><span class="badge bg-secondary">{{ $encounter->tipe_kunjungan ?? 'AMB' }}</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered table-sm mb-0 small">
                            <tbody>
                                <tr>
                                    <th class="bg-light text-muted" style="width: 35%;">Tgl Kunjungan</th>
                                    <td>{{ $encounter->tgl_kunjung ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted">Mulai Dilayani</th>
                                    <td>{{ $encounter->tgl_dilayani ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted">Selesai Dilayani</th>
                                    <td>{{ $encounter->tgl_selesai_dilayani ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted">Tgl Pulang</th>
                                    <td>{{ $encounter->tgl_pulang ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($encounter && $encounter->diagnossis && $encounter->diagnossis->isNotEmpty())
                <h6 class="fw-bold mt-4 mb-2 text-dark">
                    <i class="bi bi-file-medical text-primary me-1"></i> Diagnosa (ICD-10)
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered small">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 80px;">Jenis</th>
                                <th style="width: 120px;">Kode</th>
                                <th>Diagnosa</th>
                                <th>UUID</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($encounter->diagnossis as $dx)
                            <tr>
                                <td>
                                    <span class="badge {{ $dx->rank == 1 ? 'bg-primary' : 'bg-secondary' }}">
                                        {{ $dx->rank == 1 ? 'Primer' : 'Sekunder (' . $dx->rank . ')' }}
                                    </span>
                                </td>
                                <td><code>{{ $dx->code }}</code></td>
                                <td>{{ $dx->dx_name }}</td>
                                <td><code class="small text-muted">{{ $dx->uuid }}</code></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- TAB 3: Payload FHIR Bundle -->
    <div class="tab-pane fade" id="tab-payload" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-code-square text-primary me-2"></i>Data JSON yang Dikirim
                    </h6>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyToClipboard(document.getElementById('bundleJsonRaw').innerText, this)">
                    <i class="bi bi-clipboard me-1"></i> Salin JSON
                </button>
            </div>
            <div class="card-body p-3">
                @if($previewError)
                    <div class="alert alert-danger small">
                        <i class="bi bi-exclamation-triangle me-1"></i> Gagal menyiapkan data: {{ $previewError }}
                    </div>
                @endif

                @if(!empty($bundlePreview['builder_errors']))
                    <div class="alert alert-warning small mb-3">
                        <strong>Catatan:</strong>
                        <ul class="mb-0 ps-3">
                            @foreach($bundlePreview['builder_errors'] as $bName => $bErr)
                                <li><code>{{ $bName }}</code>: {{ $bErr }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <pre class="bg-dark text-info p-3 rounded small font-monospace mb-0" id="bundleJsonRaw" style="max-height: 480px; overflow-y: auto;"><code>{{ json_encode($bundlePreview['bundle'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
            </div>
        </div>
    </div>

    <!-- TAB 4: Koreksi Data (Non-Database Draft) -->
    <div class="tab-pane fade" id="tab-draft" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-pencil-square text-warning me-2"></i>Koreksi Data
                        </h6>
                        <small class="text-muted">Koreksi hanya dipakai saat kirim ke SATUSEHAT. Data SIMRS tidak berubah.</small>
                    </div>
                </div>
            </div>
            <div class="card-body p-4">

                <form method="POST" action="{{ route('satusehat.draft.save', $visitId) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">
                                ID Lokasi SATUSEHAT
                            </label>
                            <input type="text" name="idunitsatset" class="form-control" value="{{ $draft['fields']['idunitsatset'] ?? ($encounter->idunitsatset ?? '') }}" placeholder="Contoh: 6f819251-9226-4445-96cb-8a0eeaa70dd2">
                            <div class="form-text small">ID lokasi poli/ruangan di SATUSEHAT.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold text-secondary">
                                Kondisi Pulang
                            </label>
                            <select name="kondisi_pulang" class="form-select">
                                @php
                                    $curKondisi = $draft['fields']['kondisi_pulang'] ?? 'Pulang';
                                @endphp
                                <option value="Pulang" {{ $curKondisi == 'Pulang' ? 'selected' : '' }}>Pulang</option>
                                <option value="Sembuh" {{ $curKondisi == 'Sembuh' ? 'selected' : '' }}>Sembuh</option>
                                <option value="Membaik" {{ $curKondisi == 'Membaik' ? 'selected' : '' }}>Membaik</option>
                                <option value="Rujuk" {{ $curKondisi == 'Rujuk' ? 'selected' : '' }}>Dirujuk</option>
                                <option value="Atas Permintaan Sendiri" {{ $curKondisi == 'Atas Permintaan Sendiri' ? 'selected' : '' }}>Atas Permintaan Sendiri (APS)</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary">
                                Instruksi Pulang
                            </label>
                            <textarea name="instruksi_pulang" rows="3" class="form-control" placeholder="Edukasi dan instruksi untuk pasien">{{ $draft['fields']['instruksi_pulang'] ?? ($encounter->instruksi_pulang ?? 'Pasien dipulangkan dalam kondisi stabil dan dianjurkan istirahat serta minum obat teratur.') }}</textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary">
                                Keterangan Pulang
                            </label>
                            <input type="text" name="alasan_pulang" class="form-control" value="{{ $draft['fields']['alasan_pulang'] ?? '' }}" placeholder="Tindak lanjut setelah pulang">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        @if(!empty($draft))
                            <button type="submit" formaction="{{ route('satusehat.draft.clear', $visitId) }}" class="btn btn-outline-danger btn-sm" onclick="return confirm('Hapus koreksi ini?')">
                                <i class="bi bi-trash me-1"></i> Hapus Koreksi
                            </button>
                        @else
                            <div></div>
                        @endif
                        <button type="submit" class="btn btn-success btn-sm px-4 fw-semibold">
                            <i class="bi bi-save me-1"></i> Simpan Koreksi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB 5: Log Riwayat Respons -->
    <div class="tab-pane fade" id="tab-logs" role="tabpanel">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-clock-history text-primary me-2"></i>Riwayat Pengiriman
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Waktu</th>
                                <th>Status</th>
                                <th>Kode</th>
                                <th>Jenis Error</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $l)
                            <tr>
                                <td class="ps-3 font-monospace text-secondary" style="font-size: 11px;">{{ date('d/m/Y H:i:s', strtotime($l['timestamp'])) }}</td>
                                <td>
                                    @if(($l['status'] ?? '') === 'SUCCESS')
                                        <span class="badge bg-success">Berhasil</span>
                                    @elseif(($l['status'] ?? '') === 'PARTIAL_SUCCESS')
                                        <span class="badge bg-warning text-dark">Sebagian</span>
                                    @elseif(($l['status'] ?? '') === 'TIMEOUT')
                                        <span class="badge bg-warning text-dark">Waktu Habis</span>
                                    @else
                                        <span class="badge bg-danger">Gagal</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace">
                                        HTTP {{ $l['http_code'] ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    @if(!empty($l['error_type']))
                                        <span class="badge bg-danger bg-opacity-10 text-danger font-monospace">
                                            {{ $l['error_type'] }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $l['message'] ?? '-' }}</div>
                                    @if(!empty($l['issues']))
                                        <ul class="mb-0 ps-3 mt-1 text-danger small">
                                            @foreach($l['issues'] as $iss)
                                                <li>{{ $iss['detail'] ?? json_encode($iss) }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted small">
                                    Belum ada riwayat pengiriman.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Kirim Ulang -->
<div class="modal fade" id="modalRetry" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold fs-6 text-dark">
                    <i class="bi bi-send text-primary me-2"></i>Kirim Ulang
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle d-inline-block mb-2">
                        <i class="bi bi-arrow-repeat fs-3"></i>
                    </div>
                    <h6 class="fw-bold mb-1">{{ $encounter->px_name ?? 'Pasien ' . $visitId }}</h6>
                    <p class="text-muted small mb-0 font-monospace">ID Kunjungan: <strong>{{ $visitId }}</strong></p>
                </div>
                <p class="small text-muted text-center mb-3">Data yang sudah terkirim tidak akan dikirim dua kali.</p>
                <div id="retryAlertResult" class="d-none"></div>
            </div>
            <div class="modal-footer bg-light p-2 border-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" id="btnCancelRetry">Batal</button>
                <button type="button" class="btn btn-primary btn-sm px-3 fw-semibold" id="btnConfirmRetry" onclick="executeRetry()">
                    <span id="btnRetrySpinner" class="spinner-border spinner-border-sm me-1 d-none"></span>
                    <i class="bi bi-send me-1" id="btnRetryIcon"></i> Kirim
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const retryModal = new bootstrap.Modal(document.getElementById('modalRetry'));
    const visitId = '{{ $visitId }}';

    function copyToClipboard(text, element) {
        navigator.clipboard.writeText(text).then(() => {
            const originalHtml = element.innerHTML;
            element.innerHTML = '<i class="bi bi-check text-success"></i> Disalin!';
            setTimeout(() => {
                element.innerHTML = originalHtml;
            }, 1500);
        }).catch(err => {
            alert('Gagal menyalin: ' + err);
        });
    }

    function showRetryModal() {
        const resultAlert = document.getElementById('retryAlertResult');
        resultAlert.classList.add('d-none');
        resultAlert.innerHTML = '';

        document.getElementById('btnConfirmRetry').disabled = false;
        document.getElementById('btnRetrySpinner').classList.add('d-none');
        document.getElementById('btnRetryIcon').classList.remove('d-none');

        retryModal.show();
    }

    function executeRetry() {
        const btn = document.getElementById('btnConfirmRetry');
        const spinner = document.getElementById('btnRetrySpinner');
        const icon = document.getElementById('btnRetryIcon');
        const resultAlert = document.getElementById('retryAlertResult');

        btn.disabled = true;
        spinner.classList.remove('d-none');
        icon.classList.add('d-none');

        fetch(`/satusehat/retry/${visitId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            }
        })
        .then(async response => {
            const data = await response.json();
            resultAlert.classList.remove('d-none');
            
            if (response.ok && data.success) {
                resultAlert.className = 'alert alert-success small mb-0';
                resultAlert.innerHTML = `<i class="bi bi-check-circle me-1"></i> <strong>Berhasil!</strong> ${data.message}`;
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                resultAlert.className = 'alert alert-danger small mb-0';
                const errorType = data.error_type ? `[${data.error_type}] ` : '';
                resultAlert.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i> <strong>Gagal:</strong> ${errorType}${data.message}`;
                btn.disabled = false;
                spinner.classList.add('d-none');
                icon.classList.remove('d-none');
            }
        })
        .catch(err => {
            resultAlert.classList.remove('d-none');
            resultAlert.className = 'alert alert-danger small mb-0';
            resultAlert.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i> <strong>Gagal:</strong> Tidak dapat terhubung ke server (${err.message}).`;
            btn.disabled = false;
            spinner.classList.add('d-none');
            icon.classList.remove('d-none');
        });
    }
</script>
@endpush
