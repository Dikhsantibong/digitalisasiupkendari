import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    AmountCell,
    NO_PELUMAS,
    RincianShell,
    formatAmount,
    toNumber,
} from '@/components/operasi/rincian-pelumas';
import type {
    Lubricant,
    RincianShellProps,
} from '@/components/operasi/rincian-pelumas';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import baFisik from '@/routes/operasi/pengusahaan/ba-fisik-pelumas';

type Row = {
    awal: number;
    penerimaan: number;
    stock: number;
    pemakaian: number;
    pengiriman: number;
    administrasi: number;
};

type Header = {
    nomor: string;
    tanggal: string;
    pukul: string;
    mengetahui: string;
    mengetahui_jabatan: string;
    dibuat: string;
    dibuat_jabatan: string;
};

type Count = { drum: number | null; cm: number | null; liter: number | null };

type Props = Omit<RincianShellProps, 'title'> & {
    lubricants: Lubricant[];
    rows: Record<string, Row>;
    header: Header;
    items: Record<string, Count>;
    catatan: string;
};

type CountInputs = Record<string, { drum: string; cm: string; liter: string }>;

const text = (value: number | null) => (value === null ? '' : String(value));

const COLUMNS: [keyof Row, string][] = [
    ['awal', 'Persediaan awal'],
    ['penerimaan', 'Penerimaan'],
    ['stock', 'Stock'],
    ['pemakaian', 'Pemk. sendiri'],
    ['pengiriman', 'Pengiriman (TUG 8)'],
    ['administrasi', 'Persediaan menurut administrasi'],
];

/**
 * Berita Acara Pemeriksaan Fisik Pelumas: the stock per jenis pelumas from
 * Perincian Minyak Pelumas next to the counted stock (drum, cm, liter) and
 * the selisih fisik − administrasi. The counted liters become the default
 * sisa fisik of Perincian and Rekap Pelumas.
 */
export default function BaFisikPelumasIndex(props: Props) {
    const {
        lubricants,
        rows,
        header: savedHeader,
        items,
        filters,
        catatan,
        can_write,
    } = props;
    const initial = useMemo(
        () => ({
            header: savedHeader,
            counts: Object.fromEntries(
                lubricants.map((l) => [
                    l.key,
                    {
                        drum: text(items[l.key]?.drum ?? null),
                        cm: text(items[l.key]?.cm ?? null),
                        liter: text(items[l.key]?.liter ?? null),
                    },
                ]),
            ) as CountInputs,
        }),
        [savedHeader, items, lubricants],
    );
    const [form, setForm] = useState(initial);
    const [note, setNote] = useState(catatan);
    const [saving, setSaving] = useState(false);
    const dirty =
        JSON.stringify(form) !== JSON.stringify(initial) || note !== catatan;

    const setHeader = (field: keyof Header, value: string) =>
        setForm((current) => ({
            ...current,
            header: { ...current.header, [field]: value },
        }));
    const setCount = (
        key: string,
        field: 'drum' | 'cm' | 'liter',
        value: string,
    ) =>
        setForm((current) => ({
            ...current,
            counts: {
                ...current.counts,
                [key]: { ...current.counts[key], [field]: value },
            },
        }));

    const counted = lubricants.filter((l) => form.counts[l.key]?.liter !== '');
    const sum = (field: keyof Row) =>
        lubricants.reduce((total, l) => total + rows[l.key][field], 0);
    const literTotal = counted.reduce(
        (total, l) => total + toNumber(form.counts[l.key].liter),
        0,
    );
    const selisihTotal = counted.reduce(
        (total, l) =>
            total +
            toNumber(form.counts[l.key].liter) -
            rows[l.key].administrasi,
        0,
    );

    const save = () =>
        router.post(
            baFisik.store().url,
            {
                ...filters,
                header: form.header,
                items: Object.fromEntries(
                    Object.entries(form.counts).map(([key, count]) => [
                        key,
                        {
                            drum:
                                count.drum === '' ? null : toNumber(count.drum),
                            cm: count.cm === '' ? null : toNumber(count.cm),
                            liter:
                                count.liter === ''
                                    ? null
                                    : toNumber(count.liter),
                        },
                    ]),
                ),
                catatan: note,
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );

    const field = (
        label: string,
        name: keyof Header,
        type: 'text' | 'date' = 'text',
        className = '',
    ) => (
        <label className={cn('flex flex-col gap-1 text-[13px]', className)}>
            <span className="text-muted-foreground">{label}</span>
            <Input
                type={type}
                value={form.header[name]}
                disabled={!can_write}
                onChange={(e) => setHeader(name, e.target.value)}
                className="h-9"
            />
        </label>
    );

    return (
        <RincianShell
            props={{ ...props, title: 'BA Pemeriksaan Fisik Pelumas' }}
            description="Berita acara pemeriksaan fisik pelumas: stock menurut administrasi (dari Perincian Minyak Pelumas) dan stock fisik hasil pemeriksaan"
            routes={{ index: baFisik.index, pdf: baFisik.pdf }}
            empty={lubricants.length === 0 ? NO_PELUMAS : null}
            dirty={dirty}
            saving={saving}
            onSave={save}
            note={note}
            setNote={setNote}
        >
            <section className="grid gap-3 rounded-md border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4">
                {field('Nomor BA', 'nomor', 'text', 'sm:col-span-2')}
                {field('Tanggal pemeriksaan', 'tanggal', 'date')}
                {field('Pukul (Wita)', 'pukul')}
                {field('Mengetahui (nama)', 'mengetahui')}
                {field('Jabatan', 'mengetahui_jabatan')}
                {field('Dibuat (nama)', 'dibuat')}
                {field('Jabatan', 'dibuat_jabatan')}
            </section>

            <section className="overflow-hidden rounded-md border border-border bg-card">
                <div className="overflow-x-auto">
                    <table
                        className="w-full border-collapse text-[13px]"
                        data-keep-table
                    >
                        <thead className="text-[12px]">
                            <tr className="bg-secondary">
                                <th
                                    rowSpan={2}
                                    className="border-r border-b border-border px-3 py-2 text-left font-semibold"
                                >
                                    Jenis pelumas
                                </th>
                                {COLUMNS.map(([key, label]) => (
                                    <th
                                        key={key}
                                        rowSpan={2}
                                        className="min-w-24 border-r border-b border-border px-2 py-2 font-semibold"
                                    >
                                        {label}
                                    </th>
                                ))}
                                <th
                                    colSpan={3}
                                    className="border-r border-b border-border px-2 py-1.5 font-semibold"
                                >
                                    Stock fisik
                                </th>
                                <th
                                    rowSpan={2}
                                    className="min-w-24 border-b border-border px-2 py-2 font-semibold"
                                >
                                    Selisih fisik − administrasi
                                </th>
                            </tr>
                            <tr className="bg-secondary">
                                {['drum', 'cm', 'liter'].map((unit) => (
                                    <th
                                        key={unit}
                                        className="min-w-20 border-r border-b border-border px-2 py-1 font-normal"
                                    >
                                        {unit}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {lubricants.map((l) => {
                                const row = rows[l.key];
                                const count = form.counts[l.key];
                                const selisih =
                                    count.liter === ''
                                        ? null
                                        : toNumber(count.liter) -
                                          row.administrasi;

                                return (
                                    <tr key={l.key}>
                                        <td className="border-b border-border px-3 py-1.5 font-medium whitespace-nowrap">
                                            {l.name}
                                            {l.code && (
                                                <span className="ml-1 text-muted-foreground">
                                                    {l.code}
                                                </span>
                                            )}
                                        </td>
                                        {COLUMNS.map(([key]) => (
                                            <td
                                                key={key}
                                                className={cn(
                                                    'border-b border-l border-border px-2 py-1.5 text-right tabular-nums',
                                                    key === 'administrasi'
                                                        ? 'font-semibold'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {formatAmount(row[key])}
                                            </td>
                                        ))}
                                        {(['drum', 'cm', 'liter'] as const).map(
                                            (unit) => (
                                                <td
                                                    key={unit}
                                                    className="border-b border-l border-border p-0"
                                                >
                                                    <AmountCell
                                                        value={count[unit]}
                                                        placeholder={
                                                            unit === 'liter'
                                                                ? formatAmount(
                                                                      row.administrasi,
                                                                  )
                                                                : undefined
                                                        }
                                                        disabled={!can_write}
                                                        label={`${unit} ${l.name}`}
                                                        onChange={(value) =>
                                                            setCount(
                                                                l.key,
                                                                unit,
                                                                value,
                                                            )
                                                        }
                                                    />
                                                </td>
                                            ),
                                        )}
                                        <td
                                            className={cn(
                                                'border-b border-l border-border px-2 py-1.5 text-right font-semibold tabular-nums',
                                                selisih !== null &&
                                                    selisih !== 0 &&
                                                    'text-destructive',
                                            )}
                                        >
                                            {selisih === null
                                                ? '—'
                                                : formatAmount(selisih)}
                                        </td>
                                    </tr>
                                );
                            })}
                            <tr className="bg-secondary font-semibold">
                                <td className="px-3 py-1.5">JUMLAH TOTAL</td>
                                {COLUMNS.map(([key]) => (
                                    <td
                                        key={key}
                                        className="border-l border-border px-2 py-1.5 text-right tabular-nums"
                                    >
                                        {formatAmount(sum(key))}
                                    </td>
                                ))}
                                <td className="border-l border-border bg-muted" />
                                <td className="border-l border-border bg-muted" />
                                <td className="border-l border-border px-2 py-1.5 text-right tabular-nums">
                                    {counted.length === 0
                                        ? '—'
                                        : formatAmount(literTotal)}
                                </td>
                                <td className="border-l border-border px-2 py-1.5 text-right tabular-nums">
                                    {counted.length === 0
                                        ? '—'
                                        : formatAmount(selisihTotal)}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p className="border-t border-border px-4 py-2 text-[12px] text-muted-foreground">
                    Isi stock fisik (liter) hasil pemeriksaan; drum dan cm
                    sebagai keterangan. Liter yang diisi menjadi sisa fisik di
                    Perincian Minyak Pelumas dan Rekap Pelumas.
                </p>
            </section>
        </RincianShell>
    );
}
