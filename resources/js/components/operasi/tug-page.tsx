import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, Eye, Files, Loader2, Pencil, Save } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import type { RouteQueryOptions } from '@/wayfinder';

type Header = {
    nomor: string | null;
    pekerjaan: string;
    no_spk: string | null;
    cost_center: string | null;
    kode_perkiraan: string | null;
    tanggal_dokumen: string | null;
};

export type Tug = {
    jenis: 'pelumas' | 'bbm';
    machine: { id: number; name: string; serial_number: string | null };
    period_label: string;
    header: Header;
    header_saved: boolean;
    columns: {
        key: string;
        name: string;
        code: string | null;
        unit_label: string;
    }[];
    days: {
        day: number;
        date: string;
        values: Record<string, number>;
        total: number;
    }[];
    totals: Record<string, number>;
    grand_total: number;
    has_sheet: boolean;
    signatories: { manager: string | null; tl_operasi: string | null };
};

type Filters = {
    unit_id: number;
    month: number;
    year: number;
    machine_id: number | null;
};

export type TugPageProps = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: Filters;
    machines: {
        id: number;
        name: string;
        serial_number: string | null;
        total: number;
        has_tug: boolean;
    }[];
    tug: Tug | null;
    can_write: boolean;
};

type Url = (options?: RouteQueryOptions) => { url: string };

type Config = {
    title: string;
    description: string;
    /** The sheet the amounts come from, e.g. Pemakaian Pelumas. */
    source: { title: string; index: Url };
    routes: { index: Url; store: Url; pdf: Url };
    emptyColumns: string;
};

const NUMBER = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const DATE = new Intl.DateTimeFormat('id-ID', {
    month: 'short',
    year: '2-digit',
});
const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);

/**
 * TUG 9 (Rekap Bon Pemakaian Energi Primer) per mesin, shared by TUG Pelumas
 * and TUG BBM: unit / bulan / tahun / mesin filter, the machine's TUG header
 * form and a read-only preview of the amounts taken from the source sheet.
 */
export function TugPage({
    unit,
    units,
    filters,
    machines,
    tug,
    can_write,
    config,
}: TugPageProps & { config: Config }) {
    const compact = useCompactLayout();
    const visit = (patch: Partial<Filters>) =>
        router.get(
            config.routes.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    const query = {
        unit_id: filters.unit_id,
        month: filters.month,
        year: filters.year,
    };
    const isBbm = tug?.jenis === 'bbm';

    return (
        <>
            <Head title={config.title} />
            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title={config.title}
                    description={`${config.description} — ${unit.name}. Angka diambil dari menu ${config.source.title}.`}
                    actions={
                        machines.length > 0 && (
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        config.routes.pdf({
                                            query: { ...query, all: 1 },
                                        }).url
                                    }
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Files className="size-4" /> Cetak semua
                                    mesin
                                </a>
                            </Button>
                        )
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-secondary p-3">
                    <OperasiSelect
                        label="Unit"
                        className="w-52"
                        value={String(filters.unit_id)}
                        onChange={(value) =>
                            visit({ unit_id: Number(value), machine_id: null })
                        }
                        options={units.map((u) => ({
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
                        options={YEARS.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                    {machines.length > 0 && (
                        <OperasiSelect
                            label="Mesin"
                            className="w-52"
                            value={String(filters.machine_id ?? '')}
                            onChange={(value) =>
                                visit({ machine_id: Number(value) })
                            }
                            options={machines.map((m) => ({
                                value: String(m.id),
                                label: m.serial_number
                                    ? `${m.name} · SN ${m.serial_number}`
                                    : m.name,
                            }))}
                        />
                    )}
                </div>

                {machines.length === 0 || tug === null ? (
                    <section className="rounded-md border border-border bg-card">
                        <EmptyState
                            title="Belum ada mesin aktif"
                            description="Tambahkan mesin unit ini di Master Mesin."
                        />
                    </section>
                ) : (
                    <>
                        {!compact && (
                            <nav
                                className="flex flex-wrap gap-2"
                                aria-label="Pilih mesin"
                            >
                                {machines.map((machine) => (
                                    <button
                                        key={machine.id}
                                        type="button"
                                        onClick={() =>
                                            visit({ machine_id: machine.id })
                                        }
                                        aria-current={
                                            machine.id === filters.machine_id
                                                ? 'true'
                                                : undefined
                                        }
                                        className={cn(
                                            'flex h-10 items-center gap-2 rounded-md border px-3 text-left text-[13px]',
                                            machine.id === filters.machine_id
                                                ? 'border-primary bg-primary/10 text-primary'
                                                : 'border-border bg-card text-foreground hover:bg-muted',
                                        )}
                                    >
                                        <span className="font-medium">
                                            {machine.name}
                                        </span>
                                        <span className="text-[12px] text-muted-foreground tabular-nums">
                                            {NUMBER.format(machine.total)}
                                        </span>
                                        {machine.has_tug && (
                                            <span
                                                className="size-1.5 rounded-full bg-emerald-500"
                                                aria-label="Kepala TUG tersimpan"
                                            />
                                        )}
                                    </button>
                                ))}
                            </nav>
                        )}

                        {!tug.has_sheet && (
                            <p className="flex flex-wrap items-center gap-3 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-[13px] text-amber-900">
                                <span className="flex-1">
                                    {config.source.title} {tug.period_label}{' '}
                                    belum diisi, jadi semua angka TUG masih 0.
                                </span>
                                <Button size="sm" variant="outline" asChild>
                                    <Link
                                        href={
                                            config.source.index({ query }).url
                                        }
                                    >
                                        Buka {config.source.title}
                                    </Link>
                                </Button>
                            </p>
                        )}

                        <HeaderForm
                            key={`${tug.machine.id}-${filters.month}-${filters.year}`}
                            tug={tug}
                            filters={filters}
                            canWrite={can_write}
                            store={config.routes.store}
                        />

                        <section className="overflow-hidden rounded-md border border-border bg-card">
                            <div className="flex flex-wrap items-center gap-2 border-b border-border px-4 py-3">
                                <h2 className="flex-1 text-base font-semibold text-foreground">
                                    {tug.machine.name}
                                    {tug.machine.serial_number && (
                                        <span className="ml-2 text-[13px] font-normal text-muted-foreground">
                                            SN {tug.machine.serial_number}
                                        </span>
                                    )}
                                </h2>
                                <Button size="sm" variant="outline" asChild>
                                    <a
                                        href={
                                            config.routes.pdf({
                                                query: {
                                                    ...query,
                                                    machine_id: tug.machine.id,
                                                },
                                            }).url
                                        }
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Eye className="size-4" /> Pratinjau PDF
                                    </a>
                                </Button>
                                <Button size="sm" variant="outline" asChild>
                                    <a
                                        href={
                                            config.routes.pdf({
                                                query: {
                                                    ...query,
                                                    machine_id: tug.machine.id,
                                                    download: 1,
                                                },
                                            }).url
                                        }
                                    >
                                        <Download className="size-4" /> Unduh
                                    </a>
                                </Button>
                                {can_write && (
                                    <Button size="sm" variant="ghost" asChild>
                                        <Link
                                            href={
                                                config.source.index({ query })
                                                    .url
                                            }
                                        >
                                            <Pencil className="size-4" /> Ubah
                                            angka
                                        </Link>
                                    </Button>
                                )}
                            </div>
                            {tug.columns.length === 0 ? (
                                <EmptyState
                                    title="Tidak ada material untuk mesin ini"
                                    description={config.emptyColumns}
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table
                                        className="w-full border-collapse text-[13px]"
                                        data-keep-table
                                    >
                                        <thead>
                                            <tr className="border-b border-border bg-secondary text-[12px] whitespace-nowrap text-foreground">
                                                <th
                                                    colSpan={2}
                                                    rowSpan={2}
                                                    className="border-r border-border px-3 py-2 text-center font-semibold"
                                                >
                                                    TANGGAL
                                                </th>
                                                {isBbm && (
                                                    <th
                                                        rowSpan={2}
                                                        className="border-r border-border px-3 py-2 text-center font-semibold"
                                                    >
                                                        SATUAN
                                                    </th>
                                                )}
                                                {tug.columns.map((column) => (
                                                    <th
                                                        key={column.key}
                                                        className="border-r border-border px-3 pt-2 pb-0.5 text-center font-semibold"
                                                    >
                                                        {column.name.toUpperCase()}
                                                    </th>
                                                ))}
                                                <th
                                                    rowSpan={2}
                                                    className="px-3 py-2 text-center font-semibold"
                                                >
                                                    JUMLAH
                                                </th>
                                            </tr>
                                            <tr className="border-b border-border bg-secondary text-[12px] text-muted-foreground">
                                                {tug.columns.map((column) => (
                                                    <th
                                                        key={column.key}
                                                        className="border-r border-border px-3 pb-2 text-center font-normal tabular-nums"
                                                    >
                                                        {column.code ??
                                                            'kode material belum diisi'}{' '}
                                                        · {column.unit_label}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {tug.days.map((row) => (
                                                <tr
                                                    key={row.day}
                                                    className="border-b border-border hover:bg-muted/30"
                                                >
                                                    <td className="w-10 border-r border-border px-2 py-1 text-right text-muted-foreground tabular-nums">
                                                        {row.day}
                                                    </td>
                                                    <td className="w-20 border-r border-border px-2 py-1 text-muted-foreground">
                                                        {DATE.format(
                                                            new Date(
                                                                `${row.date}T00:00:00`,
                                                            ),
                                                        )}
                                                    </td>
                                                    {isBbm && (
                                                        <td className="border-r border-border px-2 py-1 text-center text-muted-foreground">
                                                            Liter
                                                        </td>
                                                    )}
                                                    {tug.columns.map(
                                                        (column) => {
                                                            const value =
                                                                row.values[
                                                                    column.key
                                                                ] ?? 0;

                                                            return (
                                                                <td
                                                                    key={
                                                                        column.key
                                                                    }
                                                                    className={cn(
                                                                        'border-r border-border px-3 py-1 text-right tabular-nums',
                                                                        value ===
                                                                            0 &&
                                                                            'text-muted-foreground',
                                                                    )}
                                                                >
                                                                    {NUMBER.format(
                                                                        value,
                                                                    )}
                                                                </td>
                                                            );
                                                        },
                                                    )}
                                                    <td className="bg-secondary/60 px-3 py-1 text-right font-medium tabular-nums">
                                                        {NUMBER.format(
                                                            row.total,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                            <tr className="bg-secondary font-semibold">
                                                <td
                                                    colSpan={isBbm ? 3 : 2}
                                                    className="border-r border-border px-3 py-2 text-center"
                                                >
                                                    Jumlah total
                                                </td>
                                                {tug.columns.map((column) => (
                                                    <td
                                                        key={column.key}
                                                        className="border-r border-border px-3 py-2 text-right tabular-nums"
                                                    >
                                                        {NUMBER.format(
                                                            tug.totals[
                                                                column.key
                                                            ] ?? 0,
                                                        )}
                                                    </td>
                                                ))}
                                                <td className="px-3 py-2 text-right text-primary tabular-nums">
                                                    {NUMBER.format(
                                                        tug.grand_total,
                                                    )}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <div className="grid gap-2 border-t border-border px-4 py-3 text-[13px] text-muted-foreground sm:grid-cols-2">
                                <p>
                                    Diperiksa — Manager {unit.name}:{' '}
                                    <b className="text-foreground">
                                        {tug.signatories.manager ??
                                            'belum ada di Master Pegawai'}
                                    </b>
                                </p>
                                <p>
                                    Dibuat oleh — Team Leader Operasi:{' '}
                                    <b className="text-foreground">
                                        {tug.signatories.tl_operasi ??
                                            'belum ada di Master Pegawai'}
                                    </b>
                                </p>
                            </div>
                        </section>
                    </>
                )}
            </div>
        </>
    );
}

/** Kepala TUG of the selected machine: nomor, pekerjaan, SPK, cost center, kode perkiraan, tanggal. */
function HeaderForm({
    tug,
    filters,
    canWrite,
    store,
}: {
    tug: Tug;
    filters: Filters;
    canWrite: boolean;
    store: Url;
}) {
    const form = useForm({
        unit_id: filters.unit_id,
        machine_id: tug.machine.id,
        month: filters.month,
        year: filters.year,
        nomor: tug.header.nomor ?? '',
        pekerjaan: tug.header.pekerjaan,
        no_spk: tug.header.no_spk ?? '',
        cost_center: tug.header.cost_center ?? '',
        kode_perkiraan: tug.header.kode_perkiraan ?? '',
        tanggal_dokumen: tug.header.tanggal_dokumen ?? '',
    });

    const fields: {
        key:
            | 'nomor'
            | 'pekerjaan'
            | 'no_spk'
            | 'cost_center'
            | 'kode_perkiraan'
            | 'tanggal_dokumen';
        label: string;
        type?: string;
        placeholder?: string;
        required?: boolean;
    }[] = [
        {
            key: 'nomor',
            label: 'Nomor TUG',
            placeholder:
                tug.jenis === 'bbm'
                    ? '00086/LOG.00.02/560231/2026'
                    : '00081/LOG.00.02/550231/2026',
        },
        { key: 'pekerjaan', label: 'Pekerjaan', required: true },
        { key: 'no_spk', label: 'No. SPK' },
        { key: 'cost_center', label: 'Cost Center', placeholder: '2503133101' },
        {
            key: 'kode_perkiraan',
            label: tug.jenis === 'bbm' ? 'Kode Akun' : 'Kode Perkiraan',
            placeholder: '1023',
        },
        { key: 'tanggal_dokumen', label: 'Tanggal Dokumen', type: 'date' },
    ];

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(store().url, { preserveScroll: true });
            }}
            className="flex flex-col gap-3 rounded-md border border-border bg-card p-4"
        >
            <div className="flex flex-wrap items-center gap-2">
                <h2 className="flex-1 text-base font-semibold text-foreground">
                    Kepala TUG — {tug.machine.name}
                </h2>
                {tug.header_saved ? (
                    <StatusBadge tone="success">Tersimpan</StatusBadge>
                ) : (
                    <StatusBadge tone="neutral">Belum disimpan</StatusBadge>
                )}
            </div>
            {!tug.header_saved && (
                <p className="-mt-1 text-[13px] text-muted-foreground">
                    Cost center, kode, dan pekerjaan diisi otomatis dari TUG
                    bulan sebelumnya mesin ini. Nomor TUG diisi setiap bulan.
                </p>
            )}
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {fields.map((field) => (
                    <div key={field.key} className="flex flex-col gap-1.5">
                        <Label htmlFor={`tug-${field.key}`}>
                            {field.label}
                            {field.required && (
                                <span className="text-destructive"> *</span>
                            )}
                        </Label>
                        <Input
                            id={`tug-${field.key}`}
                            type={field.type ?? 'text'}
                            value={form.data[field.key]}
                            onChange={(e) =>
                                form.setData(field.key, e.target.value)
                            }
                            placeholder={field.placeholder}
                            readOnly={!canWrite}
                            required={field.required}
                        />
                        <InputError message={form.errors[field.key]} />
                    </div>
                ))}
            </div>
            {canWrite && (
                <div className="flex justify-end">
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        {form.processing ? (
                            <Loader2 className="size-4 animate-spin" />
                        ) : (
                            <Save className="size-4" />
                        )}
                        Simpan kepala TUG
                    </Button>
                </div>
            )}
        </form>
    );
}
