import type { RincianShellProps } from '@/components/operasi/rincian-pelumas';
import { toNumber } from '@/components/operasi/rincian-pelumas';

export type BbmFuel = { key: string; name: string; code: string | null };

export type BbmMachine = {
    id: number;
    name: string;
    merk: string | null;
    type: string | null;
    serial_number: string | null;
};

/** machine id → test / cuci / bocor → liters. */
export type LainInputs = Record<string, Record<string, string>>;

/** The typed-in part of one jenis BBM, as the inputs hold it. */
export type BbmFuelForm = {
    pengembalian: { tanggal: string; amount: string };
    koreksi: { keterangan: string; amount: string };
    fisik: string;
    lain: LainInputs;
};

type StoredFuel = {
    pengembalian: { tanggal: string | null; amount: number };
    koreksi: { keterangan: string | null; amount: number };
    fisik: number | null;
    lain: Record<string, Record<string, number>> | [];
};

export type RincianBbmProps = RincianShellProps & {
    fuels: BbmFuel[];
    machines: BbmMachine[];
    tanks: Record<string, { name: string; capacity_liter: number | null }[]>;
    auto: {
        awal: Record<string, number>;
        penerimaan: Record<
            string,
            { label: string; from: number; to: number; amount: number }[]
        >;
        pengiriman: Record<string, number>;
        /** machine id → jenis BBM code → liters (Pemakaian Bahan Bakar). */
        pemakaian: Record<string, Record<string, number>>;
    };
    manual: Record<string, StoredFuel>;
    /** test / cuci / bocor → label. */
    lain_labels: Record<string, string>;
    manager: string | null;
    catatan: string;
};

const amountText = (value: number | null | undefined) =>
    value ? String(value) : '';

/** The stored parts → form inputs, per jenis BBM. */
export function toBbmForm(props: RincianBbmProps): Record<string, BbmFuelForm> {
    return Object.fromEntries(
        props.fuels.map((fuel) => {
            const stored = props.manual[fuel.key];
            const lain = Array.isArray(stored?.lain)
                ? {}
                : (stored?.lain ?? {});

            return [
                fuel.key,
                {
                    pengembalian: {
                        tanggal: stored?.pengembalian.tanggal ?? '',
                        amount: amountText(stored?.pengembalian.amount),
                    },
                    koreksi: {
                        keterangan: stored?.koreksi.keterangan ?? '',
                        amount: amountText(stored?.koreksi.amount),
                    },
                    fisik:
                        stored?.fisik === null || stored?.fisik === undefined
                            ? ''
                            : String(stored.fisik),
                    lain: Object.fromEntries(
                        Object.entries(lain).map(([machine, fields]) => [
                            machine,
                            Object.fromEntries(
                                Object.entries(fields).map(([k, v]) => [
                                    k,
                                    String(v),
                                ]),
                            ),
                        ]),
                    ),
                },
            ];
        }),
    );
}

/** Form inputs → the request payload. */
export function toBbmPayload(form: Record<string, BbmFuelForm>) {
    return Object.fromEntries(
        Object.entries(form).map(([code, fuel]) => [
            code,
            {
                pengembalian: {
                    tanggal: fuel.pengembalian.tanggal || null,
                    amount: toNumber(fuel.pengembalian.amount),
                },
                koreksi: {
                    keterangan: fuel.koreksi.keterangan || null,
                    amount: toNumber(fuel.koreksi.amount),
                },
                fisik: fuel.fisik === '' ? null : toNumber(fuel.fisik),
                lain: Object.fromEntries(
                    Object.entries(fuel.lain).map(([machine, fields]) => [
                        machine,
                        Object.fromEntries(
                            Object.entries(fields).map(([k, v]) => [
                                k,
                                toNumber(v),
                            ]),
                        ),
                    ]),
                ),
            },
        ]),
    );
}

/** The figures of both sheets per jenis BBM (mirrors RincianBbmSheet::totals). */
export function bbmTotals(
    props: RincianBbmProps,
    form: Record<string, BbmFuelForm>,
) {
    const lainKeys = Object.keys(props.lain_labels);

    return Object.fromEntries(
        props.fuels.map(({ key }) => {
            const fuel = form[key];
            const penerimaan = props.auto.penerimaan[key].reduce(
                (total, line) => total + line.amount,
                0,
            );
            const pengembalian = toNumber(fuel.pengembalian.amount);
            const totalPersediaan =
                (props.auto.awal[key] ?? 0) + pengembalian + penerimaan;
            const mesin = Object.values(props.auto.pemakaian).reduce(
                (total, machine) => total + (machine[key] ?? 0),
                0,
            );
            const lain = Object.fromEntries(
                lainKeys.map((field) => [
                    field,
                    Object.values(fuel.lain).reduce(
                        (total, machine) => total + toNumber(machine[field]),
                        0,
                    ),
                ]),
            );
            const jumlahPemakaian =
                mesin + Object.values(lain).reduce((a, b) => a + b, 0);
            const pengiriman = props.auto.pengiriman[key] ?? 0;
            const koreksi = toNumber(fuel.koreksi.amount);
            const totalPengeluaran = jumlahPemakaian + pengiriman + koreksi;
            const sisa = totalPersediaan - totalPengeluaran;
            const fisik = fuel.fisik === '' ? sisa : toNumber(fuel.fisik);

            return [
                key,
                {
                    awal: props.auto.awal[key] ?? 0,
                    pengembalian,
                    penerimaan,
                    totalPersediaan,
                    mesin,
                    lain,
                    jumlahPemakaian,
                    pengiriman,
                    koreksi,
                    totalPengeluaran,
                    sisa,
                    fisik,
                    selisih: fisik - sisa,
                },
            ];
        }),
    );
}

export const NO_BBM = {
    title: 'Belum ada jenis BBM untuk unit ini',
    description:
        'Jenis BBM diambil dari Tangki BBM aktif unit (Data Master Operasi → Tangki BBM).',
};
