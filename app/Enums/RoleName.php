<?php

namespace App\Enums;

/**
 * The system roles seeded with the application.
 *
 * Operasi, Pemeliharaan and K3 are run through two accesses
 * ({@see PermissionGroup}): Akses 1 — Laporan Project (Project Leader &
 * Koordinator: jadwal, input, formulir and the Laporan Pembangkit) and
 * Akses 2 — Pengusahaan (Team Leader & Staf: the Pengusahaan menus and the
 * Laporan Pengusahaan). The defaults below are only a starting point; a Super
 * Admin adjusts every role per menu in Role & Akses.
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
    case TeamLeaderLogistik = 'tl_logistik';
    case TeamLeaderPdm = 'tl_pdm';
    case SiteLeader = 'site_leader';
    case ProjectLeaderOperasi = 'project_leader_operasi';
    case KoordinatorOperasi = 'koordinator_operasi';
    case KoordinatorPemeliharaan = 'koordinator_pemeliharaan';
    case KoordinatorK3 = 'koordinator_k3';
    case StafOperasi = 'staf_operasi';
    case StafPemeliharaan = 'staf_pemeliharaan';
    case StafK3 = 'staf_k3';
    case Operator = 'operator';
    case Harmes = 'harmes';
    case Harlist = 'harlist';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::ManagerUl => 'Manager UL',
            self::TeamLeaderOperasi => 'TL Operasi',
            self::TeamLeaderPemeliharaan => 'TL Pemeliharaan',
            self::TeamLeaderK3 => 'TL K3 & Keamanan',
            self::TeamLeaderLogistik => 'TL Logistik & Gudang',
            self::TeamLeaderPdm => 'TL PdM & Maturity Level',
            self::SiteLeader => 'Site Leader',
            self::ProjectLeaderOperasi => 'Project Leader Operasi',
            self::KoordinatorOperasi => 'Koordinator Operasi',
            self::KoordinatorPemeliharaan => 'Koordinator Pemeliharaan',
            self::KoordinatorK3 => 'Koordinator K3 & Keamanan',
            self::StafOperasi => 'Staf Operasi',
            self::StafPemeliharaan => 'Staf Pemeliharaan',
            self::StafK3 => 'Staf K3 & Keamanan',
            self::Operator => 'Operator',
            self::Harmes => 'Harmes (Pemeliharaan Mesin)',
            self::Harlist => 'Harlist (Pemeliharaan Listrik)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Kontrol penuh atas sistem: manajemen akses, pemantauan aktivitas, dan seluruh unit.',
            self::ManagerUl => 'Memimpin satu unit layanan dan memantau seluruh unit pembangkit di bawahnya.',
            self::TeamLeaderOperasi => 'Akses 2 — Pengusahaan operasi (menu & Laporan Pengusahaan), serta menyetujui Laporan Operasi Pembangkit pada unit yang ditugaskan.',
            self::TeamLeaderPemeliharaan => 'Akses 2 — Pengusahaan pemeliharaan (menu & Laporan Pengusahaan), serta menyetujui Laporan Pemeliharaan Pembangkit pada unit yang ditugaskan.',
            self::TeamLeaderK3 => 'Akses 2 — Pengusahaan K3 & keamanan (menu & Laporan Pengusahaan), serta menyetujui Laporan K3 Pembangkit pada unit yang ditugaskan.',
            self::TeamLeaderLogistik => 'Mengelola kegiatan logistik & gudang pada unit pembangkit yang ditugaskan.',
            self::TeamLeaderPdm => 'Mengelola kegiatan predictive maintenance (PdM) & maturity level pada unit pembangkit yang ditugaskan.',
            self::SiteLeader => 'Memimpin lokasi unit pembangkit dan menyetujui laporan tingkat unit.',
            self::ProjectLeaderOperasi => 'Project Leader: Akses 1 — melihat seluruh Laporan Project (Operasi, Pemeliharaan, K3, Logistik, PdM), serta menjadwalkan shift regu & mengelola absensi pada unit pembangkit yang ditugaskan.',
            self::KoordinatorOperasi => 'Akses 1 — Laporan Project operasi (Koordinator & Office): jadwal, input, berita acara, master dan Laporan Operasi Pembangkit, serta mengawasi logsheet & jadwal shift operator.',
            self::KoordinatorPemeliharaan => 'Akses 1 — Laporan Project pemeliharaan (Koordinator & Office): jadwal, input, formulir, master dan Laporan Pemeliharaan Pembangkit.',
            self::KoordinatorK3 => 'Akses 1 — Laporan Project K3 & keamanan (Koordinator & Office): jadwal, input, formulir, monitoring, master dan Laporan K3 Pembangkit.',
            self::StafOperasi => 'Akses 2 — Pengusahaan operasi: menu & Laporan Pengusahaan operasi pada unit yang ditugaskan.',
            self::StafPemeliharaan => 'Akses 2 — Pengusahaan pemeliharaan: menu & Laporan Pengusahaan pemeliharaan pada unit yang ditugaskan.',
            self::StafK3 => 'Akses 2 — Pengusahaan K3 & keamanan: menu & Laporan Pengusahaan K3 pada unit yang ditugaskan.',
            self::Operator => 'Operator shift (termasuk Leader Shift) divisi Operasi di bawah Koordinator Operasi: mencatat logsheet harian dan absen pada unit pembangkit yang ditugaskan.',
            self::Harmes => 'Teknisi pemeliharaan mesin divisi Pemeliharaan di bawah Koordinator Pemeliharaan pada unit pembangkit yang ditugaskan.',
            self::Harlist => 'Teknisi pemeliharaan listrik divisi Pemeliharaan di bawah Koordinator Pemeliharaan pada unit pembangkit yang ditugaskan.',
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
            self::TeamLeaderLogistik,
            self::TeamLeaderPdm,
            self::SiteLeader,
            self::ProjectLeaderOperasi,
            self::KoordinatorOperasi,
            self::KoordinatorPemeliharaan,
            self::KoordinatorK3,
            self::StafOperasi,
            self::StafPemeliharaan,
            self::StafK3,
            self::Operator,
            self::Harmes,
            self::Harlist => RoleScope::Unit,
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
                PermissionName::HarPengusahaanView,
                // Manager oversees K3 & security reporting across their UL (read-only).
                PermissionName::K3LaporanView,
                PermissionName::K3MonitoringView,
                PermissionName::K3PengusahaanView,
                PermissionName::OperasiPengusahaanView,
                // Manager oversees logistik & gudang reporting across their UL (read-only).
                PermissionName::LogistikLaporanView,
                // Manager oversees PdM & maturity level reporting across their UL (read-only).
                PermissionName::PdmLaporanView,
                // Manager oversees the Operator module (read-only): field
                // logsheets and the shift schedule / attendance.
                PermissionName::OperatorLogsheetView,
                PermissionName::OperatorAbsensiView,
            ],

            // Akses 1 — Laporan Project: the Koordinator (and Office) of the
            // divisi prepare the Laporan Pembangkit.
            self::KoordinatorPemeliharaan => [
                ...$this->teamLeaderBasePermissions(),
                PermissionName::HarInputView,
                PermissionName::HarInputWrite,
                PermissionName::HarLaporanView,
                PermissionName::HarExecutiveView,
                PermissionName::HarMasterViewAny,
                PermissionName::HarMasterManage,
            ],

            self::KoordinatorOperasi => [
                ...$this->teamLeaderBasePermissions(),
                PermissionName::OperasiInputView,
                PermissionName::OperasiInputWrite,
                PermissionName::OperasiLaporanView,
                PermissionName::OperasiBeritaAcaraView,
                PermissionName::OperasiBeritaAcaraCreate,
                PermissionName::OperasiMasterViewAny,
                PermissionName::OperasiMasterManage,
                // The operators work under the Koordinator Operasi: field
                // logsheets and the shift schedule & attendance.
                PermissionName::OperatorLogsheetView,
                PermissionName::OperatorAbsensiView,
                PermissionName::OperatorAbsensiWrite,
            ],

            self::KoordinatorK3 => [
                ...$this->teamLeaderBasePermissions(),
                PermissionName::K3InputView,
                PermissionName::K3InputWrite,
                PermissionName::K3LaporanView,
                PermissionName::K3MonitoringView,
                PermissionName::K3MasterViewAny,
                PermissionName::K3MasterManage,
            ],

            // Akses 2 — Pengusahaan. The Team Leader keeps read access to the
            // Laporan Pembangkit only because it approves (menyetujui) it in the
            // report workflow.
            self::TeamLeaderPemeliharaan => [
                ...$this->teamLeaderBasePermissions(),
                PermissionName::HarPengusahaanView,
                PermissionName::HarPengusahaanWrite,
                PermissionName::HarLaporanView,
                PermissionName::HarExecutiveView,
            ],

            self::TeamLeaderOperasi => [
                ...$this->teamLeaderBasePermissions(),
                PermissionName::OperasiPengusahaanView,
                PermissionName::OperasiPengusahaanWrite,
                PermissionName::OperasiLaporanView,
                // TL Operasi still verifies the operators' logsheets and sees
                // the shift schedule.
                PermissionName::OperatorLogsheetView,
                PermissionName::OperatorAbsensiView,
            ],

            self::TeamLeaderK3 => [
                ...$this->teamLeaderBasePermissions(),
                PermissionName::K3PengusahaanView,
                PermissionName::K3PengusahaanWrite,
                PermissionName::K3LaporanView,
                PermissionName::K3MonitoringView,
            ],

            self::StafPemeliharaan => [
                ...$this->stafBasePermissions(),
                PermissionName::HarPengusahaanView,
                PermissionName::HarPengusahaanWrite,
            ],

            self::StafOperasi => [
                ...$this->stafBasePermissions(),
                PermissionName::OperasiPengusahaanView,
                PermissionName::OperasiPengusahaanWrite,
            ],

            self::StafK3 => [
                ...$this->stafBasePermissions(),
                PermissionName::K3PengusahaanView,
                PermissionName::K3PengusahaanWrite,
            ],

            self::TeamLeaderLogistik => [
                ...$this->teamLeaderBasePermissions(),
                // The logistik & gudang module belongs to TL Logistik & Gudang.
                PermissionName::LogistikInputView,
                PermissionName::LogistikInputWrite,
                PermissionName::LogistikLaporanView,
                PermissionName::LogistikMasterViewAny,
                PermissionName::LogistikMasterManage,
            ],

            self::TeamLeaderPdm => [
                ...$this->teamLeaderBasePermissions(),
                // The PdM & maturity level module belongs to TL PdM.
                PermissionName::PdmInputView,
                PermissionName::PdmInputWrite,
                PermissionName::PdmLaporanView,
                PermissionName::PdmMasterViewAny,
                PermissionName::PdmMasterManage,
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
                PermissionName::OperatorPresensi,
                PermissionName::OperasiLaporanView,
                ...PermissionName::operasiLapangan(),
                // Akses 1 — the Project Leader reads (and signs "Mengetahui")
                // every Laporan Project.
                PermissionName::HarLaporanView,
                PermissionName::HarExecutiveView,
                PermissionName::K3LaporanView,
                PermissionName::K3MonitoringView,
                PermissionName::LogistikLaporanView,
                PermissionName::PdmLaporanView,
            ],

            self::Operator => [
                ...$this->operatorBasePermissions(),
                // The operator sees the shift schedule they belong to (read-only).
                PermissionName::OperatorAbsensiView,
                PermissionName::OperatorPresensi,
                // Every Operasi input page, one permission each — adjustable per
                // role in Role & Akses.
                ...PermissionName::operasiLapangan(),
            ],

            // Field maintenance staff (divisi Pemeliharaan): absen plus every HAR
            // input & formulir page, one permission each — adjustable per role
            // in Role & Akses — not the TL's har.input.* permissions.
            self::Harmes,
            self::Harlist => [
                PermissionName::UnitViewAny,
                PermissionName::UnitView,
                PermissionName::MachineViewAny,
                PermissionName::MachineView,
                PermissionName::OperatorPresensi,
                ...PermissionName::harLapangan(),
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
     * The read-only basics of a Staf (Akses 2 — Pengusahaan) account.
     *
     * @return list<PermissionName>
     */
    private function stafBasePermissions(): array
    {
        return [
            PermissionName::UnitViewAny,
            PermissionName::UnitView,
            PermissionName::MachineViewAny,
            PermissionName::MachineView,
            PermissionName::EmployeeViewAny,
            PermissionName::EmployeeView,
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
