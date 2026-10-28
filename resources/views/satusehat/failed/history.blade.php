@extends('satusehat.layout')

@section('title', 'Riwayat Perbaikan Data')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <a href="{{ route('satusehat.failed.show', $log->id ?? $log->log_id) }}" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Detail
        </a>
        <h3 class="fw-bold text-dark mb-0">
            Riwayat Perbaikan Data
        </h3>
        <small class="text-secondary">Riwayat perubahan data untuk Kunjungan ID: <code class="text-dark fw-bold">{{ $visitId }}</code></small>
    </div>
    <div>
        <a href="{{ route('satusehat.failed.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-list me-1"></i> Daftar Data Gagal
        </a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold text-dark mb-0">
            <i class="bi bi-clipboard-data text-primary me-2"></i>Catatan Perubahan Data
        </h6>
        <span class="badge bg-light text-secondary border font-monospace">{{ count($histories) }} Catatan</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th class="ps-3" style="width: 70px;">#</th>
                    <th style="width: 150px;">Waktu</th>
                    <th style="width: 140px;">Tabel</th>
                    <th style="width: 140px;">Kolom</th>
                    <th>Nilai Lama</th>
                    <th>Nilai Baru</th>
                    <th>Alasan</th>
                    <th style="width: 120px;">Petugas</th>
                </tr>
            </thead>
            <tbody>
                @forelse($histories as $idx => $h)
                    <tr>
                        <td class="ps-3 text-muted">{{ $idx + 1 }}</td>
                        <td class="font-monospace text-secondary" style="font-size: 11px;">
                            {{ $h['repaired_at'] ?? '-' }}
                        </td>
                        <td><code>{{ $h['staging_table'] ?? '-' }}</code></td>
                        <td><span class="fw-bold font-monospace text-primary">{{ $h['field_name'] ?? '-' }}</span></td>
                        <td><span class="text-danger font-monospace">{{ $h['old_value'] ?: '(kosong)' }}</span></td>
                        <td><span class="text-success font-monospace">{{ $h['new_value'] }}</span></td>
                        <td>{{ $h['reason'] ?? '-' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $h['repaired_by'] ?? 'Petugas' }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 text-secondary mb-2 d-block"></i>
                            Belum ada riwayat perbaikan untuk kunjungan ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
