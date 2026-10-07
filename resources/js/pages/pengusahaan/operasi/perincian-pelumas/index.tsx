import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import {
    AmountCell,
    NO_PELUMAS,
    RincianShell,
    formatAmount,
    fromInputs,
    sumOf,
    toInputs,
    toNumber,
} from '@/components/operasi/rincian-pelumas';
import type {
    AmountInputs,
    Amounts,
    Lubricant,
    RincianPageProps,
} from '@/components/operasi/rincian-pelumas';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import perincian from '@/routes/operasi/pengusahaan/perincian-pelumas';

type Line = { tanggal: string | null; amounts: Amounts | [] };

type Props = RincianPageProps & {
    manual: {
        asal: Record<string, string> | [];
        pengembalian: Line;
        non_mesin: Amounts | [];
        pengiriman: Record<string, Line>;
        fisik: Amounts | [];
    };
};

type LineInputs = { tanggal: string; amounts: AmountInputs };

const lineInputs = (line: Line | undefined): LineInputs => ({
    tanggal: line?.tanggal ?? '',
    amounts: toInputs(line?.amounts),
});

/**
 * Perincian Minyak Pelumas: persediaan awal, penerimaan per tanggal and
 * pemakaian per mesin come from Persediaan / Pemakaian Pelumas; the asal of a
 * penerimaan, pengembalian, pemakaian non mesin, pengiriman ke unit and the
 * physical stock are typed in. Mirrors PerincianPelumasSheet::totals.
 */
export default function PerincianPelumasIndex(props: Props) {
    const {
        lubricants,
        machines,
        auto,
        manual,
        lines,
        filters,
        catatan,
        can_write,
    } = props;
    const initial = useMemo(
        () => ({
            asal: { ...(Array.isArray(manual.asal) ? {} : manual.asal) },
            pengembalian: lineInputs(manual.pengembalian),
            nonMesin: toInputs(manual.non_mesin),
            pengiriman: Object.fromEntries(
                Object.keys(lines).map((line) => [
                    line,
                    lineInputs(manual.pengiriman[line]),
                ]),
            ),
            fisik: toInputs(manual.fisik),
        }),
        [manual, lines],
    );
    const [form, setForm] = useState(initial);
    const [note, setNote] = useState(catatan);
    const [saving, setSaving] = useState(false);
    const dirty =
        JSON.stringify(form) !== JSON.stringify(initial) || note !== catatan;

    const field = (inputs: AmountInputs): Amounts =>
        Object.fromEntries(
            lubricants.map((l) => [l.key, toNumber(inputs[l.key])]),
        );
    const totals = Object.fromEntries(
        lubricants.map((l) => {
            const k = l.key;
            const penerimaan = auto.penerimaan.reduce(
                (total, line) => total + (line.amounts[k] ?? 0),
                0,
            );
            const jumlahPenerimaan =
                penerimaan + toNumber(form.pengembalian.amounts[k]);
            const totalPersediaan = (auto.awal[k] ?? 0) + jumlahPenerimaan;
            const jumlahPemakaian = Object.values(auto.pemakaian).reduce(
                (total, machine) => total + (machine[k] ?? 0),
                0,
            );
            const nonMesin = toNumber(form.nonMesin[k]);
            const jumlahPengiriman =
                (auto.kirim_persediaan[k] ?? 0) +
                Object.values(form.pengiriman).reduce(
                    (total, line) => total + toNumber(line.amounts[k]),
                    0,
                );
            const sisa =
                totalPersediaan - jumlahPemakaian - nonMesin - jumlahPengiriman;
            const fisik =
                form.fisik[k] === undefined || form.fisik[k] === ''
                    ? (auto.ba_fisik[k] ?? sisa)
                    : toNumber(form.fisik[k]);

            return [
                k,
                {
                    awal: auto.awal[k] ?? 0,
                    jumlahPenerimaan,
                    totalPersediaan,
                    jumlahPemakaian,
                    nonMesin,
                    jumlahPengiriman,
                    sisa,
                    fisik,
                    selisih: fisik - sisa,
                },
            ];
        }),
    );
    const pick = (name: keyof (typeof totals)[string]): Amounts =>
        Object.fromEntries(lubricants.map((l) => [l.key, totals[l.key][name]]));

    const save = () =>
        router.post(
            perincian.store().url,
            {
                ...filters,
                asal: form.asal,
                pengembalian: {
                    tanggal: form.pengembalian.tanggal || null,
                    amounts: fromInputs(form.pengembalian.amounts),
                },
                non_mesin: fromInputs(form.nonMesin),
                pengiriman: Object.fromEntries(
                    Object.entries(form.pengiriman).map(([line, value]) => [
                        line,
                        {
                            tanggal: value.tanggal || null,
                            amounts: fromInputs(value.amounts),
                        },
                    ]),
                ),
                fisik: Object.fromEntries(
                    Object.entries(form.fisik)
                        .filter(([, value]) => value !== '')
                        .map(([key, value]) => [key, toNumber(value)]),
                ),
                catatan: note,
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );

    const dateLabel = (day: number) =>
        `${String(day).padStart(2, '0')}/${String(filters.month).padStart(2, '0')}/${filters.year}`;

    return (
        <RincianShell
            props={props}
            empty={lubricants.length === 0 ? NO_PELUMAS : null}
            description="Rincian persediaan, penerimaan, pemakaian dan pengiriman pelumas per jenis. Angka abu-abu terisi otomatis dari Persediaan & Pemakaian Pelumas"
            routes={{ index: perincian.index, pdf: perincian.pdf }}
            dirty={dirty}
            saving={saving}
            onSave={save}
            note={note}
            setNote={setNote}
        >
            <section className="overflow-hidden rounded-md border border-border bg-card">
                <div className="overflow-x-auto">
                    <table
                        className="w-full border-collapse text-[13px]"
                        data-keep-table
                    >
                        <thead className="text-[12px]">
                            <tr className="bg-secondary">
                                <th
                                    colSpan={3}
                                    rowSpan={2}
                                    className="border-r border-b border-border px-3 py-2 text-left font-semibold"
                                >
                                    Uraian
                                </th>
                                {lubricants.map((l) => (
                                    <th
                                        key={l.key}
                                        className="min-w-28 border-r border-border px-2 pt-2 font-semibold uppercase"
                                    >
                                        {l.name}
                                    </th>
                                ))}
                                <th className="min-w-28 border-border px-2 pt-2 font-semibold">
                                    TOTAL
                                </th>
                            </tr>
                            <tr className="bg-secondary text-muted-foreground">
                                {lubricants.map((l) => (
                                    <th
                                        key={l.key}
                                        className="border-r border-b border-border px-2 pb-2 font-normal"
                                    >
                                        {l.code ?? l.unit_label}
                                    </th>
                                ))}
                                <th className="border-b border-border px-2 pb-2 font-normal">
                                    PELUMAS
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <Row
                                no="I."
                                label="Persediaan Awal"
                                lubricants={lubricants}
                                values={pick('awal')}
                                strong
                            />
                            <Heading
                                no="II."
                                label="Penerimaan"
                                span={lubricants.length + 1}
                            />
                            {auto.penerimaan.length === 0 && (
                                <tr>
                                    <td />
                                    <td
                                        colSpan={lubricants.length + 3}
                                        className="border-b border-border px-3 py-1.5 text-[12px] text-muted-foreground"
                                    >
                                        Belum ada penerimaan di Persediaan
                                        Pelumas bulan ini.
                                    </td>
                                </tr>
                            )}
                            {auto.penerimaan.map((line, index) => (
                                <Row
                                    key={line.day}
                                    no={String(index + 1)}
                                    label={
                                        <input
                                            aria-label={`Asal penerimaan tanggal ${line.day}`}
                                            value={form.asal[line.day] ?? ''}
                                            placeholder="Asal (mis. UPDK Kendari)"
                                            disabled={!can_write}
                                            onChange={(e) =>
                                                setForm((current) => ({
                                                    ...current,
                                                    asal: {
                                                        ...current.asal,
                                                        [line.day]:
                                                            e.target.value,
                                                    },
                                                }))
                                            }
                                            className="h-7 w-full rounded border border-transparent bg-transparent px-1 hover:border-border focus:border-border focus:outline-none"
                                        />
                                    }
                                    sub={`tgl ${dateLabel(line.day)}`}
                                    lubricants={lubricants}
                                    values={line.amounts}
                                />
                            ))}
                            <InputRow
                                no="III"
                                label="Pengembalian"
                                lubricants={lubricants}
                                line={form.pengembalian}
                                canWrite={can_write}
                                onChange={(next) =>
                                    setForm((current) => ({
                                        ...current,
                                        pengembalian: next,
                                    }))
                                }
                                strong
                            />
                            <Row
                                label="Jumlah Penerimaan"
                                lubricants={lubricants}
                                values={pick('jumlahPenerimaan')}
                                total
                            />
                            <Row
                                no="IV"
                                label="Total Persediaan"
                                lubricants={lubricants}
                                values={pick('totalPersediaan')}
                                strong
                                total
                            />
                            <Heading
                                no="V"
                                label="Pemakaian Mesin"
                                span={lubricants.length + 1}
                            />
                            {machines.map((machine, index) => (
                                <Row
                                    key={machine.id}
                                    no={String(index + 1)}
                                    label={machine.name}
                                    sub={machine.type ?? ''}
                                    lubricants={lubricants}
                                    values={auto.pemakaian[machine.id] ?? {}}
                                />
                            ))}
                            <Row
                                label="Jumlah Pemakaian"
                                lubricants={lubricants}
                                values={pick('jumlahPemakaian')}
                                total
                            />
                            <tr>
                                <td className="border-b border-border" />
                                <td
                                    colSpan={2}
                                    className="border-b border-border px-3 py-1.5 font-semibold"
                                >
                                    Pemakaian Non Mesin
                                </td>
                                {lubricants.map((l) => (
                                    <td
                                        key={l.key}
                                        className="border-b border-l border-border p-0"
                                    >
                                        <AmountCell
                                            value={form.nonMesin[l.key]}
                                            disabled={!can_write}
                                            label={`Pemakaian non mesin ${l.name}`}
                                            onChange={(value) =>
                                                setForm((current) => ({
                                                    ...current,
                                                    nonMesin: {
                                                        ...current.nonMesin,
                                                        [l.key]: value,
                                                    },
                                                }))
                                            }
                                        />
                                    </td>
                                ))}
                                <TotalCell
                                    value={sumOf(
                                        lubricants,
                                        field(form.nonMesin),
                                    )}
                                />
                            </tr>
                            <Heading
                                no="VI"
                                label="Pengiriman ke Unit"
                                span={lubricants.length + 1}
                            />
                            {Object.entries(lines).map(
                                ([line, label], index) => (
                                    <InputRow
                                        key={line}
                                        no={`${index + 1}.`}
                                        label={label}
                                        lubricants={lubricants}
                                        line={form.pengiriman[line]}
                                        canWrite={can_write}
                                        onChange={(next) =>
                                            setForm((current) => ({
                                                ...current,
                                                pengiriman: {
                                                    ...current.pengiriman,
                                                    [line]: next,
                                                },
                                            }))
                                        }
                                    />
                                ),
                            )}
                            {sumOf(lubricants, auto.kirim_persediaan) > 0 && (
                                <Row
                                    no={`${Object.keys(lines).length + 1}.`}
                                    label="TUG 8 / TUG 10 / Over Flow"
                                    sub="Persediaan Pelumas"
                                    lubricants={lubricants}
                                    values={auto.kirim_persediaan}
                                />
                            )}
                            <Row
                                label="Jumlah Pengiriman"
                                lubricants={lubricants}
                                values={pick('jumlahPengiriman')}
                                total
                            />
                            <Row
                                no="VII"
                                label="Sisa Pelumas sesuai perhitungan"
                                lubricants={lubricants}
                                values={pick('sisa')}
                                strong
                                total
                            />
                            <tr className="font-semibold">
                                <td className="border-b border-border px-3 py-1.5">
                                    VIII
                                </td>
                                <td
                                    colSpan={2}
                                    className="border-b border-border px-3 py-1.5"
                                >
                                    Sisa Pelumas persediaan akhir (Fisik)
                                </td>
                                {lubricants.map((l) => (
                                    <td
                                        key={l.key}
                                        className="border-b border-l border-border p-0"
                                    >
                                        <AmountCell
                                            value={form.fisik[l.key]}
                                            placeholder={formatAmount(
                                                auto.ba_fisik[l.key] ??
                                                    totals[l.key].sisa,
                                            )}
                                            disabled={!can_write}
                                            label={`Sisa fisik ${l.name}`}
                                            onChange={(value) =>
                                                setForm((current) => ({
                                                    ...current,
                                                    fisik: {
                                                        ...current.fisik,
                                                        [l.key]: value,
                                                    },
                                                }))
                                            }
                                        />
                                    </td>
                                ))}
                                <TotalCell
                                    value={sumOf(lubricants, pick('fisik'))}
                                />
                            </tr>
                            <Row
                                no="IX"
                                label="Selisih"
                                lubricants={lubricants}
                                values={pick('selisih')}
                                strong
                                total
                            />
                        </tbody>
                    </table>
                </div>
            </section>
            <p className="text-[13px] text-muted-foreground">
                Sisa fisik yang dikosongkan mengikuti sisa sesuai perhitungan.
                Selisih = fisik − perhitungan. Penerimaan, persediaan awal dan
                TUG / over flow diubah di menu Persediaan Pelumas; pemakaian
                mesin di menu Pemakaian Pelumas.
            </p>
        </RincianShell>
    );
}

function Heading({
    no,
    label,
    span,
}: {
    no: string;
    label: string;
    span: number;
}) {
    return (
        <tr className="font-semibold">
            <td className="w-12 border-b border-border px-3 py-1.5">{no}</td>
            <td
                colSpan={2 + span}
                className="border-b border-border px-3 py-1.5"
            >
                {label}
            </td>
        </tr>
    );
}

function TotalCell({ value }: { value: number }) {
    return (
        <td className="border-b border-l border-border bg-secondary/60 px-2 py-1.5 text-right font-semibold tabular-nums">
            {formatAmount(value)}
        </td>
    );
}

function Row({
    no = '',
    label,
    sub = '',
    lubricants,
    values,
    strong = false,
    total = false,
}: {
    no?: string;
    label: ReactNode;
    sub?: string;
    lubricants: Lubricant[];
    values: Amounts;
    strong?: boolean;
    total?: boolean;
}) {
    return (
        <tr className={cn(strong && 'font-semibold', total && 'bg-muted/30')}>
            <td className="w-12 border-b border-border px-3 py-1.5">{no}</td>
            <td
                className={cn(
                    'min-w-52 border-b border-border px-3 py-1.5',
                    total && !no && 'pl-8 font-semibold',
                )}
            >
                {label}
            </td>
            <td className="min-w-28 border-b border-border px-3 py-1.5 text-[12px] whitespace-nowrap text-muted-foreground">
                {sub}
            </td>
            {lubricants.map((l) => (
                <td
                    key={l.key}
                    className="border-b border-l border-border px-2 py-1.5 text-right text-muted-foreground tabular-nums"
                >
                    {formatAmount(values[l.key] ?? 0)}
                </td>
            ))}
            <TotalCell value={sumOf(lubricants, values)} />
        </tr>
    );
}

function InputRow({
    no,
    label,
    lubricants,
    line,
    canWrite,
    onChange,
    strong = false,
}: {
    no: string;
    label: string;
    lubricants: Lubricant[];
    line: LineInputs;
    canWrite: boolean;
    onChange: (next: LineInputs) => void;
    strong?: boolean;
}) {
    return (
        <tr className={cn(strong && 'font-semibold')}>
            <td className="w-12 border-b border-border px-3 py-1.5">{no}</td>
            <td className="border-b border-border px-3 py-1.5">{label}</td>
            <td className="border-b border-border px-2 py-1">
                <Input
                    type="date"
                    aria-label={`Tanggal ${label}`}
                    value={line.tanggal}
                    disabled={!canWrite}
                    onChange={(e) =>
                        onChange({ ...line, tanggal: e.target.value })
                    }
                    className="h-7 w-36 px-2 text-[12px] font-normal"
                />
            </td>
            {lubricants.map((l) => (
                <td key={l.key} className="border-b border-l border-border p-0">
                    <AmountCell
                        value={line.amounts[l.key]}
                        disabled={!canWrite}
                        label={`${label} ${l.name}`}
                        onChange={(value) =>
                            onChange({
                                ...line,
                                amounts: { ...line.amounts, [l.key]: value },
                            })
                        }
                    />
                </td>
            ))}
            <TotalCell
                value={lubricants.reduce(
                    (total, l) => total + toNumber(line.amounts[l.key]),
                    0,
                )}
            />
        </tr>
    );
}
