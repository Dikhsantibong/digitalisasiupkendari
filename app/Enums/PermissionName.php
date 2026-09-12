<?php

namespace App\Enums;

/**
 * The application's permission catalogue.
 *
 * This enum is the single source of truth. Adding a capability means adding a
 * case here, listing it under the roles that should hold it in
 * {@see RoleName::defaultPermissions()}, and re-running the permission seeder —
 * no migration is required.
 */
enum PermissionName: string
{
    case ServiceUnitViewAny = 'service_unit.view_any';
    case ServiceUnitView = 'service_unit.view';
    case ServiceUnitCreate = 'service_unit.create';
    case ServiceUnitUpdate = 'service_unit.update';
    case ServiceUnitDelete = 'service_unit.delete';

    case UnitViewAny = 'unit.view_any';
    case UnitView = 'unit.view';
    case UnitCreate = 'unit.create';
    case UnitUpdate = 'unit.update';
    case UnitDelete = 'unit.delete';

    case MachineViewAny = 'machine.view_any';
    case MachineView = 'machine.view';
    case MachineCreate = 'machine.create';
    case MachineUpdate = 'machine.update';
    case MachineDelete = 'machine.delete';

    case EmployeeViewAny = 'employee.view_any';
    case EmployeeView = 'employee.view';
    case EmployeeCreate = 'employee.create';
    case EmployeeUpdate = 'employee.update';
    case EmployeeDelete = 'employee.delete';

    case UserViewAny = 'user.view_any';
    case UserView = 'user.view';
    case UserCreate = 'user.create';
    case UserUpdate = 'user.update';
    case UserDelete = 'user.delete';

    case RoleViewAny = 'role.view_any';
    case RoleView = 'role.view';
    case RoleCreate = 'role.create';
    case RoleUpdate = 'role.update';
    case RoleDelete = 'role.delete';
    case RoleAssign = 'role.assign';

    case ActivityLogViewAny = 'activity_log.view_any';
    case ActivityLogExport = 'activity_log.export';

    case ReportUnitViewAny = 'report_unit.view_any';
    case ReportUnitView = 'report_unit.view';
    case ReportUnitCreate = 'report_unit.create';
    case ReportUnitUpdate = 'report_unit.update';
    case ReportUnitDelete = 'report_unit.delete';
    case ReportUnitSubmit = 'report_unit.submit';
    case ReportUnitApprove = 'report_unit.approve';
    case ReportUnitExport = 'report_unit.export';

    case OperasiInputView = 'operasi.input.view';
    case OperasiInputWrite = 'operasi.input.write';
    case OperasiLaporanView = 'operasi.laporan.view';
    case OperasiBeritaAcaraView = 'operasi.berita_acara.view';
    case OperasiBeritaAcaraCreate = 'operasi.berita_acara.create';
    case OperasiMasterViewAny = 'operasi.master.view_any';
    case OperasiMasterManage = 'operasi.master.manage';

    // The Operator module (logsheet + shift schedule) is separate from Operasi.
    // Operasi may later pull these figures, but the capabilities are their own.
    case OperatorLogsheetView = 'operator.logsheet.view';
    case OperatorLogsheetWrite = 'operator.logsheet.write';
    case OperatorAbsensiView = 'operator.absensi.view';
    case OperatorAbsensiWrite = 'operator.absensi.write';

    case HarInputView = 'har.input.view';
    case HarInputWrite = 'har.input.write';
    case HarLaporanView = 'har.laporan.view';
    case HarExecutiveView = 'har.executive.view';
    case HarMasterViewAny = 'har.master.view_any';
    case HarMasterManage = 'har.master.manage';

    case K3InputView = 'k3.input.view';
    case K3InputWrite = 'k3.input.write';
    case K3LaporanView = 'k3.laporan.view';
    case K3MonitoringView = 'k3.monitoring.view';
    case K3MasterViewAny = 'k3.master.view_any';
    case K3MasterManage = 'k3.master.manage';

    case ProjectViewAny = 'project.view_any';
    case ProjectView = 'project.view';
    case ProjectCreate = 'project.create';
    case ProjectUpdate = 'project.update';
    case ProjectDelete = 'project.delete';
    case ProjectApprove = 'project.approve';

    case ReportProjectViewAny = 'report_project.view_any';
    case ReportProjectView = 'report_project.view';
    case ReportProjectCreate = 'report_project.create';
    case ReportProjectUpdate = 'report_project.update';
    case ReportProjectDelete = 'report_project.delete';
    case ReportProjectSubmit = 'report_project.submit';
    case ReportProjectApprove = 'report_project.approve';
    case ReportProjectExport = 'report_project.export';

    case SettingManage = 'setting.manage';

    public function group(): PermissionGroup
    {
        return match ($this) {
            self::ServiceUnitViewAny,
            self::ServiceUnitView,
            self::ServiceUnitCreate,
            self::ServiceUnitUpdate,
            self::ServiceUnitDelete,
            self::UnitViewAny,
            self::UnitView,
            self::UnitCreate,
            self::UnitUpdate,
            self::UnitDelete,
            self::MachineViewAny,
            self::MachineView,
            self::MachineCreate,
            self::MachineUpdate,
            self::MachineDelete,
            self::EmployeeViewAny,
            self::EmployeeView,
            self::EmployeeCreate,
            self::EmployeeUpdate,
            self::EmployeeDelete => PermissionGroup::MasterData,

            self::UserViewAny,
            self::UserView,
            self::UserCreate,
            self::UserUpdate,
            self::UserDelete,
            self::RoleViewAny,
            self::RoleView,
            self::RoleCreate,
            self::RoleUpdate,
            self::RoleDelete,
            self::RoleAssign => PermissionGroup::AccessManagement,

            self::ActivityLogViewAny,
            self::ActivityLogExport => PermissionGroup::Monitoring,

            self::ReportUnitViewAny,
            self::ReportUnitView,
            self::ReportUnitCreate,
            self::ReportUnitUpdate,
            self::ReportUnitDelete,
            self::ReportUnitSubmit,
            self::ReportUnitApprove,
            self::ReportUnitExport => PermissionGroup::ReportUnit,

            self::OperasiInputView,
            self::OperasiInputWrite,
            self::OperasiLaporanView,
            self::OperasiBeritaAcaraView,
            self::OperasiBeritaAcaraCreate,
            self::OperasiMasterViewAny,
            self::OperasiMasterManage => PermissionGroup::Operasi,

            self::OperatorLogsheetView,
            self::OperatorLogsheetWrite,
            self::OperatorAbsensiView,
            self::OperatorAbsensiWrite => PermissionGroup::Operator,

            self::HarInputView,
            self::HarInputWrite,
            self::HarLaporanView,
            self::HarExecutiveView,
            self::HarMasterViewAny,
            self::HarMasterManage => PermissionGroup::Pemeliharaan,

            self::K3InputView,
            self::K3InputWrite,
            self::K3LaporanView,
            self::K3MonitoringView,
            self::K3MasterViewAny,
            self::K3MasterManage => PermissionGroup::K3,

            self::ProjectViewAny,
            self::ProjectView,
            self::ProjectCreate,
            self::ProjectUpdate,
            self::ProjectDelete,
            self::ProjectApprove => PermissionGroup::Project,

            self::ReportProjectViewAny,
            self::ReportProjectView,
            self::ReportProjectCreate,
            self::ReportProjectUpdate,
            self::ReportProjectDelete,
            self::ReportProjectSubmit,
            self::ReportProjectApprove,
            self::ReportProjectExport => PermissionGroup::ReportProject,

            self::SettingManage => PermissionGroup::System,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ServiceUnitViewAny => 'Melihat daftar UL',
            self::ServiceUnitView => 'Melihat detail UL',
            self::ServiceUnitCreate => 'Menambah UL',
            self::ServiceUnitUpdate => 'Mengubah UL',
            self::ServiceUnitDelete => 'Menghapus UL',

            self::UnitViewAny => 'Melihat daftar unit',
            self::UnitView => 'Melihat detail unit',
            self::UnitCreate => 'Menambah unit',
            self::UnitUpdate => 'Mengubah unit',
            self::UnitDelete => 'Menghapus unit',

            self::MachineViewAny => 'Melihat daftar mesin',
            self::MachineView => 'Melihat detail mesin',
            self::MachineCreate => 'Menambah mesin',
            self::MachineUpdate => 'Mengubah mesin',
            self::MachineDelete => 'Menghapus mesin',

            self::EmployeeViewAny => 'Melihat daftar pegawai',
            self::EmployeeView => 'Melihat detail pegawai',
            self::EmployeeCreate => 'Menambah pegawai',
            self::EmployeeUpdate => 'Mengubah pegawai',
            self::EmployeeDelete => 'Menghapus pegawai',

            self::UserViewAny => 'Melihat daftar pengguna',
            self::UserView => 'Melihat detail pengguna',
            self::UserCreate => 'Menambah pengguna',
            self::UserUpdate => 'Mengubah pengguna',
            self::UserDelete => 'Menghapus pengguna',

            self::RoleViewAny => 'Melihat daftar role',
            self::RoleView => 'Melihat detail role',
            self::RoleCreate => 'Membuat role',
            self::RoleUpdate => 'Mengubah role & permission',
            self::RoleDelete => 'Menghapus role',
            self::RoleAssign => 'Menugaskan role ke pengguna',

            self::ActivityLogViewAny => 'Melihat log aktivitas',
            self::ActivityLogExport => 'Mengekspor log aktivitas',

            self::ReportUnitViewAny => 'Melihat daftar laporan unit',
            self::ReportUnitView => 'Melihat detail laporan unit',
            self::ReportUnitCreate => 'Membuat laporan unit',
            self::ReportUnitUpdate => 'Mengubah laporan unit',
            self::ReportUnitDelete => 'Menghapus laporan unit',
            self::ReportUnitSubmit => 'Mengajukan laporan unit',
            self::ReportUnitApprove => 'Menyetujui laporan unit',
            self::ReportUnitExport => 'Mengekspor laporan unit',

            self::OperasiInputView => 'Melihat input operasi',
            self::OperasiInputWrite => 'Mengisi input operasi',
            self::OperasiLaporanView => 'Melihat & mencetak laporan operasi',
            self::OperasiBeritaAcaraView => 'Melihat berita acara operasi',
            self::OperasiBeritaAcaraCreate => 'Membuat berita acara operasi',
            self::OperasiMasterViewAny => 'Melihat master data operasi',
            self::OperasiMasterManage => 'Mengelola master data operasi',
            self::OperatorLogsheetView => 'Melihat logsheet operator',
            self::OperatorLogsheetWrite => 'Mengisi logsheet operator',
            self::OperatorAbsensiView => 'Melihat jadwal & absensi shift',
            self::OperatorAbsensiWrite => 'Menjadwalkan & mengisi absensi shift',

            self::HarInputView => 'Melihat input pemeliharaan',
            self::HarInputWrite => 'Mengisi input pemeliharaan (WO/SR, log kegiatan, biaya, foto)',
            self::HarLaporanView => 'Melihat & mencetak laporan pemeliharaan',
            self::HarExecutiveView => 'Melihat executive summary pemeliharaan',
            self::HarMasterViewAny => 'Melihat master data pemeliharaan',
            self::HarMasterManage => 'Mengelola master data pemeliharaan',

            self::K3InputView => 'Melihat input K3 & keamanan',
            self::K3InputWrite => 'Mengisi input K3 & keamanan (inspeksi, patroli, sertifikat, lampiran)',
            self::K3LaporanView => 'Melihat & mencetak laporan K3 & keamanan',
            self::K3MonitoringView => 'Melihat monitoring status K3 (sertifikat/APAR)',
            self::K3MasterViewAny => 'Melihat master data K3 & keamanan',
            self::K3MasterManage => 'Mengelola master data K3 & keamanan',

            self::ProjectViewAny => 'Melihat daftar project',
            self::ProjectView => 'Melihat detail project',
            self::ProjectCreate => 'Membuat project',
            self::ProjectUpdate => 'Mengubah project',
            self::ProjectDelete => 'Menghapus project',
            self::ProjectApprove => 'Menyetujui project',

            self::ReportProjectViewAny => 'Melihat daftar laporan project',
            self::ReportProjectView => 'Melihat detail laporan project',
            self::ReportProjectCreate => 'Membuat laporan project',
            self::ReportProjectUpdate => 'Mengubah laporan project',
            self::ReportProjectDelete => 'Menghapus laporan project',
            self::ReportProjectSubmit => 'Mengajukan laporan project',
            self::ReportProjectApprove => 'Menyetujui laporan project',
            self::ReportProjectExport => 'Mengekspor laporan project',

            self::SettingManage => 'Mengelola pengaturan aplikasi',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}
