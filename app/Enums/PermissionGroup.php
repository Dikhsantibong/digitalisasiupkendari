<?php

namespace App\Enums;

/**
 * Groups permissions for presentation in the access management screens.
 */
enum PermissionGroup: string
{
    case MasterData = 'master_data';
    case AccessManagement = 'access_management';
    case Monitoring = 'monitoring';
    case ReportUnit = 'report_unit';
    case Operator = 'operator';
    case Operasi = 'operasi';
    case OperasiLapangan = 'operasi_lapangan';
    case Pemeliharaan = 'pemeliharaan';
    case PemeliharaanLapangan = 'pemeliharaan_lapangan';
    case K3 = 'k3';
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
            self::Operasi => 'Operasi',
            self::OperasiLapangan => 'Operasi — Input Lapangan',
            self::Pemeliharaan => 'Pemeliharaan',
            self::PemeliharaanLapangan => 'Pemeliharaan — Input Lapangan',
            self::K3 => 'K3 & Keamanan',
            self::Logistik => 'Logistik & Gudang',
            self::Pdm => 'PdM & Maturity Level',
            self::Project => 'Project',
            self::ReportProject => 'Laporan Project',
            self::System => 'Sistem',
        };
    }
}
