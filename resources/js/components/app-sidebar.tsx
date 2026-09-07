import { Link } from '@inertiajs/react';
import {
    Building2,
    Cog,
    Database,
    Factory,
    FileBarChart,
    FileCog,
    FileSignature,
    Fuel,
    Gauge,
    History,
    LayoutGrid,
    Plug,
    ShieldCheck,
    TimerReset,
    UserCog,
    Users,
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
import beritaAcara from '@/routes/operasi/berita-acara';
import documentTemplate from '@/routes/operasi/document-template';
import auxiliary from '@/routes/operasi/input/auxiliary';
import dailyReport from '@/routes/operasi/input/daily-report';
import feeder from '@/routes/operasi/input/feeder';
import fuelReceipt from '@/routes/operasi/input/fuel-receipt';
import starStop from '@/routes/operasi/input/star-stop';
import laporan from '@/routes/operasi/laporan';
import master from '@/routes/operasi/master';
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
