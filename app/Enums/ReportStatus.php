<?php

namespace App\Enums;

/**
 * Workflow status of a Laporan Pembangkit, following the hierarchy
 * pemeriksaan → persetujuan → pengesahan:
 *
 *   DRAFT → DIAJUKAN (menunggu verifikasi Koordinator divisi)
 *         → VERIFIKASI (diverifikasi, menunggu persetujuan Team Leader modul)
 *         → DISETUJUI (menunggu pengesahan Manager UL)
 *         → DISAHKAN → FINAL
 *
 * DITOLAK (at any of the three steps) sends the report back for perbaikan
 * before it is diajukan kembali.
 */
enum ReportStatus: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case Verifikasi = 'verifikasi';
    case Disetujui = 'disetujui';
    case Disahkan = 'disahkan';
    /** Legacy: the former in-report signing stage, kept only for the audit trail of older reports. */
    case Ditandatangani = 'ditandatangani';
    case Final = 'final';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Diajukan — Menunggu Verifikasi Koordinator',
            self::Verifikasi => 'Diverifikasi — Menunggu Persetujuan Team Leader',
            self::Disetujui => 'Disetujui — Menunggu Pengesahan Manager UL',
            self::Disahkan => 'Disahkan',
            self::Ditandatangani => 'Ditandatangani',
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
            self::Diajukan, self::Verifikasi, self::Disetujui => 'info',
            self::Disahkan, self::Ditandatangani, self::Final => 'success',
            self::Ditolak => 'danger',
        };
    }

    /**
     * Only a draft or a rejected (perbaikan) report may be edited or regenerated.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Ditolak], true);
    }

    public function canBeSubmitted(): bool
    {
        return $this->isEditable();
    }

    /**
     * The status in which each approval step (by sequence) is open:
     * 1 Koordinator memeriksa, 2 Team Leader modul menyetujui, 3 Manager UL mengesahkan.
     */
    public static function awaitingStep(int $sequence): self
    {
        return match ($sequence) {
            1 => self::Diajukan,
            2 => self::Verifikasi,
            3 => self::Disetujui,
        };
    }
}
