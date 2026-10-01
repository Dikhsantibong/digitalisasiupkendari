import { Link, router } from '@inertiajs/react';
import { ClipboardCheck, FileCheck2, LayoutDashboard } from 'lucide-react';
import type { ReactNode } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import monitoring from '@/routes/monitoring';

export type MonitoringFilters = { month: number; year: number; service_unit_id: number | null };
export type MonitoringOptions = { service_units: { id: number; name: string }[]; years: number[] };

const PAGES = [
    { title: 'Ringkasan', icon: LayoutDashboard, route: monitoring.index },
    { title: 'Kelengkapan Input', icon: ClipboardCheck, route: monitoring.input },
    { title: 'Verifikasi Laporan', icon: FileCheck2, route: monitoring.laporan },
] as const;

/** Query of the current filters, carried between the monitoring pages. */
export function filterQuery(filters: MonitoringFilters): Record<string, number> {
    return {
        month: filters.month,
        year: filters.year,
        ...(filters.service_unit_id ? { service_unit_id: filters.service_unit_id } : {}),
    };
}

/** Tabs between the monitoring pages (keeping the period & Unit Layanan). */
export function MonitoringTabs({ filters }: { filters: MonitoringFilters }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <nav className="-mx-4 flex gap-2 overflow-x-auto px-4 [scrollbar-width:none] md:mx-0 md:px-0 [&::-webkit-scrollbar]:hidden" aria-label="Halaman monitoring">
            {PAGES.map((page) => {
                const href = page.route({ query: filterQuery(filters) });
                const active = isCurrentUrl(page.route());
                const Icon = page.icon;

                return (
                    <Link
                        key={page.title}
                        href={href}
                        preserveScroll
                        className={cn(
                            'flex h-9 shrink-0 items-center gap-1.5 rounded-md border px-3.5 text-[13px] font-medium whitespace-nowrap transition',
                            active ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-card text-foreground hover:bg-muted',
                        )}
                    >
                        <Icon className="size-4" />
                        {page.title}
                    </Link>
                );
            })}
        </nav>
    );
}

/** Unit Layanan, Bulan & Tahun of a monitoring page. */
export function MonitoringFilterBar({
    url,
    filters,
    options,
    children,
}: {
    url: string;
    filters: MonitoringFilters;
    options: MonitoringOptions;
    children?: ReactNode;
}) {
    const visit = (patch: Partial<MonitoringFilters>) => {
        const next = { ...filters, ...patch };
        router.get(url, filterQuery(next), { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-secondary p-3">
            {options.service_units.length > 1 && (
                <OperasiSelect
                    label="Unit Layanan"
                    className="w-52"
                    value={filters.service_unit_id ? String(filters.service_unit_id) : 'all'}
                    onChange={(value) => visit({ service_unit_id: value === 'all' ? null : Number(value) })}
                    options={[{ value: 'all', label: 'Semua Unit Layanan' }, ...options.service_units.map((s) => ({ value: String(s.id), label: s.name }))]}
                />
            )}
            <OperasiSelect
                label="Bulan"
                className="w-36"
                value={String(filters.month)}
                onChange={(value) => visit({ month: Number(value) })}
                options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
            />
            <OperasiSelect
                label="Tahun"
                className="w-28"
                value={String(filters.year)}
                onChange={(value) => visit({ year: Number(value) })}
                options={options.years.map((year) => ({ value: String(year), label: String(year) }))}
            />
            {children}
        </div>
    );
}

/**
 * Colour of a completeness percentage. In the current month an empty input is
 * "belum" (amber), not yet late; null means nothing is due.
 */
export function percentTone(percent: number | null, current = false): string {
    if (percent === null) {
        return 'bg-muted text-muted-foreground';
    }

    if (percent >= 90) {
        return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
    }

    if (percent >= 50) {
        return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
    }

    if (percent === 0 && current) {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'bg-red-500/15 text-red-700 dark:text-red-300';
}

export function PercentPill({ percent, current = false, className }: { percent: number | null; current?: boolean; className?: string }) {
    return (
        <span className={cn('inline-flex min-w-12 items-center justify-center rounded-md px-1.5 py-0.5 text-[12px] font-semibold tabular-nums', percentTone(percent, current), className)}>
            {percent === null ? '—' : `${percent}%`}
        </span>
    );
}

/** A thin progress bar for a percentage. */
export function PercentBar({ percent }: { percent: number | null }) {
    const value = percent ?? 0;
    const color = percent === null ? 'bg-muted-foreground/30' : value >= 90 ? 'bg-emerald-500' : value >= 50 ? 'bg-amber-500' : 'bg-red-500';

    return (
        <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
            <div className={cn('h-full rounded-full transition-all', color)} style={{ width: `${value}%` }} />
        </div>
    );
}

const DATE_TIME = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

/** "3 Okt, 14.05" in the viewer's own time zone, or "—". */
export function formatStamp(iso: string | null): string {
    return iso ? DATE_TIME.format(new Date(iso)) : '—';
}
