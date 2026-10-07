import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import { OperasiSelect } from '@/components/operasi/filter-select';
import {
    NO_BBM,
    bbmTotals,
    toBbmForm,
    toBbmPayload,
} from '@/components/operasi/rincian-bbm';
import type {
    BbmFuelForm,
    RincianBbmProps,
} from '@/components/operasi/rincian-bbm';
import {
    AmountCell,
    RincianShell,
    formatAmount,
} from '@/components/operasi/rincian-pelumas';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import perincian from '@/routes/operasi/pengusahaan/perincian-bbm';

/**
 * Perincian Bahan Bakar of one jenis BBM, as the field Excel: tank
 * capacities, persediaan awal, pengembalian (TUG 10), penerimaan per periode,
 * pemakaian per mesin, pengiriman, koreksi, sisa perhitungan / fisik and
 * selisih. Shares its typed-in record with Rekap Bahan Bakar.
 */
export default function PerincianBbmIndex(props: RincianBbmProps) {
    const {
        fuels,
        machines,
        tanks,
        auto,
        lain_labels,
        filters,
        catatan,
        can_write,
    } = props;
    const initial = useMemo(() => toBbmForm(props), [props]);
    const [form, setForm] = useState(initial);
    const [note, setNote] = useState(catatan);
    const [saving, setSaving] = useState(false);
    const [code, setCode] = useState(fuels[0]?.key ?? '');
    const dirty =
        JSON.stringify(form) !== JSON.stringify(initial) || note !== catatan;
    const totals = bbmTotals(props, form);
    const fuel = form[code];
    const sum = totals[code];
    const month = String(filters.month).padStart(2, '0');
    const pad = (day: number) => String(day).padStart(2, '0');

    const update = (patch: (current: BbmFuelForm) => BbmFuelForm) =>
        setForm((current) => ({ ...current, [code]: patch(current[code]) }));

    const save = () =>
        router.post(
            perincian.store().url,
            { ...filters, fuels: toBbmPayload(form), catatan: note },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );

    return (
        <RincianShell
            props={props}
            description="Perincian persediaan, penerimaan, pemakaian dan sisa bahan bakar per jenis BBM. Angka abu-abu terisi otomatis dari Persediaan & Pemakaian Bahan Bakar"
            routes={{
                index: perincian.index,
                pdf: (options) =>
                    perincian.pdf({
                        ...options,
                        query: { ...(options?.query ?? {}), fuel: code },
                    }),
            }}
            empty={fuels.length === 0 ? NO_BBM : null}
            filtersExtra={
                fuels.length > 1 && (
                    <OperasiSelect
                        label="Jenis BBM"
                        className="w-52"
                        value={code}
                        onChange={setCode}
                        options={fuels.map((f) => ({
                            value: f.key,
                            label: `${f.name} (${f.key})`,
                        }))}
                    />
                )
            }
            dirty={dirty}
            saving={saving}
            onSave={save}
            note={note}
            setNote={setNote}
        >
            {fuel && sum && (
                <section className="overflow-hidden rounded-md border border-border bg-card">
                    <div className="border-b border-border bg-secondary px-4 py-2.5 text-sm font-semibold">
                        PERINCIAN BAHAN BAKAR{' '}
                        {fuels.find((f) => f.key === code)?.name.toUpperCase()}{' '}
                        ({code})
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[640px] border-collapse text-[13px]">
                            <tbody>
                                <Heading label="Kapasitas tangki penyimpanan" />
                                {tanks[code].length === 0 && (
                                    <Line
                                        label="Belum ada tangki di Data Master"
                                        muted
                                    />
                                )}
                                {tanks[code].map((tank, index) => (
                                    <Line
                                        key={`${tank.name}-${index}`}
                                        no={`${index + 1}.`}
                                        label={tank.name}
                                        value={tank.capacity_liter ?? 0}
                                    />
                                ))}
                                <Line
                                    label="Jumlah"
                                    value={tanks[code].reduce(
                                        (total, t) =>
                                            total + (t.capacity_liter ?? 0),
                                        0,
                                    )}
                                    total
                                />

                                <Line
                                    no="I."
                                    label="Persediaan Awal"
                                    value={sum.awal}
                                    strong
                                />
                                <tr className="font-semibold">
                                    <Cell className="w-14">II</Cell>
                                    <Cell>Pengembalian (TUG 10)</Cell>
                                    <Cell>
                                        <Input
                                            type="date"
                                            aria-label="Tanggal pengembalian"
                                            value={fuel.pengembalian.tanggal}
                                            disabled={!can_write}
                                            onChange={(e) =>
                                                update((current) => ({
                                                    ...current,
                                                    pengembalian: {
                                                        ...current.pengembalian,
                                                        tanggal: e.target.value,
                                                    },
                                                }))
                                            }
                                            className="h-7 w-36 px-2 text-[12px] font-normal"
                                        />
                                    </Cell>
                                    <InputCell
                                        value={fuel.pengembalian.amount}
                                        disabled={!can_write}
                                        label="Pengembalian TUG 10"
                                        onChange={(value) =>
                                            update((current) => ({
                                                ...current,
                                                pengembalian: {
                                                    ...current.pengembalian,
                                                    amount: value,
                                                },
                                            }))
                                        }
                                    />
                                </tr>

                                <Heading no="III" label="Penerimaan" />
                                {auto.penerimaan[code].map((line, index) => (
                                    <Line
                                        key={line.label}
                                        no={`${index + 1}.`}
                                        label={`Pesanan BBM Pri. ${['I', 'II', 'III', 'IV'][index] ?? index + 1}`}
                                        sub={`tgl ${pad(line.from)}-${pad(line.to)}/${month}/${filters.year}`}
                                        value={line.amount}
                                    />
                                ))}
                                <Line
                                    label="Jumlah Penerimaan"
                                    value={sum.penerimaan}
                                    total
                                />
                                <Line
                                    label="Total Persediaan"
                                    value={sum.totalPersediaan}
                                    total
                                />

                                <Heading no="IV" label="Pemakaian" />
                                <Line no="1." label="Pemakaian Mesin" muted />
                                {machines.map((machine, index) => (
                                    <Line
                                        key={machine.id}
                                        no={String(index + 1)}
                                        label={machine.name}
                                        sub={machine.type ?? ''}
                                        value={
                                            auto.pemakaian[machine.id]?.[
                                                code
                                            ] ?? 0
                                        }
                                        indent
                                    />
                                ))}
                                {Object.entries(lain_labels).map(
                                    ([field, label]) =>
                                        sum.lain[field] > 0 && (
                                            <Line
                                                key={field}
                                                label={label}
                                                sub="Rekap Bahan Bakar"
                                                value={sum.lain[field]}
                                                indent
                                            />
                                        ),
                                )}
                                <Line
                                    label="Jumlah Pemakaian"
                                    value={sum.jumlahPemakaian}
                                    total
                                />
                                <Line
                                    no="2."
                                    label="Pengiriman (TUG 8 / Pinjam)"
                                    sub="Persediaan Bahan Bakar"
                                    value={sum.pengiriman}
                                />
                                <tr>
                                    <Cell className="w-14">3.</Cell>
                                    <Cell>Koreksi, Peminjaman</Cell>
                                    <Cell>
                                        <input
                                            aria-label="Keterangan koreksi"
                                            value={fuel.koreksi.keterangan}
                                            placeholder="Keterangan"
                                            disabled={!can_write}
                                            onChange={(e) =>
                                                update((current) => ({
                                                    ...current,
                                                    koreksi: {
                                                        ...current.koreksi,
                                                        keterangan:
                                                            e.target.value,
                                                    },
                                                }))
                                            }
                                            className="h-7 w-full rounded border border-border bg-background px-2 text-[12px]"
                                        />
                                    </Cell>
                                    <InputCell
                                        value={fuel.koreksi.amount}
                                        disabled={!can_write}
                                        label="Koreksi"
                                        onChange={(value) =>
                                            update((current) => ({
                                                ...current,
                                                koreksi: {
                                                    ...current.koreksi,
                                                    amount: value,
                                                },
                                            }))
                                        }
                                    />
                                </tr>
                                <Line
                                    label="Total Pengeluaran"
                                    value={sum.totalPengeluaran}
                                    total
                                />

                                <Line
                                    no="V"
                                    label="Sisa BBM sesuai perhitungan"
                                    value={sum.sisa}
                                    strong
                                />
                                <tr className="font-semibold">
                                    <Cell className="w-14">VI</Cell>
                                    <Cell colSpan={2}>
                                        Sisa BBM persediaan akhir (Fisik)
                                    </Cell>
                                    <InputCell
                                        value={fuel.fisik}
                                        placeholder={formatAmount(sum.sisa)}
                                        disabled={!can_write}
                                        label="Sisa fisik"
                                        onChange={(value) =>
                                            update((current) => ({
                                                ...current,
                                                fisik: value,
                                            }))
                                        }
                                    />
                                </tr>
                                <Line
                                    no="VII"
                                    label="Selisih (fisik − perhitungan)"
                                    value={sum.selisih}
                                    strong
                                />
                            </tbody>
                        </table>
                    </div>
                    <p className="border-t border-border px-4 py-2 text-[12px] text-muted-foreground">
                        Sisa fisik yang dikosongkan mengikuti sisa perhitungan.
                        Pemakaian on operasi / cuci / bocor diisi di Rekap Bahan
                        Bakar; penerimaan dan TUG 8 di Persediaan Bahan Bakar.
                        Semua angka dalam liter.
                    </p>
                </section>
            )}
        </RincianShell>
    );
}

function Cell({
    children,
    className,
    colSpan,
}: {
    children?: ReactNode;
    className?: string;
    colSpan?: number;
}) {
    return (
        <td
            colSpan={colSpan}
            className={cn('border-b border-border px-3 py-1.5', className)}
        >
            {children}
        </td>
    );
}

function Heading({ no = '', label }: { no?: string; label: string }) {
    return (
        <tr className="bg-muted/30 font-semibold">
            <Cell className="w-14">{no}</Cell>
            <Cell colSpan={3}>{label}</Cell>
        </tr>
    );
}

function Line({
    no = '',
    label,
    sub = '',
    value,
    strong = false,
    total = false,
    indent = false,
    muted = false,
}: {
    no?: string;
    label: string;
    sub?: string;
    value?: number;
    strong?: boolean;
    total?: boolean;
    indent?: boolean;
    muted?: boolean;
}) {
    return (
        <tr
            className={cn(
                (strong || total) && 'font-semibold',
                strong && 'bg-muted/30',
                muted && 'text-muted-foreground',
            )}
        >
            <Cell className="w-14">{no}</Cell>
            <Cell className={cn(indent && 'pl-8', total && 'pl-16')}>
                {label}
            </Cell>
            <Cell className="text-[12px] text-muted-foreground">{sub}</Cell>
            <Cell className="w-44 text-right tabular-nums">
                {value === undefined ? '' : `${formatAmount(value)} liter`}
            </Cell>
        </tr>
    );
}

function InputCell({
    value,
    onChange,
    disabled,
    label,
    placeholder,
}: {
    value: string;
    onChange: (value: string) => void;
    disabled: boolean;
    label: string;
    placeholder?: string;
}) {
    return (
        <td className="w-44 border-b border-border p-1">
            <AmountCell
                value={value}
                onChange={onChange}
                disabled={disabled}
                label={label}
                placeholder={placeholder}
                className="rounded border border-border bg-background"
            />
        </td>
    );
}
