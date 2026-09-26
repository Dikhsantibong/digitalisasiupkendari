import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Menu, Search, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { MobileShellContext } from '@/hooks/use-mobile-module';
import type { ResolvedMobileModule } from '@/hooks/use-mobile-module';
import { MOBILE_MENU_GROUPS } from '@/layouts/mobile/types';
import type { MobileMenu, MobileMenuGroup } from '@/layouts/mobile/types';
import { cn } from '@/lib/utils';
import type { Auth } from '@/types';

const DATE_FORMAT = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
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

function MenuTile({ menu }: { menu: MobileMenu }) {
    const Icon = menu.icon;

    return (
        <Link
            href={menu.href}
            prefetch
            className="flex flex-col items-center gap-1.5 rounded-xl p-1.5 text-center transition active:scale-95 active:bg-muted/70"
            title={menu.title}
        >
            <span
                className={cn(
                    'flex size-12 items-center justify-center rounded-2xl',
                    menu.tone,
                )}
            >
                <Icon className="size-6" />
            </span>
            <span className="line-clamp-2 text-[11px] leading-tight font-medium text-foreground">
                {menu.short ?? menu.title}
            </span>
        </Link>
    );
}

function MobileHome({ resolved }: { resolved: ResolvedMobileModule }) {
    const auth = usePage().props.auth as Auth;
    const roleLabel = auth.roles.map((role) => role.display_name).join(', ');
    const [query, setQuery] = useState('');
    const [section, setSection] = useState<MobileMenuGroup | 'all'>('all');

    const sections = useMemo(
        () =>
            MOBILE_MENU_GROUPS.map((group) => ({
                ...group,
                menus: resolved.menus.filter(
                    (menu) => menu.group === group.key,
                ),
            })).filter((group) => group.menus.length > 0),
        [resolved.menus],
    );
    const withFilters = resolved.menus.length >= FILTER_FROM;
    const search = query.trim().toLowerCase();
    const results =
        search === ''
            ? []
            : resolved.menus.filter((menu) =>
                  `${menu.title} ${menu.short ?? ''} ${menu.description}`
                      .toLowerCase()
                      .includes(search),
              );
    const shown =
        section === 'all'
            ? sections
            : sections.filter((group) => group.key === section);

    return (
        <div className="flex flex-col gap-4 px-4 pt-4 pb-8">
            <Head title={resolved.module.title} />
            <div className="relative overflow-hidden rounded-2xl bg-linear-to-br from-chart-5 via-[#0b6aa2] to-chart-1 px-4 py-3.5 text-white shadow-md">
                <div className="pointer-events-none absolute -top-16 -right-12 size-40 rounded-full bg-white/10 blur-2xl" />
                <div className="relative flex items-center justify-between gap-3">
                    <div className="min-w-0">
                        <h1 className="truncate text-[17px] font-semibold">
                            Halo, {auth.user.name.split(' ')[0]}
                        </h1>
                        <p className="truncate text-[12px] text-white/75">
                            {roleLabel ? `${roleLabel} · ` : ''}
                            {DATE_FORMAT.format(new Date())}
                        </p>
                    </div>
                    <UserButton onDark />
                </div>
            </div>

            {resolved.menus.length === 0 ? (
                <p className="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">
                    Belum ada menu yang dapat Anda akses.
                </p>
            ) : (
                <>
                    {withFilters && (
                        <div className="sticky top-0 z-20 -mx-4 flex flex-col gap-2 bg-muted/40 px-4 pt-1 pb-2 backdrop-blur">
                            <label className="flex h-10 items-center gap-2 rounded-xl border border-border bg-card px-3">
                                <Search className="size-4 shrink-0 text-muted-foreground" />
                                <input
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Cari menu…"
                                    className="min-w-0 flex-1 bg-transparent text-[14px] outline-none placeholder:text-muted-foreground"
                                />
                                {query && (
                                    <button
                                        type="button"
                                        onClick={() => setQuery('')}
                                        aria-label="Hapus pencarian"
                                    >
                                        <X className="size-4 text-muted-foreground" />
                                    </button>
                                )}
                            </label>
                            {search === '' && sections.length > 1 && (
                                <div className="-mx-4 flex gap-1.5 overflow-x-auto px-4">
                                    {[
                                        {
                                            key: 'all' as const,
                                            label: 'Semua',
                                            count: resolved.menus.length,
                                        },
                                        ...sections.map((group) => ({
                                            key: group.key,
                                            label: group.label,
                                            count: group.menus.length,
                                        })),
                                    ].map((chip) => (
                                        <button
                                            key={chip.key}
                                            type="button"
                                            onClick={() => setSection(chip.key)}
                                            className={cn(
                                                'shrink-0 rounded-full border px-3 py-1.5 text-[12px] font-medium transition',
                                                section === chip.key
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-border bg-card text-foreground',
                                            )}
                                        >
                                            {chip.label}{' '}
                                            <span className="opacity-70">
                                                {chip.count}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}

                    {search !== '' ? (
                        results.length === 0 ? (
                            <p className="py-6 text-center text-[13px] text-muted-foreground">
                                Tidak ada menu "{query}".
                            </p>
                        ) : (
                            <div className="grid grid-cols-4 gap-x-1 gap-y-2">
                                {results.map((menu) => (
                                    <MenuTile key={menu.key} menu={menu} />
                                ))}
                            </div>
                        )
                    ) : (
                        shown.map((group) => (
                            <section
                                key={group.key}
                                className="flex flex-col gap-1.5 rounded-2xl border border-border bg-card p-2.5"
                            >
                                <h2 className="px-1 text-[11.5px] font-semibold tracking-wide text-muted-foreground uppercase">
                                    {group.label}
                                </h2>
                                <div className="grid grid-cols-4 gap-x-1 gap-y-2">
                                    {group.menus.map((menu) => (
                                        <MenuTile key={menu.key} menu={menu} />
                                    ))}
                                </div>
                            </section>
                        ))
                    )}
                </>
            )}

            <div className="mt-1 flex items-center justify-center">
                <img
                    src="/logo/sidebar-logo.png"
                    alt="PLN Nusantara Power"
                    className="h-6 w-auto opacity-60"
                />
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
                            <div className="pr-1">
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
