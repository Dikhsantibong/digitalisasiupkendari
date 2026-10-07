import { Head, router } from '@inertiajs/react';
import { Download, Eye, Loader2, RotateCcw, Save } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { formatStamp } from '@/components/monitoring/shared';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { SummaryCard } from '@/components/summary-card';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import kinerja from '@/routes/operasi/pengusahaan/kinerja';

type Field =
    | 'daya_terpasang'
    | 'daya_mampu'
    | 'kondisi'
    | 'kode_kondisi'
    | 'jam_operasi'
    | 'jam_har'
    | 'jam_gangguan';

type Values = Record<Field, number | string>;

type Row = {
    machine: {
        id: number;
        name: string;
        merk: string | null;
        type: string | null;
        serial_number: string | null;
    };
    auto: Values;
    values: Values;
    edited: Field[];
    ratio: number;
};

type Filters = { unit_id: number; month: number; year: number };

type Props = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: Filters;
    period_label: string;
    rows: Row[];
    totals: {
        daya_terpasang: number;
        daya_mampu: number;
        jam_operasi: number;
        jam_har: number;
        jam_gangguan: number;
        ratio: number;
    };
    kondisi_options: string[];
    catatan: string;
    saved_at: string | null;
    can_write: boolean;
};

const COLUMNS: {
    field: Field;
    label: string;
    source: string;
    decimals: number;
}[] = [
    {
        field: 'daya_terpasang',
        label: 'Daya Terpasang',
        source: 'Master Mesin',
        decimals: 0,
    },
    {
        field: 'daya_mampu',
        label: 'Daya Mampu',
        source: 'Beban Tertinggi',
        decimals: 0,
    },
    {
        field: 'jam_operasi',
        label: 'Jam Operasi',
        source: 'Jam Operasi',
        decimals: 2,
    },
    {
        field: 'jam_har',
        label: 'Jam HAR',
        source: 'Jam Pemeliharaan',
        decimals: 2,
    },
    {
        field: 'jam_gangguan',
        label: 'Jam Gangguan',
        source: 'Jam Gangguan',
        decimals: 2,
    },
];

const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);
const formatter = (decimals: number) =>
    new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
const toNumber = (value: number | string) => {
    const parsed = Number(String(value).replace(',', '.'));

    return Number.isFinite(parsed) ? parsed : 0;
};

const initial = (rows: Row[]) =>
    Object.fromEntries(
        rows.map((row) => [
            row.machine.id,
            Object.fromEntries(
                Object.entries(row.values).map(([field, value]) => [
                    field,
                    String(value),
                ]),
            ),
        ]),
    ) as Record<string, Record<Field, string>>;

/**
 * Kinerja Unit Mesin — filled automatically from the other sheets; each cell
 * can be corrected (marked) and reset to the automatic value.
 */
export default function KinerjaIndex({
    unit,
    units,
    filters,
    period_label,
    rows,
    totals,
    kondisi_options,
    catatan,
    saved_at,
    can_write,
}: Props) {
    const [values, setValues] = useState(() => initial(rows));
    const [note, setNote] = useState(catatan);
    const [saving, setSaving] = useState(false);
    const dirty =
        JSON.stringify(values) !== JSON.stringify(initial(rows)) ||
        note !== catatan;

    const set = (machineId: number, field: Field, value: string) =>
        setValues((current) => ({
            ...current,
            [machineId]: { ...current[machineId], [field]: value },
        }));
    const isEdited = (row: Row, field: Field) =>
        String(values[row.machine.id]?.[field] ?? '') !==
        String(row.auto[field]);
    const ratioOf = (row: Row) => {
        const terpasang = toNumber(values[row.machine.id].daya_terpasang);

        return terpasang > 0
            ? (toNumber(values[row.machine.id].daya_mampu) / terpasang) * 100
            : 0;
    };
    const operating = rows.filter(
        (row) => values[row.machine.id].kondisi === 'Beroperasi',
    );
    const liveRatio =
        operating.length === 0
            ? 0
            : operating.reduce((sum, row) => sum + ratioOf(row), 0) /
              operating.length;
    const sum = (field: Field) =>
        rows.reduce(
            (total, row) => total + toNumber(values[row.machine.id][field]),
            0,
        );

    const visit = (patch: Partial<Filters>) => {
        if (
            dirty &&
            can_write &&
            !window.confirm(
                'Perubahan belum disimpan. Pindah halaman dan buang perubahan?',
            )
        ) {
            return;
        }

        router.get(
            kinerja.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    };

    const save = () =>
        router.post(
            kinerja.store().url,
            { ...filters, values, catatan: note },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );

    const query = {
        unit_id: filters.unit_id,
        month: filters.month,
        year: filters.year,
    };
    const editedCount = rows.reduce(
        (count, row) =>
            count +
            (Object.keys(row.values) as Field[]).filter((field) =>
                isEdited(row, field),
            ).length,
        0,
    );

    return (
        <>
            <Head title="Kinerja Unit Mesin" />
            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Kinerja Unit Mesin"
                    description={`Pengusahaan pembangkitan ${unit.name}, ${period_label}. Terisi otomatis dari Master Mesin, Beban Tertinggi, dan Jam Operasi/Pemeliharaan/Gangguan — nilai bisa dikoreksi.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={kinerja.pdf({ query }).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Eye className="size-4" /> Pratinjau PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        kinerja.pdf({
                                            query: { ...query, download: 1 },
                                        }).url
                                    }
                                >
                                    <Download className="size-4" /> Unduh
                                </a>
                            </Button>
                            {can_write && (
                                <Button
                                    onClick={save}
                                    disabled={saving || !dirty}
                                >
                                    {saving ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <Save className="size-4" />
                                    )}
                                    Simpan
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-secondary p-3">
                    <OperasiSelect
                        label="Unit"
                        className="w-52"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
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
                    <p className="ml-auto self-center text-[13px] text-muted-foreground">
                        {dirty && can_write ? (
                            <span className="font-medium text-amber-700">
                                Ada perubahan yang belum disimpan
                            </span>
                        ) : saved_at ? (
                            `Tersimpan ${formatStamp(saved_at)}`
                        ) : (
                            'Nilai otomatis (belum ada koreksi)'
                        )}
                    </p>
                </div>

                {rows.length === 0 ? (
                    <section className="rounded-md border border-border bg-card">
                        <EmptyState
                            title="Belum ada mesin aktif"
                            description="Tambahkan mesin unit ini di Master Mesin."
                        />
                    </section>
                ) : (
                    <>
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <SummaryCard
                                label="Daya terpasang"
                                value={formatter(0).format(
                                    sum('daya_terpasang'),
                                )}
                                unit="kW"
                            />
                            <SummaryCard
                                label="Daya mampu"
                                value={formatter(0).format(sum('daya_mampu'))}
                                unit="kW"
                            />
                            <SummaryCard
                                label="Ratio daya pembangkit"
                                value={formatter(2).format(liveRatio)}
                                unit="%"
                                hint={`Rata-rata ${operating.length} mesin beroperasi`}
                            />
                            <SummaryCard
                                label="Nilai dikoreksi"
                                value={editedCount}
                                hint="Sel yang berbeda dari nilai otomatis"
                            />
                        </div>

                        <section className="overflow-hidden rounded-md border border-border bg-card">
                            <div className="overflow-x-auto">
                                <table
                                    className="w-full border-collapse text-[13px]"
                                    data-keep-table
                                >
                                    <thead>
                                        <tr className="border-b border-border bg-secondary text-left text-[12px] whitespace-nowrap text-muted-foreground">
                                            <th className="px-3 py-2 font-semibold">
                                                Mesin
                                            </th>
                                            <th className="px-3 py-2 font-semibold">
                                                Merk / Type / Seri
                                            </th>
                                            {COLUMNS.slice(0, 2).map(
                                                (column) => (
                                                    <th
                                                        key={column.field}
                                                        className="px-3 py-2 text-right font-semibold"
                                                    >
                                                        {column.label}
                                                    </th>
                                                ),
                                            )}
                                            <th className="px-3 py-2 font-semibold">
                                                Kondisi
                                            </th>
                                            <th className="px-3 py-2 font-semibold">
                                                Kode
                                            </th>
                                            {COLUMNS.slice(2).map((column) => (
                                                <th
                                                    key={column.field}
                                                    className="px-3 py-2 text-right font-semibold"
                                                >
                                                    {column.label}
                                                </th>
                                            ))}
                                            <th className="px-3 py-2 text-right font-semibold">
                                                Ratio Daya (%)
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row) => {
                                            const numeric = (
                                                column: (typeof COLUMNS)[number],
                                            ) => (
                                                <EditableCell
                                                    key={column.field}
                                                    edited={isEdited(
                                                        row,
                                                        column.field,
                                                    )}
                                                    autoLabel={`${column.source}: ${formatter(column.decimals).format(toNumber(row.auto[column.field]))}`}
                                                    onReset={() =>
                                                        set(
                                                            row.machine.id,
                                                            column.field,
                                                            String(
                                                                row.auto[
                                                                    column.field
                                                                ],
                                                            ),
                                                        )
                                                    }
                                                    canWrite={can_write}
                                                >
                                                    <input
                                                        inputMode="decimal"
                                                        aria-label={`${column.label} ${row.machine.name}`}
                                                        value={
                                                            values[
                                                                row.machine.id
                                                            ][column.field]
                                                        }
                                                        readOnly={!can_write}
                                                        onChange={(e) =>
                                                            /^\d*([.,]\d{0,3})?$/.test(
                                                                e.target.value,
                                                            ) &&
                                                            set(
                                                                row.machine.id,
                                                                column.field,
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="h-8 w-24 bg-transparent px-1 text-right tabular-nums outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                    />
                                                </EditableCell>
                                            );

                                            return (
                                                <tr
                                                    key={row.machine.id}
                                                    className="border-b border-border"
                                                >
                                                    <td className="px-3 py-1.5 font-medium whitespace-nowrap text-foreground">
                                                        {row.machine.name}
                                                    </td>
                                                    <td className="px-3 py-1.5 text-[12px] whitespace-nowrap text-muted-foreground">
                                                        {[
                                                            row.machine.merk,
                                                            row.machine.type,
                                                            row.machine
                                                                .serial_number,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ') || '—'}
                                                    </td>
                                                    {COLUMNS.slice(0, 2).map(
                                                        numeric,
                                                    )}
                                                    <EditableCell
                                                        edited={isEdited(
                                                            row,
                                                            'kondisi',
                                                        )}
                                                        autoLabel={`Otomatis: ${row.auto.kondisi}`}
                                                        onReset={() =>
                                                            set(
                                                                row.machine.id,
                                                                'kondisi',
                                                                String(
                                                                    row.auto
                                                                        .kondisi,
                                                                ),
                                                            )
                                                        }
                                                        canWrite={can_write}
                                                        align="left"
                                                    >
                                                        <Select
                                                            value={
                                                                values[
                                                                    row.machine
                                                                        .id
                                                                ].kondisi ||
                                                                undefined
                                                            }
                                                            disabled={
                                                                !can_write
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                set(
                                                                    row.machine
                                                                        .id,
                                                                    'kondisi',
                                                                    value,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger
                                                                aria-label={`Kondisi ${row.machine.name}`}
                                                                className="h-8 min-w-36 text-[13px] disabled:opacity-100"
                                                            >
                                                                <SelectValue placeholder="Pilih kondisi" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {kondisi_options.map(
                                                                    (
                                                                        option,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                option
                                                                            }
                                                                            value={
                                                                                option
                                                                            }
                                                                        >
                                                                            {
                                                                                option
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    </EditableCell>
                                                    <EditableCell
                                                        edited={isEdited(
                                                            row,
                                                            'kode_kondisi',
                                                        )}
                                                        autoLabel="Otomatis: kosong"
                                                        onReset={() =>
                                                            set(
                                                                row.machine.id,
                                                                'kode_kondisi',
                                                                '',
                                                            )
                                                        }
                                                        canWrite={can_write}
                                                        align="left"
                                                    >
                                                        <input
                                                            aria-label={`Kode kondisi ${row.machine.name}`}
                                                            value={
                                                                values[
                                                                    row.machine
                                                                        .id
                                                                ].kode_kondisi
                                                            }
                                                            readOnly={
                                                                !can_write
                                                            }
                                                            maxLength={20}
                                                            onChange={(e) =>
                                                                set(
                                                                    row.machine
                                                                        .id,
                                                                    'kode_kondisi',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="h-8 w-16 bg-transparent px-1 outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                        />
                                                    </EditableCell>
                                                    {COLUMNS.slice(2).map(
                                                        numeric,
                                                    )}
                                                    <td className="px-3 py-1.5 text-right font-semibold tabular-nums">
                                                        {formatter(3).format(
                                                            ratioOf(row),
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                        <tr className="bg-secondary font-semibold">
                                            <td
                                                colSpan={2}
                                                className="px-3 py-2 text-right"
                                            >
                                                Jumlah
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {formatter(0).format(
                                                    sum('daya_terpasang'),
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {formatter(0).format(
                                                    sum('daya_mampu'),
                                                )}
                                            </td>
                                            <td colSpan={2} />
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {formatter(2).format(
                                                    sum('jam_operasi'),
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {formatter(2).format(
                                                    sum('jam_har'),
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {formatter(2).format(
                                                    sum('jam_gangguan'),
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-right text-primary tabular-nums">
                                                {formatter(2).format(liveRatio)}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p className="border-t border-border px-4 py-2 text-[12px] text-muted-foreground">
                                Sel bertanda biru sudah dikoreksi dari nilai
                                otomatis (arahkan kursor untuk melihat nilai
                                otomatisnya). Ratio unit = rata-rata ratio mesin
                                yang beroperasi. Total tersimpan saat ini: ratio{' '}
                                {formatter(2).format(totals.ratio)}%.
                            </p>
                        </section>
                    </>
                )}

                <section className="flex flex-col gap-1.5 rounded-md border border-border bg-card p-4">
                    <label
                        htmlFor="catatan"
                        className="text-sm font-medium text-foreground"
                    >
                        Catatan
                    </label>
                    <Textarea
                        id="catatan"
                        rows={3}
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        readOnly={!can_write}
                        placeholder={
                            can_write
                                ? 'Catatan (opsional)'
                                : 'Tidak ada catatan'
                        }
                    />
                </section>
            </div>
        </>
    );
}

/** A table cell that marks a corrected value and offers a reset to the automatic one. */
function EditableCell({
    edited,
    autoLabel,
    onReset,
    canWrite,
    align = 'right',
    children,
}: {
    edited: boolean;
    autoLabel: string;
    onReset: () => void;
    canWrite: boolean;
    align?: 'left' | 'right';
    children: React.ReactNode;
}) {
    return (
        <td
            className={cn('px-2 py-1', edited && 'bg-primary/5')}
            title={autoLabel}
        >
            <div
                className={cn(
                    'flex items-center gap-1',
                    align === 'right' ? 'justify-end' : 'justify-start',
                )}
            >
                {children}
                {edited && canWrite ? (
                    <button
                        type="button"
                        onClick={onReset}
                        className="rounded-sm p-0.5 text-primary hover:bg-primary/10"
                        aria-label={`Kembalikan ke nilai otomatis (${autoLabel})`}
                    >
                        <RotateCcw className="size-3.5" />
                    </button>
                ) : (
                    edited && (
                        <span
                            className="size-1.5 rounded-full bg-primary"
                            aria-label="Dikoreksi"
                        />
                    )
                )}
            </div>
        </td>
    );
}
