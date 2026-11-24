import { Link, usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    Target,
    BarChart3,
    Building2,
    CalendarRange,
    ClipboardCheck,
    Fingerprint,
    FilePen,
    Cog,
    Database,
    FileCog,
    Factory,
    FileBarChart,
    FileCheck2,
    Gauge,
    HardHat,
    History,
    LayoutGrid,
    MapPin,
    NotebookPen,
    ShieldCheck,
    SquarePen,
    UserCheck,
    UserCog,
    Users,
    Wrench,
    Pin,
    PinOff,
    Power,
    Fuel,
    Droplets,
    Zap,
    Store,
    Wallet,
} from 'lucide-react';
import { useRef } from 'react';
import AppLogo from '@/components/app-logo';
import { useSidebarPin } from '@/components/app-shell';

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
    useSidebar,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { FIELD_MENUS } from '@/layouts/mobile/modules';
import { MOBILE_MENU_GROUPS } from '@/layouts/mobile/types';
import {
    PENGUSAHAAN_MENUS,
    PENGUSAHAAN_MODULES,
    PENGUSAHAAN_SECTIONS,
    pengusahaanSection,
} from '@/lib/pengusahaan-menus';
import type { PengusahaanModuleKey } from '@/lib/pengusahaan-menus';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import activityLogs from '@/routes/admin/activity-logs';
import attendanceLocations from '@/routes/admin/attendance-locations';
import employees from '@/routes/admin/employees';
import machines from '@/routes/admin/machines';
import reportSigners from '@/routes/admin/report-signers';
import roles from '@/routes/admin/roles';
import serviceUnits from '@/routes/admin/service-units';
import units from '@/routes/admin/units';
import users from '@/routes/admin/users';
import approvals from '@/routes/approvals';
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
import keuanganCashCard from '@/routes/keuangan/cash-card';
import keuanganSupplier from '@/routes/keuangan/supplier';
import laporanHubRoutes from '@/routes/laporan';
import laporanUl from '@/routes/laporan-ul';
import logistikInput from '@/routes/logistik/input';
import logistikJadwal from '@/routes/logistik/jadwal';
import logistikLaporan from '@/routes/logistik/laporan';
import monitoringRoutes from '@/routes/monitoring';
import operasiInput from '@/routes/operasi/input';
import operasiJadwal from '@/routes/operasi/jadwal';
import laporan from '@/routes/operasi/laporan';
import master from '@/routes/operasi/master';
import patrolCheckTemplate from '@/routes/operasi/patrol-check-template';
import absensi from '@/routes/operator/absensi';
import logsheet from '@/routes/operator/logsheet';
import logsheetTemplate from '@/routes/operator/logsheet-template';
import mutasiOperator from '@/routes/operator/mutasi';
import opnameBbm from '@/routes/operator/opname-bbm';
import opnameKwh from '@/routes/operator/opname-kwh';
import opnamePelumas from '@/routes/operator/opname-pelumas';
import pengajuan from '@/routes/operator/pengajuan';
import presensi from '@/routes/operator/presensi';
import resumeOperator from '@/routes/operator/resume';
import startStop from '@/routes/operator/start-stop';
import pdmInput from '@/routes/pdm/input';
import pdmJadwal from '@/routes/pdm/jadwal';
import pdmLaporan from '@/routes/pdm/laporan';
import sla from '@/routes/sla';
import type { Auth, NavGroup } from '@/types';

/** A menu longer than this many rows becomes collapsible groups (user, 2026-11-19). */
const LONG_MENU_ROWS = 18;

export function AppSidebar() {
    const { can, isSuperAdmin } = usePermissions();
    // Project Leader (laporan.hub): one "Laporan" menu with every report instead of one per module (user, 2026-11-18).
    const laporanHub = can('laporan.hub') && !isSuperAdmin;
    const { pinned, togglePinned, setPeek } = useSidebarPin();
    const { open: peeking } = useSidebar();
    const hoverTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    /** Unlocked: open while the cursor is on the sidebar, fold back shortly after it leaves. */
    const hover = (inside: boolean) => {
        if (pinned) {
            return;
        }

        if (hoverTimer.current) {
            clearTimeout(hoverTimer.current);
        }

        hoverTimer.current = setTimeout(
            () => setPeek(inside),
            inside ? 120 : 250,
        );
    };

    const approvalCount = (usePage().props.auth as Auth).approvals ?? null;

    // Akses 2 (TL & Staf) menus of a module, listed inside the module's own
    // group before its Laporan (registry: lib/pengusahaan-menus.ts, shown to
    // holders of `*.pengusahaan.view`), named by the divisi only (user,
    // 2026-11-17: no "Pengusahaan"). Someone holding Akses 1 too sees the Akses 1
    // Input / Formulir suffixed "Project" (projectTitle) so the menus stay apart.
    const pengusahaanItems = (key: PengusahaanModuleKey): NavGroup['items'] => {
        const module = PENGUSAHAAN_MODULES.find((item) => item.key === key)!;

        if (!can(module.permission)) {
            return [];
        }

        // A section shows only when the module has pages in it; Operasi names its Formulir "Berita Acara".
        // (only the sections with a page the user may open: the operators get Input, not Berita Acara).
        return PENGUSAHAAN_SECTIONS.filter((section) =>
            PENGUSAHAAN_MENUS.some(
                (menu) =>
                    menu.module === key &&
                    menu.section === section.key &&
                    can(menu.permission),
            ),
        ).map((section) => {
            const { title, icon } = pengusahaanSection(section.key, key);

            return {
                // Named after the divisi ("Input Operasi", "Formulir Pemeliharaan") so it never reads
                // as the Akses 1 "Input Project"; a module's own name (Berita Acara) stays as is.
                title: section.modules?.[key]
                    ? title
                    : `${title} ${module.title}`,
                href: module.hub(section.key),
                icon,
            };
        });
    };

    /** An Akses 1 (Laporan Project) menu title, suffixed "Project" when the Akses 2 menus of the divisi show too. */
    const projectTitle = (title: string, key: PengusahaanModuleKey): string => {
        const module = PENGUSAHAAN_MODULES.find((item) => item.key === key)!;

        return can(module.permission) ? `${title} Project` : title;
    };

    const groups: NavGroup[] = [
        {
            label: 'Umum',
            items: [
                { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
                // Team Leader, Koordinator, Project Leader: what waits for their decision.
                ...(approvalCount !== null
                    ? [
                          {
                              title:
                                  approvalCount > 0
                                      ? `Persetujuan (${approvalCount})`
                                      : 'Persetujuan',
                              href: approvals.index(),
                              icon: BadgeCheck,
                          },
                      ]
                    : []),
            ],
        },
        // Monitoring lintas modul (Super Admin, atau role yang diberi monitoring.view).
        {
            label: 'Monitoring',
            items: [
                can('monitoring.view') && {
                    title: 'Ringkasan Monitoring',
                    href: monitoringRoutes.index(),
                    icon: Gauge,
                },
                can('monitoring.view') && {
                    title: 'Kelengkapan Input',
                    href: monitoringRoutes.input(),
                    icon: ClipboardCheck,
                },
                can('monitoring.view') && {
                    title: 'Verifikasi Laporan',
                    href: monitoringRoutes.laporan(),
                    icon: FileCheck2,
                },
                can('activity_log.view_any') && {
                    title: 'Log Aktivitas',
                    href: activityLogs.index(),
                    icon: History,
                },
            ].filter(Boolean) as NavGroup['items'],
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
                (can('operasi.master.view_any') || can('setting.manage')) && {
                    title: 'Template Form Mesin',
                    href: logsheetTemplate.index(),
                    icon: FileCog,
                },
                (can('operasi.master.view_any') || can('setting.manage')) && {
                    title: 'Template Patrol Check',
                    href: patrolCheckTemplate.index(),
                    icon: ClipboardCheck,
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
                can('operator.logsheet.view') && {
                    title: 'Start Stop Engine',
                    href: startStop.index(),
                    icon: Power,
                },
                can('operator.logsheet.view') && {
                    title: 'Opname BBM',
                    href: opnameBbm.index(),
                    icon: Fuel,
                },
                can('operator.logsheet.view') && {
                    title: 'Opname Pelumas',
                    href: opnamePelumas.index(),
                    icon: Droplets,
                },
                can('operator.logsheet.view') && {
                    title: 'Opname kWh',
                    href: opnameKwh.index(),
                    icon: Zap,
                },
                can('operator.mutasi.view') && {
                    title: 'Lembar Mutasi',
                    href: mutasiOperator.index(),
                    icon: ClipboardCheck,
                },
                can('operator.absensi.view') && {
                    title: 'Jadwal Shift',
                    href: absensi.index(),
                    icon: CalendarRange,
                },
                can([
                    'operator.presensi',
                    'operator.absensi.view',
                    'operator.absensi.write',
                    'operator.pengajuan.approve_operasi',
                    'operator.pengajuan.approve_har',
                ]) && {
                    title: 'Pengajuan Absensi',
                    href: pengajuan.index(),
                    icon: FilePen,
                },
                (can('operator.presensi') || can('operator.absensi.view')) && {
                    title: 'Resume Statistik',
                    href: resumeOperator.index(),
                    icon: BarChart3,
                },
            ].filter(Boolean) as NavGroup['items'],
        },
        // Module pages opened to field staff (operators: Input Operasi,
        // Harmes / Harlist: Input & Formulir Pemeliharaan), one group per
        // section; roles with the full module input already reach them under
        // their own module.
        ...MOBILE_MENU_GROUPS.filter((section) => section.key !== 'umum').map(
            (section) => ({
                label: section.label,
                items: FIELD_MENUS.filter(
                    (menu) =>
                        menu.group === section.key &&
                        can(menu.permission) &&
                        !can(menu.coveredBy ?? ''),
                ).map((menu) => ({
                    title: menu.title,
                    href: menu.href,
                    icon: menu.icon,
                })),
            }),
        ),
        {
            label: 'Operasi',
            items: [
                can('operasi.input.view') && {
                    title: 'Jadwal',
                    href: operasiJadwal.index(),
                    icon: CalendarRange,
                },
                can('operasi.input.view') && {
                    title: projectTitle('Input', 'operasi'),
                    href: operasiInput.index(),
                    icon: SquarePen,
                },
                ...pengusahaanItems('operasi'),
                !laporanHub &&
                    can([
                        'operasi.laporan.view',
                        'operasi.pengusahaan.view',
                    ]) && {
                        title: 'Laporan',
                        href: laporan.index(),
                        icon: FileBarChart,
                    },
                isSuperAdmin &&
                    can([
                        'operasi.laporan.view',
                        'operasi.pengusahaan.view',
                    ]) && {
                        title: 'SLA',
                        href: sla.show('operasi'),
                        icon: Target,
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
                    title: projectTitle('Input', 'har'),
                    href: harInput.index(),
                    icon: SquarePen,
                },
                can('har.input.view') && {
                    title: projectTitle('Formulir', 'har'),
                    href: harFormulir.index(),
                    icon: ClipboardCheck,
                },
                // Project Leader (and field staff) hold only the Daily Meeting page.
                !can('har.input.view') &&
                    can('har.lapangan.daily_meeting') && {
                        title: 'Daily Meeting',
                        href: harFormulir.dailyMeeting.index(),
                        icon: ClipboardCheck,
                    },
                ...pengusahaanItems('har'),
                !laporanHub &&
                    can(['har.laporan.view', 'har.pengusahaan.view']) && {
                        title: 'Laporan',
                        href: harLaporan.index(),
                        icon: FileBarChart,
                    },
                isSuperAdmin &&
                    can(['har.laporan.view', 'har.pengusahaan.view']) && {
                        title: 'SLA',
                        href: sla.show('pemeliharaan'),
                        icon: Target,
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
                    title: projectTitle('Input', 'k3'),
                    href: k3Input.index(),
                    icon: SquarePen,
                },
                can('k3.input.view') && {
                    title: projectTitle('Formulir', 'k3'),
                    href: k3Formulir.index(),
                    icon: ClipboardCheck,
                },
                ...pengusahaanItems('k3'),
                !laporanHub &&
                    can(['k3.laporan.view', 'k3.pengusahaan.view']) && {
                        title: 'Laporan',
                        href: k3Laporan.index(),
                        icon: FileBarChart,
                    },
                isSuperAdmin &&
                    can(['k3.laporan.view', 'k3.pengusahaan.view']) && {
                        title: 'SLA',
                        href: sla.show('k3'),
                        icon: Target,
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
                !laporanHub &&
                    can('logistik.laporan.view') && {
                        title: 'Laporan',
                        href: logistikLaporan.index(),
                        icon: FileBarChart,
                    },
                isSuperAdmin &&
                    can('logistik.laporan.view') && {
                        title: 'SLA',
                        href: sla.show('logistik'),
                        icon: Target,
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
                !laporanHub &&
                    can('pdm.laporan.view') && {
                        title: 'Laporan',
                        href: pdmLaporan.index(),
                        icon: FileBarChart,
                    },
                isSuperAdmin &&
                    can('pdm.laporan.view') && {
                        title: 'SLA',
                        href: sla.show('pdm'),
                        icon: Target,
                    },
            ].filter(Boolean) as NavGroup['items'],
        },
        // Project Leader: the single Laporan menu and the Laporan SLA gabungan.
        // Super Admin: the SLA gabungan of every module in one file too (user, 2026-11-19).
        {
            label: 'Laporan',
            items: [
                laporanHub && {
                    title: 'Laporan',
                    href: laporanHubRoutes.hub(),
                    icon: FileBarChart,
                },
                (laporanHub || isSuperAdmin) &&
                    can('sla.gabungan') && {
                        title: isSuperAdmin ? 'SLA Gabungan' : 'SLA',
                        href: sla.combined.index(),
                        icon: Target,
                    },
            ].filter(Boolean) as NavGroup['items'],
        },
        // Administrasi & Keuangan per UL (user, 2026-11-20): JOF Administrasi Umum (Manager UL & Admin UL read).
        {
            label: 'Administrasi & Keuangan',
            items: [
                can('keuangan.view') && {
                    title: 'Laporan Cash Card',
                    href: keuanganCashCard.index(),
                    icon: Wallet,
                },
                can('keuangan.view') && {
                    title: 'Master Supplier',
                    href: keuanganSupplier.index(),
                    icon: Store,
                },
            ].filter(Boolean) as NavGroup['items'],
        },
        // Admin UL (& Super Admin): Laporan Pengusahaan Pembangkit, Logistik, K3L & PPT bulanan of the UL — just above Administrasi (user, 2026-11-18).
        {
            label: 'Laporan UL',
            items: [
                can('laporan_ul.view') && {
                    title: 'Laporan UL',
                    href: laporanUl.index(),
                    icon: Building2,
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
                can('role.assign') && {
                    title: 'Penanda Tangan Laporan',
                    href: reportSigners.index(),
                    icon: UserCheck,
                },
                can('setting.manage') && {
                    title: 'Lokasi Absensi',
                    href: attendanceLocations.index(),
                    icon: MapPin,
                },
            ].filter(Boolean) as NavGroup['items'],
        },
    ].filter((group) => group.items.length > 0);

    // Rows a flat menu would take (group headings, items, sub-items): past this it needs scrolling
    // on a laptop screen, so the groups become dropdowns like the Super Admin's.
    const menuRows = groups.reduce(
        (rows, group) =>
            rows +
            1 +
            group.items.reduce(
                (count, item) => count + 1 + (item.items?.length ?? 0),
                0,
            ),
        0,
    );
    const collapsibleGroups = isSuperAdmin || menuRows > LONG_MENU_ROWS;

    return (
        <>
            <Sidebar
                collapsible="offcanvas"
                onMouseEnter={() => hover(true)}
                onMouseLeave={() => hover(false)}
            >
                <SidebarHeader>
                    <div className="flex items-center gap-1">
                        <SidebarMenu className="min-w-0 flex-1">
                            <SidebarMenuItem>
                                <SidebarMenuButton size="lg" asChild>
                                    <Link href={dashboard()} prefetch>
                                        <AppLogo />
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                        {/* Lock the sidebar open, or let it hide when the cursor leaves it. */}
                        <button
                            type="button"
                            onClick={togglePinned}
                            className={cn(
                                'flex size-8 shrink-0 items-center justify-center rounded-md transition hover:bg-sidebar-accent',
                                pinned
                                    ? 'text-sidebar-foreground'
                                    : 'text-sidebar-foreground/60',
                            )}
                            title={
                                pinned
                                    ? 'Sidebar dikunci. Klik agar tersembunyi otomatis saat kursor keluar.'
                                    : 'Kunci sidebar agar selalu tampil'
                            }
                            aria-label={
                                pinned ? 'Lepas kunci sidebar' : 'Kunci sidebar'
                            }
                            aria-pressed={pinned}
                        >
                            {pinned ? (
                                <Pin className="size-4 fill-current" />
                            ) : (
                                <PinOff className="size-4" />
                            )}
                        </button>
                    </div>
                </SidebarHeader>

                <SidebarContent>
                    {/* The Super Admin — and any role whose menu is long enough to scroll (user, 2026-11-19) —
                        gets collapsible groups (the one holding the current page opens); a short menu stays flat. */}
                    {groups.map((group) =>
                        group.label === 'Umum' || !collapsibleGroups ? (
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

            {/* Hidden sidebar: the "Portal Saya" tab on the left edge opens it on hover / tap. */}
            {!pinned && !peeking && (
                <button
                    type="button"
                    onMouseEnter={() => hover(true)}
                    onClick={() => setPeek(true)}
                    className="fixed top-1/2 left-0 z-40 hidden -translate-y-1/2 flex-col items-center gap-1.5 rounded-r-lg bg-sidebar px-1.5 py-3 text-sidebar-foreground shadow-lg transition hover:pl-2.5 md:flex"
                    aria-label="Buka menu Portal Saya"
                >
                    <LayoutGrid className="size-4" />
                    <span className="rotate-180 text-[11px] font-semibold tracking-wide [writing-mode:vertical-rl]">
                        Portal Saya
                    </span>
                </button>
            )}
        </>
    );
}
