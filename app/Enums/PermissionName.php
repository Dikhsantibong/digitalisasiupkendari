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
