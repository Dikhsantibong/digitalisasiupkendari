import { Link, usePage } from '@inertiajs/react';
import {
    ChevronRight,
    ClipboardCheck,
    Cog,
    Eye,
    Factory,
    FileBarChart,
    FileCheck2,
    Home,
    LayoutGrid,
    Menu,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { AppContent } from '@/components/app-content';
import AppLogo from '@/components/app-logo';
import { AppShell } from '@/components/app-shell';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { NavUser } from '@/components/nav-user';
import { NotificationBell } from '@/components/notifications/notification-bell';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useIsMobile } from '@/hooks/use-mobile';
import { usePermissions } from '@/hooks/use-permissions';
import { cn, toUrl } from '@/lib/utils';
import employees from '@/routes/admin/employees';
import machines from '@/routes/admin/machines';
import units from '@/routes/admin/units';
import monitoring from '@/routes/monitoring';
import portal from '@/routes/portal';
import type { Auth, BreadcrumbItem } from '@/types';

type NavLink = {
    title: string;
    short: string;
    href: string;
    icon: typeof Home;
    match: string[];
};

type Nav = {
    auth: Auth;
    links: NavLink[];
    manage: NavLink[];
    active: (link: NavLink) => boolean;
    crumbs: BreadcrumbItem[];
};

/**
 * The Portal Pemantauan shell of the kantor induk UP Kendari (Manager UP, TL &
 * Asman bidang) and the Manager UL: a view-focused layout with only Beranda,
 * Data Input, Status Input, Status Laporan and Laporan (plus the master data a
 * non view-only account still manages). On a desktop it is the app's sidebar
 * shell; on a phone a top bar with bottom tabs. Any page reached from it (an
 * input or a report) renders inside it; for a view-only account the server
 * refuses every change, and the header says so.
 */
export default function PortalLayout({
    children,
    breadcrumbs = [],
}: {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}) {
    const auth = usePage().props.auth as Auth;
    const { can } = usePermissions();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const isMobile = useIsMobile();
    const pathname = usePage().url.split('?')[0];

    const links: NavLink[] = [
        {
            title: 'Beranda',
            short: 'Beranda',
            href: portal.index().url,
            icon: Home,
            match: [portal.index().url],
        },
        {
            title: 'Data Input',
            short: 'Input',
            href: portal.input().url,
            icon: LayoutGrid,
            match: [portal.input().url],
        },
        {
            title: 'Status Input',
            short: 'Status',
            href: monitoring.input().url,
            icon: ClipboardCheck,
            match: [monitoring.input().url, monitoring.index().url],
        },
        {
            title: 'Status Laporan',
            short: 'Verifikasi',
            href: monitoring.laporan().url,
            icon: FileCheck2,
            match: [monitoring.laporan().url],
        },
        {
            title: 'Laporan',
            short: 'Laporan',
            href: portal.laporan().url,
            icon: FileBarChart,
            match: [portal.laporan().url],
        },
    ];

    // Pages the account may still manage (e.g. the Manager UL's master data) — never for a view-only account.
    const manage: NavLink[] = auth.readOnly
        ? []
        : [
              can('unit.view_any') && {
                  title: 'Unit Pembangkit',
                  short: 'Unit',
                  href: units.index().url,
                  icon: Factory,
                  match: [units.index().url],
              },
              can('machine.view_any') && {
                  title: 'Master Mesin',
                  short: 'Mesin',
                  href: machines.index().url,
                  icon: Cog,
                  match: [machines.index().url],
              },
              can('employee.view_any') && {
                  title: 'Master Pegawai',
                  short: 'Pegawai',
                  href: employees.index().url,
                  icon: Users,
                  match: [employees.index().url],
              },
          ].filter((item): item is NavLink => Boolean(item));

    const active = (link: NavLink) =>
        link.href === portal.index().url
            ? pathname === portal.index().url
            : link.match.some((href) => isCurrentOrParentUrl(href));

    // A portal page has no breadcrumbs of its own: "Portal Pemantauan / <menu>".
    const current = links.find((link) => active(link));
    const crumbs: BreadcrumbItem[] =
        breadcrumbs.length > 0
            ? breadcrumbs.map((crumb) =>
                  crumb.title === 'Dashboard'
                      ? { title: 'Beranda', href: portal.index().url }
                      : crumb,
              )
            : [
                  { title: 'Portal Pemantauan', href: portal.index().url },
                  ...(current && current.href !== portal.index().url
                      ? [{ title: current.title, href: current.href }]
                      : []),
              ];

    const nav: Nav = { auth, links, manage, active, crumbs };

    if (isMobile) {
        return (
            <PortalMobile nav={nav} deep={breadcrumbs.length > 0 && !current}>
                {children}
            </PortalMobile>
        );
    }

    return <PortalDesktop nav={nav}>{children}</PortalDesktop>;
}

function ReadOnlyBadge({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'items-center gap-1 rounded-sm border border-primary/20 bg-primary/10 px-2 py-0.5 text-[12px] font-medium whitespace-nowrap text-primary',
                className,
            )}
        >
            <Eye className="size-3.5" />
            Mode lihat saja
        </span>
    );
}

/** Desktop & tablet: the same sidebar shell as the rest of the app, with the portal menu. */
function PortalDesktop({ nav, children }: { nav: Nav; children: ReactNode }) {
    const { auth, links, manage, active, crumbs } = nav;
    const groups = [
        { label: 'Pemantauan', items: links },
        ...(manage.length > 0 ? [{ label: 'Kelola Data', items: manage }] : []),
    ];

    return (
        <AppShell variant="sidebar">
            <Sidebar collapsible="icon">
                <SidebarHeader>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton size="lg" asChild>
                                <Link href={portal.index().url}>
                                    <AppLogo />
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarHeader>

                <SidebarContent>
                    {groups.map((group) => (
                        <SidebarGroup key={group.label} className="px-2 py-0">
                            <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                            <SidebarMenu>
                                {group.items.map((link) => (
                                    <SidebarMenuItem key={link.title}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={active(link)}
                                            tooltip={{ children: link.title }}
                                        >
                                            <Link href={link.href} prefetch>
                                                <link.icon />
                                                <span>{link.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroup>
                    ))}
                </SidebarContent>

                <SidebarFooter>
                    <NavUser />
                </SidebarFooter>
            </Sidebar>

            <AppContent variant="sidebar" className="overflow-x-hidden">
                <header className="flex h-12 shrink-0 items-center justify-between gap-2 border-b px-4">
                    <div className="flex min-w-0 items-center gap-2">
                        <SidebarTrigger className="-ml-1" />
                        <Breadcrumbs breadcrumbs={crumbs} />
                    </div>
                    <div className="flex shrink-0 items-center gap-3">
                        {auth.readOnly && <ReadOnlyBadge className="flex" />}
                        <span className="hidden text-[13px] text-muted-foreground xl:inline">
                            {auth.roles
                                .map((role) => role.display_name)
                                .join(', ')}
                        </span>
                        <NotificationBell />
                    </div>
                </header>
                {children}
            </AppContent>
        </AppShell>
    );
}

/** Phone: a compact top bar and bottom tabs. */
function PortalMobile({
    nav,
    deep,
    children,
}: {
    nav: Nav;
    deep: boolean;
    children: ReactNode;
}) {
    const { auth, links, manage, active, crumbs } = nav;

    return (
        <div className="flex min-h-dvh flex-col bg-background">
            <header className="sticky top-0 z-30 border-b border-border bg-card">
                <div className="flex h-14 w-full items-center gap-3 px-4">
                    <Link
                        href={portal.index().url}
                        className="flex shrink-0 items-center gap-2"
                    >
                        <img
                            src="/logo/sidebar-logo.png"
                            alt="PLN Nusantara Power"
                            className="h-7 w-auto"
                        />
                    </Link>

                    <div className="ml-auto flex items-center gap-2">
                        {auth.readOnly && (
                            <ReadOnlyBadge className="hidden min-[400px]:flex" />
                        )}
                        <NotificationBell variant="muted" />
                        <DropdownMenu>
                            <DropdownMenuTrigger
                                className="flex size-10 items-center justify-center rounded-md border border-border outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                                aria-label="Menu akun"
                            >
                                <Menu className="size-5 text-foreground" />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-60" align="end">
                                <UserMenuContent user={auth.user} />
                                {manage.length > 0 && (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuLabel>
                                            Kelola data
                                        </DropdownMenuLabel>
                                        {manage.map((item) => (
                                            <DropdownMenuItem
                                                key={item.title}
                                                asChild
                                            >
                                                <Link href={item.href}>
                                                    {item.title}
                                                </Link>
                                            </DropdownMenuItem>
                                        ))}
                                    </>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                {deep && (
                    <div className="flex w-full [scrollbar-width:none] items-center gap-1 overflow-x-auto px-4 pb-2 text-[12px] whitespace-nowrap text-muted-foreground [&::-webkit-scrollbar]:hidden">
                        {crumbs.map((crumb, index) => (
                            <span
                                key={`${crumb.title}-${index}`}
                                className="flex items-center gap-1"
                            >
                                {index > 0 && (
                                    <ChevronRight className="size-3" />
                                )}
                                {index === crumbs.length - 1 ? (
                                    <span className="font-medium text-foreground">
                                        {crumb.title}
                                    </span>
                                ) : (
                                    <Link
                                        href={toUrl(crumb.href)}
                                        className="hover:text-foreground"
                                    >
                                        {crumb.title}
                                    </Link>
                                )}
                            </span>
                        ))}
                    </div>
                )}
            </header>

            <main className="flex w-full min-w-0 flex-1 flex-col pb-20">
                {children}
            </main>

            <nav
                className="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-border bg-card pb-[env(safe-area-inset-bottom)]"
                aria-label="Portal"
            >
                {links.map((link) => (
                    <Link
                        key={link.title}
                        href={link.href}
                        aria-current={active(link) ? 'page' : undefined}
                        className={cn(
                            'relative flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium',
                            active(link)
                                ? 'text-primary before:absolute before:inset-x-4 before:top-0 before:h-0.5 before:bg-primary'
                                : 'text-muted-foreground',
                        )}
                    >
                        <link.icon className="size-5" />
                        {link.short}
                    </Link>
                ))}
            </nav>
        </div>
    );
}
