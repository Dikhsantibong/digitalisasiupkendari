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

    // Operasi input pages opened to shift operators, one per page (see operasiLapangan()).
    case OperasiLapanganDailyReport = 'operasi.lapangan.daily_report';
    case OperasiLapanganStarStop = 'operasi.lapangan.star_stop';
    case OperasiLapanganFeeder = 'operasi.lapangan.feeder';
    case OperasiLapanganAuxiliary = 'operasi.lapangan.auxiliary';
    case OperasiLapanganFuelReceipt = 'operasi.lapangan.fuel_receipt';
    case OperasiLapanganKondisiAbnormal = 'operasi.lapangan.kondisi_abnormal';
    case OperasiLapanganResourcePembangkit = 'operasi.lapangan.resource_pembangkit';
    case OperasiLapanganMaterialPeralatan = 'operasi.lapangan.material_peralatan';
    case OperasiLapanganPermitToWork = 'operasi.lapangan.permit_to_work';
    case OperasiLapanganFlmMonitoring = 'operasi.lapangan.flm_monitoring';
    case OperasiLapanganPatrolCheckMesin = 'operasi.lapangan.patrol_check_mesin';
    case OperasiLapanganChecklistCommissioning = 'operasi.lapangan.checklist_commissioning';
    case OperasiLapanganUnsafeCondition = 'operasi.lapangan.unsafe_condition';
    case OperasiLapanganProgram5s5r = 'operasi.lapangan.program_5s5r';

    // The Operator module (logsheet + shift schedule) is separate from Operasi.
    // Operasi may later pull these figures, but the capabilities are their own.
    case OperatorLogsheetView = 'operator.logsheet.view';
    case OperatorLogsheetWrite = 'operator.logsheet.write';
    case OperatorAbsensiView = 'operator.absensi.view';
    case OperatorAbsensiWrite = 'operator.absensi.write';
    case OperatorPresensi = 'operator.presensi';

    case HarInputView = 'har.input.view';
    case HarInputWrite = 'har.input.write';
    case HarLaporanView = 'har.laporan.view';
    case HarExecutiveView = 'har.executive.view';
    case HarMasterViewAny = 'har.master.view_any';
    case HarMasterManage = 'har.master.manage';

    // Pemeliharaan input & formulir pages opened to Harmes / Harlist, one per page (see harLapangan()).
    case HarLapanganWorkOrder = 'har.lapangan.work_order';
    case HarLapanganServiceRequest = 'har.lapangan.service_request';
    case HarLapanganActivity = 'har.lapangan.activity';
    case HarLapanganCost = 'har.lapangan.cost';
    case HarLapanganSchedule = 'har.lapangan.schedule';
    case HarLapanganAttachment = 'har.lapangan.attachment';
    case HarLapanganUnsafeCondition = 'har.lapangan.unsafe_condition';
    case HarLapanganRekapGangguan = 'har.lapangan.rekap_gangguan';
    case HarLapanganAbnormalGangguan = 'har.lapangan.abnormal_gangguan';
    case HarLapanganProgram5s5r = 'har.lapangan.program_5s5r';
    case HarLapanganPatrolCheckPemeliharaan = 'har.lapangan.patrol_check_pemeliharaan';
    case HarLapanganPatrolCheckParameter = 'har.lapangan.patrol_check_parameter';
    case HarLapanganPrelubeTest = 'har.lapangan.prelube_test';
    case HarLapanganHydrotest = 'har.lapangan.hydrotest';
    case HarLapanganTimingInjectionPump = 'har.lapangan.timing_injection_pump';
    case HarLapanganCrankshaftDeflection = 'har.lapangan.crankshaft_deflection';
    case HarLapanganCounterWeight = 'har.lapangan.counter_weight';
    case HarLapanganAxialConrod = 'har.lapangan.axial_conrod';
    case HarLapanganClearanceValve = 'har.lapangan.clearance_valve';
    case HarLapanganCombustionPressure = 'har.lapangan.combustion_pressure';
    case HarLapanganInjectorPressure = 'har.lapangan.injector_pressure';
    case HarLapanganMotorCurrent = 'har.lapangan.motor_current';
    case HarLapanganVibration = 'har.lapangan.vibration';
    case HarLapanganLubeQuality = 'har.lapangan.lube_quality';
    case HarLapanganBatteryVoltage = 'har.lapangan.battery_voltage';
    case HarLapanganLaporanGangguan = 'har.lapangan.laporan_gangguan';
    case HarLapanganDailyMeeting = 'har.lapangan.daily_meeting';
    case HarLapanganLogbookMutasi = 'har.lapangan.logbook_mutasi';

    case K3InputView = 'k3.input.view';
    case K3InputWrite = 'k3.input.write';
    case K3LaporanView = 'k3.laporan.view';
    case K3MonitoringView = 'k3.monitoring.view';
    case K3MasterViewAny = 'k3.master.view_any';
    case K3MasterManage = 'k3.master.manage';

    case LogistikInputView = 'logistik.input.view';
    case LogistikInputWrite = 'logistik.input.write';
    case LogistikLaporanView = 'logistik.laporan.view';
    case LogistikMasterViewAny = 'logistik.master.view_any';
    case LogistikMasterManage = 'logistik.master.manage';

    case PdmInputView = 'pdm.input.view';
    case PdmInputWrite = 'pdm.input.write';
    case PdmLaporanView = 'pdm.laporan.view';
    case PdmMasterViewAny = 'pdm.master.view_any';
    case PdmMasterManage = 'pdm.master.manage';

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

            self::OperasiLapanganDailyReport,
            self::OperasiLapanganStarStop,
            self::OperasiLapanganFeeder,
            self::OperasiLapanganAuxiliary,
            self::OperasiLapanganFuelReceipt,
            self::OperasiLapanganKondisiAbnormal,
            self::OperasiLapanganResourcePembangkit,
            self::OperasiLapanganMaterialPeralatan,
            self::OperasiLapanganPermitToWork,
            self::OperasiLapanganFlmMonitoring,
            self::OperasiLapanganPatrolCheckMesin,
            self::OperasiLapanganChecklistCommissioning,
            self::OperasiLapanganUnsafeCondition,
            self::OperasiLapanganProgram5s5r => PermissionGroup::OperasiLapangan,

            self::OperatorLogsheetView,
            self::OperatorLogsheetWrite,
            self::OperatorAbsensiView,
            self::OperatorAbsensiWrite,
            self::OperatorPresensi => PermissionGroup::Operator,

            self::HarInputView,
            self::HarInputWrite,
            self::HarLaporanView,
            self::HarExecutiveView,
            self::HarMasterViewAny,
            self::HarMasterManage => PermissionGroup::Pemeliharaan,

            self::HarLapanganWorkOrder,
            self::HarLapanganServiceRequest,
            self::HarLapanganActivity,
            self::HarLapanganCost,
            self::HarLapanganSchedule,
            self::HarLapanganAttachment,
            self::HarLapanganUnsafeCondition,
            self::HarLapanganRekapGangguan,
            self::HarLapanganAbnormalGangguan,
            self::HarLapanganProgram5s5r,
            self::HarLapanganPatrolCheckPemeliharaan,
            self::HarLapanganPatrolCheckParameter,
            self::HarLapanganPrelubeTest,
            self::HarLapanganHydrotest,
            self::HarLapanganTimingInjectionPump,
            self::HarLapanganCrankshaftDeflection,
            self::HarLapanganCounterWeight,
            self::HarLapanganAxialConrod,
            self::HarLapanganClearanceValve,
            self::HarLapanganCombustionPressure,
            self::HarLapanganInjectorPressure,
            self::HarLapanganMotorCurrent,
            self::HarLapanganVibration,
            self::HarLapanganLubeQuality,
            self::HarLapanganBatteryVoltage,
            self::HarLapanganLaporanGangguan,
            self::HarLapanganDailyMeeting,
            self::HarLapanganLogbookMutasi => PermissionGroup::PemeliharaanLapangan,

            self::K3InputView,
            self::K3InputWrite,
            self::K3LaporanView,
            self::K3MonitoringView,
            self::K3MasterViewAny,
            self::K3MasterManage => PermissionGroup::K3,

            self::LogistikInputView,
            self::LogistikInputWrite,
            self::LogistikLaporanView,
            self::LogistikMasterViewAny,
            self::LogistikMasterManage => PermissionGroup::Logistik,

            self::PdmInputView,
            self::PdmInputWrite,
            self::PdmLaporanView,
            self::PdmMasterViewAny,
            self::PdmMasterManage => PermissionGroup::Pdm,

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
            self::OperasiLapanganDailyReport => 'Input lapangan: Input Harian',
            self::OperasiLapanganStarStop => 'Input lapangan: Star-Stop Mesin',
            self::OperasiLapanganFeeder => 'Input lapangan: Feeder',
            self::OperasiLapanganAuxiliary => 'Input lapangan: Pasokan Cadangan',
            self::OperasiLapanganFuelReceipt => 'Input lapangan: Penerimaan BBM',
            self::OperasiLapanganKondisiAbnormal => 'Input lapangan: Kondisi Abnormal & Gangguan',
            self::OperasiLapanganResourcePembangkit => 'Input lapangan: Resource Pembangkit',
            self::OperasiLapanganMaterialPeralatan => 'Input lapangan: Material & Peralatan',
            self::OperasiLapanganPermitToWork => 'Input lapangan: Permit to Work (PTW)',
            self::OperasiLapanganFlmMonitoring => 'Input lapangan: Monitoring FLM',
            self::OperasiLapanganPatrolCheckMesin => 'Input lapangan: Patrol Check Mesin',
            self::OperasiLapanganChecklistCommissioning => 'Input lapangan: Checklist Commissioning Test Mesin',
            self::OperasiLapanganUnsafeCondition => 'Input lapangan: Unsafe Action & Unsafe Condition',
            self::OperasiLapanganProgram5s5r => 'Input lapangan: Program 5S 5R Pengoperasian KIT',
            self::OperatorLogsheetView => 'Melihat logsheet operator',
            self::OperatorLogsheetWrite => 'Mengisi logsheet operator',
            self::OperatorAbsensiView => 'Melihat jadwal & absensi shift',
            self::OperatorAbsensiWrite => 'Menjadwalkan & mengisi absensi shift',
            self::OperatorPresensi => 'Absen masuk & pulang dalam radius kantor',

            self::HarInputView => 'Melihat input pemeliharaan',
            self::HarInputWrite => 'Mengisi input pemeliharaan (WO/SR, log kegiatan, biaya, foto)',
            self::HarLaporanView => 'Melihat & mencetak laporan pemeliharaan',
            self::HarExecutiveView => 'Melihat executive summary pemeliharaan',
            self::HarMasterViewAny => 'Melihat master data pemeliharaan',
            self::HarMasterManage => 'Mengelola master data pemeliharaan',
            self::HarLapanganWorkOrder => 'Input lapangan: Work Order',
            self::HarLapanganServiceRequest => 'Input lapangan: Service Request',
            self::HarLapanganActivity => 'Input lapangan: Log Kegiatan',
            self::HarLapanganCost => 'Input lapangan: Biaya',
            self::HarLapanganSchedule => 'Input lapangan: Rencana vs Realisasi',
            self::HarLapanganAttachment => 'Input lapangan: Lampiran Foto',
            self::HarLapanganUnsafeCondition => 'Input lapangan: Unsafe Action & Unsafe Condition',
            self::HarLapanganRekapGangguan => 'Input lapangan: Rekap Laporan Gangguan',
            self::HarLapanganAbnormalGangguan => 'Input lapangan: Laporan Kondisi Abnormal & Gangguan',
            self::HarLapanganProgram5s5r => 'Input lapangan: Jadwal Program 5S 5R Pemeliharaan',
            self::HarLapanganPatrolCheckPemeliharaan => 'Input lapangan: Laporan Patrol Check Pemeliharaan',
            self::HarLapanganPatrolCheckParameter => 'Input lapangan: Patrol Check Parameter Mesin',
            self::HarLapanganPrelubeTest => 'Input lapangan: Formulir Checklist Prelube Test',
            self::HarLapanganHydrotest => 'Input lapangan: Formulir Checklist Hydrotest',
            self::HarLapanganTimingInjectionPump => 'Input lapangan: Formulir Timing Injection Pump',
            self::HarLapanganCrankshaftDeflection => 'Input lapangan: Formulir Defleksi Crankshaft',
            self::HarLapanganCounterWeight => 'Input lapangan: Formulir Baut Counter Weight',
            self::HarLapanganAxialConrod => 'Input lapangan: Formulir Axial Conrod & Baut Conrod',
            self::HarLapanganClearanceValve => 'Input lapangan: Formulir Clearance Valve',
            self::HarLapanganCombustionPressure => 'Input lapangan: Formulir Tekanan Pembakaran',
            self::HarLapanganInjectorPressure => 'Input lapangan: Formulir Tekanan Pengabutan Injektor',
            self::HarLapanganMotorCurrent => 'Input lapangan: Data Arus Kerja Elektro Motor',
            self::HarLapanganVibration => 'Input lapangan: Formulir Tekanan Vibrasi',
            self::HarLapanganLubeQuality => 'Input lapangan: Formulir Kualitas Pelumas',
            self::HarLapanganBatteryVoltage => 'Input lapangan: Formulir Tegangan Baterai',
            self::HarLapanganLaporanGangguan => 'Input lapangan: Formulir Laporan Gangguan (LH-05)',
            self::HarLapanganDailyMeeting => 'Input lapangan: Formulir Daily Meeting',
            self::HarLapanganLogbookMutasi => 'Input lapangan: Form Logbook Mutasi Harian',

            self::K3InputView => 'Melihat input K3 & keamanan',
            self::K3InputWrite => 'Mengisi input K3 & keamanan (inspeksi, patroli, sertifikat, lampiran)',
            self::K3LaporanView => 'Melihat & mencetak laporan K3 & keamanan',
            self::K3MonitoringView => 'Melihat monitoring status K3 (sertifikat/APAR)',
            self::K3MasterViewAny => 'Melihat master data K3 & keamanan',
            self::K3MasterManage => 'Mengelola master data K3 & keamanan',

            self::LogistikInputView => 'Melihat input logistik & gudang',
            self::LogistikInputWrite => 'Mengisi input logistik & gudang (patrol check, inventaris, stok, permit to work)',
            self::LogistikLaporanView => 'Melihat & mencetak laporan logistik & gudang',
            self::LogistikMasterViewAny => 'Melihat master data logistik & gudang',
            self::LogistikMasterManage => 'Mengelola master data logistik & gudang',

            self::PdmInputView => 'Melihat input PdM & maturity level',
            self::PdmInputWrite => 'Mengisi input PdM & maturity level (kesiapan APD, patrol check, checklist, log sheet)',
            self::PdmLaporanView => 'Melihat & mencetak laporan PdM & maturity level',
            self::PdmMasterViewAny => 'Melihat master data PdM & maturity level',
            self::PdmMasterManage => 'Mengelola master data PdM & maturity level',

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
     * The Operasi input pages opened to shift operators (one permission each).
     *
     * @return list<self>
     */
    public static function operasiLapangan(): array
    {
        return array_values(array_filter(self::cases(), fn (self $p): bool => $p->group() === PermissionGroup::OperasiLapangan));
    }

    /**
     * The Pemeliharaan input & formulir pages opened to Harmes / Harlist (one permission each).
     *
     * @return list<self>
     */
    public static function harLapangan(): array
    {
        return array_values(array_filter(self::cases(), fn (self $p): bool => $p->group() === PermissionGroup::PemeliharaanLapangan));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}
