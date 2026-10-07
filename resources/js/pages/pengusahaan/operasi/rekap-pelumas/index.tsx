import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import {
    AmountCell,
    NO_PELUMAS,
    RincianShell,
    formatAmount,
    fromInputs,
    toInputs,
    toNumber,
} from '@/components/operasi/rincian-pelumas';
import type {
    AmountInputs,
    Amounts,
    Lubricant,
    RincianPageProps,
} from '@/components/operasi/rincian-pelumas';
import { cn } from '@/lib/utils';
import rekap from '@/routes/operasi/pengusahaan/rekap-pelumas';

type Props = RincianPageProps & {
    manual: {
        alat_bantu: Record<string, Amounts> | [];
        fisik: Amounts | [];
    };
};

/** The PELUMAS (liter / drum) and GREASE (kg) totals of a row. */
const split = (lubricants: Lubricant[], amounts: Amounts) =>
    lubricants.reduce(
        (total, l) =>
            l.unit_of_measure === 'kg'
                ? { ...total, grease: total.grease + (amounts[l.key] ?? 0) }
                : { ...total, pelumas: total.pelumas + (amounts[l.key] ?? 0) },
        { pelumas: 0, grease: 0 },
    );

/**
 * Rekap Pelumas — Pemakaian Pelumas & Grease: persediaan, (A) pemakaian per
 * mesin, (B) pemakaian alat bantu (the first line, Pengiriman (TUG 8), from
 * Persediaan Pelumas; the rest typed in), sisa kartu / fisik and selisih.
 * Mirrors RekapPelumasSheet::totals.
 */
export default function RekapPelumasIndex(props: Props) {
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
            alatBantu: Object.fromEntries(
                Object.keys(lines)
                    .filter((line) => line !== 'pengiriman')
                    .map((line) => [
                        line,
                        toInputs(
                            Array.isArray(manual.alat_bantu)
                                ? undefined
                                : manual.alat_bantu[line],
                        ),
                    ]),
            ) as Record<string, AmountInputs>,
            fisik: toInputs(manual.fisik),
        }),
        [manual, lines],
    );
    const [form, setForm] = useState(initial);
    const [note, setNote] = useState(catatan);
    const [saving, setSaving] = useState(false);
    const dirty =
        JSON.stringify(form) !== JSON.stringify(initial) || note !== catatan;

    const totals = Object.fromEntries(
        lubricants.map((l) => {
            const k = l.key;
            const penerimaan = auto.penerimaan.reduce(
                (total, line) => total + (line.amounts[k] ?? 0),
                0,
            );
            const total = (auto.awal[k] ?? 0) + penerimaan;
            const mesin = Object.values(auto.pemakaian).reduce(
                (sum, machine) => sum + (machine[k] ?? 0),
                0,
            );
            const alatBantu =
                (auto.kirim_persediaan[k] ?? 0) +
                Object.values(form.alatBantu).reduce(
                    (sum, line) => sum + toNumber(line[k]),
                    0,
                );
            const kartu = total - mesin - alatBantu;
            const fisik =
                form.fisik[k] === undefined || form.fisik[k] === ''
                    ? (auto.ba_fisik[k] ?? kartu)
                    : toNumber(form.fisik[k]);

            return [
                k,
                {
                    awal: auto.awal[k] ?? 0,
                    penerimaan,
                    total,
                    mesin,
                    alatBantu,
                    pemakaian: mesin + alatBantu,
                    kartu,
                    fisik,
                    selisih: fisik - kartu,
                },
            ];
        }),
    );
    const pick = (name: keyof (typeof totals)[string]): Amounts =>
        Object.fromEntries(lubricants.map((l) => [l.key, totals[l.key][name]]));
    const fromAmountInputs = (inputs: AmountInputs): Amounts =>
        Object.fromEntries(
            lubricants.map((l) => [l.key, toNumber(inputs[l.key])]),
        );

    const save = () =>
        router.post(
            rekap.store().url,
            {
                ...filters,
                alat_bantu: Object.fromEntries(
                    Object.entries(form.alatBantu).map(([line, inputs]) => [
                        line,
                        fromInputs(inputs),
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

    const totalCells = (amounts: Amounts) => {
        const { pelumas, grease } = split(lubricants, amounts);

        return (
            <>
                <td className="border-b border-l border-border bg-secondary/60 px-2 py-1.5 text-right font-semibold tabular-nums">
                    {formatAmount(pelumas)}
                </td>
                <td className="border-b border-l border-border bg-secondary/60 px-2 py-1.5 text-right font-semibold tabular-nums">
                    {formatAmount(grease)}
                </td>
            </>
        );
    };
    const valueRow = (
        key: string,
        lead: ReactNode,
        amounts: Amounts,
        className?: string,
    ) => (
        <tr key={key} className={className}>
            {lead}
            {lubricants.map((l) => (
                <td
                    key={l.key}
                    className="border-b border-l border-border px-2 py-1.5 text-right text-muted-foreground tabular-nums"
                >
                    {formatAmount(amounts[l.key] ?? 0)}
                </td>
            ))}
            {totalCells(amounts)}
        </tr>
    );
    const labelCell = (label: string, align: 'center' | 'left' = 'center') => (
        <td
            colSpan={5}
            className={cn(
                'border-b border-border px-3 py-1.5 font-semibold',
                align === 'center' ? 'text-center' : 'text-left',
            )}
        >
            {label}
        </td>
    );

    return (
        <RincianShell
            props={props}
            empty={lubricants.length === 0 ? NO_PELUMAS : null}
            description="Pemakaian pelumas & grease per mesin dan alat bantu, dengan sisa persediaan kartu dan fisik. Angka abu-abu terisi otomatis dari Persediaan & Pemakaian Pelumas"
            routes={{ index: rekap.index, pdf: rekap.pdf }}
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
                                    colSpan={5}
                                    className="border-r border-b border-border px-2 py-1.5 font-semibold"
                                >
                                    DATA PEMBANGKIT
                                </th>
                                {lubricants.map((l) => (
                                    <th
                                        key={l.key}
                                        rowSpan={2}
                                        className="min-w-28 border-r border-b border-border px-2 py-1.5 font-semibold"
                                    >
                                        {l.name}
                                        {l.code && (
                                            <span className="block font-normal text-muted-foreground">
                                                {l.code}
                                            </span>
                                        )}
                                    </th>
                                ))}
                                <th
                                    rowSpan={2}
                                    className="min-w-28 border-r border-b border-border px-2 py-1.5 font-semibold"
                                >
                                    TOTAL (LTR)
                                    <span className="block font-normal text-muted-foreground">
                                        PELUMAS
                                    </span>
                                </th>
                                <th
                                    rowSpan={2}
                                    className="min-w-28 border-b border-border px-2 py-1.5 font-semibold"
                                >
                                    TOTAL (KG)
                                    <span className="block font-normal text-muted-foreground">
                                        GREASE
                                    </span>
                                </th>
                            </tr>
                            <tr className="bg-secondary">
                                {['NO', 'UNIT', 'MERK', 'TYPE', 'NO. SERI'].map(
                                    (label) => (
                                        <th
                                            key={label}
                                            className="border-r border-b border-border px-2 py-1.5 font-semibold whitespace-nowrap"
                                        >
                                            {label}
                                        </th>
                                    ),
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {valueRow(
                                'awal',
                                labelCell('PERSEDIAAN AWAL'),
                                pick('awal'),
                            )}
                            {valueRow(
                                'penerimaan',
                                labelCell('PENERIMAAN'),
                                pick('penerimaan'),
                            )}
                            {valueRow(
                                'total',
                                labelCell('TOTAL'),
                                pick('total'),
                                'bg-muted/30 font-semibold',
                            )}
                            {machines.map((machine, index) =>
                                valueRow(
                                    `m-${machine.id}`,
                                    <>
                                        <td className="border-b border-border px-2 py-1.5 text-center">
                                            {index + 1}
                                        </td>
                                        <td className="border-b border-border px-2 py-1.5 font-medium whitespace-nowrap">
                                            {machine.name}
                                        </td>
                                        <td className="border-b border-border px-2 py-1.5 whitespace-nowrap">
                                            {machine.merk ?? '-'}
                                        </td>
                                        <td className="border-b border-border px-2 py-1.5 whitespace-nowrap">
                                            {machine.type ?? '-'}
                                        </td>
                                        <td className="border-b border-border px-2 py-1.5 whitespace-nowrap">
                                            {machine.serial_number ?? '-'}
                                        </td>
                                    </>,
                                    auto.pemakaian[machine.id] ?? {},
                                ),
                            )}
                            {valueRow(
                                'a',
                                labelCell('(A) JUMLAH PEMAKAIAN MESIN', 'left'),
                                pick('mesin'),
                                'bg-muted/30 font-semibold',
                            )}
                            {Object.entries(lines).map(
                                ([line, label], index) => {
                                    const lead = (
                                        <>
                                            <td className="border-b border-border px-2 py-1.5 text-center">
                                                {index + 1}
                                            </td>
                                            <td
                                                colSpan={4}
                                                className="border-b border-border px-2 py-1.5"
                                            >
                                                {label}
                                                {line === 'pengiriman' && (
                                                    <span className="ml-1 text-[12px] text-muted-foreground">
                                                        (dari Persediaan
                                                        Pelumas: TUG 8 / TUG 10
                                                        / Over Flow)
                                                    </span>
                                                )}
                                            </td>
                                        </>
                                    );

                                    if (line === 'pengiriman') {
                                        return valueRow(
                                            line,
                                            lead,
                                            auto.kirim_persediaan,
                                        );
                                    }

                                    const inputs = form.alatBantu[line] ?? {};

                                    return (
                                        <tr key={line}>
                                            {lead}
                                            {lubricants.map((l) => (
                                                <td
                                                    key={l.key}
                                                    className="border-b border-l border-border p-0"
                                                >
                                                    <AmountCell
                                                        value={inputs[l.key]}
                                                        disabled={!can_write}
                                                        label={`${label} ${l.name}`}
                                                        onChange={(value) =>
                                                            setForm(
                                                                (current) => ({
                                                                    ...current,
                                                                    alatBantu: {
                                                                        ...current.alatBantu,
                                                                        [line]: {
                                                                            ...(current
                                                                                .alatBantu[
                                                                                line
                                                                            ] ??
                                                                                {}),
                                                                            [l.key]:
                                                                                value,
                                                                        },
                                                                    },
                                                                }),
                                                            )
                                                        }
                                                    />
                                                </td>
                                            ))}
                                            {totalCells(
                                                fromAmountInputs(inputs),
                                            )}
                                        </tr>
                                    );
                                },
                            )}
                            {valueRow(
                                'b',
                                labelCell(
                                    '(B) JUMLAH PEMAKAIAN ALAT BANTU',
                                    'left',
                                ),
                                pick('alatBantu'),
                                'bg-muted/30 font-semibold',
                            )}
                            {valueRow(
                                'ab',
                                labelCell('TOTAL (A+B)'),
                                pick('pemakaian'),
                                'bg-muted/30 font-semibold',
                            )}
                            {valueRow(
                                'kartu',
                                labelCell('SISA PERSEDIAAN AKHIR / KARTU'),
                                pick('kartu'),
                                'font-semibold',
                            )}
                            <tr className="font-semibold">
                                {labelCell('SISA PERSEDIAAN AKHIR / FISIK')}
                                {lubricants.map((l) => (
                                    <td
                                        key={l.key}
                                        className="border-b border-l border-border p-0"
                                    >
                                        <AmountCell
                                            value={form.fisik[l.key]}
                                            placeholder={formatAmount(
                                                auto.ba_fisik[l.key] ??
                                                    totals[l.key].kartu,
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
                                {totalCells(pick('fisik'))}
                            </tr>
                            {valueRow(
                                'selisih',
                                labelCell('S E L I S I H'),
                                pick('selisih'),
                                'bg-muted/30 font-semibold',
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
            <p className="text-[13px] text-muted-foreground">
                Kolom TOTAL (KG) GREASE menjumlahkan jenis pelumas bersatuan Kg
                di Data Master; lainnya masuk TOTAL (LTR) PELUMAS. Sisa fisik
                yang dikosongkan mengikuti sisa kartu. Selisih = fisik − kartu.
            </p>
        </RincianShell>
    );
}
