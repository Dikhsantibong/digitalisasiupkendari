<?php

namespace App\Enums;

use App\Models\HarDocumentRecord;
use App\Models\K3DocumentRecord;
use App\Models\LogistikDocumentRecord;
use App\Models\OperasiReportDocument;
use App\Models\PdmDocumentRecord;

/**
 * The Laporan Pembangkit that go through the verification & pengesahan
 * workflow — one per divisi (work_modules.code in {@see self::division()}).
 */
enum ReportModule: string
{
    case Operasi = 'operasi';
    case Har = 'har';
    case K3 = 'k3';
    case Logistik = 'logistik';
    case Pdm = 'pdm';

    public function label(): string
    {
        return match ($this) {
            self::Operasi => 'Laporan Operasi Pembangkit',
            self::Har => 'Laporan Pemeliharaan Pembangkit',
            self::K3 => 'Laporan K3 & Keamanan',
            self::Logistik => 'Laporan Logistik & Gudang',
            self::Pdm => 'Laporan PdM',
        };
    }

    /**
     * The divisi (work_modules.code) the report belongs to.
     */
    public function division(): string
    {
        return match ($this) {
            self::Operasi => 'operasi',
            self::Har => 'pemeliharaan',
            self::K3 => 'k3',
            self::Logistik => 'logistik',
            self::Pdm => 'pdm',
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
        return match ($this) {
            self::Operasi => EmployeePosition::TeamLeaderOperasi,
            self::K3 => EmployeePosition::TeamLeaderK3,
            self::Har, self::Pdm, self::Logistik => EmployeePosition::TeamLeaderPemeliharaan,
        };
    }

    /**
     * The approval chain — and the Lembar Pengesahan, printed in the same
     * order: the Koordinator of the report's divisi memeriksa (verifikasi),
     * the Team Leader sesuai modul menyetujui, the Manager UL mengesahkan (last).
     *
     * @return list<array{caption: string, position: EmployeePosition}>
     */
    public function pengesahanSigners(): array
    {
        return [
            ['caption' => 'Memeriksa', 'position' => EmployeePosition::koordinatorFor($this->division())],
            ['caption' => 'Menyetujui', 'position' => $this->teamLeaderPosition()],
            ['caption' => 'Mengesahkan', 'position' => EmployeePosition::ManagerUl],
        ];
    }

    /**
     * The signature block inside the report (after the Lembar Pengesahan):
     * Project Leader + the Office of the report's divisi; the PdM report is
     * signed by the Koordinator Pemeliharaan + PIC PDM instead. Not part of the
     * approval chain: the signers are frozen when the report is diajukan and
     * their signatures print once the Manager UL has made it FINAL.
     *
     * @return list<array{caption: string, position: EmployeePosition}>
     */
    public function reportSigners(): array
    {
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
}
