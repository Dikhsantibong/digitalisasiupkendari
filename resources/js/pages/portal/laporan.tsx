import { Head, Link, router } from '@inertiajs/react';
import { Download, Eye } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { formatStamp } from '@/components/monitoring/shared';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import portal from '@/routes/portal';
import type { Tone } from '@/types';

type Row = {
    key: string;
    unit: string;
    title: string;
    module: string;
    status: string;
    status_label: string;
    status_tone: string;
    finalized_at: string | null;
    view_url: string;
    download_url: string;
    document_url: string;
};

type Filters = {
    month: number;
    year: number;
    service_unit_id: number | null;
    unit_id: number | null;
    jenis: 'pembangkit' | 'pengusahaan';
    status: 'final' | 'semua';
};

type Props = {
    filters: Filters;
    options: {
        service_units: { id: number; name: string }[];
        units: { id: number; name: string }[];
        years: number[];
    };
    period_label: string;
    rows: Row[];
    has_pengusahaan: boolean;
};

function RowActions({ row }: { row: Row }) {
    return (
        <div className="flex justify-end gap-2 max-md:justify-start">
            <Button size="sm" variant="outline" asChild>
                <a href={row.view_url} target="_blank" rel="noreferrer">
                    <Eye className="size-4" /> Lihat PDF
                </a>
            </Button>
            <Button size="sm" variant="ghost" asChild>
                <a href={row.download_url}>
                    <Download className="size-4" /> Unduh
                </a>
            </Button>
        </div>
    );
}

export default function PortalLaporan({
    filters,
    options,
    period_label,
    rows,
    has_pengusahaan,
}: Props) {
    const visit = (patch: Partial<Filters>) => {
        const next = { ...filters, ...patch };
        router.get(
            portal.laporan().url,
            Object.fromEntries(
                Object.entries(next).filter(([, value]) => value !== null),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Laporan" />
            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Laporan"
                    description={`Laporan Pembangkit (Project) dan Laporan Pengusahaan — lihat atau unduh PDF. ${period_label}.`}
                />

                <div className="-mx-4 flex [scrollbar-width:none] gap-2 overflow-x-auto px-4 md:mx-0 md:px-0 [&::-webkit-scrollbar]:hidden">
                    {(
                        [
                            ['pembangkit', 'Laporan Pembangkit (Project)'],
                            ...(has_pengusahaan
                                ? [['pengusahaan', 'Laporan Pengusahaan']]
                                : []),
                        ] as [Filters['jenis'], string][]
                    ).map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => visit({ jenis: key })}
                            className={cn(
                                'h-9 shrink-0 rounded-md border px-4 text-[13px] font-medium whitespace-nowrap',
                                filters.jenis === key
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-border bg-card text-foreground hover:bg-muted',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </div>

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
                        value={
                            filters.unit_id ? String(filters.unit_id) : 'all'
                        }
                        onChange={(value) =>
                            visit({
                                unit_id: value === 'all' ? null : Number(value),
                            })
                        }
                        options={[
                            { value: 'all', label: 'Semua unit' },
                            ...options.units.map((u) => ({
                                value: String(u.id),
                                label: u.name,
                            })),
                        ]}
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
                    {filters.jenis === 'pembangkit' && (
                        <OperasiSelect
                            label="Status"
                            className="w-44"
                            value={filters.status}
                            onChange={(value) =>
                                visit({ status: value as Filters['status'] })
                            }
                            options={[
                                {
                                    value: 'final',
                                    label: 'Hanya laporan final',
                                },
                                { value: 'semua', label: 'Semua status' },
                            ]}
                        />
                    )}
                </div>

                <section className="rounded-md border border-border bg-card">
                    {rows.length === 0 ? (
                        <EmptyState
                            title={
                                filters.status === 'final' &&
                                filters.jenis === 'pembangkit'
                                    ? 'Belum ada laporan final'
                                    : 'Tidak ada laporan'
                            }
                            description={
                                filters.status === 'final' &&
                                filters.jenis === 'pembangkit'
                                    ? 'Laporan Pembangkit periode ini belum disahkan Manager UL. Pilih "Semua status" untuk melihat laporan yang masih berjalan.'
                                    : 'Ubah filter unit atau periode.'
                            }
                        />
                    ) : (
                        <>
                            <div className="hidden overflow-x-auto md:block">
                                <table
                                    className="w-full border-collapse text-[13px]"
                                    data-keep-table
                                >
                                    <thead>
                                        <tr className="border-b border-border bg-secondary text-left text-[12px] whitespace-nowrap text-muted-foreground">
                                            <th className="px-4 py-2.5 font-semibold">
                                                Laporan
                                            </th>
                                            <th className="px-3 py-2.5 font-semibold">
                                                Unit
                                            </th>
                                            <th className="px-3 py-2.5 font-semibold">
                                                Status
                                            </th>
                                            <th className="px-3 py-2.5 font-semibold">
                                                Diperbarui
                                            </th>
                                            <th className="px-4 py-2.5 text-right font-semibold">
                                                Aksi
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row) => (
                                            <tr
                                                key={row.key}
                                                className="border-b border-border last:border-0 hover:bg-muted/40"
                                            >
                                                <td className="px-4 py-2.5 font-medium text-foreground">
                                                    <Link
                                                        href={row.document_url}
                                                        className="hover:text-primary hover:underline"
                                                    >
                                                        {row.title}
                                                    </Link>
                                                </td>
                                                <td className="px-3 py-2.5 whitespace-nowrap text-foreground">
                                                    {row.unit}
                                                </td>
                                                <td className="px-3 py-2.5">
                                                    <StatusBadge
                                                        tone={
                                                            row.status_tone as Tone
                                                        }
                                                    >
                                                        {row.status === 'final'
                                                            ? 'Final'
                                                            : row.status_label}
                                                    </StatusBadge>
                                                </td>
                                                <td className="px-3 py-2.5 whitespace-nowrap text-muted-foreground tabular-nums">
                                                    {formatStamp(
                                                        row.finalized_at,
                                                    )}
                                                </td>
                                                <td className="px-4 py-2.5">
                                                    <RowActions row={row} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <ul className="divide-y divide-border md:hidden">
                                {rows.map((row) => (
                                    <li
                                        key={row.key}
                                        className="flex flex-col gap-2 px-4 py-3"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="text-sm font-medium text-foreground">
                                                    {row.title}
                                                </p>
                                                <p className="text-[13px] text-muted-foreground">
                                                    {row.unit} ·{' '}
                                                    {formatStamp(
                                                        row.finalized_at,
                                                    )}
                                                </p>
                                            </div>
                                            <StatusBadge
                                                tone={row.status_tone as Tone}
                                            >
                                                {row.status === 'final'
                                                    ? 'Final'
                                                    : row.status_label}
                                            </StatusBadge>
                                        </div>
                                        <RowActions row={row} />
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </section>
            </div>
        </>
    );
}

PortalLaporan.layout = { breadcrumbs: [] };
