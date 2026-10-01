import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight, Eye, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import {
    PercentBar,
    PercentPill,
    formatStamp,
} from '@/components/monitoring/shared';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import portal from '@/routes/portal';

type Item = {
    key: string;
    label: string;
    period: 'harian' | 'bulanan' | 'tahunan';
    kind: string;
    filled: number;
    expected: number;
    percent: number | null;
    last: string | null;
    url: string;
};

type Filters = {
    month: number;
    year: number;
    service_unit_id: number | null;
    unit_id: number | null;
    bidang: string | null;
};

type Props = {
    filters: Filters;
    options: {
        service_units: { id: number; name: string }[];
        units: { id: number; name: string }[];
        years: number[];
    };
    areas: { key: string; label: string }[];
    period_label: string;
    state: 'past' | 'current' | 'future';
    groups: {
        key: string;
        label: string;
        percent: number | null;
        items: Item[];
    }[];
};

const PERIOD_LABEL: Record<Item['period'], string> = {
    harian: 'Harian',
    bulanan: 'Bulanan',
    tahunan: 'Tahunan',
};

function detail(item: Item): string {
    if (item.expected === 0) {
        return 'Belum jatuh tempo';
    }

    if (item.kind === 'daily' || item.kind === 'daily_day') {
        return `${Math.min(item.filled, item.expected)} dari ${item.expected} hari terisi`;
    }

    if (item.kind === 'daily_engine') {
        return `${Math.min(item.filled, item.expected)} dari ${item.expected} hari-mesin terisi`;
    }

    if (item.kind === 'count') {
        return `${Math.min(item.filled, item.expected)} dari ${item.expected} dokumen`;
    }

    return item.filled > 0 ? `Terisi · ${item.filled} baris` : 'Belum diisi';
}

export default function PortalInput({
    filters,
    options,
    areas,
    period_label,
    state,
    groups,
}: Props) {
    const [search, setSearch] = useState('');
    const current = state === 'current';
    const visit = (patch: Partial<Filters>) => {
        const next = { ...filters, ...patch };
        router.get(
            portal.input().url,
            Object.fromEntries(
                Object.entries(next).filter(([, value]) => value !== null),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const shown = useMemo(() => {
        const q = search.trim().toLowerCase();

        return groups
            .map((group) => ({
                ...group,
                items:
                    q === ''
                        ? group.items
                        : group.items.filter((item) =>
                              item.label.toLowerCase().includes(q),
                          ),
            }))
            .filter((group) => group.items.length > 0);
    }, [groups, search]);
    const unitName =
        options.units.find((u) => u.id === filters.unit_id)?.name ?? '';

    return (
        <>
            <Head title="Data Input" />
            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Data Input"
                    description="Lihat hasil input setiap unit per bidang. Klik sebuah input untuk membuka datanya (tampilan lihat saja)."
                />

                {areas.length > 1 && (
                    <div className="-mx-4 flex [scrollbar-width:none] gap-2 overflow-x-auto px-4 md:mx-0 md:px-0 [&::-webkit-scrollbar]:hidden">
                        {areas.map((area) => (
                            <button
                                key={area.key}
                                type="button"
                                onClick={() => visit({ bidang: area.key })}
                                className={cn(
                                    'h-9 shrink-0 rounded-md border px-4 text-[13px] font-medium whitespace-nowrap',
                                    filters.bidang === area.key
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-border bg-card text-foreground hover:bg-muted',
                                )}
                            >
                                {area.label}
                            </button>
                        ))}
                    </div>
                )}

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-secondary p-3">
                    {options.service_units.length > 1 && (
                        <OperasiSelect
                            label="Unit Layanan"
                            className="w-48"
                            value={
                                filters.service_unit_id
                                    ? String(filters.service_unit_id)
                                    : 'all'
                            }
                            onChange={(value) =>
                                visit({
                                    service_unit_id:
                                        value === 'all' ? null : Number(value),
                                    unit_id: null,
                                })
                            }
                            options={[
                                { value: 'all', label: 'Semua Unit Layanan' },
                                ...options.service_units.map((s) => ({
                                    value: String(s.id),
                                    label: s.name,
                                })),
                            ]}
                        />
                    )}
                    <OperasiSelect
                        label="Unit"
                        className="w-52"
                        value={filters.unit_id ? String(filters.unit_id) : ''}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        className="w-36"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={OPERASI_MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        className="w-28"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                    <label className="flex flex-col gap-1 text-[13px]">
                        <span className="text-muted-foreground">
                            Cari input
                        </span>
                        <span className="relative">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Nama input…"
                                className="h-9 w-48 pl-8"
                            />
                        </span>
                    </label>
                </div>

                <p className="text-[13px] text-muted-foreground">
                    <b className="text-foreground">{unitName}</b> ·{' '}
                    {period_label}
                </p>

                {shown.length === 0 ? (
                    <section className="rounded-md border border-border bg-card">
                        <EmptyState
                            title="Tidak ada input yang cocok"
                            description="Ubah kata pencarian atau pilih bidang lain."
                        />
                    </section>
                ) : (
                    shown.map((group) => (
                        <section
                            key={group.key}
                            className="rounded-md border border-border bg-card"
                        >
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-border px-4 py-3">
                                <h2 className="flex-1 text-base font-semibold text-foreground">
                                    {group.label}
                                    <span className="ml-2 rounded-sm bg-muted px-1.5 text-[12px] font-medium text-muted-foreground tabular-nums">
                                        {group.items.length} input
                                    </span>
                                </h2>
                                <div className="flex w-full items-center gap-3 sm:w-64">
                                    <span className="text-[13px] whitespace-nowrap text-muted-foreground">
                                        Kelengkapan
                                    </span>
                                    <div className="flex-1">
                                        <PercentBar percent={group.percent} />
                                    </div>
                                    <PercentPill
                                        percent={group.percent}
                                        current={current}
                                    />
                                </div>
                            </div>

                            <div className="hidden overflow-x-auto md:block">
                                <table
                                    className="w-full border-collapse text-[13px]"
                                    data-keep-table
                                >
                                    <thead>
                                        <tr className="border-b border-border bg-secondary text-left text-[12px] whitespace-nowrap text-muted-foreground">
                                            <th className="w-12 px-4 py-2.5 text-right font-semibold">
                                                No
                                            </th>
                                            <th className="px-3 py-2.5 font-semibold">
                                                Input
                                            </th>
                                            <th className="px-3 py-2.5 font-semibold">
                                                Periode
                                            </th>
                                            <th className="px-3 py-2.5 font-semibold">
                                                Keterangan
                                            </th>
                                            <th className="px-3 py-2.5 font-semibold">
                                                Terakhir diubah
                                            </th>
                                            <th className="px-3 py-2.5 text-right font-semibold">
                                                Kelengkapan
                                            </th>
                                            <th className="px-4 py-2.5 text-right font-semibold">
                                                Aksi
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {group.items.map((item, index) => (
                                            <tr
                                                key={item.key}
                                                className="border-b border-border last:border-0 hover:bg-muted/40"
                                            >
                                                <td className="px-4 py-2 text-right text-muted-foreground tabular-nums">
                                                    {index + 1}
                                                </td>
                                                <td className="px-3 py-2 font-medium text-foreground">
                                                    <Link
                                                        href={item.url}
                                                        className="hover:text-primary hover:underline"
                                                    >
                                                        {item.label}
                                                    </Link>
                                                </td>
                                                <td className="px-3 py-2 text-muted-foreground">
                                                    {PERIOD_LABEL[item.period]}
                                                </td>
                                                <td className="px-3 py-2 text-muted-foreground">
                                                    {detail(item)}
                                                </td>
                                                <td className="px-3 py-2 whitespace-nowrap text-muted-foreground tabular-nums">
                                                    {formatStamp(item.last)}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    <PercentPill
                                                        percent={item.percent}
                                                        current={current}
                                                    />
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <Link href={item.url}>
                                                            <Eye className="size-4" />{' '}
                                                            Lihat
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <ul className="divide-y divide-border md:hidden">
                                {group.items.map((item) => (
                                    <li key={item.key}>
                                        <Link
                                            href={item.url}
                                            className="flex items-center gap-3 px-4 py-3 hover:bg-muted/40"
                                        >
                                            <span className="min-w-0 flex-1">
                                                <span className="block text-sm font-medium text-foreground">
                                                    {item.label}
                                                </span>
                                                <span className="block text-[12px] text-muted-foreground">
                                                    {PERIOD_LABEL[item.period]}{' '}
                                                    · {detail(item)} · Diubah{' '}
                                                    {formatStamp(item.last)}
                                                </span>
                                            </span>
                                            <PercentPill
                                                percent={item.percent}
                                                current={current}
                                            />
                                            <ChevronRight className="size-4 shrink-0 text-muted-foreground" />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))
                )}
            </div>
        </>
    );
}

PortalInput.layout = { breadcrumbs: [] };
