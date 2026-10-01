import { Head, Link } from '@inertiajs/react';
import { ExternalLink, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { MonitoringFilterBar, MonitoringTabs, PercentBar, PercentPill, formatStamp } from '@/components/monitoring/shared';
import type { MonitoringFilters, MonitoringOptions } from '@/components/monitoring/shared';
import { PageHeader } from '@/components/page-header';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import monitoring from '@/routes/monitoring';

type Entry = { key: string; group: string; label: string; period: 'harian' | 'bulanan' | 'tahunan'; kind: string; url: string };
type Cell = { filled: number; expected: number; percent: number | null; last: string | null };
type UnitRow = { id: number; name: string; percent: number | null; groups: Record<string, number | null>; cells: Record<string, Cell> };

type Props = {
    filters: MonitoringFilters;
    options: MonitoringOptions;
    period_label: string;
    groups: { key: string; label: string; percent: number | null }[];
    state: 'past' | 'current' | 'future';
    days_due: number;
    days_in_month: number;
    percent: number | null;
    entries: Entry[];
    units: UnitRow[];
};

const PERIOD_LABEL: Record<Entry['period'], string> = { harian: 'Harian', bulanan: 'Bulanan', tahunan: 'Tahunan' };

function cellDetail(entry: Entry, cell: Cell | undefined): string {
    if (!cell || cell.expected === 0) {
        return entry.kind === 'daily_engine' ? 'Belum ada mesin aktif / belum jatuh tempo' : 'Belum jatuh tempo';
    }

    if (entry.kind === 'daily' || entry.kind === 'daily_day') {
        return `${Math.min(cell.filled, cell.expected)} / ${cell.expected} hari`;
    }

    if (entry.kind === 'daily_engine') {
        return `${Math.min(cell.filled, cell.expected)} / ${cell.expected} hari-mesin`;
    }

    if (entry.kind === 'count') {
        return `${Math.min(cell.filled, cell.expected)} / ${cell.expected} dokumen`;
    }

    return cell.filled > 0 ? `Terisi (${cell.filled} baris)` : 'Belum diisi';
}

export default function MonitoringInput({ filters, options, period_label, groups, state, days_due, days_in_month, percent, entries, units }: Props) {
    const [group, setGroup] = useState<string>('all');
    const [search, setSearch] = useState('');
    const [openUnit, setOpenUnit] = useState<UnitRow | null>(null);
    const current = state === 'current';

    const groupEntries = useMemo(() => entries.filter((entry) => entry.group === group), [entries, group]);
    const shownUnits = useMemo(() => {
        const q = search.trim().toLowerCase();

        return q === '' ? units : units.filter((unit) => unit.name.toLowerCase().includes(q));
    }, [units, search]);
    const urlFor = (entry: Entry, unitId: number) => entry.url.replace('__UNIT__', String(unitId));

    return (
        <>
            <Head title="Monitoring — Kelengkapan Input" />
            <div className="flex min-w-0 flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader title="Kelengkapan Input" description={`Seberapa lengkap setiap unit mengisi input tiap modul — ${period_label}.`} />
                <MonitoringTabs filters={filters} />
                <MonitoringFilterBar url={monitoring.input().url} filters={filters} options={options}>
                    <label className="flex flex-col gap-1 text-[13px]">
                        <span className="text-muted-foreground">Cari unit</span>
                        <span className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Nama unit…" className="h-9 w-48 pl-8" />
                        </span>
                    </label>
                </MonitoringFilterBar>

                <div className="flex flex-col gap-3 rounded-md border border-border bg-card p-4 md:flex-row md:items-center">
                    <div className="flex items-center gap-3 md:w-64">
                        <span className="text-3xl font-bold text-foreground tabular-nums">{percent === null ? '—' : `${percent}%`}</span>
                        <span className="text-[12px] leading-snug text-muted-foreground">
                            rata-rata seluruh unit &amp; input
                            <br />
                            {state === 'future' ? 'periode belum berjalan' : current ? `hari berjalan: ${days_due} dari ${days_in_month}` : `${days_in_month} hari`}
                        </span>
                    </div>
                    <div className="flex flex-1 flex-col gap-1 text-[12px] text-muted-foreground">
                        <span>
                            <b className="text-emerald-600 dark:text-emerald-400">≥ 90%</b> lengkap · <b className="text-amber-600 dark:text-amber-400">50–89%</b> sebagian ·{' '}
                            <b className="text-red-600 dark:text-red-400">&lt; 50%</b> kurang · <b>—</b> belum jatuh tempo.
                        </span>
                        <span>Input harian dihitung per hari (per mesin untuk Input Harian & Logsheet); input bulanan/tahunan terisi atau belum.</span>
                    </div>
                </div>

                {/* Module filter */}
                <div className="-mx-4 flex gap-2 overflow-x-auto px-4 [scrollbar-width:none] md:mx-0 md:flex-wrap md:px-0 [&::-webkit-scrollbar]:hidden">
                    {[{ key: 'all', label: 'Semua modul', percent }, ...groups].map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => setGroup(item.key)}
                            aria-pressed={group === item.key}
                            className={cn(
                                'flex h-9 shrink-0 items-center gap-2 rounded-md border px-3.5 text-[13px] font-medium whitespace-nowrap transition',
                                group === item.key ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-card text-foreground hover:bg-muted',
                            )}
                        >
                            {item.label}
                            <span className={cn('text-[12px] tabular-nums', group === item.key ? 'opacity-80' : 'text-muted-foreground')}>
                                {item.percent === null ? '—' : `${item.percent}%`}
                            </span>
                        </button>
                    ))}
                </div>

                <div className="overflow-hidden rounded-md border border-border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-[13px]" data-keep-table>
                            <thead>
                                <tr className="border-b border-border bg-muted/50 text-left text-[12px] text-muted-foreground">
                                    <th className="sticky left-0 z-10 min-w-40 bg-muted px-3 py-2.5 font-semibold">Unit</th>
                                    <th className="px-2 py-2.5 text-center font-semibold">Total</th>
                                    {group === 'all'
                                        ? groups.map((g) => (
                                              <th key={g.key} className="min-w-28 px-2 py-2.5 text-center font-semibold">
                                                  {g.label}
                                              </th>
                                          ))
                                        : groupEntries.map((entry) => (
                                              <th key={entry.key} className="min-w-28 px-2 py-2.5 text-center align-bottom font-semibold">
                                                  <span className="line-clamp-2">{entry.label}</span>
                                                  <span className="block text-[10.5px] font-normal">{PERIOD_LABEL[entry.period]}</span>
                                              </th>
                                          ))}
                                </tr>
                            </thead>
                            <tbody>
                                {shownUnits.length === 0 && (
                                    <tr>
                                        <td colSpan={99} className="px-3 py-8 text-center text-muted-foreground">
                                            Tidak ada unit.
                                        </td>
                                    </tr>
                                )}
                                {shownUnits.map((unit) => (
                                    <tr key={unit.id} className="border-b border-border last:border-0 hover:bg-muted/30">
                                        <td className="sticky left-0 z-10 bg-card px-3 py-2">
                                            <button type="button" onClick={() => setOpenUnit(unit)} className="text-left font-medium text-primary hover:underline">
                                                {unit.name}
                                            </button>
                                        </td>
                                        <td className="px-2 py-2 text-center">
                                            <PercentPill percent={unit.percent} current={current} />
                                        </td>
                                        {group === 'all'
                                            ? groups.map((g) => (
                                                  <td key={g.key} className="px-2 py-2 text-center">
                                                      <PercentPill percent={unit.groups[g.key] ?? null} current={current} />
                                                  </td>
                                              ))
                                            : groupEntries.map((entry) => {
                                                  const cell = unit.cells[entry.key];

                                                  return (
                                                      <td key={entry.key} className="px-2 py-2 text-center">
                                                          <Link href={urlFor(entry, unit.id)} title={`${entry.label} — ${cellDetail(entry, cell)}`}>
                                                              <PercentPill percent={cell?.percent ?? null} current={current} />
                                                          </Link>
                                                      </td>
                                                  );
                                              })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <Sheet open={openUnit !== null} onOpenChange={(open) => !open && setOpenUnit(null)}>
                <SheetContent className="w-full overflow-y-auto sm:max-w-xl">
                    {openUnit && (
                        <>
                            <SheetHeader>
                                <SheetTitle>{openUnit.name}</SheetTitle>
                                <SheetDescription>
                                    Kelengkapan input {period_label} · rata-rata {openUnit.percent === null ? '—' : `${openUnit.percent}%`}
                                </SheetDescription>
                            </SheetHeader>
                            <div className="flex flex-col gap-4 px-4 pb-6">
                                {groups.map((g) => (
                                    <section key={g.key} className="flex flex-col gap-2">
                                        <div className="flex items-center gap-2">
                                            <h3 className="flex-1 text-[13px] font-semibold text-foreground">{g.label}</h3>
                                            <PercentPill percent={openUnit.groups[g.key] ?? null} current={current} />
                                        </div>
                                        <PercentBar percent={openUnit.groups[g.key] ?? null} />
                                        <ul className="flex flex-col divide-y divide-border rounded-md border border-border">
                                            {entries
                                                .filter((entry) => entry.group === g.key)
                                                .map((entry) => {
                                                    const cell = openUnit.cells[entry.key];

                                                    return (
                                                        <li key={entry.key}>
                                                            <Link href={urlFor(entry, openUnit.id)} className="flex items-center gap-3 px-3 py-2 hover:bg-muted/50">
                                                                <span className="min-w-0 flex-1">
                                                                    <span className="block truncate text-[13px] text-foreground">{entry.label}</span>
                                                                    <span className="block text-[11.5px] text-muted-foreground">
                                                                        {PERIOD_LABEL[entry.period]} · {cellDetail(entry, cell)} · diubah {formatStamp(cell?.last ?? null)}
                                                                    </span>
                                                                </span>
                                                                <PercentPill percent={cell?.percent ?? null} current={current} />
                                                                <ExternalLink className="size-3.5 shrink-0 text-muted-foreground" />
                                                            </Link>
                                                        </li>
                                                    );
                                                })}
                                        </ul>
                                    </section>
                                ))}
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>
        </>
    );
}

MonitoringInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Monitoring', href: monitoring.index() },
        { title: 'Kelengkapan Input', href: monitoring.input() },
    ],
};
