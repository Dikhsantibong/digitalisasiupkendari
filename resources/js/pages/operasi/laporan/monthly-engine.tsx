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
    /* The cover keeps its A4 proportion; the report starts on the next sheet. */
    .report-cover { page-break-after: always; }
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
                <div className="report-cover relative mx-auto mb-8 aspect-[794/1123] w-full max-w-[794px] overflow-hidden bg-white shadow-xs">
                    <svg className="pointer-events-none absolute inset-0 size-full" viewBox="0 0 794 1123" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="0,0 210,0 0,270" fill="#0b2545" />
                        <polygon points="210,0 248,0 0,320 0,270" fill="#00a3e0" />
                        <polygon points="248,0 262,0 0,338 0,320" fill="#f59e0b" />
                        <polygon points="0,110 135,35 110,170 0,230" fill="#0080b0" opacity="0.25" />
                        <polygon points="460,1123 794,520 794,1123" fill="#005b82" />
                        <path d="M 0 715 Q 220 815 540 735 Q 568 725 565 750 C 560 780 480 960 470 1123 L 0 1123 Z" fill="#0b2545" />
                        <path d="M 0 707 Q 220 807 540 727 Q 575 717 572 750 C 567 780 487 960 477 1123 L 470 1123 C 480 960 560 780 565 750 Q 568 725 540 735 Q 220 815 0 715 Z" fill="#f59e0b" />
                    </svg>

                    <div className="relative z-10 flex flex-col items-center px-10 pt-28">
                        <div className="flex items-center justify-center gap-6">
                            <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="h-14 object-contain" />
                            <div className="h-12 w-[1.5px] bg-[#0b2545]" />
                            <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" className="h-12 object-contain" />
                        </div>

                        <div className="mt-24 text-center">
                            <h1 className="text-3xl font-extrabold uppercase tracking-wider text-[#0b2545] sm:text-4xl">
                                Laporan Operasi<br />Pembangkit
                            </h1>
                            <div className="mx-auto mt-4 h-[2.5px] w-56 bg-[#0284c7]" />
                        </div>

                        <div className="mx-auto mt-20 w-full max-w-[540px] rounded-2xl border-2 border-[#0284c7] bg-white p-6 shadow-xs">
                            <table className="w-full text-left text-sm font-bold uppercase text-[#0b2545]">
                                <tbody>
                                    <tr>
                                        <td className="w-48 py-1.5">Nama Pembangkit</td>
                                        <td className="w-4 py-1.5 text-center">:</td>
                                        <td className="py-1.5">{data.unit.name}</td>
                                    </tr>
                                    <tr>
                                        <td className="py-1.5">Periode Pelaporan</td>
                                        <td className="py-1.5 text-center">:</td>
                                        <td className="py-1.5">BULAN {data.period.label}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="absolute bottom-10 left-10 z-10 flex items-center gap-3 rounded-md border border-slate-200 bg-white px-3.5 py-2 shadow-md">
                        <div className="flex items-center gap-1.5">
                            <svg className="size-4.5 text-[#0a2540]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                <polyline points="9 12 11 14 15 10"/>
                            </svg>
                            <div className="flex flex-col leading-none">
                                <span className="text-[9px] font-extrabold text-[#0a2540]">ANDAL</span>
                                <span className="text-[7px] font-semibold text-[#0a2540]/80">RELIABLE</span>
                            </div>
                        </div>
                        <div className="h-6 w-px bg-slate-200" />
                        <div className="flex items-center gap-1.5">
                            <svg className="size-4.5 text-[#0a2540]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                            <div className="flex flex-col leading-none">
                                <span className="text-[9px] font-extrabold text-[#0a2540]">EFISIEN</span>
                                <span className="text-[7px] font-semibold text-[#0a2540]/80">EFFICIENT</span>
                            </div>
                        </div>
                        <div className="h-6 w-px bg-slate-200" />
                        <div className="flex items-center gap-1.5">
                            <svg className="size-4.5 text-[#0a2540]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/>
                                <path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>
                            </svg>
                            <div className="flex flex-col leading-none">
                                <span className="text-[9px] font-extrabold text-[#0a2540]">BERKELANJUTAN</span>
                                <span className="text-[7px] font-semibold text-[#0a2540]/80">SUSTAINABLE</span>
                            </div>
                        </div>
                        <div className="h-6 w-px bg-slate-200" />
                        <div className="flex items-center gap-1.5">
                            <svg className="size-4.5 text-[#0a2540]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <circle cx="9" cy="7" r="3"/>
                                <path d="M3 18v-1a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v1"/>
                                <circle cx="17" cy="9" r="2.5"/>
                                <path d="M17 14h2a3 3 0 0 1 3 3v1"/>
                            </svg>
                            <div className="flex flex-col leading-none">
                                <span className="text-[9px] font-extrabold text-[#0a2540]">KOLABORATIF</span>
                                <span className="text-[7px] font-semibold text-[#0a2540]/80">COLLABORATIVE</span>
                            </div>
                        </div>
                    </div>
                </div>

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
