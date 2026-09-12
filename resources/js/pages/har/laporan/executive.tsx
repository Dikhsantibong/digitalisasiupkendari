import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import laporan from '@/routes/har/laporan';

const PRINT_STYLES = `
@media print {
    body * { visibility: hidden; }
    .print-area, .print-area * { visibility: visible; }
    .print-area { position: absolute; inset: 0; margin: 0; padding: 16px; box-shadow: none !important; min-height: 0 !important; }
    .print-canvas { background: #fff !important; padding: 0 !important; }
    .no-print { display: none !important; }
}
`;

type Data = {
    unit: { name: string; service_unit: string | null };
    period: { label: string };
    sr_total: number;
    sr_open: number;
    wo_total: number;
    wo_complete: number;
    wo_percent: number;
    wo_by_type: { type: string; count: number }[];
    waiting_count: number;
    cost_total: number;
    cost_ytd: number;
    cost_source: string;
};

const rupiah = (v: number) => `Rp ${formatNumber(String(v))}`;

function Metric({ label, value, sub }: { label: string; value: string; sub?: string }) {
    return (
        <div className="rounded-md border border-slate-300 p-3">
            <div className="text-[11px] text-slate-500">{label}</div>
            <div className="text-xl font-bold">{value}</div>
            {sub && <div className="text-[11px] text-slate-500">{sub}</div>}
        </div>
    );
}

export default function ExecutiveSummary({ data }: { data: Data }) {
    return (
        <>
            <Head title={`Executive Summary — ${data.unit.name}`} />
            <style>{PRINT_STYLES}</style>

            <div className="flex min-h-screen flex-col bg-slate-200">
                <div className="sticky top-0 z-10 flex items-center justify-between gap-2 border-b border-border bg-card p-3 no-print">
                    <Button variant="secondary" onClick={() => router.get(laporan.index().url)}>
                        <ArrowLeft className="size-4" />
                        Kembali
                    </Button>
                    <Button onClick={() => window.print()}>
                        <Printer className="size-4" />
                        Cetak / Unduh PDF
                    </Button>
                </div>

                <div className="print-canvas flex-1 overflow-auto p-4 md:p-8">
                    <div className="print-area mx-auto min-h-[1123px] w-full max-w-[794px] bg-white p-10 text-slate-900 shadow-xl">
                        <header className="mb-5 text-center">
                    <p className="text-sm font-semibold uppercase">{data.unit.service_unit ?? 'UNIT LAYANAN'}</p>
                    <h1 className="text-lg font-bold uppercase">Executive Summary Pemeliharaan</h1>
                    <p className="text-sm">{data.unit.name} · Periode {data.period.label}</p>
                </header>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Metric label="Total SR" value={String(data.sr_total)} sub={`${data.sr_open} open`} />
                    <Metric label="Total WO" value={String(data.wo_total)} sub={`${data.wo_complete} complete`} />
                    <Metric label="% Complete WO" value={`${data.wo_percent}%`} />
                    <Metric label="WO Tertunda" value={String(data.waiting_count)} />
                    <Metric label={`Biaya Bulan Ini (${data.cost_source})`} value={rupiah(data.cost_total)} />
                    <Metric label="Akumulasi Biaya (YTD)" value={rupiah(data.cost_ytd)} />
                </div>

                <h2 className="mb-2 mt-6 font-semibold">Work Order per Jenis</h2>
                <table className="w-full border-collapse text-[12px]">
                    <thead>
                        <tr className="bg-slate-100">
                            <th className="border px-2 py-1 text-left">Jenis</th>
                            <th className="border px-2 py-1 text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.wo_by_type.length === 0 ? (
                            <tr>
                                <td className="border px-2 py-1 text-slate-500" colSpan={2}>Tidak ada Work Order.</td>
                            </tr>
                        ) : (
                            data.wo_by_type.map((g) => (
                                <tr key={g.type}>
                                    <td className="border px-2 py-1">{g.type}</td>
                                    <td className="border px-2 py-1 text-right tabular-nums">{g.count}</td>
                                </tr>
                            ))
                        )}
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </>
    );
}
