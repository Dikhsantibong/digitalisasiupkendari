import { Link } from '@inertiajs/react';
import {
    Building2,
    CalendarRange,
    ClipboardCheck,
    ClipboardList,
    Cog,
    Database,
    Factory,
    FileBarChart,
    Flame,
    FileCog,
    FileSignature,
    Fuel,
    Gauge,
    HardHat,
    History,
    Image,
    LayoutGrid,
    NotebookPen,
    Plug,
    ScrollText,
    ShieldCheck,
    Siren,
    TimerReset,
    UserCog,
    Users,
    Wallet,
    Wrench,
    Zap,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';

import { NavCollapsible } from '@/components/nav-collapsible';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import activityLogs from '@/routes/admin/activity-logs';
import employees from '@/routes/admin/employees';
import machines from '@/routes/admin/machines';
import roles from '@/routes/admin/roles';
import serviceUnits from '@/routes/admin/service-units';
import units from '@/routes/admin/units';
import users from '@/routes/admin/users';
import harActivity from '@/routes/har/input/activity';
import harAttachment from '@/routes/har/input/attachment';
import harCost from '@/routes/har/input/cost';
import harSchedule from '@/routes/har/input/schedule';
import harServiceRequest from '@/routes/har/input/service-request';
import harWorkOrder from '@/routes/har/input/work-order';
import harLaporan from '@/routes/har/laporan';
import harMaster from '@/routes/har/master';
import k3Accident from '@/routes/k3/input/accident';
import k3AparCheck from '@/routes/k3/input/apar-check';
import k3Attachment from '@/routes/k3/input/attachment';
import k3Certificate from '@/routes/k3/input/certificate';
import k3Emergency from '@/routes/k3/input/emergency';
import k3Inspection from '@/routes/k3/input/inspection';
import k3Patrol from '@/routes/k3/input/patrol';
import k3TimeFrame from '@/routes/k3/input/time-frame';
import k3Laporan from '@/routes/k3/laporan';
import k3Master from '@/routes/k3/master';
import k3Monitoring from '@/routes/k3/monitoring';
import beritaAcara from '@/routes/operasi/berita-acara';
import documentTemplate from '@/routes/operasi/document-template';
import auxiliary from '@/routes/operasi/input/auxiliary';
import dailyReport from '@/routes/operasi/input/daily-report';
import feeder from '@/routes/operasi/input/feeder';
import fuelReceipt from '@/routes/operasi/input/fuel-receipt';
import starStop from '@/routes/operasi/input/star-stop';
import laporan from '@/routes/operasi/laporan';
import master from '@/routes/operasi/master';
import absensi from '@/routes/operator/absensi';
import logsheet from '@/routes/operator/logsheet';
import type { NavGroup } from '@/types';

export function AppSidebar() {
    const { can } = usePermissions();

    const groups: NavGroup[] = [
        {
            label: 'Umum',
            items: [
                { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            ],
        },
        {
            label: 'Master Data',
            items: [
                can('service_unit.view_any') && {
                    title: 'Unit Layanan',
                    href: serviceUnits.index(),
                    icon: Building2,
                },
                can('unit.view_any') && {
                    title: 'Unit Pembangkit',
                    href: units.index(),
                    icon: Factory,
                },
                can('machine.view_any') && {
                    title: 'Master Mesin',
                    href: machines.index(),
                    icon: Cog,
                },
                can('employee.view_any') && {
                    title: 'Master Pegawai',
                    href: employees.index(),
                    icon: UserCog,
                },
                can('operasi.master.view_any') && {
                    title: 'Master Operasi',
                    href: master.index('feeders'),
                    icon: Database,
                },
                can('har.master.view_any') && {
                    title: 'Master Pemeliharaan',
                    href: harMaster.index('maintenance-types'),
                    icon: Wrench,
                },
                can('k3.master.view_any') && {
                    title: 'Master K3 & Keamanan',
                    href: k3Master.index('activity-types'),
                    icon: HardHat,
                },
            ].filter(Boolean) as NavGroup['items'],
        },
        {
            label: 'Operator',
            items: [
                can('operator.logsheet.view') && {
                    title: 'Logsheet Operator',
                    href: logsheet.index(),
                    icon: NotebookPen,
                },
                can('operator.absensi.view') && {
                    title: 'Absensi & Jadwal',
                    href: absensi.index(),
                    icon: CalendarRange,
                },
            ].filter(Boolean) as NavGroup['items'],
        },
        {
            label: 'Operasi',
            items: [
                can('operasi.input.view') && {
                    title: 'Input Harian',
                    href: dailyReport.index(),
                    icon: Gauge,
                },
                can('operasi.input.view') && {
                    title: 'Star-Stop',
                    href: starStop.index(),
                    icon: TimerReset,
                },
                can('operasi.input.view') && {
                    title: 'Feeder',
                    href: feeder.index(),
                    icon: Zap,
                },
                can('operasi.input.view') && {
                    title: 'Pasokan Cadangan',
                    href: auxiliary.index(),
                    icon: Plug,
                },
                can('operasi.input.view') && {
                    title: 'Penerimaan BBM',
                    href: fuelReceipt.index(),
                    icon: Fuel,
                },
                can('operasi.laporan.view') && {
                    title: 'Laporan',
                    href: laporan.index(),
                    icon: FileBarChart,
                },
                can('operasi.berita_acara.view') && {
                    title: 'Berita Acara',
                    href: beritaAcara.index(),
                    icon: FileSignature,
                },
                can('operasi.master.manage') && {
                    title: 'Template BA',
                    href: documentTemplate.index(),
                    icon: FileCog,
                },
            ].filter(Boolean) as NavGroup['items'],
        },
        {
            label: 'Pemeliharaan',
            items: [
                can('har.input.view') && {
                    title: 'Work Order',
                    href: harWorkOrder.index(),
                    icon: ClipboardList,
                },
                can('har.input.view') && {
                    title: 'Service Request',
                    href: harServiceRequest.index(),
                    icon: ClipboardCheck,
                },
                can('har.input.view') && {
                    title: 'Log Kegiatan',
                    href: harActivity.index(),
                    icon: NotebookPen,
                },
                can('har.input.view') && {
                    title: 'Biaya',
                    href: harCost.index(),
                    icon: Wallet,
                },
                can('har.input.view') && {
                    title: 'Rencana vs Realisasi',
                    href: harSchedule.index(),
                    icon: CalendarRange,
                },
                can('har.input.view') && {
                    title: 'Lampiran Foto',
                    href: harAttachment.index(),
                    icon: Image,
                },
                can('har.laporan.view') && {
                    title: 'Laporan HAR',
                    href: harLaporan.index(),
                    icon: FileBarChart,
                },
            ].filter(Boolean) as NavGroup['items'],
        },
        {
            label: 'K3 & Keamanan',
            items: [
                can('k3.input.view') && {
                    title: 'Time Frame',
                    href: k3TimeFrame.index(),
                    icon: CalendarRange,
                },
                can('k3.input.view') && {
                    title: 'Laporan Kecelakaan',
                    href: k3Accident.index(),
                    icon: ClipboardCheck,
                },
                can('k3.input.view') && {
                    title: 'Inspeksi Checklist',
                    href: k3Inspection.index(),
                    icon: ClipboardList,
                },
                can('k3.input.view') && {
                    title: 'Inspeksi APAR/APAB',
                    href: k3AparCheck.index(),
                    icon: Flame,
                },
                can('k3.input.view') && {
                    title: 'Fasilitas Darurat',
                    href: k3Emergency.index(),
                    icon: Siren,
                },
                can('k3.input.view') && {
                    title: 'Patroli Keamanan',
                    href: k3Patrol.index(),
                    icon: ShieldCheck,
                },
                can('k3.input.view') && {
                    title: 'Sertifikasi Peralatan',
                    href: k3Certificate.index(),
                    icon: ScrollText,
                },
                can('k3.input.view') && {
                    title: 'Lampiran K3',
                    href: k3Attachment.index(),
                    icon: Image,
                },
                can('k3.monitoring.view') && {
                    title: 'Monitoring K3',
                    href: k3Monitoring.index(),
                    icon: Gauge,
                },
                can('k3.laporan.view') && {
                    title: 'Laporan K3',
                    href: k3Laporan.index(),
                    icon: FileBarChart,
                },
            ].filter(Boolean) as NavGroup['items'],
        },
        {
            label: 'Administrasi',
            items: [
                can('user.view_any') && {
                    title: 'Pengguna',
                    href: users.index(),
                    icon: Users,
                },
                can('role.view_any') && {
                    title: 'Role & Akses',
                    href: roles.index(),
                    icon: ShieldCheck,
                },
                can('activity_log.view_any') && {
                    title: 'Log Aktivitas',
                    href: activityLogs.index(),
                    icon: History,
                },
            ].filter(Boolean) as NavGroup['items'],
        },
    ].filter((group) => group.items.length > 0);

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {groups.map((group) =>
                    group.label === 'Umum' ? (
                        <NavMain
                            key={group.label}
                            label={group.label}
                            items={group.items}
                        />
                    ) : (
                        <NavCollapsible
                            key={group.label}
                            label={group.label}
                            items={group.items}
                        />
                    ),
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
