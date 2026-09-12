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
    case TeamLeaderK3 = 'tl_k3';
    case SiteLeader = 'site_leader';
    case ProjectLeaderOperasi = 'project_leader_operasi';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::ManagerUl => 'Manager UL',
            self::TeamLeaderOperasi => 'TL Operasi',
            self::TeamLeaderPemeliharaan => 'TL Pemeliharaan',
            self::TeamLeaderK3 => 'TL K3 & Keamanan',
            self::SiteLeader => 'Site Leader',
            self::ProjectLeaderOperasi => 'Project Leader Operasi',
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
            self::TeamLeaderK3 => 'Mengelola kegiatan K3 & keamanan pada unit pembangkit yang ditugaskan.',
            self::SiteLeader => 'Memimpin lokasi unit pembangkit dan menyetujui laporan tingkat unit.',
            self::ProjectLeaderOperasi => 'Operator senior yang menjadwalkan shift regu, mengelola absensi & laporan pada unit pembangkit yang ditugaskan.',
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
            self::TeamLeaderK3,
            self::SiteLeader,
            self::ProjectLeaderOperasi,
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
                PermissionName::MachineViewAny,
                PermissionName::MachineView,
                PermissionName::MachineCreate,
                PermissionName::MachineUpdate,
                PermissionName::MachineDelete,
                PermissionName::EmployeeViewAny,
                PermissionName::EmployeeView,
                PermissionName::EmployeeCreate,
                PermissionName::EmployeeUpdate,
                PermissionName::EmployeeDelete,
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
                // Manager oversees maintenance reporting across their UL (read-only).
                PermissionName::HarLaporanView,
                PermissionName::HarExecutiveView,
                // Manager oversees K3 & security reporting across their UL (read-only).
                PermissionName::K3LaporanView,
                PermissionName::K3MonitoringView,
                // Manager oversees the Operator module (read-only): field
                // logsheets and the shift schedule / attendance.
                PermissionName::OperatorLogsheetView,
                PermissionName::OperatorAbsensiView,
            ],

            self::TeamLeaderPemeliharaan => [
                ...$this->teamLeaderBasePermissions(),
                // The pemeliharaan (HAR) module belongs to TL Pemeliharaan.
                PermissionName::HarInputView,
                PermissionName::HarInputWrite,
                PermissionName::HarLaporanView,
                PermissionName::HarExecutiveView,
                PermissionName::HarMasterViewAny,
                PermissionName::HarMasterManage,
            ],

            self::TeamLeaderOperasi => [
                ...$this->teamLeaderBasePermissions(),
                // The operasi module belongs exclusively to TL Operasi.
                PermissionName::OperasiInputView,
                PermissionName::OperasiInputWrite,
                PermissionName::OperasiLaporanView,
                PermissionName::OperasiBeritaAcaraView,
                PermissionName::OperasiBeritaAcaraCreate,
                PermissionName::OperasiMasterViewAny,
                PermissionName::OperasiMasterManage,
                // TL Operasi also oversees the Operator module: verifies field
                // logsheets and manages the shift schedule & attendance.
                PermissionName::OperatorLogsheetView,
                PermissionName::OperatorAbsensiView,
                PermissionName::OperatorAbsensiWrite,
            ],

            self::TeamLeaderK3 => [
                ...$this->teamLeaderBasePermissions(),
                // The K3 & security module belongs to TL K3 & Keamanan.
                PermissionName::K3InputView,
                PermissionName::K3InputWrite,
                PermissionName::K3LaporanView,
                PermissionName::K3MonitoringView,
                PermissionName::K3MasterViewAny,
                PermissionName::K3MasterManage,
            ],

            self::SiteLeader => [
                PermissionName::UnitViewAny,
                PermissionName::UnitView,
                PermissionName::MachineViewAny,
                PermissionName::MachineView,
                PermissionName::EmployeeViewAny,
                PermissionName::EmployeeView,
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

            // The project leader is a senior operator: it holds every operator
            // permission and adds shift scheduling, attendance management, and
            // operasi report access. One operator per unit is assigned this role
            // instead of a bare Operator — the Operator role itself is untouched.
            self::ProjectLeaderOperasi => [
                ...$this->operatorBasePermissions(),
                PermissionName::OperatorAbsensiView,
                PermissionName::OperatorAbsensiWrite,
                PermissionName::OperasiLaporanView,
            ],

            self::Operator => [
                ...$this->operatorBasePermissions(),
                // The operator sees the shift schedule they belong to (read-only).
                PermissionName::OperatorAbsensiView,
            ],
        };
    }

    /**
     * The permissions shared by the operator and the project leader (a senior
     * operator). The project leader layers scheduling and reporting on top.
     *
     * @return list<PermissionName>
     */
    private function operatorBasePermissions(): array
    {
        return [
            PermissionName::UnitViewAny,
            PermissionName::UnitView,
            PermissionName::MachineViewAny,
            PermissionName::MachineView,
            PermissionName::EmployeeViewAny,
            PermissionName::EmployeeView,
            PermissionName::ReportUnitViewAny,
            PermissionName::ReportUnitView,
            PermissionName::ReportUnitCreate,
            PermissionName::ReportUnitUpdate,
            PermissionName::ReportUnitSubmit,
            PermissionName::ProjectViewAny,
            PermissionName::ProjectView,
            PermissionName::ReportProjectViewAny,
            PermissionName::ReportProjectView,
            // The operator fills the hourly logsheet (Operator module).
            PermissionName::OperatorLogsheetWrite,
            PermissionName::OperatorLogsheetView,
        ];
    }

    /**
     * The permissions shared by every unit-scoped team leader, before any
     * module-specific grants are layered on top.
     *
     * @return list<PermissionName>
     */
    private function teamLeaderBasePermissions(): array
    {
        return [
            PermissionName::UnitViewAny,
            PermissionName::UnitView,
            PermissionName::MachineViewAny,
            PermissionName::MachineView,
            PermissionName::EmployeeViewAny,
            PermissionName::EmployeeView,
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
        ];
    }
}
