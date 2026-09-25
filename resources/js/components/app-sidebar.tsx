import { Link } from '@inertiajs/react';
import {
    Building2,
    CalendarRange,
    ClipboardCheck,
    ClipboardList,
    Fingerprint,
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
    MapPin,
    NotebookPen,
    Plug,
    ScrollText,
    ShieldCheck,
    Siren,
    SquarePen,
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
import attendanceLocations from '@/routes/admin/attendance-locations';
import employees from '@/routes/admin/employees';
import machines from '@/routes/admin/machines';
import roles from '@/routes/admin/roles';
import serviceUnits from '@/routes/admin/service-units';
import units from '@/routes/admin/units';
import users from '@/routes/admin/users';
import harFormulir from '@/routes/har/formulir';
import harInput from '@/routes/har/input';
import harJadwal from '@/routes/har/jadwal';
import harLaporan from '@/routes/har/laporan';
import harMaster from '@/routes/har/master';
import k3Formulir from '@/routes/k3/formulir';
import k3Input from '@/routes/k3/input';
import k3Jadwal from '@/routes/k3/jadwal';
import k3Laporan from '@/routes/k3/laporan';
import k3Master from '@/routes/k3/master';
import k3Monitoring from '@/routes/k3/monitoring';
import logistikInput from '@/routes/logistik/input';
import logistikJadwal from '@/routes/logistik/jadwal';
import logistikLaporan from '@/routes/logistik/laporan';
import beritaAcara from '@/routes/operasi/berita-acara';
import documentTemplate from '@/routes/operasi/document-template';
import operasiInput from '@/routes/operasi/input';
import operasiJadwal from '@/routes/operasi/jadwal';
import laporan from '@/routes/operasi/laporan';
import master from '@/routes/operasi/master';
import absensi from '@/routes/operator/absensi';
import logsheet from '@/routes/operator/logsheet';
import presensi from '@/routes/operator/presensi';
import pdmInput from '@/routes/pdm/input';
import pdmJadwal from '@/routes/pdm/jadwal';
import pdmLaporan from '@/routes/pdm/laporan';
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
                can('operator.presensi') && {
                    title: 'Absensi',
                    href: presensi.index(),
                    icon: Fingerprint,
                },
                can('operator.logsheet.view') && {
                    title: 'Logsheet Operator',
                    href: logsheet.index(),
                    icon: NotebookPen,
                },
                can('operator.absensi.view') && {
                    title: 'Jadwal Shift',
                    href: absensi.index(),
                    icon: CalendarRange,
                },
            ].filter(Boolean) as NavGroup['items'],
        },
        {
            label: 'Operasi',
            items: [
                can('operasi.input.view') && {
                    title: 'Jadwal',
                    href: operasiJadwal.index(),
                    icon: CalendarRange,
                },
                can('operasi.input.view') && {
                    title: 'Input',
                    href: operasiInput.index(),
                    icon: SquarePen,
                },
                can('operasi.berita_acara.view') && {
                    title: 'Berita Acara',
                    href: beritaAcara.index(),
                    icon: FileSignature,
                },
                can('operasi.laporan.view') && {
                    title: 'Laporan Operasi Pembangkit',
                    href: laporan.index(),
                    icon: FileBarChart,
                },
            ].filter(Boolean) as NavGroup['items'],
        },
        {
            label: 'Pemeliharaan',
            items: [
                can('har.input.view') && {
                    title: 'Jadwal',
                    href: harJadwal.index(),
                    icon: CalendarRange,
                },
                can('har.input.view') && {
                    title: 'Input',
                    href: harInput.index(),
                    icon: SquarePen,
                },
                can('har.input.view') && {
                    title: 'Formulir',
                    href: harFormulir.index(),
                    icon: ClipboardCheck,
                },
                can('har.laporan.view') && {
                    title: 'Laporan Pemeliharaan Pembangkit',
                    href: harLaporan.index(),
                    icon: FileBarChart,
                },
            ].filter(Boolean) as NavGroup['items'],
        },
        {
            label: 'K3 & Keamanan',
            items: [
                can('k3.input.view') && {
                    title: 'Jadwal',
                    href: k3Jadwal.index(),
                    icon: CalendarRange,
                },
                can('k3.input.view') && {
                    title: 'Input',
                    href: k3Input.index(),
                    icon: SquarePen,
                },
                can('k3.input.view') && {
                    title: 'Formulir',
                    href: k3Formulir.index(),
                    icon: ClipboardCheck,
                },
                can('k3.laporan.view') && {
                    title: 'Laporan K3 Lingkungan Pembangkit',
                    href: k3Laporan.index(),
                    icon: FileBarChart,
                },
            ].filter(Boolean) as NavGroup['items'],
        },
        {
            label: 'Logistik & Gudang',
            items: [
                can('logistik.input.view') && {
                    title: 'Jadwal',
                    href: logistikJadwal.index(),
                    icon: CalendarRange,
                },
                can('logistik.input.view') && {
                    title: 'Input',
                    href: logistikInput.index(),
                    icon: SquarePen,
                },
                can('logistik.laporan.view') && {
                    title: 'Laporan Logistik & Gudang',
                    href: logistikLaporan.index(),
                    icon: FileBarChart,
                },
            ].filter(Boolean) as NavGroup['items'],
        },
        {
            label: 'PdM & Maturity Level',
            items: [
                can('pdm.input.view') && {
                    title: 'Jadwal',
                    href: pdmJadwal.index(),
                    icon: CalendarRange,
                },
                can('pdm.input.view') && {
                    title: 'Input',
                    href: pdmInput.index(),
                    icon: SquarePen,
                },
                can('pdm.laporan.view') && {
                    title: 'Laporan PdM & Maturity Level',
                    href: pdmLaporan.index(),
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
                can('setting.manage') && {
                    title: 'Lokasi Absensi',
                    href: attendanceLocations.index(),
                    icon: MapPin,
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
