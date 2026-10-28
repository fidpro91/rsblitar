<?php

namespace App\Libraries\SatuSehat;

/**
 * Pengklasifikasi error pengiriman SATUSEHAT.
 * Diadaptasi dari implementasi MedBridge untuk menentukan kategori,
 * status tindakan (RETRYABLE vs NEED_REPAIR vs BLOCKED), dan badge UI.
 */
class ErrorClassifier
{
    public const CATEGORY_RETRYABLE        = 'RETRYABLE';
    public const CATEGORY_DATA_ERROR       = 'DATA_ERROR';
    public const CATEGORY_DEPENDENCY_ERROR = 'DEPENDENCY_ERROR';
    public const CATEGORY_AUTH_ERROR       = 'AUTH_ERROR';
    public const CATEGORY_UNKNOWN          = 'UNKNOWN';

    public const STATUS_RETRYABLE   = 'RETRYABLE';
    public const STATUS_NEED_REPAIR = 'NEED_REPAIR';
    public const STATUS_BLOCKED     = 'BLOCKED';
    public const STATUS_MAX_RETRY   = 'MAX_RETRY';
    public const STATUS_SUCCESS     = 'SUCCESS';

    /**
     * Mengklasifikasikan error berdasarkan HTTP status, pesan error, dependency, dan retry count.
     *
     * @param int|null $httpStatus
     * @param string|null $errorMessage
     * @param bool $isEncounterSuccess
     * @param int $retryCount
     * @return array
     */
    public static function classify(?int $httpStatus, ?string $errorMessage, bool $isEncounterSuccess = true, int $retryCount = 0): array
    {
        // 1. Dependency Check: Jika Encounter belum berhasil, child resource diblokir
        if (!$isEncounterSuccess) {
            return [
                'category' => self::CATEGORY_DEPENDENCY_ERROR,
                'status'   => self::STATUS_BLOCKED,
                'label'    => 'Blocked (Encounter Belum Success)',
                'badge'    => 'bg-secondary',
                'description' => 'Resource turunan tidak dapat dikirim sebelum Encounter utama sukses dibuat di SATUSEHAT.'
            ];
        }

        // 2. Batas Maksimal Retry
        if ($retryCount >= 3) {
            return [
                'category' => self::CATEGORY_RETRYABLE,
                'status'   => self::STATUS_MAX_RETRY,
                'label'    => 'Max Retry Reached (≥ 3)',
                'badge'    => 'bg-dark',
                'description' => 'Percobaan pengiriman otomatis telah mencapai batas maksimum (3 kali). Perlu pemeriksaan manual.'
            ];
        }

        // 3. Autentikasi / Token Expired
        if ($httpStatus === 401 || $httpStatus === 403) {
            return [
                'category' => self::CATEGORY_AUTH_ERROR,
                'status'   => self::STATUS_RETRYABLE,
                'label'    => 'Auth Error / Token Expired',
                'badge'    => 'bg-warning text-dark',
                'description' => 'Token kedaluwarsa atau masalah izin akses OAuth2. Sistem dapat me-refresh token secara otomatis.'
            ];
        }

        // 4. Server / Network Retryable Errors
        if (in_array($httpStatus, [408, 429, 500, 502, 503, 504], true)) {
            return [
                'category' => self::CATEGORY_RETRYABLE,
                'status'   => self::STATUS_RETRYABLE,
                'label'    => 'Retryable (Server / Timeout)',
                'badge'    => 'bg-warning text-dark',
                'description' => 'Kendala sementara pada server SATUSEHAT atau timeout. Aman untuk dikirim ulang tanpa mengubah data.'
            ];
        }

        $errMsg = strtolower($errorMessage ?? '');

        if (str_contains($errMsg, 'timeout') || str_contains($errMsg, 'curl error') || str_contains($errMsg, 'connection refused') || str_contains($errMsg, 'failed to connect')) {
            return [
                'category' => self::CATEGORY_RETRYABLE,
                'status'   => self::STATUS_RETRYABLE,
                'label'    => 'Retryable (Connection Error)',
                'badge'    => 'bg-warning text-dark',
                'description' => 'Koneksi jaringan terputus atau timeout saat menghubungi endpoint SATUSEHAT.'
            ];
        }

        // 5. Data Errors (Validasi, Kolom Wajib Kosong, Format Salah)
        if (
            in_array($httpStatus, [400, 422], true) ||
            str_contains($errMsg, 'required') ||
            str_contains($errMsg, 'invalid') ||
            str_contains($errMsg, 'null') ||
            str_contains($errMsg, 'not found') ||
            str_contains($errMsg, 'is mandatory') ||
            str_contains($errMsg, 'duplicate') ||
            str_contains($errMsg, 'operationoutcome')
        ) {
            return [
                'category' => self::CATEGORY_DATA_ERROR,
                'status'   => self::STATUS_NEED_REPAIR,
                'label'    => 'Need Repair (Data Error)',
                'badge'    => 'bg-danger',
                'description' => 'Data staging tidak valid atau kolom prasyarat SATUSEHAT belum lengkap. Perlu perbaikan data staging.'
            ];
        }

        return [
            'category' => self::CATEGORY_UNKNOWN,
            'status'   => self::STATUS_NEED_REPAIR,
            'label'    => 'Need Repair (Unknown Error)',
            'badge'    => 'bg-danger',
            'description' => 'Terjadi kesalahan yang memerlukan pemeriksaan dan verifikasi data.'
        ];
    }
}
