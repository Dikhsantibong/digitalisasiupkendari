import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { MobileShellContext } from '@/hooks/use-mobile-module';
import type { ResolvedMobileModule } from '@/hooks/use-mobile-module';
import type { Auth } from '@/types';

const DATE_FORMAT = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

function UserButton() {
    const auth = usePage().props.auth as Auth;
    const getInitials = useInitials();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className="rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring"
                aria-label="Menu akun"
            >
                <Avatar className="size-9 overflow-hidden rounded-full ring-2 ring-white/40">
                    <AvatarImage src={auth.user.avatar} alt={auth.user.name} />
                    <AvatarFallback className="rounded-full bg-primary/15 text-[12px] font-semibold text-primary">
                        {getInitials(auth.user.name)}
                    </AvatarFallback>
                </Avatar>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-60" align="end">
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function MobileHome({ resolved }: { resolved: ResolvedMobileModule }) {
    const auth = usePage().props.auth as Auth;
    const roleLabel = auth.roles.map((role) => role.display_name).join(', ');

    return (
        <div className="flex flex-col gap-5 px-4 pt-4 pb-8">
            <Head title={resolved.module.title} />
            <div className="relative overflow-hidden rounded-2xl bg-linear-to-br from-chart-5 via-[#0b6aa2] to-chart-1 p-5 text-white shadow-md">
                <div className="pointer-events-none absolute -top-16 -right-12 size-48 rounded-full bg-white/10 blur-2xl" />
                <div className="relative flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <p className="text-[11px] font-semibold tracking-[0.16em] text-chart-4 uppercase">
                            {resolved.module.title}
                        </p>
                        <h1 className="mt-1 truncate text-xl font-semibold">
                            Halo, {auth.user.name.split(' ')[0]}
                        </h1>
                        {roleLabel && (
                            <p className="truncate text-[12.5px] text-white/75">
                                {roleLabel}
                            </p>
                        )}
                    </div>
                    <UserButton />
                </div>
                <p className="relative mt-4 inline-flex rounded-full bg-white/15 px-3 py-1 text-[12px] ring-1 ring-white/20">
                    {DATE_FORMAT.format(new Date())}
                </p>
            </div>

            <div className="flex flex-col gap-1">
                <h2 className="text-[13px] font-semibold tracking-wide text-muted-foreground uppercase">
                    Menu
                </h2>
                <p className="text-[12.5px] text-muted-foreground">
                    {resolved.module.subtitle}
                </p>
            </div>

            {resolved.menus.length === 0 ? (
                <p className="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">
                    Belum ada menu yang dapat Anda akses.
                </p>
            ) : (
                <div className="flex flex-col gap-3">
                    {resolved.menus.map((menu) => {
                        const Icon = menu.icon;

                        return (
                            <Link
                                key={menu.key}
                                href={menu.href}
                                prefetch
                                className="group flex items-center gap-4 rounded-2xl border border-border bg-card p-4 shadow-xs transition active:scale-[0.98] active:bg-muted/60"
                            >
                                <span
                                    className={`flex size-12 shrink-0 items-center justify-center rounded-xl ${menu.tone}`}
                                >
                                    <Icon className="size-6" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-[15px] font-semibold text-foreground">
                                        {menu.title}
                                    </span>
                                    <span className="block text-[12.5px] text-muted-foreground">
                                        {menu.description}
                                    </span>
                                </span>
                                <ChevronRight className="size-5 shrink-0 text-muted-foreground transition group-active:translate-x-0.5" />
                            </Link>
                        );
                    })}
                </div>
            )}

            <div className="mt-2 flex items-center justify-center">
                <img
                    src="/logo/sidebar-logo.png"
                    alt="PLN Nusantara Power"
                    className="h-7 w-auto opacity-70"
                />
            </div>
        </div>
    );
}

/**
 * Phone-only shell for a registered module (see `layouts/mobile/modules.ts`):
 * no sidebar, no app header. The module's home shows a card menu; its pages get
 * a slim top bar with a back button.
 */
export default function MobileModuleLayout({
    resolved,
    children,
}: {
    resolved: ResolvedMobileModule;
    children: ReactNode;
}) {
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
                                    {resolved.module.title}
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
