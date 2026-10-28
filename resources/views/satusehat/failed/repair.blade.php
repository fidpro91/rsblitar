@extends('satusehat.layout')

@section('title', 'Perbaikan Data Staging')

@section('content')
@php
    $currentTab = $activeTab ?? 'encounter';
    $currentStagingId = $stagingId ?? null;
@endphp

<!-- Header & Navigasi Atas -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <a href="{{ route('satusehat.detail', $visitId) }}" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Detail Kunjungan
        </a>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <h3 class="fw-bold text-dark mb-0">
                <i class="bi bi-database-gear text-primary me-2"></i>Perbaikan Data Staging
            </h3>
            <span class="badge bg-primary px-3 py-1 fs-6 font-monospace">Kunjungan #{{ $visitId }}</span>
            @if(($encounter->bundle_status ?? '') === 'SUCCESS')
                <span class="badge bg-success px-3 py-1 fs-6"><i class="bi bi-check-circle-fill me-1"></i>Terkirim</span>
            @elseif(($encounter->bundle_status ?? '') === 'FAILED')
                <span class="badge bg-danger px-3 py-1 fs-6"><i class="bi bi-x-circle-fill me-1"></i>Gagal</span>
            @else
                <span class="badge bg-warning text-dark px-3 py-1 fs-6"><i class="bi bi-clock-fill me-1"></i>Pending</span>
            @endif
        </div>
        <small class="text-secondary">Perbaiki langsung data pada tabel staging (Encounter, Diagnosa, Farmasi, Tindakan, dan lainnya) untuk diperbarui langsung ke database dan dikirim ke SATUSEHAT.</small>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-info text-white fw-semibold shadow-sm" onclick="runLiveValidation()">
            <i class="bi bi-shield-check me-1"></i> Cek Kelengkapan Data
        </button>
    </div>
</div>

<div id="validationAlert" class="mb-3" style="display: none;"></div>

<!-- Banner Konteks Pasien & DPJP -->
<div class="card shadow-sm border-0 border-start border-4 border-primary mb-4 bg-white">
    <div class="card-body py-3">
        <div class="row align-items-center g-3">
            <div class="col-lg-6">
                <div class="small text-muted fw-bold">Data Pasien & Kunjungan:</div>
                <div class="fw-bold text-dark fs-6">
                    {{ $encounter->px_name ?? 'Pasien ' . $visitId }}
                    <span class="text-secondary fw-normal">(No. RM: {{ $encounter->px_norm ?? '-' }})</span>
                </div>
                <div class="small text-muted font-monospace mt-1">
                    NIK: <span class="text-dark fw-bold">{{ $encounter->no_ktp ?? '-' }}</span> |
                    IHS Pasien: <span class="text-primary fw-bold">{{ $encounter->kode_pasien ?? 'Belum ada' }}</span> |
                    Tipe: <span class="badge bg-light text-dark border">{{ $encounter->tipe_kunjungan ?? 'AMB' }}</span>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="small text-muted fw-bold">Dokter DPJP & Poliklinik:</div>
                <div class="fw-bold text-dark fs-6">
                    {{ $encounter->dpjp_name ?? '-' }}
                    <span class="text-secondary fw-normal">({{ $encounter->unit_name ?? '-' }})</span>
                </div>
                <div class="small text-muted font-monospace mt-1">
                    IHS Dokter: <span class="text-dark fw-bold">{{ $encounter->kode_dokter ?? '-' }}</span> |
                    Tanggal Kunjungan: <span class="text-dark">{{ $encounter->tgl_kunjung ? date('d/m/Y H:i', strtotime($encounter->tgl_kunjung)) : '-' }}</span>
                </div>
            </div>
            @if(!empty($encounter->last_error) || !empty($log->error_message))
                <div class="col-12 pt-2 border-top">
                    <div class="p-2 bg-danger bg-opacity-10 border border-danger border-opacity-25 rounded text-danger small font-monospace">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <strong>Catatan Error Terakhir:</strong> {{ $encounter->last_error ?? ($log->error_message ?? '') }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Navigasi Tab Semua Tabel Staging -->
<ul class="nav nav-pills mb-3 gap-2 flex-wrap bg-white p-2 rounded border shadow-sm" id="stagingTabs" role="tablist">
    @foreach($allStaging as $key => $stg)
        @php
            $isActive = ($currentTab === $key);
            $count = $stg['count'] ?? 0;
        @endphp
        <li class="nav-item" role="presentation">
            <button class="nav-link py-2 px-3 fw-semibold d-flex align-items-center gap-2 {{ $isActive ? 'active' : '' }}"
                    id="tab-btn-{{ $key }}"
                    data-bs-toggle="pill"
                    data-bs-target="#tab-pane-{{ $key }}"
                    type="button"
                    role="tab"
                    aria-controls="tab-pane-{{ $key }}"
                    aria-selected="{{ $isActive ? 'true' : 'false' }}">
                <i class="bi {{ $stg['icon'] ?? 'bi-table' }}"></i>
                <span>{{ $stg['title'] }}</span>
                <span class="badge {{ $isActive ? 'bg-white text-primary' : ($count > 0 ? 'bg-primary' : 'bg-secondary') }} rounded-pill" style="font-size: 11px;">
                    {{ $count }}
                </span>
            </button>
        </li>
    @endforeach
</ul>

<!-- Konten Tab Staging -->
<div class="tab-content" id="stagingTabsContent">

    <!-- ================= 1. TAB ENCOUNTER ================= -->
    <div class="tab-pane fade {{ $currentTab === 'encounter' ? 'show active' : '' }}" id="tab-pane-encounter" role="tabpanel">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-hospital me-2 text-primary"></i>Tabel Staging: <code>visit_encounter</code>
                </h6>
                <span class="badge bg-light text-secondary border font-monospace">Record ID: #{{ $encounter->id ?? 'New' }}</span>
            </div>
            <form method="POST" action="{{ route('satusehat.failed.save-repair', $log->id ?? $log->log_id) }}">
                @csrf
                <input type="hidden" name="staging_table" value="visit_encounter">
                <input type="hidden" name="staging_id" value="{{ $encounter->id ?? '' }}">
                <input type="hidden" name="resource_type" value="Encounter">
                <input type="hidden" name="active_tab" value="encounter">
                <input type="hidden" name="redirect_to" value="repair">

                <div class="card-body pt-0">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">NIK Pasien (16 Digit) <span class="text-danger">*</span></label>
                            <input type="text" name="no_ktp" class="form-control form-control-sm font-monospace fw-bold" value="{{ old('no_ktp', $encounter->no_ktp ?? '') }}" required maxlength="16" placeholder="3505xxxxxxxxxxxx">
                            <small class="text-muted" style="font-size: 11px;">Wajib 16 digit angka sesuai KTP / KK.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">No. Rekam Medis (RM)</label>
                            <input type="text" name="px_norm" class="form-control form-control-sm font-monospace" value="{{ old('px_norm', $encounter->px_norm ?? '') }}" placeholder="012345">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Nama Lengkap Pasien <span class="text-danger">*</span></label>
                            <input type="text" name="px_name" class="form-control form-control-sm fw-bold" value="{{ old('px_name', $encounter->px_name ?? '') }}" required placeholder="Nama Lengkap Sesuai KTP">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Kode SATUSEHAT Pasien (IHS Patient)</label>
                            <input type="text" name="kode_pasien" class="form-control form-control-sm font-monospace text-primary fw-bold" value="{{ old('kode_pasien', $encounter->kode_pasien ?? '') }}" placeholder="Pxxxxxxxxxx">
                            <small class="text-muted" style="font-size: 11px;">Awali dengan huruf 'P'. Kosongkan bila belum terdaftar.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Kode SATUSEHAT Dokter DPJP (IHS Practitioner) <span class="text-danger">*</span></label>
                            <input type="text" name="kode_dokter" class="form-control form-control-sm font-monospace text-primary fw-bold" value="{{ old('kode_dokter', $encounter->kode_dokter ?? '') }}" required placeholder="1000xxxxxxx">
                            <small class="text-muted" style="font-size: 11px;">Wajib terisi kode SATUSEHAT tenaga medis.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Nama Dokter DPJP</label>
                            <input type="text" name="dpjp_name" class="form-control form-control-sm" value="{{ old('dpjp_name', $encounter->dpjp_name ?? '') }}" placeholder="dr. Nama Dokter, Sp.X">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Nama Poliklinik / Ruangan Pelayanan</label>
                            <input type="text" name="unit_name" class="form-control form-control-sm" value="{{ old('unit_name', $encounter->unit_name ?? '') }}" placeholder="Contoh: Poli Penyakit Dalam">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">ID Lokasi SATUSEHAT Poliklinik</label>
                            <input type="text" name="idunitsatset" class="form-control form-control-sm font-monospace" value="{{ old('idunitsatset', $encounter->idunitsatset ?? '') }}" placeholder="UUID Location SATUSEHAT">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold text-secondary">Tipe Kunjungan</label>
                            <select name="tipe_kunjungan" class="form-select form-select-sm">
                                <option value="AMB" {{ old('tipe_kunjungan', $encounter->tipe_kunjungan ?? '') === 'AMB' ? 'selected' : '' }}>Rawat Jalan (AMB)</option>
                                <option value="IMP" {{ old('tipe_kunjungan', $encounter->tipe_kunjungan ?? '') === 'IMP' ? 'selected' : '' }}>Rawat Inap (IMP)</option>
                                <option value="EMER" {{ old('tipe_kunjungan', $encounter->tipe_kunjungan ?? '') === 'EMER' ? 'selected' : '' }}>Gawat Darurat (EMER)</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-secondary">Waktu Datang (Period Start) <span class="text-danger">*</span></label>
                            <input type="text" name="tgl_kunjung" class="form-control form-control-sm font-monospace" value="{{ old('tgl_kunjung', $encounter->tgl_kunjung ?? '') }}" required placeholder="YYYY-MM-DD HH:mm:ss">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-secondary">Waktu Mulai Dilayani</label>
                            <input type="text" name="tgl_dilayani" class="form-control form-control-sm font-monospace" value="{{ old('tgl_dilayani', $encounter->tgl_dilayani ?? '') }}" placeholder="YYYY-MM-DD HH:mm:ss">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-secondary">Waktu Selesai Dilayani</label>
                            <input type="text" name="tgl_selesai_dilayani" class="form-control form-control-sm font-monospace" value="{{ old('tgl_selesai_dilayani', $encounter->tgl_selesai_dilayani ?? '') }}" placeholder="YYYY-MM-DD HH:mm:ss">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-secondary">Waktu Pulang (Period End)</label>
                            <input type="text" name="tgl_pulang" class="form-control form-control-sm font-monospace" value="{{ old('tgl_pulang', $encounter->tgl_pulang ?? '') }}" placeholder="YYYY-MM-DD HH:mm:ss">
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-semibold text-secondary">Instruksi Pulang</label>
                            <input type="text" name="instruksi_pulang" class="form-control form-control-sm" value="{{ old('instruksi_pulang', $encounter->instruksi_pulang ?? '') }}" placeholder="Contoh: Kontrol ulang 1 minggu lagi, minum obat teratur">
                        </div>

                        <div class="col-12 mt-3 pt-2 border-top">
                            <label class="form-label small fw-bold text-dark">
                                Alasan Perbaikan Data Kunjungan <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="reason" class="form-control form-control-sm" required value="{{ old('reason', 'Pembaruan data kunjungan pasien & dokter') }}" placeholder="Contoh: Koreksi NIK pasien / kode dokter DPJP">
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="small text-muted">
                        <i class="bi bi-shield-check text-success me-1"></i> Perubahan langsung disimpan ke tabel staging <code>visit_encounter</code> di database.
                    </span>
                    <div class="d-flex gap-2">
                        <button type="submit" name="submit_type" value="save_only" class="btn btn-primary btn-sm fw-semibold">
                            <i class="bi bi-save me-1"></i> Simpan ke Database Staging
                        </button>
                        <button type="submit" name="submit_type" value="save_and_retry" class="btn btn-success btn-sm fw-semibold shadow-sm">
                            <i class="bi bi-send me-1"></i> Simpan & Kirim Ulang
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= 2. TAB DIAGNOSA (CONDITION) ================= -->
    <div class="tab-pane fade {{ $currentTab === 'diagnosis' ? 'show active' : '' }}" id="tab-pane-diagnosis" role="tabpanel">
        @php
            $diagnoses = $allStaging['diagnosis']['records'] ?? collect();
            $selectedDiag = ($currentTab === 'diagnosis' && $currentStagingId)
                ? $diagnoses->firstWhere('id', $currentStagingId)
                : $diagnoses->first();
            $diagId = $selectedDiag->id ?? null;
        @endphp

        <!-- Tabel Daftar Diagnosa Staging -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-clipboard-pulse me-2 text-primary"></i>Daftar Diagnosa Siap Kirim (Tabel <code>diagnosis</code> - {{ $diagnoses->count() }} Data)
                </h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 80px;">ID</th>
                            <th style="width: 130px;">Peringkat</th>
                            <th style="width: 150px;">Kode ICD-10</th>
                            <th>Nama Diagnosa</th>
                            <th class="text-center" style="width: 140px;">Pilihan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($diagnoses as $diag)
                            @php $isThisDiag = ($diagId == $diag->id); @endphp
                            <tr class="{{ $isThisDiag ? 'table-primary' : '' }}">
                                <td class="ps-3 font-monospace fw-bold">#{{ $diag->id }}</td>
                                <td>
                                    @if(($diag->rank ?? 1) == 1)
                                        <span class="badge bg-primary">Primer (1)</span>
                                    @elseif(($diag->rank ?? 1) == 2)
                                        <span class="badge bg-secondary">Sekunder (2)</span>
                                    @else
                                        <span class="badge bg-info text-dark">Komplikasi (3)</span>
                                    @endif
                                </td>
                                <td><span class="font-monospace fw-bold text-primary fs-6">{{ $diag->code ?? '-' }}</span></td>
                                <td class="fw-medium text-dark">{{ $diag->dx_name ?? '-' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('satusehat.failed.repair', ['id' => $log->id ?? $log->log_id, 'tab' => 'diagnosis', 'staging_id' => $diag->id, 'table' => 'diagnosis']) }}"
                                       class="btn btn-xs {{ $isThisDiag ? 'btn-primary' : 'btn-outline-primary' }} py-1 px-3">
                                        <i class="bi bi-pencil-square me-1"></i>{{ $isThisDiag ? 'Sedang Diedit' : 'Pilih & Edit' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada data diagnosa pada kunjungan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Form Edit Diagnosa -->
        @if($selectedDiag)
            <div class="card shadow-sm border-0 mb-4 border-start border-4 border-primary">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-pencil me-2 text-primary"></i>Edit Diagnosa: Record #{{ $selectedDiag->id }}
                    </h6>
                    <span class="badge bg-light text-secondary border font-monospace">Tabel: diagnosis</span>
                </div>
                <form method="POST" action="{{ route('satusehat.failed.save-repair', $log->id ?? $log->log_id) }}">
                    @csrf
                    <input type="hidden" name="staging_table" value="diagnosis">
                    <input type="hidden" name="staging_id" value="{{ $selectedDiag->id }}">
                    <input type="hidden" name="resource_type" value="Condition">
                    <input type="hidden" name="active_tab" value="diagnosis">
                    <input type="hidden" name="redirect_to" value="repair">

                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-secondary">Peringkat (Rank) <span class="text-danger">*</span></label>
                                <select name="rank" class="form-select form-select-sm" required>
                                    <option value="1" {{ ($selectedDiag->rank ?? 1) == 1 ? 'selected' : '' }}>1 (Primer)</option>
                                    <option value="2" {{ ($selectedDiag->rank ?? 1) == 2 ? 'selected' : '' }}>2 (Sekunder)</option>
                                    <option value="3" {{ ($selectedDiag->rank ?? 1) == 3 ? 'selected' : '' }}>3 (Komplikasi)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-secondary">Kode Diagnosa ICD-10 <span class="text-danger">*</span></label>
                                <input type="text" name="code" class="form-control form-control-sm font-monospace fw-bold text-primary fs-6" value="{{ old('code', $selectedDiag->code ?? '') }}" required placeholder="Contoh: I10, A09, E11.9">
                                <small class="text-muted" style="font-size: 11px;">Format kode standar ICD-10 WHO.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Nama Diagnosa <span class="text-danger">*</span></label>
                                <input type="text" name="dx_name" class="form-control form-control-sm" value="{{ old('dx_name', $selectedDiag->dx_name ?? '') }}" required placeholder="Deskripsi diagnosa">
                            </div>
                            <div class="col-12 mt-3 pt-2 border-top">
                                <label class="form-label small fw-bold text-dark">Alasan Perbaikan <span class="text-danger">*</span></label>
                                <input type="text" name="reason" class="form-control form-control-sm" required value="{{ old('reason', 'Koreksi kode diagnosa ICD-10') }}">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="small text-muted">
                            <i class="bi bi-shield-check text-success me-1"></i> Data langsung diperbarui di tabel <code>diagnosis</code> record #{{ $selectedDiag->id }}.
                        </span>
                        <div class="d-flex gap-2">
                            <button type="submit" name="submit_type" value="save_only" class="btn btn-primary btn-sm fw-semibold">
                                <i class="bi bi-save me-1"></i> Simpan ke Database Staging
                            </button>
                            <button type="submit" name="submit_type" value="save_and_retry" class="btn btn-success btn-sm fw-semibold shadow-sm">
                                <i class="bi bi-send me-1"></i> Simpan & Kirim Ulang
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <!-- ================= 3. TAB FARMASI (MEDICATION) ================= -->
    <div class="tab-pane fade {{ $currentTab === 'medication' ? 'show active' : '' }}" id="tab-pane-medication" role="tabpanel">
        @php
            $medications = $allStaging['medication']['records'] ?? collect();
            $selectedMed = ($currentTab === 'medication' && $currentStagingId)
                ? $medications->firstWhere('id', $currentStagingId)
                : $medications->first();
            $medId = $selectedMed->id ?? null;
        @endphp

        <!-- Tabel Daftar Farmasi Staging -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-capsule me-2 text-primary"></i>Daftar Obat Farmasi Siap Kirim (Tabel <code>visit_farmasi</code> - {{ $medications->count() }} Data)
                </h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 80px;">ID</th>
                            <th style="width: 140px;">Kode KFA</th>
                            <th>Nama Obat / Master KFA</th>
                            <th style="width: 100px;">Jumlah</th>
                            <th style="width: 140px;">Dosis</th>
                            <th style="width: 110px;">Jenis</th>
                            <th>Dokter Peresep</th>
                            <th class="text-center" style="width: 140px;">Pilihan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($medications as $med)
                            @php
                                $isThisMed = ($medId == $med->id);
                                $kfaName = $med->ms_kfa->nama_kfa ?? ($med->item_id_simrs ?? 'Obat #' . $med->id);
                            @endphp
                            <tr class="{{ $isThisMed ? 'table-primary' : '' }}">
                                <td class="ps-3 font-monospace fw-bold">#{{ $med->id }}</td>
                                <td><span class="font-monospace fw-bold text-primary">{{ $med->item_id_kfa ?? '-' }}</span></td>
                                <td class="fw-medium text-dark">{{ $kfaName }}</td>
                                <td>{{ $med->sale_qty ?? '-' }}</td>
                                <td>{{ $med->dosis ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $med->racikan ? 'bg-warning text-dark' : 'bg-info text-dark' }}">
                                        {{ $med->racikan ? 'Racikan' : 'Non-Racikan' }}
                                    </span>
                                </td>
                                <td>{{ $med->dokter_peresep ?? '-' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('satusehat.failed.repair', ['id' => $log->id ?? $log->log_id, 'tab' => 'medication', 'staging_id' => $med->id, 'table' => 'visit_farmasi']) }}"
                                       class="btn btn-xs {{ $isThisMed ? 'btn-primary' : 'btn-outline-primary' }} py-1 px-3">
                                        <i class="bi bi-pencil-square me-1"></i>{{ $isThisMed ? 'Sedang Diedit' : 'Pilih & Edit' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Belum ada data obat farmasi pada kunjungan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Form Edit Farmasi -->
        @if($selectedMed)
            <div class="card shadow-sm border-0 mb-4 border-start border-4 border-primary">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-pencil me-2 text-primary"></i>Edit Resep Obat: Record #{{ $selectedMed->id }}
                    </h6>
                    <span class="badge bg-light text-secondary border font-monospace">Tabel: visit_farmasi</span>
                </div>
                <form method="POST" action="{{ route('satusehat.failed.save-repair', $log->id ?? $log->log_id) }}">
                    @csrf
                    <input type="hidden" name="staging_table" value="visit_farmasi">
                    <input type="hidden" name="staging_id" value="{{ $selectedMed->id }}">
                    <input type="hidden" name="resource_type" value="Medication">
                    <input type="hidden" name="active_tab" value="medication">
                    <input type="hidden" name="redirect_to" value="repair">

                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Kode KFA Obat (SATUSEHAT) <span class="text-danger">*</span></label>
                                <input type="text" name="item_id_kfa" class="form-control form-control-sm font-monospace fw-bold text-primary" value="{{ old('item_id_kfa', $selectedMed->item_id_kfa ?? '') }}" required placeholder="Contoh: 93000123">
                                <small class="text-muted" style="font-size: 11px;">Kode KFA resmi Kementerian Kesehatan.</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small fw-semibold text-secondary">Jumlah (Qty) <span class="text-danger">*</span></label>
                                <input type="number" step="any" name="sale_qty" class="form-control form-control-sm" value="{{ old('sale_qty', $selectedMed->sale_qty ?? '1') }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-secondary">Dosis / Aturan Pakai</label>
                                <input type="text" name="dosis" class="form-control form-control-sm" value="{{ old('dosis', $selectedMed->dosis ?? '') }}" placeholder="Contoh: 3x1 sehari sesudah makan">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold text-secondary">Bentuk Obat</label>
                                <select name="racikan" class="form-select form-select-sm">
                                    <option value="0" {{ empty($selectedMed->racikan) ? 'selected' : '' }}>Non-Racikan (Tunggal)</option>
                                    <option value="1" {{ !empty($selectedMed->racikan) ? 'selected' : '' }}>Racikan (Compound)</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Kode SATUSEHAT Dokter Peresep</label>
                                <input type="text" name="kode_dokter" class="form-control form-control-sm font-monospace" value="{{ old('kode_dokter', $selectedMed->kode_dokter ?? '') }}" placeholder="1000xxxxxxx">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Nama Dokter Peresep</label>
                                <input type="text" name="dokter_peresep" class="form-control form-control-sm" value="{{ old('dokter_peresep', $selectedMed->dokter_peresep ?? '') }}" placeholder="dr. Nama Dokter">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Unit Farmasi</label>
                                <input type="text" name="unit_name" class="form-control form-control-sm" value="{{ old('unit_name', $selectedMed->unit_name ?? '') }}" placeholder="Depo Rawat Jalan">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Waktu Resep Dibuat</label>
                                <input type="text" name="waktu_resep_dibuat" class="form-control form-control-sm font-monospace" value="{{ old('waktu_resep_dibuat', $selectedMed->waktu_resep_dibuat ?? '') }}" placeholder="YYYY-MM-DD HH:mm:ss">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Waktu Obat Diserahkan</label>
                                <input type="text" name="waktu_diserahkan" class="form-control form-control-sm font-monospace" value="{{ old('waktu_diserahkan', $selectedMed->waktu_diserahkan ?? '') }}" placeholder="YYYY-MM-DD HH:mm:ss">
                            </div>

                            <div class="col-12 mt-3 pt-2 border-top">
                                <label class="form-label small fw-bold text-dark">Alasan Perbaikan <span class="text-danger">*</span></label>
                                <input type="text" name="reason" class="form-control form-control-sm" required value="{{ old('reason', 'Koreksi kode KFA obat farmasi') }}">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="small text-muted">
                            <i class="bi bi-shield-check text-success me-1"></i> Data langsung diperbarui di tabel <code>visit_farmasi</code> record #{{ $selectedMed->id }}.
                        </span>
                        <div class="d-flex gap-2">
                            <button type="submit" name="submit_type" value="save_only" class="btn btn-primary btn-sm fw-semibold">
                                <i class="bi bi-save me-1"></i> Simpan ke Database Staging
                            </button>
                            <button type="submit" name="submit_type" value="save_and_retry" class="btn btn-success btn-sm fw-semibold shadow-sm">
                                <i class="bi bi-send me-1"></i> Simpan & Kirim Ulang
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <!-- ================= 4. TAB TINDAKAN (PROCEDURE) ================= -->
    <div class="tab-pane fade {{ $currentTab === 'procedure' ? 'show active' : '' }}" id="tab-pane-procedure" role="tabpanel">
        @php
            $procedures = $allStaging['procedure']['records'] ?? collect();
            $selectedProc = ($currentTab === 'procedure' && $currentStagingId)
                ? $procedures->firstWhere('id', $currentStagingId)
                : $procedures->first();
            $procId = $selectedProc->id ?? null;
        @endphp

        <!-- Tabel Daftar Tindakan ICD-9 -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-bandaid me-2 text-primary"></i>Daftar Tindakan Medis Siap Kirim (Tabel <code>visit_icd9</code> - {{ $procedures->count() }} Data)
                </h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 80px;">ID</th>
                            <th style="width: 150px;">Kode ICD-9-CM</th>
                            <th>Nama Tindakan Medis</th>
                            <th class="text-center" style="width: 140px;">Pilihan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($procedures as $proc)
                            @php $isThisProc = ($procId == $proc->id); @endphp
                            <tr class="{{ $isThisProc ? 'table-primary' : '' }}">
                                <td class="ps-3 font-monospace fw-bold">#{{ $proc->id }}</td>
                                <td><span class="font-monospace fw-bold text-primary fs-6">{{ $proc->icd_code ?? '-' }}</span></td>
                                <td class="fw-medium text-dark">{{ $proc->icd_name ?? '-' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('satusehat.failed.repair', ['id' => $log->id ?? $log->log_id, 'tab' => 'procedure', 'staging_id' => $proc->id, 'table' => 'visit_icd9']) }}"
                                       class="btn btn-xs {{ $isThisProc ? 'btn-primary' : 'btn-outline-primary' }} py-1 px-3">
                                        <i class="bi bi-pencil-square me-1"></i>{{ $isThisProc ? 'Sedang Diedit' : 'Pilih & Edit' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Belum ada data tindakan medis pada kunjungan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Form Edit Tindakan -->
        @if($selectedProc)
            <div class="card shadow-sm border-0 mb-4 border-start border-4 border-primary">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-pencil me-2 text-primary"></i>Edit Tindakan Medis: Record #{{ $selectedProc->id }}
                    </h6>
                    <span class="badge bg-light text-secondary border font-monospace">Tabel: visit_icd9</span>
                </div>
                <form method="POST" action="{{ route('satusehat.failed.save-repair', $log->id ?? $log->log_id) }}">
                    @csrf
                    <input type="hidden" name="staging_table" value="visit_icd9">
                    <input type="hidden" name="staging_id" value="{{ $selectedProc->id }}">
                    <input type="hidden" name="resource_type" value="Procedure">
                    <input type="hidden" name="active_tab" value="procedure">
                    <input type="hidden" name="redirect_to" value="repair">

                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Kode ICD-9-CM <span class="text-danger">*</span></label>
                                <input type="text" name="icd_code" class="form-control form-control-sm font-monospace fw-bold text-primary fs-6" value="{{ old('icd_code', $selectedProc->icd_code ?? '') }}" required placeholder="Contoh: 89.07, 88.76">
                                <small class="text-muted" style="font-size: 11px;">Format kode standar ICD-9-CM.</small>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold text-secondary">Nama Tindakan Medis <span class="text-danger">*</span></label>
                                <input type="text" name="icd_name" class="form-control form-control-sm" value="{{ old('icd_name', $selectedProc->icd_name ?? '') }}" required placeholder="Contoh: General medical examination">
                            </div>
                            <div class="col-12 mt-3 pt-2 border-top">
                                <label class="form-label small fw-bold text-dark">Alasan Perbaikan <span class="text-danger">*</span></label>
                                <input type="text" name="reason" class="form-control form-control-sm" required value="{{ old('reason', 'Koreksi kode tindakan ICD-9-CM') }}">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="small text-muted">
                            <i class="bi bi-shield-check text-success me-1"></i> Data langsung diperbarui di tabel <code>visit_icd9</code> record #{{ $selectedProc->id }}.
                        </span>
                        <div class="d-flex gap-2">
                            <button type="submit" name="submit_type" value="save_only" class="btn btn-primary btn-sm fw-semibold">
                                <i class="bi bi-save me-1"></i> Simpan ke Database Staging
                            </button>
                            <button type="submit" name="submit_type" value="save_and_retry" class="btn btn-success btn-sm fw-semibold shadow-sm">
                                <i class="bi bi-send me-1"></i> Simpan & Kirim Ulang
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <!-- ================= 5. TAB OBSERVASI (OBSERVATION) ================= -->
    <div class="tab-pane fade {{ $currentTab === 'observation' ? 'show active' : '' }}" id="tab-pane-observation" role="tabpanel">
        @php
            $observations = $allStaging['observation']['records'] ?? collect();
            $selectedObs = ($currentTab === 'observation' && $currentStagingId)
                ? $observations->firstWhere('id', $currentStagingId)
                : $observations->first();
            $obsId = $selectedObs->id ?? null;
        @endphp

        <!-- Tabel Daftar Observasi Tanda Vital -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-heart-pulse me-2 text-primary"></i>Daftar Observasi & Tanda Vital (Tabel <code>observation</code> - {{ $observations->count() }} Data)
                </h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 80px;">ID</th>
                            <th>Nama Observasi / Parameter</th>
                            <th style="width: 200px;">Hasil / Nilai</th>
                            <th class="text-center" style="width: 140px;">Pilihan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($observations as $obs)
                            @php $isThisObs = ($obsId == $obs->id); @endphp
                            <tr class="{{ $isThisObs ? 'table-primary' : '' }}">
                                <td class="ps-3 font-monospace fw-bold">#{{ $obs->id }}</td>
                                <td class="fw-medium text-dark">{{ $obs->observation_name ?? '-' }}</td>
                                <td><span class="font-monospace fw-bold text-primary fs-6">{{ $obs->result ?? '-' }}</span></td>
                                <td class="text-center">
                                    <a href="{{ route('satusehat.failed.repair', ['id' => $log->id ?? $log->log_id, 'tab' => 'observation', 'staging_id' => $obs->id, 'table' => 'observation']) }}"
                                       class="btn btn-xs {{ $isThisObs ? 'btn-primary' : 'btn-outline-primary' }} py-1 px-3">
                                        <i class="bi bi-pencil-square me-1"></i>{{ $isThisObs ? 'Sedang Diedit' : 'Pilih & Edit' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Belum ada data observasi tanda vital pada kunjungan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Form Edit Observasi -->
        @if($selectedObs)
            <div class="card shadow-sm border-0 mb-4 border-start border-4 border-primary">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-pencil me-2 text-primary"></i>Edit Observasi: Record #{{ $selectedObs->id }}
                    </h6>
                    <span class="badge bg-light text-secondary border font-monospace">Tabel: observation</span>
                </div>
                <form method="POST" action="{{ route('satusehat.failed.save-repair', $log->id ?? $log->log_id) }}">
                    @csrf
                    <input type="hidden" name="staging_table" value="observation">
                    <input type="hidden" name="staging_id" value="{{ $selectedObs->id }}">
                    <input type="hidden" name="resource_type" value="Observation">
                    <input type="hidden" name="active_tab" value="observation">
                    <input type="hidden" name="redirect_to" value="repair">

                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Nama Observasi / Tanda Vital <span class="text-danger">*</span></label>
                                <input type="text" name="observation_name" class="form-control form-control-sm fw-bold" value="{{ old('observation_name', $selectedObs->observation_name ?? '') }}" required placeholder="Contoh: Sistolik, Diastolik, Nadi">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Hasil / Nilai Pengukuran <span class="text-danger">*</span></label>
                                <input type="text" name="result" class="form-control form-control-sm font-monospace fw-bold text-primary fs-6" value="{{ old('result', $selectedObs->result ?? '') }}" required placeholder="Contoh: 120, 80, 36.5">
                            </div>
                            <div class="col-12 mt-3 pt-2 border-top">
                                <label class="form-label small fw-bold text-dark">Alasan Perbaikan <span class="text-danger">*</span></label>
                                <input type="text" name="reason" class="form-control form-control-sm" required value="{{ old('reason', 'Koreksi nilai tanda vital') }}">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="small text-muted">
                            <i class="bi bi-shield-check text-success me-1"></i> Data langsung diperbarui di tabel <code>observation</code> record #{{ $selectedObs->id }}.
                        </span>
                        <div class="d-flex gap-2">
                            <button type="submit" name="submit_type" value="save_only" class="btn btn-primary btn-sm fw-semibold">
                                <i class="bi bi-save me-1"></i> Simpan ke Database Staging
                            </button>
                            <button type="submit" name="submit_type" value="save_and_retry" class="btn btn-success btn-sm fw-semibold shadow-sm">
                                <i class="bi bi-send me-1"></i> Simpan & Kirim Ulang
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <!-- ================= 6. TAB LABORATORIUM (LAB) ================= -->
    <div class="tab-pane fade {{ $currentTab === 'lab' ? 'show active' : '' }}" id="tab-pane-lab" role="tabpanel">
        @php
            $labs = $allStaging['lab']['records'] ?? collect();
            $selectedLab = ($currentTab === 'lab' && $currentStagingId)
                ? $labs->firstWhere('id', $currentStagingId)
                : $labs->first();
            $labId = $selectedLab->id ?? null;
        @endphp

        <!-- Tabel Daftar Laboratorium -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-virus me-2 text-primary"></i>Daftar Pemeriksaan Laboratorium (Tabel <code>visit_lab</code> - {{ $labs->count() }} Data)
                </h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 80px;">ID</th>
                            <th>Nama Pemeriksaan</th>
                            <th style="width: 150px;">Hasil Lab</th>
                            <th>Dokter Lab</th>
                            <th style="width: 110px;">Status</th>
                            <th class="text-center" style="width: 140px;">Pilihan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($labs as $lab)
                            @php $isThisLab = ($labId == $lab->id); @endphp
                            <tr class="{{ $isThisLab ? 'table-primary' : '' }}">
                                <td class="ps-3 font-monospace fw-bold">#{{ $lab->id }}</td>
                                <td class="fw-medium text-dark">{{ $lab->nama_pemeriksaan ?? '-' }}</td>
                                <td><span class="font-monospace fw-bold text-primary">{{ $lab->hasil_lab ?? '-' }}</span></td>
                                <td>{{ $lab->dokter_lab ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ strtolower($lab->status_normal ?? '') === 'abnormal' ? 'bg-danger' : 'bg-success' }}">
                                        {{ $lab->status_normal ?? 'Normal' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('satusehat.failed.repair', ['id' => $log->id ?? $log->log_id, 'tab' => 'lab', 'staging_id' => $lab->id, 'table' => 'visit_lab']) }}"
                                       class="btn btn-xs {{ $isThisLab ? 'btn-primary' : 'btn-outline-primary' }} py-1 px-3">
                                        <i class="bi bi-pencil-square me-1"></i>{{ $isThisLab ? 'Sedang Diedit' : 'Pilih & Edit' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Belum ada data laboratorium pada kunjungan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Form Edit Laboratorium -->
        @if($selectedLab)
            <div class="card shadow-sm border-0 mb-4 border-start border-4 border-primary">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-pencil me-2 text-primary"></i>Edit Pemeriksaan Lab: Record #{{ $selectedLab->id }}
                    </h6>
                    <span class="badge bg-light text-secondary border font-monospace">Tabel: visit_lab</span>
                </div>
                <form method="POST" action="{{ route('satusehat.failed.save-repair', $log->id ?? $log->log_id) }}">
                    @csrf
                    <input type="hidden" name="staging_table" value="visit_lab">
                    <input type="hidden" name="staging_id" value="{{ $selectedLab->id }}">
                    <input type="hidden" name="resource_type" value="ServiceRequest">
                    <input type="hidden" name="active_tab" value="lab">
                    <input type="hidden" name="redirect_to" value="repair">

                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Nama Pemeriksaan Lab <span class="text-danger">*</span></label>
                                <input type="text" name="nama_pemeriksaan" class="form-control form-control-sm fw-bold" value="{{ old('nama_pemeriksaan', $selectedLab->nama_pemeriksaan ?? '') }}" required placeholder="Contoh: Darah Rutin, Hemoglobin">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Hasil Pemeriksaan Lab <span class="text-danger">*</span></label>
                                <input type="text" name="hasil_lab" class="form-control form-control-sm font-monospace fw-bold text-primary" value="{{ old('hasil_lab', $selectedLab->hasil_lab ?? '') }}" required placeholder="Contoh: 14.2 g/dL">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Dokter Pemeriksa Lab</label>
                                <input type="text" name="dokter_lab" class="form-control form-control-sm" value="{{ old('dokter_lab', $selectedLab->dokter_lab ?? '') }}" placeholder="dr. Nama Dokter Sp.PK">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Kode SATUSEHAT Dokter Lab</label>
                                <input type="text" name="kode_dokter_lab" class="form-control form-control-sm font-monospace" value="{{ old('kode_dokter_lab', $selectedLab->kode_dokter_lab ?? '') }}" placeholder="1000xxxxxxx">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Status Nilai Normal</label>
                                <select name="status_normal" class="form-select form-select-sm">
                                    <option value="Normal" {{ (strtolower($selectedLab->status_normal ?? '') !== 'abnormal') ? 'selected' : '' }}>Normal</option>
                                    <option value="Abnormal" {{ (strtolower($selectedLab->status_normal ?? '') === 'abnormal') ? 'selected' : '' }}>Abnormal</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Dokter Pengirim</label>
                                <input type="text" name="dokter_pengirim" class="form-control form-control-sm" value="{{ old('dokter_pengirim', $selectedLab->dokter_pengirim ?? '') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Kode SATUSEHAT Dokter Pengirim</label>
                                <input type="text" name="kode_pengirim" class="form-control form-control-sm font-monospace" value="{{ old('kode_pengirim', $selectedLab->kode_pengirim ?? '') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold text-secondary">Tanggal Periksa</label>
                                <input type="text" name="tgl_periksa" class="form-control form-control-sm font-monospace" value="{{ old('tgl_periksa', $selectedLab->tgl_periksa ?? '') }}" placeholder="YYYY-MM-DD HH:mm:ss">
                            </div>

                            <div class="col-12 mt-3 pt-2 border-top">
                                <label class="form-label small fw-bold text-dark">Alasan Perbaikan <span class="text-danger">*</span></label>
                                <input type="text" name="reason" class="form-control form-control-sm" required value="{{ old('reason', 'Koreksi hasil pemeriksaan laboratorium') }}">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="small text-muted">
                            <i class="bi bi-shield-check text-success me-1"></i> Data langsung diperbarui di tabel <code>visit_lab</code> record #{{ $selectedLab->id }}.
                        </span>
                        <div class="d-flex gap-2">
                            <button type="submit" name="submit_type" value="save_only" class="btn btn-primary btn-sm fw-semibold">
                                <i class="bi bi-save me-1"></i> Simpan ke Database Staging
                            </button>
                            <button type="submit" name="submit_type" value="save_and_retry" class="btn btn-success btn-sm fw-semibold shadow-sm">
                                <i class="bi bi-send me-1"></i> Simpan & Kirim Ulang
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <!-- ================= 7. TAB RENCANA RAWAT (CAREPLAN) ================= -->
    <div class="tab-pane fade {{ $currentTab === 'careplan' ? 'show active' : '' }}" id="tab-pane-careplan" role="tabpanel">
        @php
            $careplans = $allStaging['careplan']['records'] ?? collect();
            $selectedCp = ($currentTab === 'careplan' && $currentStagingId)
                ? $careplans->firstWhere('id', $currentStagingId)
                : $careplans->first();
            $cpId = $selectedCp->id ?? null;
        @endphp

        <!-- Tabel Rencana Rawat -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-journal-medical me-2 text-primary"></i>Daftar Rencana Perawatan (Tabel <code>visit_careplane</code> - {{ $careplans->count() }} Data)
                </h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 80px;">ID</th>
                            <th style="width: 180px;">Kondisi Pulang</th>
                            <th style="width: 200px;">Alasan Pulang</th>
                            <th>Keterangan / Rencana</th>
                            <th class="text-center" style="width: 140px;">Pilihan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($careplans as $cp)
                            @php $isThisCp = ($cpId == $cp->id); @endphp
                            <tr class="{{ $isThisCp ? 'table-primary' : '' }}">
                                <td class="ps-3 font-monospace fw-bold">#{{ $cp->id }}</td>
                                <td class="fw-medium text-dark">{{ $cp->kondisi_pulang ?? '-' }}</td>
                                <td>{{ $cp->alasan_pulang ?? '-' }}</td>
                                <td>{{ $cp->keterangan ?? '-' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('satusehat.failed.repair', ['id' => $log->id ?? $log->log_id, 'tab' => 'careplan', 'staging_id' => $cp->id, 'table' => 'visit_careplane']) }}"
                                       class="btn btn-xs {{ $isThisCp ? 'btn-primary' : 'btn-outline-primary' }} py-1 px-3">
                                        <i class="bi bi-pencil-square me-1"></i>{{ $isThisCp ? 'Sedang Diedit' : 'Pilih & Edit' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada data rencana perawatan pada kunjungan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Form Edit CarePlan -->
        @if($selectedCp)
            <div class="card shadow-sm border-0 mb-4 border-start border-4 border-primary">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-pencil me-2 text-primary"></i>Edit Rencana Rawat: Record #{{ $selectedCp->id }}
                    </h6>
                    <span class="badge bg-light text-secondary border font-monospace">Tabel: visit_careplane</span>
                </div>
                <form method="POST" action="{{ route('satusehat.failed.save-repair', $log->id ?? $log->log_id) }}">
                    @csrf
                    <input type="hidden" name="staging_table" value="visit_careplane">
                    <input type="hidden" name="staging_id" value="{{ $selectedCp->id }}">
                    <input type="hidden" name="resource_type" value="CarePlan">
                    <input type="hidden" name="active_tab" value="careplan">
                    <input type="hidden" name="redirect_to" value="repair">

                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Kondisi Pulang</label>
                                <input type="text" name="kondisi_pulang" class="form-control form-control-sm" value="{{ old('kondisi_pulang', $selectedCp->kondisi_pulang ?? '') }}" placeholder="Contoh: Membaik, Sembuh">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Alasan Pulang</label>
                                <input type="text" name="alasan_pulang" class="form-control form-control-sm" value="{{ old('alasan_pulang', $selectedCp->alasan_pulang ?? '') }}" placeholder="Contoh: Atas Persetujuan Dokter">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-secondary">Keterangan / Rencana Tindak Lanjut</label>
                                <textarea name="keterangan" class="form-control form-control-sm" rows="3" placeholder="Contoh: Edukasi pola makan dan istirahat, kontrol poli 1 minggu">{{ old('keterangan', $selectedCp->keterangan ?? '') }}</textarea>
                            </div>
                            <div class="col-12 mt-3 pt-2 border-top">
                                <label class="form-label small fw-bold text-dark">Alasan Perbaikan <span class="text-danger">*</span></label>
                                <input type="text" name="reason" class="form-control form-control-sm" required value="{{ old('reason', 'Koreksi rencana rawat / careplan') }}">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="small text-muted">
                            <i class="bi bi-shield-check text-success me-1"></i> Data langsung diperbarui di tabel <code>visit_careplane</code> record #{{ $selectedCp->id }}.
                        </span>
                        <div class="d-flex gap-2">
                            <button type="submit" name="submit_type" value="save_only" class="btn btn-primary btn-sm fw-semibold">
                                <i class="bi bi-save me-1"></i> Simpan ke Database Staging
                            </button>
                            <button type="submit" name="submit_type" value="save_and_retry" class="btn btn-success btn-sm fw-semibold shadow-sm">
                                <i class="bi bi-send me-1"></i> Simpan & Kirim Ulang
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <!-- ================= 8. TAB RADIOLOGI ================= -->
    <div class="tab-pane fade {{ $currentTab === 'radiologi' ? 'show active' : '' }}" id="tab-pane-radiologi" role="tabpanel">
        @php
            $radiologies = $allStaging['radiologi']['records'] ?? collect();
            $selectedRad = ($currentTab === 'radiologi' && $currentStagingId)
                ? $radiologies->firstWhere('id', $currentStagingId)
                : $radiologies->first();
            $radId = $selectedRad->id ?? null;
        @endphp

        <!-- Tabel Radiologi -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-camera me-2 text-primary"></i>Daftar Pemeriksaan Radiologi (Tabel <code>visit_radiologi</code> - {{ $radiologies->count() }} Data)
                </h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 80px;">ID</th>
                            <th>Nama Pemeriksaan</th>
                            <th>Dokter Radiologi</th>
                            <th>Hasil Pemeriksaan</th>
                            <th class="text-center" style="width: 140px;">Pilihan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($radiologies as $rad)
                            @php $isThisRad = ($radId == $rad->id); @endphp
                            <tr class="{{ $isThisRad ? 'table-primary' : '' }}">
                                <td class="ps-3 font-monospace fw-bold">#{{ $rad->id }}</td>
                                <td class="fw-medium text-dark">{{ $rad->nama_pemeriksaan ?? '-' }}</td>
                                <td>{{ $rad->dokter_radiologi ?? '-' }}</td>
                                <td>{{ Str::limit($rad->hasil_pemeriksaan ?? '-', 60) }}</td>
                                <td class="text-center">
                                    <a href="{{ route('satusehat.failed.repair', ['id' => $log->id ?? $log->log_id, 'tab' => 'radiologi', 'staging_id' => $rad->id, 'table' => 'visit_radiologi']) }}"
                                       class="btn btn-xs {{ $isThisRad ? 'btn-primary' : 'btn-outline-primary' }} py-1 px-3">
                                        <i class="bi bi-pencil-square me-1"></i>{{ $isThisRad ? 'Sedang Diedit' : 'Pilih & Edit' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada data pemeriksaan radiologi pada kunjungan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($selectedRad)
            <div class="card shadow-sm border-0 mb-4 border-start border-4 border-primary">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-pencil me-2 text-primary"></i>Edit Pemeriksaan Radiologi: Record #{{ $selectedRad->id }}
                    </h6>
                    <span class="badge bg-light text-secondary border font-monospace">Tabel: visit_radiologi</span>
                </div>
                <form method="POST" action="{{ route('satusehat.failed.save-repair', $log->id ?? $log->log_id) }}">
                    @csrf
                    <input type="hidden" name="staging_table" value="visit_radiologi">
                    <input type="hidden" name="staging_id" value="{{ $selectedRad->id }}">
                    <input type="hidden" name="resource_type" value="Radiologi">
                    <input type="hidden" name="active_tab" value="radiologi">
                    <input type="hidden" name="redirect_to" value="repair">

                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Nama Pemeriksaan Radiologi <span class="text-danger">*</span></label>
                                <input type="text" name="nama_pemeriksaan" class="form-control form-control-sm fw-bold" value="{{ old('nama_pemeriksaan', $selectedRad->nama_pemeriksaan ?? '') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Kode Map RAD LOINC</label>
                                <input type="text" name="code_map_rad" class="form-control form-control-sm font-monospace" value="{{ old('code_map_rad', $selectedRad->code_map_rad ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Dokter Radiologi</label>
                                <input type="text" name="dokter_radiologi" class="form-control form-control-sm" value="{{ old('dokter_radiologi', $selectedRad->dokter_radiologi ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Kode SATUSEHAT Dokter Radiologi</label>
                                <input type="text" name="kode_dokter_radiologi" class="form-control form-control-sm font-monospace" value="{{ old('kode_dokter_radiologi', $selectedRad->kode_dokter_radiologi ?? '') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-secondary">Hasil Pemeriksaan Radiologi</label>
                                <textarea name="hasil_pemeriksaan" class="form-control form-control-sm" rows="3">{{ old('hasil_pemeriksaan', $selectedRad->hasil_pemeriksaan ?? '') }}</textarea>
                            </div>
                            <div class="col-12 mt-3 pt-2 border-top">
                                <label class="form-label small fw-bold text-dark">Alasan Perbaikan <span class="text-danger">*</span></label>
                                <input type="text" name="reason" class="form-control form-control-sm" required value="{{ old('reason', 'Koreksi data pemeriksaan radiologi') }}">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="small text-muted">
                            <i class="bi bi-shield-check text-success me-1"></i> Data langsung diperbarui di tabel <code>visit_radiologi</code> record #{{ $selectedRad->id }}.
                        </span>
                        <div class="d-flex gap-2">
                            <button type="submit" name="submit_type" value="save_only" class="btn btn-primary btn-sm fw-semibold">
                                <i class="bi bi-save me-1"></i> Simpan ke Database Staging
                            </button>
                            <button type="submit" name="submit_type" value="save_and_retry" class="btn btn-success btn-sm fw-semibold shadow-sm">
                                <i class="bi bi-send me-1"></i> Simpan & Kirim Ulang
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <!-- ================= 9. TAB KUESIONER ================= -->
    <div class="tab-pane fade {{ $currentTab === 'quisioner' ? 'show active' : '' }}" id="tab-pane-quisioner" role="tabpanel">
        @php
            $quisioners = $allStaging['quisioner']['records'] ?? collect();
            $selectedQs = ($currentTab === 'quisioner' && $currentStagingId)
                ? $quisioners->firstWhere('id_quisioner', $currentStagingId)
                : $quisioners->first();
            $qsId = $selectedQs->id_quisioner ?? null;
        @endphp

        <!-- Tabel Kuesioner -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-ui-checks me-2 text-primary"></i>Daftar Kuesioner Telaah Resep (Tabel <code>visit_quisioner</code> - {{ $quisioners->count() }} Data)
                </h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 80px;">ID</th>
                            <th>Nama Apoteker</th>
                            <th>Kode Apoteker SATUSEHAT</th>
                            <th>Tanggal Kuesioner</th>
                            <th class="text-center" style="width: 140px;">Pilihan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quisioners as $qs)
                            @php $isThisQs = ($qsId == $qs->id_quisioner); @endphp
                            <tr class="{{ $isThisQs ? 'table-primary' : '' }}">
                                <td class="ps-3 font-monospace fw-bold">#{{ $qs->id_quisioner }}</td>
                                <td class="fw-medium text-dark">{{ $qs->nama_apoteker ?? '-' }}</td>
                                <td><span class="font-monospace text-primary fw-bold">{{ $qs->kode_apoteker ?? '-' }}</span></td>
                                <td>{{ $qs->tgl_quisioner ?? '-' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('satusehat.failed.repair', ['id' => $log->id ?? $log->log_id, 'tab' => 'quisioner', 'staging_id' => $qs->id_quisioner, 'table' => 'visit_quisioner']) }}"
                                       class="btn btn-xs {{ $isThisQs ? 'btn-primary' : 'btn-outline-primary' }} py-1 px-3">
                                        <i class="bi bi-pencil-square me-1"></i>{{ $isThisQs ? 'Sedang Diedit' : 'Pilih & Edit' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Belum ada data kuesioner telaah resep pada kunjungan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($selectedQs)
            <div class="card shadow-sm border-0 mb-4 border-start border-4 border-primary">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-pencil me-2 text-primary"></i>Edit Kuesioner Farmasi: Record #{{ $selectedQs->id_quisioner }}
                    </h6>
                    <span class="badge bg-light text-secondary border font-monospace">Tabel: visit_quisioner</span>
                </div>
                <form method="POST" action="{{ route('satusehat.failed.save-repair', $log->id ?? $log->log_id) }}">
                    @csrf
                    <input type="hidden" name="staging_table" value="visit_quisioner">
                    <input type="hidden" name="staging_id" value="{{ $selectedQs->id_quisioner }}">
                    <input type="hidden" name="resource_type" value="QuestionnaireResponse">
                    <input type="hidden" name="active_tab" value="quisioner">
                    <input type="hidden" name="redirect_to" value="repair">

                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Nama Apoteker <span class="text-danger">*</span></label>
                                <input type="text" name="nama_apoteker" class="form-control form-control-sm fw-bold" value="{{ old('nama_apoteker', $selectedQs->nama_apoteker ?? '') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Kode SATUSEHAT Apoteker (IHS)</label>
                                <input type="text" name="kode_apoteker" class="form-control form-control-sm font-monospace text-primary fw-bold" value="{{ old('kode_apoteker', $selectedQs->kode_apoteker ?? '') }}" placeholder="1000xxxxxxx">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Tanggal Kuesioner</label>
                                <input type="text" name="tgl_quisioner" class="form-control form-control-sm font-monospace" value="{{ old('tgl_quisioner', $selectedQs->tgl_quisioner ?? '') }}" placeholder="YYYY-MM-DD HH:mm:ss">
                            </div>
                            <div class="col-12 mt-3 pt-2 border-top">
                                <label class="form-label small fw-bold text-dark">Alasan Perbaikan <span class="text-danger">*</span></label>
                                <input type="text" name="reason" class="form-control form-control-sm" required value="{{ old('reason', 'Koreksi data apoteker kuesioner') }}">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="small text-muted">
                            <i class="bi bi-shield-check text-success me-1"></i> Data langsung diperbarui di tabel <code>visit_quisioner</code> record #{{ $selectedQs->id_quisioner }}.
                        </span>
                        <div class="d-flex gap-2">
                            <button type="submit" name="submit_type" value="save_only" class="btn btn-primary btn-sm fw-semibold">
                                <i class="bi bi-save me-1"></i> Simpan ke Database Staging
                            </button>
                            <button type="submit" name="submit_type" value="save_and_retry" class="btn btn-success btn-sm fw-semibold shadow-sm">
                                <i class="bi bi-send me-1"></i> Simpan & Kirim Ulang
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <!-- ================= 10. TAB ALERGI ================= -->
    <div class="tab-pane fade {{ $currentTab === 'allergy' ? 'show active' : '' }}" id="tab-pane-allergy" role="tabpanel">
        @php
            $allergies = $allStaging['allergy']['records'] ?? collect();
            $selectedAl = ($currentTab === 'allergy' && $currentStagingId)
                ? $allergies->firstWhere('id', $currentStagingId)
                : $allergies->first();
            $alId = $selectedAl->id ?? null;
        @endphp

        <!-- Tabel Alergi -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-shield-slash me-2 text-primary"></i>Daftar Riwayat Alergi (Tabel <code>visit_alergy</code> - {{ $allergies->count() }} Data)
                </h6>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 80px;">ID</th>
                            <th style="width: 180px;">Tanggal Alergi</th>
                            <th>Catatan / Uraian Alergi</th>
                            <th class="text-center" style="width: 140px;">Pilihan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allergies as $al)
                            @php $isThisAl = ($alId == $al->id); @endphp
                            <tr class="{{ $isThisAl ? 'table-primary' : '' }}">
                                <td class="ps-3 font-monospace fw-bold">#{{ $al->id }}</td>
                                <td>{{ $al->tanggal_alergi ?? '-' }}</td>
                                <td class="fw-medium text-dark">{{ $al->note ?? '-' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('satusehat.failed.repair', ['id' => $log->id ?? $log->log_id, 'tab' => 'allergy', 'staging_id' => $al->id, 'table' => 'visit_alergy']) }}"
                                       class="btn btn-xs {{ $isThisAl ? 'btn-primary' : 'btn-outline-primary' }} py-1 px-3">
                                        <i class="bi bi-pencil-square me-1"></i>{{ $isThisAl ? 'Sedang Diedit' : 'Pilih & Edit' }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">Belum ada data riwayat alergi pada kunjungan ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($selectedAl)
            <div class="card shadow-sm border-0 mb-4 border-start border-4 border-primary">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-pencil me-2 text-primary"></i>Edit Riwayat Alergi: Record #{{ $selectedAl->id }}
                    </h6>
                    <span class="badge bg-light text-secondary border font-monospace">Tabel: visit_alergy</span>
                </div>
                <form method="POST" action="{{ route('satusehat.failed.save-repair', $log->id ?? $log->log_id) }}">
                    @csrf
                    <input type="hidden" name="staging_table" value="visit_alergy">
                    <input type="hidden" name="staging_id" value="{{ $selectedAl->id }}">
                    <input type="hidden" name="resource_type" value="AllergyIntolerance">
                    <input type="hidden" name="active_tab" value="allergy">
                    <input type="hidden" name="redirect_to" value="repair">

                    <div class="card-body pt-0">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold text-secondary">Tanggal Alergi</label>
                                <input type="text" name="tanggal_alergi" class="form-control form-control-sm font-monospace" value="{{ old('tanggal_alergi', $selectedAl->tanggal_alergi ?? '') }}" placeholder="YYYY-MM-DD HH:mm:ss">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold text-secondary">Catatan Alergi / Zat Penyebab <span class="text-danger">*</span></label>
                                <textarea name="note" class="form-control form-control-sm" rows="3" required placeholder="Contoh: Alergi Amoxicillin, timbul ruam kemerahan">{{ old('note', $selectedAl->note ?? '') }}</textarea>
                            </div>
                            <div class="col-12 mt-3 pt-2 border-top">
                                <label class="form-label small fw-bold text-dark">Alasan Perbaikan <span class="text-danger">*</span></label>
                                <input type="text" name="reason" class="form-control form-control-sm" required value="{{ old('reason', 'Koreksi riwayat alergi pasien') }}">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-light py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="small text-muted">
                            <i class="bi bi-shield-check text-success me-1"></i> Data langsung diperbarui di tabel <code>visit_alergy</code> record #{{ $selectedAl->id }}.
                        </span>
                        <div class="d-flex gap-2">
                            <button type="submit" name="submit_type" value="save_only" class="btn btn-primary btn-sm fw-semibold">
                                <i class="bi bi-save me-1"></i> Simpan ke Database Staging
                            </button>
                            <button type="submit" name="submit_type" value="save_and_retry" class="btn btn-success btn-sm fw-semibold shadow-sm">
                                <i class="bi bi-send me-1"></i> Simpan & Kirim Ulang
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </div>

</div>

<!-- Modal / Script Validasi AJAX Live -->
@push('scripts')
<script>
function runLiveValidation() {
    const alertBox = document.getElementById('validationAlert');
    alertBox.style.display = 'block';
    alertBox.innerHTML = '<div class="alert alert-info border-0 shadow-sm"><div class="spinner-border spinner-border-sm me-2" role="status"></div> Memeriksa kelengkapan data staging...</div>';

    const url = "{{ route('satusehat.failed.validate', $log->id ?? $log->log_id) }}";

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.valid) {
                alertBox.innerHTML = `
                    <div class="alert alert-success border-0 shadow-sm d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                            <strong>Data Lengkap:</strong> Semua data pokok (Encounter, Pasien, DPJP, dan resource staging) siap dikirim ke SATUSEHAT.
                        </div>
                        <span class="badge bg-success">Status: ${data.status || 'RETRYABLE'}</span>
                    </div>
                `;
            } else {
                let errList = (data.errors || []).map(e => `<li>${e}</li>`).join('');
                alertBox.innerHTML = `
                    <div class="alert alert-warning border-0 shadow-sm">
                        <div class="fw-bold mb-1">
                            <i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>
                            Ada ${data.errors ? data.errors.length : 0} data yang perlu diperbaiki sebelum pengiriman:
                        </div>
                        <ul class="mb-0 small ps-3">${errList}</ul>
                    </div>
                `;
            }
        })
        .catch(err => {
            alertBox.innerHTML = `
                <div class="alert alert-danger border-0 shadow-sm">
                    <i class="bi bi-x-circle-fill me-2"></i> Gagal memeriksa kelengkapan: ${err.message}
                </div>
            `;
        });
}
</script>
@endpush
@endsection
