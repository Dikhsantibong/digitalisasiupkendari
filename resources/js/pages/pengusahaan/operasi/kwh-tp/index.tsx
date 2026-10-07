import { Head, router } from '@inertiajs/react';
import { Download, Eye } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { SummaryCard } from '@/components/summary-card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import kwhTp from '@/routes/operasi/pengusahaan/kwh-tp';
import standKwh from '@/routes/operasi/pengusahaan/stand-kwh';

type Line = {
    id: number;
    name: string;
    merk: string | null;
    type: string | null;
    serial_number: string | null;
    stand_awal: number | null;
    stand_akhir: number | null;
    faktor_kali: number;
    hasil: number | null;
};

type Filters = { unit_id: number; month: number; year: number };

type Props = {
    unit: { id: number; name: string };
    units: { id: number; name: string }[];
    filters: Filters;
    period_label: string;
    produksi: Line[];
    ps: Line[];
    total_produksi: number;
    total_ps: number;
};

/** Numbers as typed: no trailing zeros after the comma. */
const STAND = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 4,
});
const KWH = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});
const YEARS = Array.from(
    { length: 7 },
    (_, i) => new Date().getFullYear() - 4 + i,
);

/**
 * Stand kWh Meter untuk Perhitungan Transfer Pricing — read from Stand kWh
 * Harian: hal 1 the generating meters (PROD), hal 2 the own-use meters (PS).
 */
export default function KwhTransferPricingIndex({
    unit,
    units,
    filters,
    period_label,
    produksi,
    ps,
    total_produksi,
    total_ps,
}: Props) {
    const [tab, setTab] = useState<'produksi' | 'ps'>('produksi');
    const visit = (patch: Partial<Filters>) =>
        router.get(
            kwhTp.index().url,
            { ...filters, ...patch },
            { preserveScroll: true },
        );
    const query = {
        unit_id: filters.unit_id,
        month: filters.month,
        year: filters.year,
    };
    const lines = tab === 'produksi' ? produksi : ps;

    return (
        <>
            <Head title="Stand kWh Meter Transfer Pricing" />
            <div className="flex min-w-0 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Stand kWh Meter untuk Transfer Pricing"
                    description={`Stand awal & akhir kWh meter mesin pembangkit dan pemakaian sendiri — ${unit.name}, ${period_label}. Dihitung dari Stand kWh Harian.`}
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <a
                                    href={kwhTp.pdf({ query }).url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Eye className="size-4" /> Pratinjau PDF
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href={
                                        kwhTp.pdf({
                                            query: { ...query, download: 1 },
                                        }).url
                                    }
                                >
                                    <Download className="size-4" /> Unduh
                                </a>
                            </Button>
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
                    <Button
                        variant="ghost"
                        size="sm"
                        className="ml-auto self-center"
                        asChild
                    >
                        <a href={standKwh.index({ query }).url}>
                            Ubah di Stand kWh Harian
                        </a>
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <SummaryCard
                        label="Jumlah kWh produksi"
                        value={KWH.format(total_produksi)}
                        unit="kWh"
                        hint="Hal 1/2 — meter mesin pembangkit"
                    />
                    <SummaryCard
                        label="Jumlah kWh pemakaian sendiri"
                        value={KWH.format(total_ps)}
                        unit="kWh"
                        hint="Hal 2/2 — meter PS"
                    />
                </div>

                <nav className="flex gap-2" aria-label="Halaman">
                    {(
                        [
                            ['produksi', 'Hal 1/2 · Mesin Pembangkit'],
                            ['ps', 'Hal 2/2 · Pemakaian Sendiri (PS)'],
                        ] as const
                    ).map(([key, label]) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => setTab(key)}
                            aria-current={tab === key ? 'true' : undefined}
                            className={cn(
                                'h-9 rounded-md border px-4 text-[13px] font-medium whitespace-nowrap',
                                tab === key
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-border bg-card text-foreground hover:bg-muted',
                            )}
                        >
                            {label}
                        </button>
                    ))}
                </nav>

                <section className="overflow-hidden rounded-md border border-border bg-card">
                    {lines.length === 0 ? (
                        <EmptyState
                            title="Belum ada mesin aktif"
                            description="Tambahkan mesin unit ini di Master Mesin."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table
                                className="w-full border-collapse text-[13px]"
                                data-keep-table
                            >
                                <thead>
                                    <tr className="border-b border-border bg-secondary text-left text-[12px] whitespace-nowrap text-muted-foreground">
                                        <th className="px-3 py-2.5 text-right font-semibold">
                                            No
                                        </th>
                                        <th className="px-3 py-2.5 font-semibold">
                                            Merk
                                        </th>
                                        <th className="px-3 py-2.5 font-semibold">
                                            Mesin
                                        </th>
                                        <th className="px-3 py-2.5 font-semibold">
                                            Type
                                        </th>
                                        <th className="px-3 py-2.5 font-semibold">
                                            No. Seri
                                        </th>
                                        <th className="px-3 py-2.5 text-right font-semibold">
                                            Stand awal
                                        </th>
                                        <th className="px-3 py-2.5 text-right font-semibold">
                                            Stand akhir
                                        </th>
                                        <th className="px-3 py-2.5 text-right font-semibold">
                                            Faktor kali
                                        </th>
                                        <th className="px-3 py-2.5 text-right font-semibold">
                                            Hasil akhir (kWh)
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lines.map((line, index) => (
                                        <tr
                                            key={line.id}
                                            className="border-b border-border hover:bg-muted/40"
                                        >
                                            <td className="px-3 py-2 text-right text-muted-foreground tabular-nums">
                                                {index + 1}
                                            </td>
                                            <td className="px-3 py-2">
                                                {line.merk ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 font-medium text-foreground">
                                                {line.name}
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {line.type ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 text-muted-foreground">
                                                {line.serial_number ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {line.stand_awal === null
                                                    ? '—'
                                                    : STAND.format(
                                                          line.stand_awal,
                                                      )}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {line.stand_akhir === null
                                                    ? '—'
                                                    : STAND.format(
                                                          line.stand_akhir,
                                                      )}
                                            </td>
                                            <td className="px-3 py-2 text-right tabular-nums">
                                                {line.faktor_kali}
                                            </td>
                                            <td className="px-3 py-2 text-right font-semibold tabular-nums">
                                                {!line.hasil
                                                    ? '—'
                                                    : KWH.format(line.hasil)}
                                            </td>
                                        </tr>
                                    ))}
                                    <tr className="bg-secondary font-semibold">
                                        <td
                                            colSpan={8}
                                            className="px-3 py-2 text-right"
                                        >
                                            Jumlah
                                        </td>
                                        <td className="px-3 py-2 text-right text-primary tabular-nums">
                                            {KWH.format(
                                                tab === 'produksi'
                                                    ? total_produksi
                                                    : total_ps,
                                            )}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
