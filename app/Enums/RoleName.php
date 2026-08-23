<?php

namespace App\Enums;

/**
 * The system roles seeded with the application.
 *
 * Additional roles may be created at runtime by a Super Admin; only the roles
 * listed here are guaranteed to exist and are protected from deletion.
 */
enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case ManagerUl = 'manager_ul';
    case TeamLeaderOperasi = 'tl_operasi';
    case TeamLeaderPemeliharaan = 'tl_pemeliharaan';
    case SiteLeader = 'site_leader';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::ManagerUl => 'Manager UL',
            self::TeamLeaderOperasi => 'TL Operasi',
            self::TeamLeaderPemeliharaan => 'TL Pemeliharaan',
            self::SiteLeader => 'Site Leader',
            self::Operator => 'Operator',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Kontrol penuh atas sistem: manajemen akses, pemantauan aktivitas, dan seluruh unit.',
            self::ManagerUl => 'Memimpin satu unit layanan dan memantau seluruh unit pembangkit di bawahnya.',
            self::TeamLeaderOperasi => 'Mengelola kegiatan operasi pada unit pembangkit yang ditugaskan.',
            self::TeamLeaderPemeliharaan => 'Mengelola kegiatan pemeliharaan pada unit pembangkit yang ditugaskan.',
            self::SiteLeader => 'Memimpin lokasi unit pembangkit dan menyetujui laporan tingkat unit.',
            self::Operator => 'Mencatat data operasi harian pada unit pembangkit yang ditugaskan.',
        };
    }

    public function scope(): RoleScope
    {
        return match ($this) {
            self::SuperAdmin => RoleScope::Global,
            self::ManagerUl => RoleScope::ServiceUnit,
            self::TeamLeaderOperasi,
            self::TeamLeaderPemeliharaan,
            self::SiteLeader,
            self::Operator => RoleScope::Unit,
        };
    }

    /**
     * The permissions granted to this role when the catalogue is seeded.
     *
     * Super Admin is intentionally absent: it receives every permission through
     * a gate bypass so future permissions require no re-mapping here.
     *
     * @return list<PermissionName>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => PermissionName::cases(),

            self::ManagerUl => [
                PermissionName::ServiceUnitViewAny,
                PermissionName::ServiceUnitView,
                PermissionName::UnitViewAny,
                PermissionName::UnitView,
                PermissionName::UnitUpdate,
                PermissionName::UserViewAny,
                PermissionName::UserView,
                PermissionName::ActivityLogViewAny,
                PermissionName::ActivityLogExport,
                PermissionName::ReportUnitViewAny,
                PermissionName::ReportUnitView,
                PermissionName::ReportUnitApprove,
                PermissionName::ReportUnitExport,
                PermissionName::ProjectViewAny,
                PermissionName::ProjectView,
                PermissionName::ProjectApprove,
                PermissionName::ReportProjectViewAny,
                PermissionName::ReportProjectView,
                PermissionName::ReportProjectApprove,
                PermissionName::ReportProjectExport,
            ],

            self::TeamLeaderOperasi,
            self::TeamLeaderPemeliharaan => [
                PermissionName::UnitViewAny,
                PermissionName::UnitView,
                PermissionName::ReportUnitViewAny,
                PermissionName::ReportUnitView,
                PermissionName::ReportUnitCreate,
                PermissionName::ReportUnitUpdate,
                PermissionName::ReportUnitDelete,
                PermissionName::ReportUnitSubmit,
                PermissionName::ReportUnitExport,
                PermissionName::ProjectViewAny,
                PermissionName::ProjectView,
                PermissionName::ProjectCreate,
                PermissionName::ProjectUpdate,
                PermissionName::ReportProjectViewAny,
                PermissionName::ReportProjectView,
                PermissionName::ReportProjectCreate,
                PermissionName::ReportProjectUpdate,
                PermissionName::ReportProjectSubmit,
                PermissionName::ReportProjectExport,
            ],

            self::SiteLeader => [
                PermissionName::UnitViewAny,
                PermissionName::UnitView,
                PermissionName::UserViewAny,
                PermissionName::UserView,
                PermissionName::ActivityLogViewAny,
                PermissionName::ReportUnitViewAny,
                PermissionName::ReportUnitView,
                PermissionName::ReportUnitCreate,
                PermissionName::ReportUnitUpdate,
                PermissionName::ReportUnitDelete,
                PermissionName::ReportUnitSubmit,
                PermissionName::ReportUnitApprove,
                PermissionName::ReportUnitExport,
                PermissionName::ProjectViewAny,
                PermissionName::ProjectView,
                PermissionName::ProjectCreate,
                PermissionName::ProjectUpdate,
                PermissionName::ProjectDelete,
                PermissionName::ReportProjectViewAny,
                PermissionName::ReportProjectView,
                PermissionName::ReportProjectCreate,
                PermissionName::ReportProjectUpdate,
                PermissionName::ReportProjectDelete,
                PermissionName::ReportProjectSubmit,
                PermissionName::ReportProjectApprove,
                PermissionName::ReportProjectExport,
            ],

            self::Operator => [
                PermissionName::UnitViewAny,
                PermissionName::UnitView,
                PermissionName::ReportUnitViewAny,
                PermissionName::ReportUnitView,
                PermissionName::ReportUnitCreate,
                PermissionName::ReportUnitUpdate,
                PermissionName::ReportUnitSubmit,
                PermissionName::ProjectViewAny,
                PermissionName::ProjectView,
                PermissionName::ReportProjectViewAny,
                PermissionName::ReportProjectView,
            ],
        };
    }
}
