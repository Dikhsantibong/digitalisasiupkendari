import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Menu, Search, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { NotificationBell } from '@/components/notifications/notification-bell';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { MobileShellContext } from '@/hooks/use-mobile-module';
import type { ResolvedMobileModule } from '@/hooks/use-mobile-module';
import { MOBILE_MENU_GROUPS, mobileGroupHomeLabel } from '@/layouts/mobile/types';
import type { MobileMenu, MobileMenuGroup } from '@/layouts/mobile/types';
import { cn } from '@/lib/utils';
import type { Auth } from '@/types';

const DATE_FORMAT = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

/** How long the splash stays on the first load of a session, in ms. */
const SPLASH_MS = 600;
const SPLASH_KEY = 'mobile-shell-splash-shown';

/** A menu grid needs the search box and section filter from this many menus. */
const FILTER_FROM = 9;

/**
 * Full-screen loading shown while the phone layout starts, so the desktop
 * page (the dashboard and its charts) never flashes on a phone.
 */
export function MobileSplash() {
    return (
        <div className="fixed inset-0 z-50 flex flex-col items-center justify-center gap-5 bg-background">
            <div className="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-border">
                <img
                    src="/logo/sidebar-logo.png"
                    alt="PLN Nusantara Power"
                    className="h-10 w-auto"
                />
            </div>
            <div className="size-8 animate-spin rounded-full border-[3px] border-primary/20 border-t-primary" />
            <p className="text-[13px] font-medium text-muted-foreground">
                Menyiapkan menu…
            </p>
        </div>
    );
}

/**
 * The account menu of the phone shell: a hamburger button (visible on the blue
 * header and on the white top bar alike) with the user and Log out — no
 * Settings for the field roles.
 */
function UserButton({ onDark = false }: { onDark?: boolean }) {
    const auth = usePage().props.auth as Auth;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={cn(
                    'flex size-10 items-center justify-center rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring active:scale-95',
                    onDark
                        ? 'bg-white/15 text-white ring-1 ring-white/25'
                        : 'bg-muted text-foreground',
                )}
                aria-label="Menu akun"
            >
                <Menu className="size-5" />
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-60" align="end">
                <UserMenuContent user={auth.user} showSettings={false} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** Per-viewer tap counts of the home menus (a convenience only; the page works without storage). */
const USAGE_KEY = 'mobile-menu-usage';
const QUICK_COUNT = 4;

type Usage = Record<string, number>;

function readUsage(module: string): Usage {
    try {
        const raw = window.localStorage.getItem(`${USAGE_KEY}:${module}`);

        return raw ? (JSON.parse(raw) as Usage) : {};
    } catch {
        return {};
    }
}

function recordUsage(module: string, menu: string): void {
    try {
        const usage = readUsage(module);
        usage[menu] = (usage[menu] ?? 0) + 1;
        window.localStorage.setItem(`${USAGE_KEY}:${module}`, JSON.stringify(usage));
    } catch {
        // Storage blocked: quick access simply keeps its defaults.
    }
}

/**
 * The menus of "Akses Cepat": the user's most-opened menus first, then the
 * module's `quick` defaults, then the first menu of each section.
 */
function quickMenus(menus: MobileMenu[], defaults: string[], usage: Usage): MobileMenu[] {
    const byKey = new Map(menus.map((menu) => [menu.key, menu]));
    const used = Object.entries(usage)
        .filter(([key, count]) => count > 1 && byKey.has(key))
        .sort((a, b) => b[1] - a[1])
        .map(([key]) => key);
    const firstOfGroups = MOBILE_MENU_GROUPS.map((group) => menus.find((menu) => menu.group === group.key)?.key).filter(
        (key): key is string => key !== undefined,
    );
    const keys = [...new Set([...used, ...defaults.filter((key) => byKey.has(key)), ...firstOfGroups, ...menus.map((menu) => menu.key)])];

    return keys.slice(0, QUICK_COUNT).map((key) => byKey.get(key) as MobileMenu);
}

function MenuTile({ menu, onOpen }: { menu: MobileMenu; onOpen?: (menu: MobileMenu) => void }) {
    const Icon = menu.icon;

    return (
        <Link
            href={menu.href}
            prefetch
            onClick={() => onOpen?.(menu)}
            className="flex min-w-0 flex-col items-center gap-2 rounded-xl py-1.5 text-center outline-none transition focus-visible:ring-2 focus-visible:ring-ring active:scale-95 active:bg-muted/70"
            title={menu.title}
        >
            <span className={cn('flex size-12 shrink-0 items-center justify-center rounded-2xl', menu.tone)}>
                <Icon className="size-[22px]" strokeWidth={1.8} />
            </span>
            <span className="line-clamp-2 w-full text-[11px] leading-[1.3] max-[370px]:text-[10.5px] font-medium tracking-[-0.01em] hyphens-auto [overflow-wrap:anywhere] text-foreground">
                {menu.short ?? menu.title}
            </span>
        </Link>
    );
}

function MenuGrid({ menus, onOpen }: { menus: MobileMenu[]; onOpen: (menu: MobileMenu) => void }) {
    return (
        <div className="grid grid-cols-4 gap-x-0.5 gap-y-3">
            {menus.map((menu) => (
                <MenuTile key={menu.key} menu={menu} onOpen={onOpen} />
            ))}
        </div>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="flex flex-col gap-3 rounded-2xl border border-border/60 bg-card px-2 pt-3.5 pb-3 max-[370px]:px-1">
            <h2 className="px-1.5 text-[12px] font-semibold tracking-[0.06em] text-muted-foreground uppercase">{title}</h2>
            {children}
        </section>
    );
}

function MobileHome({ resolved }: { resolved: ResolvedMobileModule }) {
    const auth = usePage().props.auth as Auth;
    const roleLabel = auth.roles.map((role) => role.display_name).join(', ');
    const [query, setQuery] = useState('');
    const [section, setSection] = useState<MobileMenuGroup | 'all'>('all');
    const moduleKey = resolved.module.key;
    const [usage] = useState(() => readUsage(moduleKey));

    const sections = useMemo(
        () =>
            MOBILE_MENU_GROUPS.map((group) => ({
                key: group.key,
                label: mobileGroupHomeLabel(group),
                menus: resolved.menus.filter((menu) => menu.group === group.key),
            })).filter((group) => group.menus.length > 0),
        [resolved.menus],
    );
    const quick = useMemo(() => quickMenus(resolved.menus, resolved.module.quick ?? [], usage), [resolved.menus, resolved.module.quick, usage]);
    const withFilters = resolved.menus.length >= FILTER_FROM;
    const search = query.trim().toLowerCase();
    const results =
        search === ''
            ? []
            : resolved.menus.filter((menu) => `${menu.title} ${menu.short ?? ''} ${menu.description}`.toLowerCase().includes(search));
    const shown = section === 'all' ? sections : sections.filter((group) => group.key === section);
    const open = (menu: MobileMenu) => recordUsage(moduleKey, menu.key);

    return (
        <div className="flex flex-col gap-4 px-4 pt-4 pb-8">
            <Head title={resolved.module.title} />
            <header className="flex items-center justify-between gap-3 rounded-2xl bg-linear-to-br from-[#0b6aa2] to-[#085a8c] px-4 py-3.5 text-white shadow-sm">
                <div className="min-w-0">
                    <h1 className="truncate text-[18px] leading-tight font-semibold">Halo, {auth.user.name.split(' ')[0]}</h1>
                    <p className="mt-0.5 line-clamp-2 text-[12.5px] leading-snug text-white/80">
                        {roleLabel ? `${roleLabel} · ` : ''}
                        {DATE_FORMAT.format(new Date())}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <NotificationBell variant="onDark" />
                    <UserButton onDark />
                </div>
            </header>

            {resolved.menus.length === 0 ? (
                <p className="rounded-2xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">
                    Belum ada menu yang dapat Anda akses.
                </p>
            ) : (
                <>
                    {withFilters && (
                        <div className="sticky top-0 z-20 -mx-4 flex flex-col gap-3 bg-muted/40 px-4 py-2 backdrop-blur-md">
                            <label className="flex h-11 items-center gap-2.5 rounded-xl border border-border/70 bg-card px-3.5 focus-within:border-primary/50 focus-within:ring-2 focus-within:ring-primary/15">
                                <Search className="size-[18px] shrink-0 text-muted-foreground" />
                                <input
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Cari menu..."
                                    className="min-w-0 flex-1 bg-transparent text-[14px] outline-none placeholder:text-muted-foreground"
                                />
                                {query && (
                                    <button
                                        type="button"
                                        onClick={() => setQuery('')}
                                        aria-label="Hapus pencarian"
                                        className="-mr-1 flex size-8 items-center justify-center rounded-lg active:bg-muted"
                                    >
                                        <X className="size-4 text-muted-foreground" />
                                    </button>
                                )}
                            </label>
                            {search === '' && sections.length > 1 && (
                                // Only this strip scrolls sideways; the next chip peeks in as the cue.
                                <div className="-mx-4 flex gap-2 overflow-x-auto overscroll-x-contain px-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                                    {[
                                        { key: 'all' as const, label: 'Semua', count: resolved.menus.length },
                                        ...sections.map((group) => ({ key: group.key, label: group.label, count: group.menus.length })),
                                    ].map((chip) => (
                                        <button
                                            key={chip.key}
                                            type="button"
                                            onClick={() => setSection(chip.key)}
                                            aria-pressed={section === chip.key}
                                            className={cn(
                                                'flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-[13px] font-medium whitespace-nowrap transition active:scale-95',
                                                section === chip.key
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-border/70 bg-card text-foreground',
                                            )}
                                        >
                                            {chip.label}
                                            <span className={cn('text-[12px] tabular-nums', section === chip.key ? 'opacity-80' : 'text-muted-foreground')}>
                                                {chip.count}
                                            </span>
                                        </button>
                                    ))}
                                    <span className="w-px shrink-0" aria-hidden />
                                </div>
                            )}
                        </div>
                    )}

                    {search !== '' ? (
                        results.length === 0 ? (
                            <p className="py-6 text-center text-[13px] text-muted-foreground">Tidak ada menu "{query}".</p>
                        ) : (
                            <Section title={`Hasil pencarian · ${results.length}`}>
                                <MenuGrid menus={results} onOpen={open} />
                            </Section>
                        )
                    ) : (
                        <>
                            {withFilters && section === 'all' && (
                                <Section title="Akses Cepat">
                                    <MenuGrid menus={quick} onOpen={open} />
                                </Section>
                            )}
                            {shown.map((group) => (
                                <Section key={group.key} title={group.label}>
                                    <MenuGrid menus={group.menus} onOpen={open} />
                                </Section>
                            ))}
                        </>
                    )}
                </>
            )}

            <div className="mt-1 flex items-center justify-center">
                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="h-6 w-auto opacity-60" />
            </div>
        </div>
    );
}

/**
 * Phone-only shell for a registered module (see `layouts/mobile/modules.ts`):
 * no sidebar, no app header. The module's home shows a compact, searchable
 * menu grid; its pages get a slim top bar with a back button.
 */
export default function MobileModuleLayout({
    resolved,
    children,
}: {
    resolved: ResolvedMobileModule;
    children: ReactNode;
}) {
    // A short splash on the first load of a session, while the phone layout settles.
    const [booting, setBooting] = useState(() => {
        try {
            return window.sessionStorage.getItem(SPLASH_KEY) === null;
        } catch {
            return false;
        }
    });

    useEffect(() => {
        if (!booting) {
            return;
        }

        const timer = window.setTimeout(() => {
            setBooting(false);

            try {
                window.sessionStorage.setItem(SPLASH_KEY, '1');
            } catch {
                // Private mode: the splash simply shows again next time.
            }
        }, SPLASH_MS);

        return () => window.clearTimeout(timer);
    }, [booting]);

    if (booting) {
        return <MobileSplash />;
    }

    return (
        <MobileShellContext.Provider value={true}>
            <div className="min-h-dvh bg-muted/40">
                {resolved.isHome ? (
                    <MobileHome resolved={resolved} />
                ) : (
                    <>
                        <header className="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-border bg-background/95 px-2 backdrop-blur">
                            <Link
                                href={resolved.module.homeHref}
                                className="flex size-10 items-center justify-center rounded-full text-foreground active:bg-muted"
                                aria-label="Kembali ke menu"
                            >
                                <ArrowLeft className="size-5" />
                            </Link>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-[15px] leading-tight font-semibold text-foreground">
                                    {resolved.current?.title ??
                                        resolved.module.title}
                                </p>
                                <p className="truncate text-[11px] text-muted-foreground">
                                    {MOBILE_MENU_GROUPS.find(
                                        (group) =>
                                            group.key ===
                                            resolved.current?.group,
                                    )?.label ?? resolved.module.title}
                                </p>
                            </div>
                            <div className="flex items-center gap-1.5 pr-1">
                                <NotificationBell variant="muted" />
                                <UserButton />
                            </div>
                        </header>
                        <main className="flex flex-col pb-24">{children}</main>
                    </>
                )}
            </div>
        </MobileShellContext.Provider>
    );
}
