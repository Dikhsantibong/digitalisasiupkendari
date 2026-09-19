<?php

namespace App\Enums;

/**
 * Workflow status of a Laporan Pembangkit:
 * DRAFT → DIAJUKAN → VERIFIKASI → DISETUJUI (pengesahan berjalan) → DISAHKAN
 * → DITANDATANGANI (tanda tangan berjalan) → FINAL, with DITOLAK sending the
 * report back for perbaikan before it is diajukan kembali.
 */
enum ReportStatus: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case Verifikasi = 'verifikasi';
    case Disetujui = 'disetujui';
    case Disahkan = 'disahkan';
    case Ditandatangani = 'ditandatangani';
    case Final = 'final';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Menunggu Verifikasi',
            self::Verifikasi => 'Terverifikasi — Menunggu Pengesahan',
            self::Disetujui => 'Dalam Pengesahan',
            self::Disahkan => 'Disahkan — Menunggu Tanda Tangan',
            self::Ditandatangani => 'Dalam Penandatanganan',
            self::Final => 'Final',
            self::Ditolak => 'Ditolak — Perlu Perbaikan',
        };
    }

    /**
     * Semantic tone used by the interface, per the design system.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Diajukan, self::Verifikasi, self::Disetujui, self::Disahkan, self::Ditandatangani => 'info',
            self::Final => 'success',
            self::Ditolak => 'danger',
        };
    }

    /**
     * Only a draft or a rejected report may still be edited or regenerated.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Ditolak], true);
    }

    public function canBeSubmitted(): bool
    {
        return $this->isEditable();
    }
}
