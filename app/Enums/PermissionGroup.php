<?php

namespace App\Enums;

/**
 * Groups permissions for presentation in the access management screens.
 *
 * Operasi, Pemeliharaan and K3 have two accesses: Akses 1 — Laporan Project
 * (Project Leader & Koordinator: jadwal, input, formulir, Laporan Pembangkit)
 * and Akses 2 — Pengusahaan (Team Leader & Staf: menu & Laporan Pengusahaan).
 */
enum PermissionGroup: string
{
    case MasterData = 'master_data';
    case AccessManagement = 'access_management';
    case Monitoring = 'monitoring';
    case ReportUnit = 'report_unit';
    case Operator = 'operator';
    case Operasi = 'operasi';
    case OperasiPengusahaan = 'operasi_pengusahaan';
    case OperasiLapangan = 'operasi_lapangan';
    case Pemeliharaan = 'pemeliharaan';
    case PemeliharaanPengusahaan = 'pemeliharaan_pengusahaan';
    case PemeliharaanLapangan = 'pemeliharaan_lapangan';
    case K3 = 'k3';
    case K3Pengusahaan = 'k3_pengusahaan';
    case Logistik = 'logistik';
    case Pdm = 'pdm';
    case Project = 'project';
    case ReportProject = 'report_project';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::MasterData => 'Master Data',
            self::AccessManagement => 'Manajemen Akses',
            self::Monitoring => 'Monitoring',
            self::ReportUnit => 'Laporan Unit',
            self::Operator => 'Operator',
            self::Operasi => 'Operasi — Akses 1 Laporan Project',
            self::OperasiPengusahaan => 'Operasi — Akses 2 Pengusahaan',
            self::OperasiLapangan => 'Operasi — Input Lapangan',
            self::Pemeliharaan => 'Pemeliharaan — Akses 1 Laporan Project',
            self::PemeliharaanPengusahaan => 'Pemeliharaan — Akses 2 Pengusahaan',
            self::PemeliharaanLapangan => 'Pemeliharaan — Input Lapangan',
            self::K3 => 'K3 & Keamanan — Akses 1 Laporan Project',
            self::K3Pengusahaan => 'K3 & Keamanan — Akses 2 Pengusahaan',
            self::Logistik => 'Logistik & Gudang',
            self::Pdm => 'PdM & Maturity Level',
            self::Project => 'Project',
            self::ReportProject => 'Laporan Project',
            self::System => 'Sistem',
        };
    }
}
