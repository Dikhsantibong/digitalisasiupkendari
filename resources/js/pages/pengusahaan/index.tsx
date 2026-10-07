import { Head, router, setLayoutProps } from '@inertiajs/react';
import { LayoutGrid } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { PENGUSAHAAN_MENU_GROUPS, PENGUSAHAAN_MENUS, PENGUSAHAAN_MODULES } from '@/lib/pengusahaan-menus';
import type { PengusahaanMenu, PengusahaanModuleKey, PengusahaanSectionKey } from '@/lib/pengusahaan-menus';
import { dashboard } from '@/routes';

type Props = {
    module: { key: PengusahaanModuleKey; title: string };
    section: { key: PengusahaanSectionKey; title: string };
};

/**
 * Akses 2 — Pengusahaan (TL & Staf): the pages of one menu section of one
 * module, read from PENGUSAHAAN_MENUS and filtered by the user's permissions.
 * Menus with a `group` (Operasi Input: Bahan Bakar, Pelumas, kWh, Rekap) follow under a sub-heading.
 */
export default function PengusahaanHub({ module, section }: Props) {
    const { can } = usePermissions();
    const menus = PENGUSAHAAN_MENUS.filter((menu) => menu.module === module.key && menu.section === section.key && can(menu.permission));
    const groups = [undefined, ...PENGUSAHAAN_MENU_GROUPS]
        .map((group) => ({ group, items: menus.filter((menu) => menu.group === group) }))
        .filter(({ items }) => items.length > 0);
    const hub = PENGUSAHAAN_MODULES.find((item) => item.key === module.key)?.hub(section.key);

    setLayoutProps({
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: `${section.title} ${module.title}`, href: hub ?? dashboard() },
        ],
    });

    return (
        <>
            <Head title={`${section.title} ${module.title}`} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={`${section.title} ${module.title}`}
                    description="Akses 2 — Pengusahaan (Team Leader & Staf). Menu di halaman ini diatur per role di Role & Akses."
                />

                {menus.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-card/60 p-12 text-center">
                        <div className="mb-3 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <LayoutGrid className="size-6" />
                        </div>
                        <h3 className="text-base font-semibold text-foreground">Belum ada menu</h3>
                        <p className="mt-1 max-w-md text-sm text-muted-foreground">
                            Belum ada halaman {section.title.toLowerCase()} pengusahaan {module.title.toLowerCase()} yang tersedia untuk akun Anda.
                        </p>
                    </div>
                ) : (
                    groups.map(({ group, items }) => (
                        <section key={group ?? 'utama'} className="flex flex-col gap-3">
                            {group && <h2 className="mt-2 text-sm font-semibold text-muted-foreground">{group}</h2>}
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                {items.map((menu) => (
                                    <MenuCard key={menu.title} menu={menu} />
                                ))}
                            </div>
                        </section>
                    ))
                )}
            </div>
        </>
    );
}

function MenuCard({ menu }: { menu: PengusahaanMenu }) {
    const Icon = menu.icon;

    return (
        <div className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-5 transition-all hover:border-primary/50">
            <div>
                <div className="mb-3 flex items-center justify-between gap-2">
                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Icon className="size-5" />
                    </div>
                    {menu.href === null && (
                        <Badge variant="outline" className="text-[11px] font-normal text-muted-foreground">
                            Sementara Disusun
                        </Badge>
                    )}
                </div>
                <h3 className="text-base font-semibold text-foreground">{menu.title}</h3>
                <p className="mt-1 text-[13px] text-muted-foreground">{menu.description}</p>
            </div>
            <Button className="w-full justify-center gap-2" disabled={menu.href === null} onClick={() => menu.href && router.get(menu.href)}>
                <Icon className="size-4" />
                {menu.href === null ? 'Sedang disiapkan' : `Buka ${menu.title}`}
            </Button>
        </div>
    );
}
