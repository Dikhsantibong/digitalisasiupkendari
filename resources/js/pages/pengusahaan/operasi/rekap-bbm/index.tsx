import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Fragment, useMemo, useState } from 'react';
import {
    NO_BBM,
    bbmTotals,
    toBbmForm,
    toBbmPayload,
} from '@/components/operasi/rincian-bbm';
import type { RincianBbmProps } from '@/components/operasi/rincian-bbm';
import {
    AmountCell,
    RincianShell,
    formatAmount,
    toNumber,
} from '@/components/operasi/rincian-pelumas';
import { cn } from '@/lib/utils';
import rekap from '@/routes/operasi/pengusahaan/rekap-bbm';

/**
 * Rekap Bahan Bakar — Pemakaian Bahan Bakar per jenis BBM: per mesin the
 * pemakaian mesin (Pemakaian Bahan Bakar) and the typed-in on operasi / test,
 * cuci HAR and bocor / dll, with the stock rows of the month. Shares its
 * typed-in record with Perincian Bahan Bakar.
 */
export default function RekapBbmIndex(props: RincianBbmProps) {
    const { fuels, machines, auto, lain_labels, filters, catatan, can_write } =
        props;
    const initial = useMemo(() => toBbmForm(props), [props]);
    const [form, setForm] = useState(initial);
    const [note, setNote] = useState(catatan);
    const [saving, setSaving] = useState(false);
    const dirty =
        JSON.stringify(form) !== JSON.stringify(initial) || note !== catatan;
    const totals = bbmTotals(props, form);
    const codes = fuels.map((fuel) => fuel.key);
    const lainKeys = Object.keys(lain_labels);
    const groups: [string, string][] = [
        ['mesin', 'Pemakaian Mesin'],
        ...Object.entries(lain_labels),
    ];

    const lainOf = (code: string, machineId: number, field: string) =>
        form[code]?.lain[machineId]?.[field] ?? '';
    const setLain = (
        code: string,
        machineId: number,
        field: string,
        value: string,
    ) =>
        setForm((current) => ({
            ...current,
            [code]: {
                ...current[code],
                lain: {
                    ...current[code].lain,
                    [machineId]: {
                        ...(current[code].lain[machineId] ?? {}),
                        [field]: value,
                    },
                },
            },
        }));

    const save = () =>
        router.post(
            rekap.store().url,
            { ...filters, fuels: toBbmPayload(form), catatan: note },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );

    /** A stock row: the value sits in the TOTAL group only. */
    const stockRow = (
        label: string,
        values: Record<string, number>,
        className?: string,
    ) => (
        <tr className={className}>
            <td
                colSpan={5}
                className="border-b border-border px-3 py-1.5 text-center font-semibold"
            >
                {label}
            </td>
            {groups.map(([group]) =>
                codes.map((code) => (
                    <td
                        key={`${group}-${code}`}
                        className="border-b border-l border-border"
                    />
                )),
            )}
            {codes.map((code) => (
                <TotalCell key={code} value={values[code] ?? 0} />
            ))}
        </tr>
    );
    const pick = (
        name:
            | 'awal'
            | 'penerimaan'
            | 'pengembalian'
            | 'totalPersediaan'
            | 'pengiriman'
            | 'koreksi'
            | 'sisa'
            | 'fisik'
            | 'selisih',
    ) => Object.fromEntries(codes.map((code) => [code, totals[code][name]]));

    return (
        <RincianShell
            props={props}
            description="Pemakaian bahan bakar per mesin per jenis BBM: pemakaian mesin, on operasi / test, cuci HAR dan bocor, dengan persediaan dan sisa bulan ini"
            routes={{ index: rekap.index, pdf: rekap.pdf }}
            empty={fuels.length === 0 ? NO_BBM : null}
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
                                {groups.map(([group, label]) => (
                                    <th
                                        key={group}
                                        colSpan={codes.length}
                                        className="border-r border-b border-border px-2 py-1.5 font-semibold uppercase"
                                    >
                                        {label}
                                    </th>
                                ))}
                                <th
                                    colSpan={codes.length}
                                    className="border-b border-border px-2 py-1.5 font-semibold"
                                >
                                    TOTAL
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
                                {[...groups, ['total', 'Total']].map(
                                    ([group]) => (
                                        <Fragment key={group}>
                                            {codes.map((code) => (
                                                <th
                                                    key={code}
                                                    className="min-w-24 border-r border-b border-border px-2 py-1.5 font-normal whitespace-nowrap text-muted-foreground"
                                                >
                                                    {code} (LTR)
                                                </th>
                                            ))}
                                        </Fragment>
                                    ),
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {stockRow('PERSEDIAAN AWAL', pick('awal'))}
                            {stockRow('PENERIMAAN', pick('penerimaan'))}
                            {stockRow(
                                'TUG 10 PENGEMBALIAN',
                                pick('pengembalian'),
                            )}
                            {stockRow(
                                'TOTAL',
                                pick('totalPersediaan'),
                                'bg-muted/30',
                            )}
                            {machines.map((machine, index) => (
                                <tr key={machine.id}>
                                    <Lead>{index + 1}</Lead>
                                    <Lead strong>{machine.name}</Lead>
                                    <Lead>{machine.merk ?? '-'}</Lead>
                                    <Lead>{machine.type ?? '-'}</Lead>
                                    <Lead>{machine.serial_number ?? '-'}</Lead>
                                    {codes.map((code) => (
                                        <td
                                            key={code}
                                            className="border-b border-l border-border px-2 py-1.5 text-right text-muted-foreground tabular-nums"
                                        >
                                            {formatAmount(
                                                auto.pemakaian[machine.id]?.[
                                                    code
                                                ] ?? 0,
                                            )}
                                        </td>
                                    ))}
                                    {lainKeys.map((field) =>
                                        codes.map((code) => (
                                            <td
                                                key={`${field}-${code}`}
                                                className="border-b border-l border-border p-0"
                                            >
                                                <AmountCell
                                                    value={lainOf(
                                                        code,
                                                        machine.id,
                                                        field,
                                                    )}
                                                    disabled={!can_write}
                                                    label={`${lain_labels[field]} ${code} ${machine.name}`}
                                                    onChange={(value) =>
                                                        setLain(
                                                            code,
                                                            machine.id,
                                                            field,
                                                            value,
                                                        )
                                                    }
                                                />
                                            </td>
                                        )),
                                    )}
                                    {codes.map((code) => (
                                        <TotalCell
                                            key={code}
                                            value={
                                                (auto.pemakaian[machine.id]?.[
                                                    code
                                                ] ?? 0) +
                                                lainKeys.reduce(
                                                    (total, field) =>
                                                        total +
                                                        toNumber(
                                                            lainOf(
                                                                code,
                                                                machine.id,
                                                                field,
                                                            ),
                                                        ),
                                                    0,
                                                )
                                            }
                                        />
                                    ))}
                                </tr>
                            ))}
                            <tr className="bg-muted/30 font-semibold">
                                <td
                                    colSpan={5}
                                    className="border-b border-border px-3 py-1.5 text-center"
                                >
                                    TOTAL PEMAKAIAN
                                </td>
                                {codes.map((code) => (
                                    <TotalCell
                                        key={code}
                                        value={totals[code].mesin}
                                    />
                                ))}
                                {lainKeys.map((field) =>
                                    codes.map((code) => (
                                        <TotalCell
                                            key={`${field}-${code}`}
                                            value={totals[code].lain[field]}
                                        />
                                    )),
                                )}
                                {codes.map((code) => (
                                    <TotalCell
                                        key={code}
                                        value={totals[code].jumlahPemakaian}
                                    />
                                ))}
                            </tr>
                            {stockRow('TUG 8 KE UNIT LAIN', pick('pengiriman'))}
                            {Object.values(pick('koreksi')).some(
                                (value) => value > 0,
                            ) &&
                                stockRow(
                                    'KOREKSI / PEMINJAMAN',
                                    pick('koreksi'),
                                )}
                            {stockRow(
                                'SISA PERSEDIAAN AKHIR (PERHITUNGAN)',
                                pick('sisa'),
                                'bg-muted/30',
                            )}
                            {stockRow(
                                'SISA PERSEDIAAN AKHIR / FISIK',
                                pick('fisik'),
                                'bg-muted/30',
                            )}
                            {stockRow('S E L I S I H', pick('selisih'))}
                        </tbody>
                    </table>
                </div>
                <p className="border-t border-border px-4 py-2 text-[12px] text-muted-foreground">
                    Pemakaian mesin dari Pemakaian Bahan Bakar; on operasi /
                    test, cuci HAR dan bocor diisi di sini. Pengembalian TUG 10,
                    koreksi dan sisa fisik diisi di Perincian Bahan Bakar.
                </p>
            </section>
        </RincianShell>
    );
}

function Lead({
    children,
    strong = false,
}: {
    children: ReactNode;
    strong?: boolean;
}) {
    return (
        <td
            className={cn(
                'border-b border-border px-2 py-1.5 whitespace-nowrap',
                strong && 'font-medium',
            )}
        >
            {children}
        </td>
    );
}

function TotalCell({ value }: { value: number }) {
    return (
        <td className="border-b border-l border-border bg-secondary/60 px-2 py-1.5 text-right font-semibold tabular-nums">
            {formatAmount(value)}
        </td>
    );
}
