<?php

namespace App\Enums;

use App\Models\HarDocumentRecord;
use App\Models\K3DocumentRecord;
use App\Models\LogistikDocumentRecord;
use App\Models\OperasiReportDocument;
use App\Models\PdmDocumentRecord;

/**
 * The reports that go through the verification & pengesahan workflow:
 *
 * - the Laporan Pembangkit (Akses 1) of every divisi — Koordinator memeriksa,
 *   Team Leader menyetujui, Manager UL mengesahkan, signed inside by Project
 *   Leader + Office;
 * - the Laporan Pengusahaan (Akses 2) of Operasi, Pemeliharaan and K3 —
 *   diajukan by the divisi's Staf, Team Leader menyetujui, Manager UL
 *   mengesahkan; no Koordinator and no Project Leader.
 */
enum ReportModule: string
{
    case Operasi = 'operasi';
    case Har = 'har';
    case K3 = 'k3';
    case Logistik = 'logistik';
    case Pdm = 'pdm';
    case OperasiPengusahaan = 'operasi-pengusahaan';
    case HarPengusahaan = 'har-pengusahaan';
    case K3Pengusahaan = 'k3-pengusahaan';

    public function label(): string
    {
        return match ($this) {
            self::Operasi => 'Laporan Operasi Pembangkit',
            self::Har => 'Laporan Pemeliharaan Pembangkit',
            self::K3 => 'Laporan K3 & Keamanan',
            self::Logistik => 'Laporan Logistik & Gudang',
            self::Pdm => 'Laporan PdM',
            self::OperasiPengusahaan => 'Laporan Pengusahaan Operasi',
            self::HarPengusahaan => 'Laporan Pengusahaan Pemeliharaan',
            self::K3Pengusahaan => 'Laporan Pengusahaan K3 & Keamanan',
        };
    }

    /** Whether this is a Laporan Pengusahaan (Akses 2: Staf → Team Leader → Manager UL). */
    public function isPengusahaan(): bool
    {
        return in_array($this, [self::OperasiPengusahaan, self::HarPengusahaan, self::K3Pengusahaan], true);
    }

    /** The Laporan Pembangkit module of the same divisi. */
    public function base(): self
    {
        return match ($this) {
            self::OperasiPengusahaan => self::Operasi,
            self::HarPengusahaan => self::Har,
            self::K3Pengusahaan => self::K3,
            default => $this,
        };
    }

    /**
     * Akses 2 — Pengusahaan: the permission that opens the module's
     * Pengusahaan menus (TL & Staf); null for a module without one yet.
     */
    public function pengusahaanPermission(): ?PermissionName
    {
        return match ($this->base()) {
            self::Operasi => PermissionName::OperasiPengusahaanView,
            self::Har => PermissionName::HarPengusahaanView,
            self::K3 => PermissionName::K3PengusahaanView,
            default => null,
        };
    }

    /**
     * The divisi (work_modules.code) the report belongs to.
     */
    public function division(): string
    {
        return match ($this->base()) {
            self::Operasi => 'operasi',
            self::Har => 'pemeliharaan',
            self::K3 => 'k3',
            self::Logistik => 'logistik',
            default => 'pdm',
        };
    }

    /**
     * Whoever may edit the report document may also submit (ajukan) it.
     */
    public function writePermission(): PermissionName
    {
        return match ($this) {
            self::Operasi => PermissionName::OperasiLaporanView,
            self::Har => PermissionName::HarInputWrite,
            self::K3 => PermissionName::K3InputWrite,
            self::Logistik => PermissionName::LogistikInputWrite,
            self::Pdm => PermissionName::PdmInputWrite,
            self::OperasiPengusahaan => PermissionName::OperasiPengusahaanWrite,
            self::HarPengusahaan => PermissionName::HarPengusahaanWrite,
            self::K3Pengusahaan => PermissionName::K3PengusahaanWrite,
        };
    }

    /**
     * Whether the report document of the unit & month has been saved
     * (Pembuatan Laporan) — only a saved document can be diajukan.
     */
    public function hasSavedDocument(int $unitId, int $month, int $year): bool
    {
        $query = match ($this) {
            self::Operasi => OperasiReportDocument::query()->where('report_code', 'laporan-operasi-bulanan'),
            self::Har => HarDocumentRecord::query()->where('type', 'bulanan'),
            self::K3 => K3DocumentRecord::query()->where('type', 'bulanan'),
            self::Logistik => LogistikDocumentRecord::query()->where('type', 'bulanan'),
            self::Pdm => PdmDocumentRecord::query()->where('type', 'bulanan'),
            self::OperasiPengusahaan => OperasiReportDocument::query()->where('report_code', 'pengusahaan')->whereNull('engine_id'),
            self::HarPengusahaan => HarDocumentRecord::query()->where('type', 'pengusahaan'),
            self::K3Pengusahaan => K3DocumentRecord::query()->where('type', 'pengusahaan'),
        };

        return $query->where('unit_id', $unitId)->where('month', $month)->where('year', $year)->exists();
    }

    /**
     * The Team Leader jabatan that approves (menyetujui) this module's report.
     * HAR, PDM and Logistik are approved by TL Pemeliharaan; Operasi by TL
     * Operasi; K3 by TL K3 & Keamanan.
     */
    public function teamLeaderPosition(): EmployeePosition
    {
        return match ($this->base()) {
            self::Operasi => EmployeePosition::TeamLeaderOperasi,
            self::K3 => EmployeePosition::TeamLeaderK3,
            default => EmployeePosition::TeamLeaderPemeliharaan,
        };
    }

    /**
     * The approval chain — and the Lembar Pengesahan, printed in the same
     * order. Sequence 1 Koordinator memeriksa (verifikasi), 2 Team Leader
     * menyetujui, 3 Manager UL mengesahkan (last). A Laporan Pengusahaan has
     * no Koordinator: its chain starts at the Team Leader (sequence 2).
     *
     * @return list<array{caption: string, position: EmployeePosition, sequence: int}>
     */
    public function pengesahanSigners(): array
    {
        $chain = [
            ['caption' => 'Menyetujui', 'position' => $this->teamLeaderPosition(), 'sequence' => 2],
            ['caption' => 'Mengesahkan', 'position' => EmployeePosition::ManagerUl, 'sequence' => 3],
        ];

        return $this->isPengusahaan()
            ? $chain
            : [['caption' => 'Memeriksa', 'position' => EmployeePosition::koordinatorFor($this->division()), 'sequence' => 1], ...$chain];
    }

    /**
     * The signature block inside the report (after the Lembar Pengesahan):
     * Project Leader + the Office of the report's divisi; the PdM report is
     * signed by the Koordinator Pemeliharaan + PIC PDM instead; a Laporan
     * Pengusahaan has none. Not part of the approval chain: the signers are
     * frozen when the report is diajukan and their signatures print once the
     * Manager UL has made it FINAL.
     *
     * @return list<array{caption: string, position: EmployeePosition}>
     */
    public function reportSigners(): array
    {
        if ($this->isPengusahaan()) {
            return [];
        }

        if ($this === self::Pdm) {
            return [
                ['caption' => 'Mengetahui', 'position' => EmployeePosition::KoordinatorPemeliharaan],
                ['caption' => 'Dibuat', 'position' => EmployeePosition::PicPdm],
            ];
        }

        return [
            ['caption' => 'Mengetahui', 'position' => EmployeePosition::ProjectLeader],
            ['caption' => 'Dibuat', 'position' => EmployeePosition::officeFor($this->division())],
        ];
    }

    /**
     * The status shown for this report: a Laporan Pengusahaan goes from
     * diajukan straight to the Team Leader, so its first waiting status reads so.
     */
    public function statusLabel(ReportStatus $status): string
    {
        return $this->isPengusahaan() && $status === ReportStatus::Verifikasi
            ? 'Diajukan — Menunggu Persetujuan Team Leader'
            : $status->label();
    }

    /**
     * The document page of the report (relative URL).
     */
    public function documentUrl(int $unitId, int $month, int $year): string
    {
        $query = ['unit_id' => $unitId, 'month' => $month, 'year' => $year];

        return match ($this) {
            self::Operasi => route('operasi.laporan.document.edit', ['report' => 'laporan-operasi-bulanan', ...$query], false),
            self::OperasiPengusahaan => route('operasi.laporan.pengusahaan.edit', $query, false),
            self::HarPengusahaan => route('har.laporan.pengusahaan.edit', $query, false),
            self::K3Pengusahaan => route('k3.laporan.pengusahaan.edit', $query, false),
            default => route("{$this->value}.laporan.document.edit", $query, false),
        };
    }

    /**
     * The PDF of the report (relative URL).
     *
     * @param  array<string, int|string>  $extra
     */
    public function pdfUrl(int $unitId, int $month, int $year, array $extra = []): string
    {
        $query = ['unit_id' => $unitId, 'month' => $month, 'year' => $year, ...$extra];

        return match ($this) {
            self::Operasi => route('operasi.laporan.document.pdf', ['report' => 'laporan-operasi-bulanan', ...$query], false),
            self::OperasiPengusahaan => route('operasi.laporan.pengusahaan.pdf', $query, false),
            self::HarPengusahaan => route('har.laporan.pengusahaan.pdf', $query, false),
            self::K3Pengusahaan => route('k3.laporan.pengusahaan.pdf', $query, false),
            default => route("{$this->value}.laporan.document.pdf", $query, false),
        };
    }
}
