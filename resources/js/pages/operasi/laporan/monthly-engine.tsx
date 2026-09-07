import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import laporan from '@/routes/operasi/laporan';

const PRINT_STYLES = `
@media print {
    body * { visibility: hidden; }
    .print-area, .print-area * { visibility: visible; }
    .print-area { position: absolute; inset: 0; margin: 0; padding: 16px; }
    .no-print { display: none !important; }
}
`;

type SummaryRow = {
    kwh_produksi: number;
    kwh_pakai_sendiri: number;
    kwh_netto: number;
    pemakaian_hsd: number;
    pemakaian_mfo: number;
    pemakaian_pelumas_liter: number;
};

type Row = {
    day: number;
    kwh_produksi: number | null;
    kwh_pakai_sendiri: number | null;
    kwh_netto: number | null;
    pemakaian_hsd: number | null;
    pemakaian_mfo: number | null;
    pemakaian_pelumas_liter: string | null;
    beban_puncak_pagi_kw: string | null;
    beban_puncak_malam_kw: string | null;
};

type Data = {
    unit: { name: string; code: string; location: string | null; service_unit: string | null };
    engine: { name: string; type: string | null; fuel_type: string | null } | null;
    period: { month: number; year: number; label: string; days: number };
    rows: Row[];
    summary: Record<string, SummaryRow>;
    hours: { operasi: number; har: number; gangguan: number; standby: number; total: number } | null;
    total_bbm: number;
    sfc: { bruto: number | null; netto: number | null };
};

type Props = {
    report: { code: string; title: string };
    data: Data;
    usesMfo?: boolean;
};

const fmt = (value: number | string | null) =>
    value === null || value === '' ? '—' : formatNumber(String(value));

export default function MonthlyEngineReport({ report, data }: Props) {
    const usesMfo = data.rows.some((row) => row.pemakaian_mfo !== null);

    const summaryRow = (label: string, key: string) => {
        const s = data.summary[key];

        if (!s) {
            return null;
        }

        return (
            <tr className="bg-slate-50 font-semibold">
                <td className="border px-2 py-1">{label}</td>
                <td className="border px-2 py-1 text-right">{fmt(s.kwh_produksi)}</td>
                <td className="border px-2 py-1 text-right">{fmt(s.kwh_pakai_sendiri)}</td>
                <td className="border px-2 py-1 text-right">{fmt(s.kwh_netto)}</td>
                <td className="border px-2 py-1 text-right">{fmt(s.pemakaian_hsd)}</td>
                {usesMfo && (
                    <td className="border px-2 py-1 text-right">{fmt(s.pemakaian_mfo)}</td>
                )}
                <td className="border px-2 py-1 text-right">{fmt(s.pemakaian_pelumas_liter)}</td>
                <td className="border px-2 py-1" colSpan={2} />
            </tr>
        );
    };

    return (
        <>
            <Head title={report.title} />
            <style>{PRINT_STYLES}</style>

            <div className="flex items-center justify-between gap-2 border-b border-border bg-card p-3 no-print">
                <Button variant="secondary" onClick={() => router.get(laporan.index().url)}>
                    <ArrowLeft className="size-4" />
                    Kembali
                </Button>
                <Button onClick={() => window.print()}>
                    <Printer className="size-4" />
                    Cetak / Unduh PDF
                </Button>
            </div>

            <div className="print-area mx-auto max-w-[1100px] bg-white p-6 text-slate-900">
                <header className="mb-4 text-center">
                    <p className="text-sm font-semibold uppercase">
                        {data.unit.service_unit ?? 'UNIT LAYANAN'}
                    </p>
                    <h1 className="text-lg font-bold uppercase">{report.title}</h1>
                    <p className="text-sm">
                        {data.unit.name}
                        {data.engine ? ` — ${data.engine.name}` : ''} · Periode{' '}
                        {data.period.label}
                    </p>
                </header>

                <div className="overflow-x-auto">
                    <table className="w-full border-collapse text-[11px]">
                        <thead>
                            <tr className="bg-slate-100">
                                <th className="border px-2 py-1">Tgl</th>
                                <th className="border px-2 py-1 text-right">kWh Produksi</th>
                                <th className="border px-2 py-1 text-right">kWh PS</th>
                                <th className="border px-2 py-1 text-right">kWh Netto</th>
                                <th className="border px-2 py-1 text-right">Pakai HSD (L)</th>
                                {usesMfo && (
                                    <th className="border px-2 py-1 text-right">Pakai MFO (L)</th>
                                )}
                                <th className="border px-2 py-1 text-right">Pelumas (L)</th>
                                <th className="border px-2 py-1 text-right">BP Pagi</th>
                                <th className="border px-2 py-1 text-right">BP Malam</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.rows.map((row) => (
                                <tr key={row.day}>
                                    <td className="border px-2 py-1 text-center">{row.day}</td>
                                    <td className="border px-2 py-1 text-right">{fmt(row.kwh_produksi)}</td>
                                    <td className="border px-2 py-1 text-right">{fmt(row.kwh_pakai_sendiri)}</td>
                                    <td className="border px-2 py-1 text-right">{fmt(row.kwh_netto)}</td>
                                    <td className="border px-2 py-1 text-right">{fmt(row.pemakaian_hsd)}</td>
                                    {usesMfo && (
                                        <td className="border px-2 py-1 text-right">{fmt(row.pemakaian_mfo)}</td>
                                    )}
                                    <td className="border px-2 py-1 text-right">{fmt(row.pemakaian_pelumas_liter)}</td>
                                    <td className="border px-2 py-1 text-right">{fmt(row.beban_puncak_pagi_kw)}</td>
                                    <td className="border px-2 py-1 text-right">{fmt(row.beban_puncak_malam_kw)}</td>
                                </tr>
                            ))}
                            {summaryRow('PERIODE I', 'periode_1')}
                            {summaryRow('PERIODE II', 'periode_2')}
                            {summaryRow('PERIODE III', 'periode_3')}
                            {summaryRow('TOTAL', 'total')}
                        </tbody>
                    </table>
                </div>

                <div className="mt-5 grid grid-cols-2 gap-6">
                    {data.hours && (
                        <div>
                            <h3 className="mb-1 text-sm font-semibold">Rekap Jam</h3>
                            <table className="w-full border-collapse text-[11px]">
                                <tbody>
                                    <RekapRow label="Operasi" value={data.hours.operasi} />
                                    <RekapRow label="Pemeliharaan (HAR)" value={data.hours.har} />
                                    <RekapRow label="Gangguan" value={data.hours.gangguan} />
                                    <RekapRow label="Standby" value={data.hours.standby} />
                                    <RekapRow label="Total Jam" value={data.hours.total} bold />
                                </tbody>
                            </table>
                        </div>
                    )}
                    <div>
                        <h3 className="mb-1 text-sm font-semibold">SFC & Bahan Bakar</h3>
                        <table className="w-full border-collapse text-[11px]">
                            <tbody>
                                <RekapRow label="Total BBM (L)" value={data.total_bbm} />
                                <RekapRow label="SFC Bruto (L/kWh)" text={fmt(data.sfc.bruto)} />
                                <RekapRow label="SFC Netto (L/kWh)" text={fmt(data.sfc.netto)} />
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

function RekapRow({
    label,
    value,
    text,
    bold,
}: {
    label: string;
    value?: number;
    text?: string;
    bold?: boolean;
}) {
    return (
        <tr className={bold ? 'font-semibold' : ''}>
            <td className="border px-2 py-1">{label}</td>
            <td className="border px-2 py-1 text-right tabular-nums">
                {text ?? (value === undefined ? '—' : formatNumber(String(value)))}
            </td>
        </tr>
    );
}
