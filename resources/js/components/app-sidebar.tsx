import { Link } from '@inertiajs/react';
import {
    Building2,
    Factory,
    History,
    LayoutGrid,
    ShieldCheck,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';

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
import roles from '@/routes/admin/roles';
import serviceUnits from '@/routes/admin/service-units';
import units from '@/routes/admin/units';
import users from '@/routes/admin/users';
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
                {groups.map((group) => (
                    <NavMain
                        key={group.label}
                        label={group.label}
                        items={group.items}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
