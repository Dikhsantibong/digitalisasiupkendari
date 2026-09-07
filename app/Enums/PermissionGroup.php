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
    case Operasi = 'operasi';
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
            self::Operasi => 'Operasi',
            self::Project => 'Project',
            self::ReportProject => 'Laporan Project',
            self::System => 'Sistem',
        };
    }
}
