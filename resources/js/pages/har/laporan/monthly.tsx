import { Fragment } from 'react';
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
    /* Each major part starts on a fresh printed sheet. */
    .report-cover, .page-break { page-break-after: always; min-height: 96vh; }
    .break-before { page-break-before: always; }
    table { page-break-inside: auto; }
    tr { page-break-inside: avoid; }
}
`;

type WoRow = {
    wonum: string;
    description: string | null;
    report_date: string | null;
    sched_start: string | null;
    sched_finish: string | null;
    status: string | null;
    work_group: string | null;
    engine: string | null;
};

type WaitingRow = {
    wonum: string;
    description: string | null;
    status: string | null;
    engine: string | null;
    report_date: string | null;
    work_group: string | null;
};

type Data = {
    unit: { name: string; service_unit: string | null };
    period: { label: string };
    signatories?: {
        tl_har?: { name: string; position: string; signature: string | null };
        staff_har?: { name: string; position: string; signature: string | null };
        koordinator?: { name: string; position: string; signature: string | null };
        koordinator_har?: { name: string; position: string; signature: string | null };
        manager_ul?: { name: string; position: string; signature: string | null };
        manager?: { name: string; position: string; signature: string | null };
        project_leader?: { name: string; position: string; signature: string | null };
    };
    resume_statistik?: {
        rows: { no: number; diskripsi: string; target: number; realisasi: number; analisa_kinerja: number }[];
        total: { target: number; realisasi: number; analisa_kinerja: number };
    };
    sr_summary: {
        total: number;
        open: number;
        close: number;
        by_category: { category: string; count: number }[];
        by_engine?: { engine: string; total: number; terbit: number; cancel: number; flm: number; cm: number; pdm: number }[];
        top_assets?: { asset: string; freq: number; description: string }[];
    };
    maintenance_summary?: {
        rekap_terbit_complete: {
            url: string;
            rows: { bulan: string; terbit: number; complete: Record<number, number>; open: number }[];
        };
        rekap_status: {
            url: string;
            columns: string[];
            rows: { status: string; values: Record<string, number>; total: number }[];
            totals: Record<string, number>;
            grand_total: number;
        };
        tasks: {
            rows: {
                no: number;
                name: string;
                code: string;
                rencana_freq: number;
                rencana_pct: number;
                realisasi_freq: number;
                realisasi_pct: number;
                keterangan: string;
                material_cost: number;
                service_cost: number;
            }[];
            total_rencana_freq: number;
            total_rencana_pct: number;
            total_realisasi_freq: number;
            total_realisasi_pct: number;
            total_material_cost: number;
            total_service_cost: number;
            total_cost: number;
            mix: { label: string; pct: number; freq: number; color: string }[];
        };
    };
    wo_summary: { total: number; complete: number; open: number; percent: number };
    rekap_task_wo?: {
        categories: {
            no: string;
            title: string;
            rencana_freq: number;
            rencana_pct: number;
            realisasi_freq: number;
            realisasi_pct: number;
            disciplines: {
                name: string;
                rencana_freq: number;
                rencana_pct: number;
                realisasi_freq: number;
                realisasi_pct: number;
            }[];
        }[];
        total_rencana_freq: number;
        total_rencana_pct: number;
        total_realisasi_freq: number;
        total_realisasi_pct: number;
    };
    wo_by_type: { type: string; rows: WoRow[] }[];
    wo_waiting: { key: string; reason: string; rows: WaitingRow[] }[];
    cost: {
        auto_service: number;
        auto_material: number;
        auto_total: number;
        effective_total: number;
        source: string;
        ytd: number;
    };
    schedules: {
        scope: string;
        rows: { engine: string; rencana: Record<string, string>; realisasi: Record<string, string> }[];
    }[];
    activities: {
        date: string | null;
        engine: string | null;
        type: string | null;
        work_result: string | null;
        no_wo: string | null;
        no_sr: string | null;
        no_lh05: string | null;
        no_tug9: string | null;
        keterangan: string | null;
        tasks: string[];
        materials: { name: string; part_number: string | null; quantity: string | number | null; unit_of_measure: string | null }[];
    }[];
    attachments: {
        title: string;
        caption: string | null;
        engine: string | null;
        taken_date: string | null;
        url: string;
    }[];
};

const dayMap = (map: Record<string, string>) =>
    Object.entries(map)
        .filter(([, v]) => v)
        .map(([d, v]) => `${d}:${v}`)
        .join(' · ') || '—';

const rupiah = (v: number) => `Rp ${formatNumber(String(v))}`;

/** The ordered section list — drives the numbering and the table of contents. */
const SECTIONS = [
    'Lembar Pengesahan',
    'Resume Statistik Pemeliharaan Pembangkit',
    'Executive Summary',
    'Daftar Isi',
    'Istilah dan Definisi',
    'Service Request Map',
    'Service Request Summary',
    'Maintenance Summary',
    'Isi Laporan',
    'Work Order Summary (Fix)',
    'Akumulasi Biaya Pemeliharaan',
    'Rekapitulasi Work Order Task',
    'Work Order PM (Preventive Maintenance)',
    'Work Order PdM (Predictive Maintenance)',
    'Work Order CM (Corrective Maintenance)',
    'Work Order ENJI (Engineering)',
    'Work Order Waiting Shutdown',
    'Work Order Waiting Material & Jasa',
    'Lampiran',
] as const;

const GLOSSARY: { term: string; def: string }[] = [
    { term: 'WO (Work Order)', def: 'Perintah kerja pemeliharaan yang menjadi dasar pelaksanaan pekerjaan.' },
    { term: 'SR (Service Request)', def: 'Permintaan pekerjaan/perbaikan sebelum diterbitkan menjadi Work Order.' },
    { term: 'PM (Preventive Maintenance)', def: 'Pemeliharaan terjadwal untuk mencegah kerusakan berdasarkan jam operasi/kalender.' },
    { term: 'PdM (Predictive Maintenance)', def: 'Pemeliharaan berbasis kondisi melalui pemantauan/pengukuran parameter.' },
    { term: 'CM (Corrective Maintenance)', def: 'Pemeliharaan perbaikan setelah ditemukan kelainan/kerusakan.' },
    { term: 'ENJI (Engineering)', def: 'Pekerjaan rekayasa/modifikasi untuk peningkatan keandalan atau kinerja.' },
    { term: 'Waiting Shutdown', def: 'Work Order yang menunggu kesempatan mesin berhenti (shutdown) untuk dikerjakan.' },
    { term: 'Waiting Material / Jasa', def: 'Work Order yang tertunda karena menunggu ketersediaan material atau jasa pihak ketiga.' },
    { term: 'HARMES', def: 'Pemeliharaan Mesin — log kegiatan harian pemeliharaan pembangkit.' },
    { term: 'Rencana vs Realisasi', def: 'Perbandingan jadwal pemeliharaan yang direncanakan terhadap yang terealisasi.' },
];

/** Case-insensitive lookup of a WO type group (PDM matches both "PDM" and "PdM"). */
const groupFor = (data: Data, ...codes: string[]): WoRow[] => {
    const wanted = codes.map((c) => c.toLowerCase());

    return data.wo_by_type
        .filter((g) => wanted.includes(g.type.toLowerCase()))
        .flatMap((g) => g.rows);
};

const waitingFor = (data: Data, ...keys: string[]): WaitingRow[] =>
    data.wo_waiting.filter((g) => keys.includes(g.key)).flatMap((g) => g.rows);

function SectionTitle({ n, title, id, breakBefore }: { n: number; title: string; id?: string; breakBefore?: boolean }) {
    return (
        <h2 id={id} className={`mb-2 mt-6 border-b-2 border-slate-800 pb-1 text-[15px] font-bold uppercase tracking-wide text-slate-900 ${breakBefore ? 'break-before' : ''}`}>
            {n}. {title}
        </h2>
    );
}

function SectionKop({ title, docNumber, date }: { title: string; docNumber: string; date: string }) {
    return (
        <div className="mb-3 border border-slate-900 font-sans text-xs">
            <div className="flex border-b border-slate-900">
                <div className="flex w-44 items-center justify-center border-r border-slate-900 p-2">
                    <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="h-8" />
                </div>
                <div className="flex flex-1 flex-col items-center justify-center p-2 text-center">
                    <span className="text-sm font-bold tracking-wider">PLN NUSANTARA POWER</span>
                    <span className="text-xs font-bold">UP KENDARI</span>
                </div>
                <div className="flex w-24 items-center justify-center border-l border-slate-900 p-2">
                    <img src="/logo/k3.png" alt="K3" className="h-10" />
                </div>
            </div>
            <div className="border-b border-slate-900 bg-white py-1 text-center text-[11px] font-bold tracking-wider">
                INTEGRATED MANAGEMENT SYSTEM
            </div>
            <div className="grid grid-cols-12">
                <div className="col-span-8 flex flex-col items-center justify-center border-r border-slate-900 bg-[#7fa9d8] p-2 text-center text-xs font-bold leading-tight text-slate-900">
                    <span>{title}</span>
                </div>
                <div className="col-span-4 text-[10px]">
                    <div className="flex border-b border-slate-900 px-2 py-1">
                        <span className="w-20 font-medium">No. Dokumen</span>
                        <span className="font-semibold">: {docNumber}</span>
                    </div>
                    <div className="flex border-b border-slate-900 px-2 py-1">
                        <span className="w-20 font-medium">Revisi</span>
                        <span className="font-semibold">: 01</span>
                    </div>
                    <div className="flex px-2 py-1">
                        <span className="w-20 font-medium">Tanggal</span>
                        <span className="font-semibold">: {date}</span>
                    </div>
                </div>
            </div>
        </div>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <td className="border px-2 py-1">
            <div className="text-[10px] text-slate-500">{label}</div>
            <div className="font-semibold">{value}</div>
        </td>
    );
}

function WoTable({ rows }: { rows: WoRow[] }) {
    if (rows.length === 0) {
        return <p className="mb-3 text-slate-500">Tidak ada Work Order pada kategori ini.</p>;
    }

    return (
        <table className="mb-4 w-full border-collapse text-[10px]">
            <thead>
                <tr className="bg-slate-100">
                    <th className="border px-1 py-1">No</th>
                    <th className="border px-1 py-1">WONUM</th>
                    <th className="border px-1 py-1 text-left">Deskripsi</th>
                    <th className="border px-1 py-1">Mesin</th>
                    <th className="border px-1 py-1">Report</th>
                    <th className="border px-1 py-1">Sched Start</th>
                    <th className="border px-1 py-1">Sched Finish</th>
                    <th className="border px-1 py-1">Status</th>
                    <th className="border px-1 py-1">Group</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((r, i) => (
                    <tr key={r.wonum}>
                        <td className="border px-1 py-1 text-center">{i + 1}</td>
                        <td className="border px-1 py-1">{r.wonum}</td>
                        <td className="border px-1 py-1 text-left">{r.description ?? '—'}</td>
                        <td className="border px-1 py-1">{r.engine ?? '—'}</td>
                        <td className="border px-1 py-1">{r.report_date ?? '—'}</td>
                        <td className="border px-1 py-1">{r.sched_start ?? '—'}</td>
                        <td className="border px-1 py-1">{r.sched_finish ?? '—'}</td>
                        <td className="border px-1 py-1">{r.status ?? '—'}</td>
                        <td className="border px-1 py-1">{r.work_group ?? '—'}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function WaitingTable({ rows }: { rows: WaitingRow[] }) {
    if (rows.length === 0) {
        return <p className="mb-3 text-slate-500">Tidak ada Work Order pada kategori ini.</p>;
    }

    return (
        <table className="mb-4 w-full border-collapse text-[10px]">
            <thead>
                <tr className="bg-slate-100">
                    <th className="border px-1 py-1">No</th>
                    <th className="border px-1 py-1">WONUM</th>
                    <th className="border px-1 py-1 text-left">Deskripsi</th>
                    <th className="border px-1 py-1">Mesin</th>
                    <th className="border px-1 py-1">Report</th>
                    <th className="border px-1 py-1">Status</th>
                    <th className="border px-1 py-1">Group</th>
                </tr>
            </thead>
            <tbody>
                {rows.map((r, i) => (
                    <tr key={r.wonum}>
                        <td className="border px-1 py-1 text-center">{i + 1}</td>
                        <td className="border px-1 py-1">{r.wonum}</td>
                        <td className="border px-1 py-1 text-left">{r.description ?? '—'}</td>
                        <td className="border px-1 py-1">{r.engine ?? '—'}</td>
                        <td className="border px-1 py-1">{r.report_date ?? '—'}</td>
                        <td className="border px-1 py-1">{r.status ?? '—'}</td>
                        <td className="border px-1 py-1">{r.work_group ?? '—'}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

export default function MonthlyReport({ data }: { data: Data }) {
    const woPm = groupFor(data, 'PM');
    const woPdm = groupFor(data, 'PDM', 'PdM');
    const woCm = groupFor(data, 'CM');
    const woEnji = groupFor(data, 'ENJI');
    const waitingShutdown = waitingFor(data, 'shutdown');
    const waitingMaterialJasa = waitingFor(data, 'material', 'jasa');
    const waitingCount = data.wo_waiting.reduce((sum, g) => sum + g.rows.length, 0);
    const totalTasks = data.activities.reduce((sum, a) => sum + a.tasks.length, 0);

    // Service Request calculations
    const srByCategory = data.sr_summary.by_category ?? [];
    const srCountFor = (codes: string[]) => {
        const u = codes.map((c) => c.toUpperCase());
        return srByCategory
            .filter((c) => u.includes(c.category.toUpperCase()))
            .reduce((sum, c) => sum + c.count, 0);
    };
    const srCm = srCountFor(['CM', 'CORRECTIVE MAINTENANCE (CM)', 'CORECTIVE MAINTENANCE (CM)']);
    const srFlm = srCountFor(['FLM', 'FIRST LINE MAINTENANCE (FLM)']);
    const srCancel = srCountFor(['CANCEL', 'DIBATALKAN']);
    const srPdm = srCountFor(['PDM', 'PREDICTIVE MAINTENANCE (PDM)', 'PREDICTIVE MAINTENANCE']);
    const srTotal = data.sr_summary.total;
    const srCmPct = srTotal > 0 ? Math.round((srCm / srTotal) * 100) : 0;
    const srFlmPct = srTotal > 0 ? Math.round((srFlm / srTotal) * 100) : 0;
    const srCancelPct = srTotal > 0 ? Math.round((srCancel / srTotal) * 100) : 0;
    const srPdmPct = srTotal > 0 ? Math.round((srPdm / srTotal) * 100) : 0;

    let srMapMax = Math.max(14, srCm, srFlm, srCancel, srPdm);
    srMapMax = Math.ceil(srMapMax / 2) * 2;
    if (srMapMax < 14) srMapMax = 14;
    const srMapTicks: number[] = [];
    for (let t = 0; t <= srMapMax; t += 2) srMapTicks.push(t);

    const srByEngine = data.sr_summary.by_engine ?? [];
    const totalTerbit = srByEngine.reduce((sum, e) => sum + e.terbit, 0);
    const totalCancel = srByEngine.reduce((sum, e) => sum + e.cancel, 0);
    const totalFlm = srByEngine.reduce((sum, e) => sum + e.flm, 0);

    const unitSrRows = srByEngine.map((e) => ({
        name: e.engine,
        terbit: e.terbit,
        cancel: e.cancel,
        flm: e.flm,
        pct: totalTerbit > 0 ? Math.round((e.terbit / totalTerbit) * 100) : 0,
    }));

    const piePalette = ['#a94442', '#8ea351', '#61558f', '#eb7347', '#3b7ea1', '#e09f3e'];
    const pieSlices = unitSrRows.filter((r) => r.pct > 0);
    let curPieAngle = -90;
    const pieSlicesWithAngles = pieSlices.map((s, idx) => {
        const pColor = piePalette[idx % piePalette.length];
        const aSpan = (s.pct / 100) * 360;
        const a1 = (curPieAngle * Math.PI) / 180;
        const a2 = ((curPieAngle + aSpan) * Math.PI) / 180;
        const cx = 140;
        const cy = 90;
        const r = 55;
        const x1 = Math.round((cx + r * Math.cos(a1)) * 100) / 100;
        const y1 = Math.round((cy + r * Math.sin(a1)) * 100) / 100;
        const x2 = Math.round((cx + r * Math.cos(a2)) * 100) / 100;
        const y2 = Math.round((cy + r * Math.sin(a2)) * 100) / 100;
        const largeArc = aSpan > 180 ? 1 : 0;
        const d = `M ${cx} ${cy} L ${x1} ${y1} A ${r} ${r} 0 ${largeArc} 1 ${x2} ${y2} Z`;

        const midA = ((curPieAngle + aSpan / 2) * Math.PI) / 180;
        const lx1 = Math.round((cx + r * 0.85 * Math.cos(midA)) * 100) / 100;
        const ly1 = Math.round((cy + r * 0.85 * Math.sin(midA)) * 100) / 100;
        const lx2 = Math.round((cx + r * 1.35 * Math.cos(midA)) * 100) / 100;
        const ly2 = Math.round((cy + r * 1.35 * Math.sin(midA)) * 100) / 100;
        const anchor: 'start' | 'end' = Math.cos(midA) >= 0 ? 'start' : 'end';
        const tx = Math.cos(midA) >= 0 ? lx2 + 2 : lx2 - 2;

        curPieAngle += aSpan;

        return { ...s, pColor, d, lx1, ly1, lx2, ly2, anchor, tx };
    });

    const flmRowsReversed = [...unitSrRows].reverse();
    const flmMaxVal = Math.max(4, ...unitSrRows.map((r) => r.flm), 0);
    const flmTicks: number[] = [];
    for (let t = 0; t <= flmMaxVal; t += 1) flmTicks.push(t);

    const srSummaryOpen = data.sr_summary.open;
    const srSummaryClose = data.sr_summary.close;
    const srSummaryTotal = srSummaryOpen + srSummaryClose;
    const srSummaryOpenPct = srSummaryTotal > 0 ? Math.round((srSummaryOpen / srSummaryTotal) * 100) : 0;
    const srSummaryClosePct = srSummaryTotal > 0 ? 100 - srSummaryOpenPct : 0;

    let c1Max = Math.max(12, srSummaryOpen, srSummaryClose);
    if (c1Max % 2 !== 0) c1Max++;
    const c1Ticks: number[] = [];
    for (let t = 0; t <= c1Max; t += 2) c1Ticks.push(t);

    const top5SrAssets = data.sr_summary.top_assets ?? [];

    return (
        <>
            <Head title={`Laporan Pemeliharaan Pembangkit — ${data.unit.name}`} />
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
                    <div className="print-area mx-auto min-h-[1123px] w-full max-w-[794px] bg-white p-10 text-[12px] text-slate-900 shadow-xl">
                        {/* 1. COVER */}
                        <div className="report-cover relative mb-8 flex min-h-[1050px] flex-col items-center justify-between overflow-hidden rounded-lg border border-slate-200 bg-white p-12 text-center shadow-sm">
                            <div className="relative z-10 flex w-full flex-col items-center">
                                <div className="flex items-center justify-center gap-5">
                                    <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="h-14 object-contain" />
                                    <div className="h-12 w-[2px] bg-[#0b2545]" />
                                    <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" className="h-12 object-contain" />
                                </div>

                                <div className="mt-16 text-center">
                                    <h1 className="text-3xl font-extrabold uppercase tracking-widest text-[#0b2545]">
                                        LAPORAN PEMELIHARAAN<br />PEMBANGKIT
                                    </h1>
                                    <div className="mx-auto mt-4 h-1 w-28 bg-[#0284c7]" />
                                </div>

                                <div className="mt-14 w-full max-w-lg rounded-2xl border-2 border-[#0284c7] bg-white p-6 text-left shadow-sm">
                                    <table className="w-full text-xs font-bold uppercase text-[#0b2545]">
                                        <tbody>
                                            <tr>
                                                <td className="w-44 py-1">NAMA PEMBANGKIT</td>
                                                <td className="w-4 py-1 text-center">:</td>
                                                <td className="py-1 text-slate-800">{data.unit.name}</td>
                                            </tr>
                                            <tr>
                                                <td className="w-44 py-1">PERIODE PELAPORAN</td>
                                                <td className="w-4 py-1 text-center">:</td>
                                                <td className="py-1 text-slate-800">BULAN {data.period.label}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div className="relative z-10 mt-auto flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-[10px] text-[#0b2545]">
                                <span className="font-bold">ANDAL</span>
                                <span className="mx-3 text-slate-300">|</span>
                                <span className="font-bold">EFISIEN</span>
                                <span className="mx-3 text-slate-300">|</span>
                                <span className="font-bold">BERSIH</span>
                                <span className="mx-3 text-slate-300">|</span>
                                <span className="font-bold">AMAN</span>
                            </div>
                        </div>

                        {/* 2. LEMBAR PENGESAHAN */}
                        <div className="page-break mb-8 flex min-h-[1050px] flex-col justify-between rounded-lg border border-slate-200 bg-white p-10 text-slate-900 shadow-sm" id="sec-pengesahan">
                            <div>
                                {/* Kop Table */}
                                <table className="w-full border-collapse border border-black text-center">
                                    <tbody>
                                        <tr>
                                            <td rowSpan={4} className="w-36 border border-black p-2 text-center align-middle">
                                                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="mx-auto max-h-12 object-contain" />
                                            </td>
                                            <td className="border border-black p-1.5 text-xs font-bold uppercase tracking-wider text-black">
                                                JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE &amp; 6 SITE -KIT
                                            </td>
                                            <td rowSpan={4} className="w-32 border border-black p-2 text-center align-middle">
                                                <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" className="mx-auto max-h-11 object-contain" />
                                            </td>
                                        </tr>
                                        <tr>
                                            <td className="border border-black p-1.5 text-xs font-bold uppercase tracking-wider text-black">
                                                {data.unit.name.toUpperCase().includes('POASIA') && !data.unit.name.toUpperCase().includes('SITE')
                                                    ? `${data.unit.name.toUpperCase()} 6 SITE`
                                                    : data.unit.name.toUpperCase()}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td className="border border-black p-1.5 text-xs font-bold uppercase tracking-wider text-black">
                                                LAPORAN PROJECT
                                            </td>
                                        </tr>
                                        <tr>
                                            <td className="border border-black p-1.5 text-xs font-bold uppercase tracking-wider text-black">
                                                LEMBAR PENGESAHAN
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                {/* Statement Body */}
                                <div className="mt-8 px-2 text-sm leading-relaxed text-black">
                                    <div className="mb-4 font-bold">
                                        JASA PENDUKUNG TEKNIS 6 SITE - {data.unit.name.toUpperCase().includes('POASIA') && !data.unit.name.toUpperCase().includes('SITE') ? `${data.unit.name.toUpperCase()} 6 SITE` : data.unit.name.toUpperCase()}
                                    </div>
                                    <div className="mb-4">
                                        Dengan ini menyatakan bahwa :
                                    </div>
                                    <div className="mb-4 font-bold">
                                        1. LAPORAN PEMELIHARAAN PEMBANGKIT
                                    </div>
                                    <p className="mb-4 text-justify">
                                        Telah disusun berdasarkan kegiatan Pemeliharaan pembangkit serta administrasi dan dokumentasi pendukung.
                                    </p>
                                    <p className="mb-4 text-justify">
                                        Laporan ini telah dilakukan pemeriksaan dan dinyatakan sesuai untuk digunakan sebagai dokumen pelaporan dan evaluasi kegiatan pemeliharaan pembangkit {data.unit.name}.
                                    </p>
                                    <p className="mb-6 text-justify">
                                        Demikian lembar pengesahan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.
                                    </p>

                                    <div className="mt-8 mb-4 pr-4 text-right">
                                        Kendari, 1 {data.period.label}
                                    </div>

                                    {/* Signatures */}
                                    <div className="mt-8 flex justify-between px-2">
                                        <div className="w-1/2 text-left">
                                            <div>Disetujui,</div>
                                            <div
                                                className="font-bold"
                                                dangerouslySetInnerHTML={{
                                                    __html:
                                                        data.signatories?.tl_har?.position ??
                                                        (data.unit.service_unit
                                                            ? `TL HAR ${data.unit.service_unit.toUpperCase().replace(/\s+/g, '')}`
                                                            : 'TL HAR ULPLTD POASIA'),
                                                }}
                                            />
                                            <div className="flex h-16 items-center">
                                                {data.signatories?.tl_har?.signature ? (
                                                    <img
                                                        src={data.signatories.tl_har.signature}
                                                        alt="Ttd TL Har"
                                                        className="max-h-16 max-w-[140px] object-contain"
                                                    />
                                                ) : null}
                                            </div>
                                            <div className="font-bold">{data.signatories?.tl_har?.name ?? 'MUH. ISYAK'}</div>
                                        </div>
                                        <div className="w-1/2 text-center">
                                            <div>Dibuat,</div>
                                            <div
                                                className="font-bold"
                                                dangerouslySetInnerHTML={{
                                                    __html:
                                                        data.signatories?.staff_har?.position ??
                                                        data.signatories?.koordinator?.position ??
                                                        (data.unit.name.toLowerCase().includes('container')
                                                            ? 'Koordinator Pemeliharaan<br />Containerized Poasia'
                                                            : `Koordinator Pemeliharaan<br />${data.unit.name}`),
                                                }}
                                            />
                                            <div className="flex h-16 items-center justify-center">
                                                {(data.signatories?.staff_har?.signature ?? data.signatories?.koordinator?.signature) ? (
                                                    <img
                                                        src={(data.signatories?.staff_har?.signature ?? data.signatories?.koordinator?.signature)!}
                                                        alt="Ttd Staf Pemeliharaan"
                                                        className="mx-auto max-h-16 max-w-[140px] object-contain"
                                                    />
                                                ) : null}
                                            </div>
                                            <div className="font-bold">
                                                {data.signatories?.staff_har?.name ?? data.signatories?.koordinator?.name ?? 'AMIRULLAH'}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="mt-6 text-center">
                                        <div>Mengetahui,</div>
                                        <div
                                            className="font-bold"
                                            dangerouslySetInnerHTML={{
                                                __html:
                                                    data.signatories?.manager_ul?.position ??
                                                    data.signatories?.manager?.position ??
                                                    (data.unit.service_unit
                                                        ? `Manager ${data.unit.service_unit.toUpperCase()}`
                                                        : 'Manager ULPLTD POASIA'),
                                            }}
                                        />
                                        <div className="flex h-16 items-center justify-center">
                                            {(data.signatories?.manager_ul?.signature ?? data.signatories?.manager?.signature) ? (
                                                <img
                                                    src={(data.signatories?.manager_ul?.signature ?? data.signatories?.manager?.signature)!}
                                                    alt="Ttd Manager UL"
                                                    className="mx-auto max-h-16 max-w-[140px] object-contain"
                                                />
                                            ) : null}
                                        </div>
                                        <div className="font-bold">
                                            {data.signatories?.manager_ul?.name ?? data.signatories?.manager?.name ?? 'ZULKIFLIN'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* 3. RESUME STATISTIK PEMELIHARAAN PEMBANGKIT */}
                        <div className="page-break mb-8 flex min-h-[1050px] flex-col justify-between rounded-lg border border-slate-200 bg-white p-10 text-slate-900 shadow-sm" id="sec-resume-statistik">
                            <div>
                                {/* Header with Logos */}
                                <div className="flex items-center justify-between border-b pb-4">
                                    <div className="w-36">
                                        <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="max-h-12 object-contain" />
                                    </div>
                                    <div className="flex-1 text-center leading-tight">
                                        <div className="text-xs font-bold tracking-wider text-black">JASA PENDUKUNG TEKNIS - 6 SITE KIT</div>
                                        <div className="text-xs font-bold tracking-wider text-black">
                                            PLN NP UP KENDARI - {data.unit.name.toUpperCase().includes('POASIA') && !data.unit.name.toUpperCase().includes('SITE') ? `${data.unit.name.toUpperCase()} 6 SITE` : data.unit.name.toUpperCase()}
                                        </div>
                                        <div className="text-xs font-bold tracking-wider text-black">LAPORAN PROJECT</div>
                                        <div className="mt-1 text-sm font-bold tracking-wide text-black">RESUME STATISTIK PEMELIHARAAN PEMBANGKIT</div>
                                    </div>
                                    <div className="w-36 text-right">
                                        <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" className="ml-auto max-h-10 object-contain" />
                                        <div className="text-[9px] font-bold tracking-widest text-slate-500">MITRA KARYA PRIMA</div>
                                    </div>
                                </div>

                                <div className="mt-4 mb-2 text-xs font-bold uppercase text-black">
                                    PRIODE &nbsp;&nbsp;: &nbsp;&nbsp;{data.period.label}
                                </div>

                                {/* Table */}
                                <table className="w-full border-collapse border border-black text-xs text-black">
                                    <thead>
                                        <tr className="bg-[#c6e0b4] text-center font-bold">
                                            <th className="w-12 border border-black p-1.5">NO</th>
                                            <th className="border border-black p-1.5 text-center">DISKRIPSI</th>
                                            <th className="w-20 border border-black p-1.5">TARGET</th>
                                            <th className="w-20 border border-black p-1.5 leading-tight">REALISAS<br />I</th>
                                            <th className="w-24 border border-black p-1.5 leading-tight">ANALISA<br />KINERJA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(data.resume_statistik?.rows ?? [
                                            { no: 1, diskripsi: 'Jadwal Kegiatran Pemeliharaan', target: 20, realisasi: 20, analisa_kinerja: 100 },
                                            { no: 2, diskripsi: 'Realisasi Pemeliharaan Rutin P0-P5', target: 11, realisasi: 15, analisa_kinerja: 136 },
                                            { no: 3, diskripsi: 'Jadwal Piket OnCall', target: 7, realisasi: 7.75, analisa_kinerja: 111 },
                                            { no: 4, diskripsi: 'Jadwal Patrol Cek Pemeliharaan', target: 19, realisasi: 19, analisa_kinerja: 100 },
                                            { no: 5, diskripsi: 'Jadwal Meeting Pemeliharaan', target: 1, realisasi: 1, analisa_kinerja: 100 },
                                            { no: 6, diskripsi: 'Jadwal Pembuatan IK Pemeliharaan Kit', target: 2, realisasi: 2, analisa_kinerja: 100 },
                                            { no: 7, diskripsi: 'Jadwal Pemeriksaan Instalasi Black Start', target: 8, realisasi: 8, analisa_kinerja: 100 },
                                            { no: 8, diskripsi: 'Ratio Work Order (Closed Work Order/Total Work Order)', target: 36, realisasi: 36, analisa_kinerja: 100 },
                                            { no: 9, diskripsi: 'Laporan Input Data Aplikasi Online Pemeliharaan', target: 19, realisasi: 19, analisa_kinerja: 100 },
                                        ]).map((row) => (
                                            <tr key={row.no}>
                                                <td className="border border-black p-1.5 text-center">{row.no}</td>
                                                <td className="border border-black p-1.5 text-left">{row.diskripsi}</td>
                                                <td className="border border-black p-1.5 text-right">{row.target}</td>
                                                <td className="border border-black p-1.5 text-right">{row.realisasi}</td>
                                                <td className="border border-black p-1.5 text-right">{row.analisa_kinerja}%</td>
                                            </tr>
                                        ))}
                                        <tr className="bg-[#d9e1f2] font-bold italic">
                                            <td colSpan={2} className="border border-black p-1.5 text-right">TOTAL</td>
                                            <td className="border border-black p-1.5 text-right">{data.resume_statistik?.total?.target ?? 13.67}</td>
                                            <td className="border border-black p-1.5 text-right">{data.resume_statistik?.total?.realisasi ?? 13.75}</td>
                                            <td className="border border-black p-1.5 text-right">{data.resume_statistik?.total?.analisa_kinerja ?? 105}%</td>
                                        </tr>
                                    </tbody>
                                </table>

                                {/* Signatures (Project Leader & Koordinator Pemeliharaan) */}
                                <div className="mt-16 flex justify-between px-6 text-center text-sm text-black">
                                    <div className="w-1/2">
                                        <div>Mengetahui</div>
                                        <div className="font-bold">{data.signatories?.project_leader?.position ?? 'Project Leader'}</div>
                                        <div className="flex h-20 items-center justify-center">
                                            {data.signatories?.project_leader?.signature ? (
                                                <img
                                                    src={data.signatories.project_leader.signature}
                                                    alt="Ttd Project Leader"
                                                    className="max-h-20 max-w-[150px] object-contain"
                                                />
                                            ) : null}
                                        </div>
                                        <div className="font-bold">{data.signatories?.project_leader?.name ?? 'HERWIN SYAHPUTRA'}</div>
                                    </div>
                                    <div className="w-1/2">
                                        <div>Dibuat;</div>
                                        <div className="font-bold">{data.signatories?.koordinator_har?.position ?? 'Koordinator Pemeliharaan'}</div>
                                        <div className="flex h-20 items-center justify-center">
                                            {(data.signatories?.koordinator_har?.signature ?? data.signatories?.staff_har?.signature) ? (
                                                <img
                                                    src={(data.signatories?.koordinator_har?.signature ?? data.signatories?.staff_har?.signature)!}
                                                    alt="Ttd Koordinator Pemeliharaan"
                                                    className="mx-auto max-h-20 max-w-[150px] object-contain"
                                                />
                                            ) : null}
                                        </div>
                                        <div className="font-bold">{data.signatories?.koordinator_har?.name ?? data.signatories?.staff_har?.name ?? 'AMIRULLAH'}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* 4. EXECUTIVE SUMMARY */}
                        <SectionTitle n={4} title="Executive Summary" id="sec-exec" />
                        <p className="mb-3 text-justify leading-relaxed">
                            Laporan ini merangkum kinerja pemeliharaan {data.unit.name} pada periode{' '}
                            <span className="font-semibold">{data.period.label}</span>. Sepanjang periode tercatat{' '}
                            <span className="font-semibold">{data.wo_summary.total}</span> Work Order dengan tingkat penyelesaian{' '}
                            <span className="font-semibold">{data.wo_summary.percent}%</span> ({data.wo_summary.complete} selesai,{' '}
                            {data.wo_summary.open} berjalan), serta {data.sr_summary.total} Service Request ({data.sr_summary.open} open).
                            Terdapat {waitingCount} Work Order berstatus menunggu. Total biaya pemeliharaan efektif periode ini{' '}
                            <span className="font-semibold">{rupiah(data.cost.effective_total)}</span> (akumulasi tahun berjalan{' '}
                            {rupiah(data.cost.ytd)}).
                        </p>
                        <table className="mb-2 w-full border-collapse text-[11px]">
                            <tbody>
                                <tr>
                                    <Stat label="Total WO" value={String(data.wo_summary.total)} />
                                    <Stat label="% Complete" value={`${data.wo_summary.percent}%`} />
                                    <Stat label="Total SR" value={String(data.sr_summary.total)} />
                                    <Stat label="WO Waiting" value={String(waitingCount)} />
                                    <Stat label="Biaya Efektif" value={rupiah(data.cost.effective_total)} />
                                </tr>
                            </tbody>
                        </table>

                        {/* 5. DAFTAR ISI */}
                        <SectionTitle n={5} title="Daftar Isi" id="sec-toc" />
                        <table className="mb-4 w-full border-collapse text-[12px]">
                            <tbody>
                                <tr>
                                    <td className="py-0.5 pr-2 align-top font-semibold">1.</td>
                                    <td className="w-full py-0.5">Cover</td>
                                </tr>
                                {SECTIONS.map((title, index) => (
                                    <tr key={title}>
                                        <td className="py-0.5 pr-2 align-top font-semibold">{index + 2}.</td>
                                        <td className="w-full py-0.5">{title}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {/* 6. ISTILAH DAN DEFINISI */}
                        <SectionTitle n={6} title="Istilah dan Definisi" id="sec-glossary" />
                        <table className="mb-4 w-full border-collapse text-[11px]">
                            <thead>
                                <tr className="bg-slate-100">
                                    <th className="border px-2 py-1 text-left">Istilah</th>
                                    <th className="border px-2 py-1 text-left">Definisi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {GLOSSARY.map((g) => (
                                    <tr key={g.term}>
                                        <td className="border px-2 py-1 align-top font-semibold">{g.term}</td>
                                        <td className="border px-2 py-1 text-left">{g.def}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {/* 7. SERVICE REQUEST MAP */}
                        <div className="break-before" id="sec-sr-map">
                            <SectionKop title="SERVICE REQUEST MAP" docNumber="FMKD-314-10.3.3-A8" date={data.period.label} />

                            <div className="mb-1 text-[11px] font-bold text-slate-800">SERVICE REQUEST MAP BULAN INI</div>
                            <table className="mb-4 w-full border-collapse border border-slate-900 text-[11px]">
                                <thead>
                                    <tr className="bg-slate-100 font-bold text-slate-900">
                                        <th className="w-10 border border-slate-900 p-1 text-center">NO</th>
                                        <th className="border border-slate-900 p-1 text-left">SERVICE REQUEST</th>
                                        <th className="w-24 border border-slate-900 p-1 text-center">JUMLAH</th>
                                        <th className="w-28 border border-slate-900 p-1 text-center">PERSENTASE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td className="border border-slate-900 p-1 text-center">1</td>
                                        <td className="border border-slate-900 p-1 text-left">CORECTIVE MAINTENANCE (CM)</td>
                                        <td className="border border-slate-900 p-1 text-center">{srCm}</td>
                                        <td className="border border-slate-900 p-1 text-center">{srCmPct}%</td>
                                    </tr>
                                    <tr>
                                        <td className="border border-slate-900 p-1 text-center">2</td>
                                        <td className="border border-slate-900 p-1 text-left">FIRST LINE MAINTENANCE (FLM)</td>
                                        <td className="border border-slate-900 p-1 text-center">{srFlm}</td>
                                        <td className="border border-slate-900 p-1 text-center">{srFlmPct}%</td>
                                    </tr>
                                    <tr>
                                        <td className="border border-slate-900 p-1 text-center">3</td>
                                        <td className="border border-slate-900 p-1 text-left">CANCEL</td>
                                        <td className="border border-slate-900 p-1 text-center">{srCancel}</td>
                                        <td className="border border-slate-900 p-1 text-center">{srCancelPct}%</td>
                                    </tr>
                                    <tr>
                                        <td className="border border-slate-900 p-1 text-center">4</td>
                                        <td className="border border-slate-900 p-1 text-left">PREDICTIVE MAINTENANCE (PDM)</td>
                                        <td className="border border-slate-900 p-1 text-center">{srPdm}</td>
                                        <td className="border border-slate-900 p-1 text-center">{srPdmPct}%</td>
                                    </tr>
                                    <tr className="bg-slate-100 font-bold">
                                        <td colSpan={2} className="border border-slate-900 p-1 text-center">TOTAL SERVICE REQUEST</td>
                                        <td className="border border-slate-900 p-1 text-center">{srTotal}</td>
                                        <td className="border border-slate-900 p-1 text-center">{srTotal > 0 ? '100%' : '0%'}</td>
                                    </tr>
                                </tbody>
                            </table>

                            {/* Chart 1: SR Map Horizontal Bar Chart */}
                            <div className="mb-4 rounded border border-slate-300 bg-white p-3 text-center">
                                <div className="mb-2 text-center text-xs font-bold text-slate-800">SERVICE REQUEST MAP</div>
                                <svg viewBox="0 0 520 130" className="mx-auto block h-28 w-full font-sans">
                                    {srMapTicks.map((t) => {
                                        const tx = 160 + (t / srMapMax) * 340;
                                        return (
                                            <g key={t}>
                                                <line x1={tx} y1={10} x2={tx} y2={105} stroke="#e5e7eb" strokeWidth={0.8} />
                                                <text x={tx} y={117} textAnchor="middle" fontSize={8} fill="#444">{t}</text>
                                            </g>
                                        );
                                    })}
                                    <line x1={160} y1={10} x2={160} y2={105} stroke="#999" strokeWidth={1} />
                                    <line x1={160} y1={105} x2={500} y2={105} stroke="#999" strokeWidth={1} />
                                    {[
                                        { label: 'PREDICTIVE MAINTENANCE (PDM)', val: srPdm },
                                        { label: 'CANCEL', val: srCancel },
                                        { label: 'FIRST LINE MAINTENANCE (FLM)', val: srFlm },
                                        { label: 'CORECTIVE MAINTENANCE (CM)', val: srCm },
                                    ].map((b, bIdx) => {
                                        const by = 16 + bIdx * 23;
                                        const bw = srMapMax > 0 ? (b.val / srMapMax) * 340 : 0;
                                        return (
                                            <g key={b.label}>
                                                <text x={155} y={by + 10} textAnchor="end" fontSize={8} fill="#000">{b.label}</text>
                                                {bw > 0 && <rect x={160} y={by} width={bw} height={13} fill="#5b9bd5" />}
                                                {bw > 0 && <text x={160 + bw + 5} y={by + 10} fontSize={8} fontWeight="bold" fill="#333">{b.val}</text>}
                                            </g>
                                        );
                                    })}
                                </svg>
                            </div>

                            {/* Table: SR TERBIT PER UNIT */}
                            <div className="mb-1 text-[11px] font-bold text-slate-800">SR TERBIT PER UNIT</div>
                            <table className="mb-4 w-full border-collapse border border-slate-900 text-[10px]">
                                <thead>
                                    <tr className="bg-slate-100 font-bold text-slate-900">
                                        <th rowSpan={2} className="w-10 border border-slate-900 p-1 text-center">NO</th>
                                        <th rowSpan={2} className="border border-slate-900 p-1 text-center">GROUP UNIT/ MESIN</th>
                                        <th colSpan={4} className="border border-slate-900 p-1 text-center">JUMLAH SERVICE REQUEST</th>
                                    </tr>
                                    <tr className="bg-slate-100 font-bold text-slate-900">
                                        <th className="w-20 border border-slate-900 p-1 text-center">TERBIT</th>
                                        <th className="w-24 border border-slate-900 p-1 text-center">PERSENTASE</th>
                                        <th className="w-20 border border-slate-900 p-1 text-center">CANCEL</th>
                                        <th className="w-20 border border-slate-900 p-1 text-center">FLM</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {unitSrRows.map((row, idx) => (
                                        <tr key={row.name}>
                                            <td className="border border-slate-900 p-1 text-center">{idx + 1}</td>
                                            <td className="border border-slate-900 p-1 text-left">{row.name}</td>
                                            <td className="border border-slate-900 p-1 text-center">{row.terbit}</td>
                                            <td className="border border-slate-900 p-1 text-center">{row.pct}%</td>
                                            <td className="border border-slate-900 p-1 text-center">{row.cancel}</td>
                                            <td className="border border-slate-900 p-1 text-center">{row.flm}</td>
                                        </tr>
                                    ))}
                                    <tr className="bg-slate-100 font-bold">
                                        <td colSpan={2} className="border border-slate-900 p-1 text-center">TOTAL</td>
                                        <td className="border border-slate-900 p-1 text-center">{totalTerbit}</td>
                                        <td className="border border-slate-900 p-1 text-center">{totalTerbit > 0 ? '100%' : '0%'}</td>
                                        <td className="border border-slate-900 p-1 text-center">{totalCancel}</td>
                                        <td className="border border-slate-900 p-1 text-center">{totalFlm}</td>
                                    </tr>
                                </tbody>
                            </table>

                            {/* Charts Side-by-Side: Donut Pie & FLM per Unit */}
                            <div className="mb-4 grid grid-cols-2 gap-3">
                                <div className="flex flex-col items-center justify-center rounded border border-slate-300 bg-white p-2">
                                    <svg viewBox="0 0 280 180" className="h-44 w-full font-sans">
                                        {pieSlicesWithAngles.length === 0 ? (
                                            <text x={140} y={90} fontSize={9} fill="#888" textAnchor="middle">Tidak ada data Service Request</text>
                                        ) : (
                                            pieSlicesWithAngles.map((s) => (
                                                <g key={s.name}>
                                                    <path d={s.d} fill={s.pColor} stroke="#fff" strokeWidth={1.2} />
                                                    <line x1={s.lx1} y1={s.ly1} x2={s.lx2} y2={s.ly2} stroke="#444" strokeWidth={0.8} />
                                                    <text x={s.tx} y={s.ly2} fontSize={7.5} fill="#000" textAnchor={s.anchor}>{s.name}</text>
                                                    <text x={s.tx} y={s.ly2 + 9} fontSize={7.5} fontWeight="bold" fill="#000" textAnchor={s.anchor}>{s.pct}%</text>
                                                </g>
                                            ))
                                        )}
                                    </svg>
                                </div>
                                <div className="rounded border border-slate-300 bg-white p-2">
                                    <div className="mb-1 text-center text-[10px] font-bold">FLM PER UNIT</div>
                                    <svg viewBox="0 0 280 180" className="h-44 w-full font-sans">
                                        {flmTicks.map((t) => {
                                            const tx = 75 + (t / flmMaxVal) * 175;
                                            return (
                                                <g key={t}>
                                                    <line x1={tx} y1={24} x2={tx} y2={150} stroke="#e5e7eb" strokeWidth={0.8} />
                                                    <text x={tx} y={162} textAnchor="middle" fontSize={8} fill="#444">{t}</text>
                                                </g>
                                            );
                                        })}
                                        <line x1={75} y1={24} x2={75} y2={150} stroke="#999" strokeWidth={1} />
                                        <line x1={75} y1={150} x2={250} y2={150} stroke="#999" strokeWidth={1} />
                                        {flmRowsReversed.map((fr, rIdx) => {
                                            const ry = 30 + rIdx * 20;
                                            const rw = flmMaxVal > 0 ? (fr.flm / flmMaxVal) * 175 : 0;
                                            return (
                                                <g key={fr.name}>
                                                    <text x={70} y={ry + 10} textAnchor="end" fontSize={7.5} fill="#000">{fr.name}</text>
                                                    {rw > 0 && <rect x={75} y={ry} width={rw} height={13} fill="#5b9bd5" />}
                                                    <text x={75 + rw + 4} y={ry + 10} fontSize={7.5} fontWeight="bold" fill="#000">{fr.flm}</text>
                                                </g>
                                            );
                                        })}
                                    </svg>
                                </div>
                            </div>
                        </div>

                        {/* 6. SERVICE REQUEST SUMMARY */}
                        <div className="break-before" id="sec-sr-summary">
                            <SectionKop title="SERVICE REQUEST SUMMARY" docNumber="FMKD-314-10.3.3-A9" date={data.period.label} />

                            <div className="mb-1 text-[11px] font-bold text-slate-800">SR AKTIF PER STATUS BULAN INI</div>
                            <table className="mb-4 w-72 border-collapse border border-slate-900 text-[11px]">
                                <thead>
                                    <tr className="bg-slate-100 font-bold text-slate-900">
                                        <th className="w-10 border border-slate-900 p-1 text-center">NO</th>
                                        <th className="border border-slate-900 p-1 text-left">SERVICE REQUEST</th>
                                        <th className="w-20 border border-slate-900 p-1 text-center">JUMLAH</th>
                                        <th className="w-24 border border-slate-900 p-1 text-center">PERSENTASE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td className="border border-slate-900 p-1 text-center">1</td>
                                        <td className="border border-slate-900 p-1 text-left">OPEN</td>
                                        <td className="border border-slate-900 p-1 text-center">{srSummaryOpen}</td>
                                        <td className="border border-slate-900 p-1 text-center">{srSummaryOpenPct}%</td>
                                    </tr>
                                    <tr>
                                        <td className="border border-slate-900 p-1 text-center">2</td>
                                        <td className="border border-slate-900 p-1 text-left">CLOSED</td>
                                        <td className="border border-slate-900 p-1 text-center">{srSummaryClose}</td>
                                        <td className="border border-slate-900 p-1 text-center">{srSummaryClosePct}%</td>
                                    </tr>
                                    <tr className="bg-slate-100 font-bold">
                                        <td className="border border-slate-900 p-1 text-center">3</td>
                                        <td className="border border-slate-900 p-1 text-left">SR TERBIT BULAN INI</td>
                                        <td className="border border-slate-900 p-1 text-center">{srSummaryTotal}</td>
                                        <td className="border border-slate-900 p-1 text-center">100%</td>
                                    </tr>
                                </tbody>
                            </table>

                            {/* Chart 1: SR Status Horizontal Bar Chart */}
                            <div className="mb-4 rounded border border-slate-300 bg-white p-3 text-center">
                                <div className="mb-2 text-center text-xs font-bold tracking-wider text-slate-600">SERVICE REQUEST STATUS</div>
                                <svg viewBox="0 0 540 120" className="mx-auto block h-28 w-full font-sans">
                                    {c1Ticks.map((t) => {
                                        const tx = 65 + (t / c1Max) * 445;
                                        return (
                                            <g key={t}>
                                                <line x1={tx} y1={10} x2={tx} y2={85} stroke="#e5e7eb" strokeDasharray="2,2" strokeWidth={0.8} />
                                                <text x={tx} y={99} textAnchor="middle" fontSize={8} fill="#555">{t}</text>
                                            </g>
                                        );
                                    })}
                                    <line x1={65} y1={10} x2={65} y2={85} stroke="#bbb" strokeWidth={1} />
                                    <line x1={65} y1={85} x2={510} y2={85} stroke="#bbb" strokeWidth={1} />
                                    {/* CLOSED */}
                                    <text x={57} y={23} textAnchor="end" fontSize={8} fill="#333">CLOSED</text>
                                    {c1Max > 0 && srSummaryClose > 0 && (
                                        <>
                                            <rect x={65} y={12} width={(srSummaryClose / c1Max) * 445} height={16} fill="#62b0f4" />
                                            <text x={65 + (srSummaryClose / c1Max) * 445 + 5} y={24} fontSize={8} fontWeight="bold" fill="#333">{srSummaryClose}</text>
                                        </>
                                    )}
                                    {/* OPEN */}
                                    <text x={57} y={56} textAnchor="end" fontSize={8} fill="#333">OPEN</text>
                                    {c1Max > 0 && srSummaryOpen > 0 && (
                                        <>
                                            <rect x={65} y={45} width={(srSummaryOpen / c1Max) * 445} height={16} fill="#62b0f4" />
                                            <text x={65 + (srSummaryOpen / c1Max) * 445 + 5} y={57} fontSize={8} fontWeight="bold" fill="#333">{srSummaryOpen}</text>
                                        </>
                                    )}
                                </svg>
                            </div>

                            {/* Chart 2: Top 5 Frequency SR Unit */}
                            <div className="mb-4 rounded border border-slate-300 bg-white p-3 text-center">
                                <div className="mb-2 text-center text-xs font-bold tracking-wider text-slate-800">TOP FIVE FREQUENCY SERVICE REQUEST (SR) UNIT</div>
                                <svg viewBox="0 0 540 180" className="mx-auto block h-40 w-full font-sans">
                                    <line x1={30} y1={90} x2={510} y2={90} stroke="#bbb" strokeWidth={1} />
                                    {top5SrAssets.length === 0 ? (
                                        <text x={270} y={55} fontSize={9} fill="#888" textAnchor="middle">Tidak ada data frekuensi Service Request</text>
                                    ) : (
                                        top5SrAssets.map((assetItem, idx) => {
                                            const c2Centers = [80, 175, 270, 365, 460];
                                            const cx = c2Centers[idx] ?? (80 + idx * 95);
                                            const freq = assetItem.freq;
                                            const barH = Math.max(14, freq * 22);
                                            const barY = 90 - barH;
                                            const lbl = assetItem.asset.length > 18 ? assetItem.asset.substring(0, 18) + '...' : assetItem.asset;
                                            return (
                                                <g key={assetItem.asset + idx}>
                                                    <line x1={cx - 33} y1={12} x2={cx - 33} y2={90} stroke="#f0f0f0" strokeWidth={0.8} />
                                                    <line x1={cx + 33} y1={12} x2={cx + 33} y2={90} stroke="#f0f0f0" strokeWidth={0.8} />
                                                    <rect x={cx - 23} y={barY} width={46} height={barH} fill="#62b0f4" />
                                                    <text x={cx} y={barY + barH / 2 + 4} fill="#fff" fontSize={10} fontWeight="bold" textAnchor="middle">{freq}</text>
                                                    <text x={cx} y={98} fill="#222" fontSize={8} fontWeight="bold" textAnchor="end" transform={`rotate(-45 ${cx} 98)`}>{lbl}</text>
                                                </g>
                                            );
                                        })
                                    )}
                                </svg>
                            </div>

                            {/* Table: Keterangan Top 5 Assets */}
                            <div className="mb-1 text-[11px] font-bold text-slate-800">KETERANGAN</div>
                            <table className="mb-4 w-full border-collapse text-[11px]">
                                <thead>
                                    <tr className="border-b font-bold text-slate-900">
                                        <th className="w-56 p-1 text-left">Asset</th>
                                        <th className="w-16 p-1 text-center">Freq</th>
                                        <th className="p-1 text-left">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {top5SrAssets.length === 0 ? (
                                        <tr><td colSpan={3} className="p-2 text-center italic text-slate-400">Tidak ada data Service Request pada periode ini.</td></tr>
                                    ) : (
                                        top5SrAssets.map((row) => (
                                            <tr key={row.asset + row.description} className="border-b border-slate-100">
                                                <td className="p-1 font-medium text-slate-800">{row.asset}</td>
                                                <td className="p-1 text-center font-bold text-slate-700">{row.freq}</td>
                                                <td className="p-1 text-slate-600">{row.description}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* 9. MAINTENANCE SUMMARY */}
                        {data.maintenance_summary && (
                            <>
                                <SectionTitle n={9} title="Maintenance Summary" id="sec-maintenance-summary" breakBefore />

                                <h3 className="mb-1 mt-3 text-[13px] font-semibold text-slate-800">9.1 Rekapitulasi WO Terbit dan Complete</h3>
                                <table className="mb-4 w-full border-collapse text-[10px] text-center">
                                    <thead>
                                        <tr className="bg-slate-100">
                                            <th className="border px-1 py-1">BULAN</th>
                                            <th className="border px-1 py-1">TERBIT</th>
                                            <th className="border px-1 py-1">JAN</th>
                                            <th className="border px-1 py-1">FEB</th>
                                            <th className="border px-1 py-1">MAR</th>
                                            <th className="border px-1 py-1">APR</th>
                                            <th className="border px-1 py-1">MEI</th>
                                            <th className="border px-1 py-1">JUN</th>
                                            <th className="border px-1 py-1">JUL</th>
                                            <th className="border px-1 py-1">AUG</th>
                                            <th className="border px-1 py-1">SEP</th>
                                            <th className="border px-1 py-1">OKT</th>
                                            <th className="border px-1 py-1">NOV</th>
                                            <th className="border px-1 py-1">DES</th>
                                            <th className="border px-1 py-1">OPEN</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.maintenance_summary.rekap_terbit_complete.rows.map((r) => (
                                            <tr key={r.bulan}>
                                                <td className="border px-1 py-1 font-semibold">{r.bulan}</td>
                                                <td className="border px-1 py-1">{r.terbit}</td>
                                                {Array.from({ length: 12 }, (_, i) => i + 1).map((m) => (
                                                    <td key={m} className={`border px-1 py-1 ${r.complete[m] ? 'font-bold' : 'text-slate-400'}`}>
                                                        {r.complete[m] ?? 0}
                                                    </td>
                                                ))}
                                                <td className="border px-1 py-1 font-bold text-blue-600">{r.open}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>

                                <h3 className="mb-1 mt-3 text-[13px] font-semibold text-slate-800">9.2 Rekapitulasi Status WO</h3>
                                <table className="mb-4 w-full border-collapse text-[10px] text-center">
                                    <thead>
                                        <tr className="bg-slate-100">
                                            <th className="border px-1 py-1 text-left">STATUS</th>
                                            {data.maintenance_summary.rekap_status.columns.map((c) => (
                                                <th key={c} className="border px-1 py-1">{c}</th>
                                            ))}
                                            <th className="border px-1 py-1">TOTAL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.maintenance_summary.rekap_status.rows.map((sr) => (
                                            <tr key={sr.status}>
                                                <td className="border px-1 py-1 text-left font-semibold">{sr.status}</td>
                                                {data.maintenance_summary!.rekap_status.columns.map((c) => (
                                                    <td key={c} className={`border px-1 py-1 ${sr.values[c] ? 'font-bold' : 'text-slate-400'}`}>
                                                        {sr.values[c] ?? 0}
                                                    </td>
                                                ))}
                                                <td className="border px-1 py-1 font-bold text-blue-600">{sr.total}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>

                                <h3 className="mb-1 mt-3 text-[13px] font-semibold text-slate-800">9.3 Penyelesaian Work Order Task</h3>
                                <table className="mb-4 w-full border-collapse text-[10px]">
                                    <thead>
                                        <tr className="bg-slate-100 text-center">
                                            <th className="border px-1 py-1" rowSpan={2}>NO</th>
                                            <th className="border px-2 py-1 text-left" rowSpan={2}>MAINTENANCE TYPE</th>
                                            <th className="border px-1 py-1" colSpan={2}>RENCANA</th>
                                            <th className="border px-1 py-1" colSpan={2}>REALISASI</th>
                                            <th className="border px-1 py-1" colSpan={2}>BIAYA PEMELIHARAAN</th>
                                        </tr>
                                        <tr className="bg-slate-100 text-center">
                                            <th className="border px-1 py-1">FREQ</th>
                                            <th className="border px-1 py-1">%</th>
                                            <th className="border px-1 py-1">FREQ</th>
                                            <th className="border px-1 py-1">%</th>
                                            <th className="border px-1 py-1">Material</th>
                                            <th className="border px-1 py-1">Jasa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.maintenance_summary.tasks.rows.map((tr) => (
                                            <tr key={tr.no}>
                                                <td className="border px-1 py-1 text-center">{tr.no}</td>
                                                <td className="border px-2 py-1 font-medium">{tr.name}</td>
                                                <td className="border px-1 py-1 text-center">{tr.rencana_freq || ''}</td>
                                                <td className="border px-1 py-1 text-center">{tr.rencana_pct}%</td>
                                                <td className="border px-1 py-1 text-center">{tr.realisasi_freq || ''}</td>
                                                <td className="border px-1 py-1 text-center">{tr.realisasi_pct}%</td>
                                                <td className="border px-1 py-1 text-right">{tr.material_cost > 0 ? rupiah(tr.material_cost) : '-'}</td>
                                                <td className="border px-1 py-1 text-right">{tr.service_cost > 0 ? rupiah(tr.service_cost) : '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </>
                        )}

                        {/* 10. ISI LAPORAN */}
                        <SectionTitle n={10} title="Isi Laporan" id="sec-body" breakBefore />
                        <p className="mb-3 text-justify leading-relaxed">
                            Bagian ini memuat rincian pelaksanaan pemeliharaan {data.unit.name} periode {data.period.label},
                            meliputi ringkasan Service Request, rencana versus realisasi pemeliharaan, dan log kegiatan HARMES.
                        </p>

                        <h3 className="mb-1 mt-3 text-[13px] font-semibold text-slate-800">10.1 Ringkasan Service Request</h3>
                        <table className="mb-4 w-full border-collapse text-[11px]">
                            <tbody>
                                <tr>
                                    <Stat label="Total SR" value={String(data.sr_summary.total)} />
                                    <Stat label="Open" value={String(data.sr_summary.open)} />
                                    <Stat label="Close" value={String(data.sr_summary.close)} />
                                    <td className="border px-2 py-1">
                                        <div className="text-[10px] text-slate-500">Per Kategori</div>
                                        <div>{data.sr_summary.by_category.map((c) => `${c.category}: ${c.count}`).join(' · ') || '—'}</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <h3 className="mb-1 mt-3 text-[13px] font-semibold text-slate-800">10.2 Rencana vs Realisasi</h3>
                        {data.schedules.length === 0 ? (
                            <p className="mb-4 text-slate-500">Belum ada jadwal.</p>
                        ) : (
                            data.schedules.map((scope) => (
                                <div key={scope.scope} className="mb-3">
                                    <p className="font-semibold">{scope.scope}</p>
                                    <table className="w-full border-collapse text-[10px]">
                                        <thead>
                                            <tr className="bg-slate-100">
                                                <th className="border px-1 py-1 text-left">Mesin</th>
                                                <th className="border px-1 py-1 text-left">Rencana (tgl:kode)</th>
                                                <th className="border px-1 py-1 text-left">Realisasi (tgl:kode)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {scope.rows.map((r) => (
                                                <tr key={r.engine}>
                                                    <td className="border px-1 py-1">{r.engine}</td>
                                                    <td className="border px-1 py-1 text-left">{dayMap(r.rencana)}</td>
                                                    <td className="border px-1 py-1 text-left">{dayMap(r.realisasi)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ))
                        )}

                        <h3 className="mb-1 mt-3 text-[13px] font-semibold text-slate-800">10.3 Log Kegiatan HARMES</h3>
                        {data.activities.length === 0 ? (
                            <p className="mb-4 text-slate-500">Belum ada log kegiatan.</p>
                        ) : (
                            <table className="mb-4 w-full border-collapse text-[10px]">
                                <thead>
                                    <tr className="bg-slate-100">
                                        <th className="border px-1 py-1">Tanggal</th>
                                        <th className="border px-1 py-1">Mesin</th>
                                        <th className="border px-1 py-1">Jenis</th>
                                        <th className="border px-1 py-1 text-left">Uraian Kegiatan</th>
                                        <th className="border px-1 py-1 text-left">Material</th>
                                        <th className="border px-1 py-1">Hasil</th>
                                        <th className="border px-1 py-1">No. WO/SR</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.activities.map((a, index) => (
                                        <tr key={index}>
                                            <td className="border px-1 py-1">{a.date ?? '—'}</td>
                                            <td className="border px-1 py-1">{a.engine ?? '—'}</td>
                                            <td className="border px-1 py-1">{a.type ?? '—'}</td>
                                            <td className="border px-1 py-1 text-left">
                                                {a.tasks.length > 0 ? (
                                                    <ul className="ml-3 list-disc">
                                                        {a.tasks.map((t, i) => (
                                                            <li key={i}>{t}</li>
                                                        ))}
                                                    </ul>
                                                ) : (
                                                    a.keterangan ?? '—'
                                                )}
                                            </td>
                                            <td className="border px-1 py-1 text-left">
                                                {a.materials.length > 0
                                                    ? a.materials
                                                          .map((m) => `${m.name} (${m.quantity ?? ''}${m.unit_of_measure ?? ''})`)
                                                          .join(', ')
                                                    : '—'}
                                            </td>
                                            <td className="border px-1 py-1">{a.work_result ?? '—'}</td>
                                            <td className="border px-1 py-1">{a.no_wo ?? a.no_sr ?? '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}

                        {/* 11. WORK ORDER SUMMARY (FIX) */}
                        <SectionTitle n={11} title="Work Order Summary (Fix)" id="sec-wo-summary" breakBefore />
                        <table className="mb-4 w-full border-collapse text-[11px]">
                            <tbody>
                                <tr>
                                    <Stat label="Total WO" value={String(data.wo_summary.total)} />
                                    <Stat label="Complete (Fix)" value={String(data.wo_summary.complete)} />
                                    <Stat label="Open" value={String(data.wo_summary.open)} />
                                    <Stat label="% Complete" value={`${data.wo_summary.percent}%`} />
                                </tr>
                            </tbody>
                        </table>

                        {/* 12. AKUMULASI BIAYA PEMELIHARAAN */}
                        <SectionTitle n={12} title="Akumulasi Biaya Pemeliharaan" id="sec-cost" />
                        <table className="mb-2 w-full border-collapse text-[11px]">
                            <tbody>
                                <tr>
                                    <Stat label="Jasa (WO)" value={rupiah(data.cost.auto_service)} />
                                    <Stat label="Material (WO)" value={rupiah(data.cost.auto_material)} />
                                    <Stat label="Total Otomatis" value={rupiah(data.cost.auto_total)} />
                                    <Stat label={`Efektif (${data.cost.source})`} value={rupiah(data.cost.effective_total)} />
                                    <Stat label="Akumulasi YTD" value={rupiah(data.cost.ytd)} />
                                </tr>
                            </tbody>
                        </table>
                        <p className="mb-4 text-[10px] text-slate-500">
                            Sumber biaya: {data.cost.source === 'manual' ? 'input manual' : 'akumulasi otomatis dari Work Order'}. YTD =
                            akumulasi Januari s.d. bulan laporan.
                        </p>

                        {/* 13. REKAPITULASI WORK ORDER TASK */}
                        <div className="break-before" id="sec-recap">
                            {/* Kop Standard PLN NP */}
                            <div className="mb-3 border border-slate-900 font-sans text-xs">
                                <div className="flex border-b border-slate-900">
                                    <div className="flex w-44 items-center justify-center border-r border-slate-900 p-2">
                                        <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="h-8" />
                                    </div>
                                    <div className="flex flex-1 flex-col items-center justify-center p-2 text-center">
                                        <span className="text-sm font-bold tracking-wider">PLN NUSANTARA POWER</span>
                                        <span className="text-xs font-bold">UP KENDARI</span>
                                    </div>
                                    <div className="flex w-24 items-center justify-center border-l border-slate-900 p-2">
                                        <img src="/logo/k3.png" alt="K3" className="h-10" />
                                    </div>
                                </div>
                                <div className="border-b border-slate-900 bg-white py-1 text-center text-[11px] font-bold tracking-wider">
                                    INTEGRATED MANAGEMENT SYSTEM
                                </div>
                                <div className="grid grid-cols-12">
                                    <div className="col-span-8 flex flex-col items-center justify-center border-r border-slate-900 bg-[#7fa9d8] p-2 text-center text-xs font-bold leading-tight text-slate-900">
                                        <span>REKAPITULASI</span>
                                        <span>WO TASK PREVENTIVE, PROACTIVE, PREDICTIVE,</span>
                                        <span>CORRECTIVE, EMERGENCY, ECP</span>
                                    </div>
                                    <div className="col-span-4 text-[10px]">
                                        <div className="flex border-b border-slate-900 px-2 py-1">
                                            <span className="w-20 font-medium">No. Dokumen</span>
                                            <span className="font-semibold">: FMKD-314-10.3.3-A11</span>
                                        </div>
                                        <div className="flex border-b border-slate-900 px-2 py-1">
                                            <span className="w-20 font-medium">Revisi</span>
                                            <span className="font-semibold">: 01</span>
                                        </div>
                                        <div className="flex px-2 py-1">
                                            <span className="w-20 font-medium">Tanggal</span>
                                            <span className="font-semibold">: {data.period.label}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="mb-1 text-[11px] font-medium italic text-slate-700">Rekap Task WO</div>

                            {data.rekap_task_wo ? (
                                <table className="mb-2 w-full border-collapse border border-slate-900 text-[11px]">
                                    <thead>
                                        <tr className="bg-white text-center font-bold text-slate-900">
                                            <th rowSpan={2} className="w-10 border border-slate-900 px-2 py-1">NO</th>
                                            <th rowSpan={2} className="border border-slate-900 px-3 py-1 text-center">URAIAN</th>
                                            <th colSpan={2} className="w-48 border border-slate-900 px-2 py-1">
                                                <div>RENCANA</div>
                                                <div className="text-[9px] font-normal">(Base On Schedule Finsihed)</div>
                                            </th>
                                            <th colSpan={2} className="w-52 border border-slate-900 px-2 py-1">
                                                <div>REALISASI</div>
                                                <div className="text-[9px] font-normal">(Base On Sched Finish Status Comp and Close)</div>
                                            </th>
                                        </tr>
                                        <tr className="bg-white text-center font-bold text-slate-900">
                                            <th className="w-24 border border-slate-900 px-2 py-1">[Freq]</th>
                                            <th className="w-24 border border-slate-900 px-2 py-1">%</th>
                                            <th className="w-24 border border-slate-900 px-2 py-1">FREKWENSI</th>
                                            <th className="w-28 border border-slate-900 px-2 py-1">% Compliance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.rekap_task_wo.categories.map((cat) => (
                                            <Fragment key={cat.no}>
                                                <tr className="bg-[#595959] font-bold text-white">
                                                    <td className="border border-slate-900 px-2 py-1 text-center">{cat.no}</td>
                                                    <td className="border border-slate-900 px-2 py-1 text-left">{cat.title}</td>
                                                    <td className="border border-slate-900 px-2 py-1 text-center">{cat.rencana_freq}</td>
                                                    <td className="border border-slate-900 px-2 py-1 text-center">{cat.rencana_pct > 0 ? `${cat.rencana_pct.toString().replace('.', ',')}%` : '0%'}</td>
                                                    <td className="border border-slate-900 px-2 py-1 text-center">{cat.realisasi_freq}</td>
                                                    <td className="border border-slate-900 px-2 py-1 text-center">{cat.realisasi_pct > 0 ? `${cat.realisasi_pct.toString().replace('.', ',')}%` : '0%'}</td>
                                                </tr>
                                                {cat.disciplines.map((d, dIdx) => (
                                                    <tr key={dIdx} className="bg-white hover:bg-slate-50">
                                                        <td className="border border-slate-900 px-2 py-0.5 text-center"></td>
                                                        <td className="border border-slate-900 px-2 py-0.5 pl-6 text-left">{d.name}</td>
                                                        <td className="border border-slate-900 px-2 py-0.5 text-center">{d.rencana_freq}</td>
                                                        <td className="border border-slate-900 px-2 py-0.5 text-center">{d.rencana_pct > 0 ? `${d.rencana_pct.toString().replace('.', ',')}%` : '0%'}</td>
                                                        <td className="border border-slate-900 px-2 py-0.5 text-center">{d.realisasi_freq}</td>
                                                        <td className="border border-slate-900 px-2 py-0.5 text-center">{d.realisasi_pct > 0 ? `${d.realisasi_pct.toString().replace('.', ',')}%` : '0%'}</td>
                                                    </tr>
                                                ))}
                                            </Fragment>
                                        ))}
                                        <tr className="bg-slate-100 font-bold">
                                            <td colSpan={2} className="border border-slate-900 px-3 py-1.5 text-center">TOTAL</td>
                                            <td className="border border-slate-900 px-2 py-1.5 text-center">{data.rekap_task_wo.total_rencana_freq}</td>
                                            <td className="border border-slate-900 px-2 py-1.5 text-center">{data.rekap_task_wo.total_rencana_pct > 0 ? `${data.rekap_task_wo.total_rencana_pct.toString().replace('.', ',')}%` : '0%'}</td>
                                            <td className="border border-slate-900 px-2 py-1.5 text-center">{data.rekap_task_wo.total_realisasi_freq}</td>
                                            <td className="border border-slate-900 px-2 py-1.5 text-center">{data.rekap_task_wo.total_realisasi_pct > 0 ? `${data.rekap_task_wo.total_realisasi_pct.toString().replace('.', ',')}%` : '0%'}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            ) : (
                                <table className="mb-2 w-full border-collapse text-[11px]">
                                    <thead>
                                        <tr className="bg-slate-100">
                                            <th className="border px-2 py-1 text-left">Jenis Work Order</th>
                                            <th className="border px-2 py-1">Jumlah WO</th>
                                            <th className="border px-2 py-1">Porsi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.wo_by_type.map((g) => (
                                            <tr key={g.type}>
                                                <td className="border px-2 py-1 text-left">{g.type}</td>
                                                <td className="border px-2 py-1 text-center">{g.rows.length}</td>
                                                <td className="border px-2 py-1 text-center">
                                                    {data.wo_summary.total > 0
                                                        ? `${Math.round((g.rows.length / data.wo_summary.total) * 100)}%`
                                                        : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}

                            <p className="mb-4 text-[10px] text-slate-500">
                                Total uraian task (dari log kegiatan HARMES): <span className="font-semibold">{totalTasks}</span> item pada{' '}
                                {data.activities.length} kegiatan.
                            </p>
                        </div>

                        {/* 14. WO PM (WO PREVENTIVE MAINTANANCE - FMKD-314-10.3.3-A12) */}
                        <div className="break-before" id="sec-wo-pm">
                            {/* Kop Standard PLN NP */}
                            <div className="mb-3 border border-slate-900 font-sans text-xs">
                                <div className="flex border-b border-slate-900">
                                    <div className="flex w-44 items-center justify-center border-r border-slate-900 p-2">
                                        <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="h-8" />
                                    </div>
                                    <div className="flex flex-1 flex-col items-center justify-center p-2 text-center">
                                        <span className="text-sm font-bold tracking-wider">PLN NUSANTARA POWER</span>
                                        <span className="text-xs font-bold">UP KENDARI</span>
                                    </div>
                                    <div className="flex w-24 items-center justify-center border-l border-slate-900 p-2">
                                        <img src="/logo/k3.png" alt="K3" className="h-10" />
                                    </div>
                                </div>
                                <div className="border-b border-slate-900 bg-white py-1 text-center text-[11px] font-bold tracking-wider">
                                    INTEGRATED MANAGEMENT SYSTEM
                                </div>
                                <div className="grid grid-cols-12">
                                    <div className="col-span-8 flex flex-col items-center justify-center border-r border-slate-900 bg-[#7fa9d8] p-2 text-center text-xs font-bold leading-tight text-slate-900">
                                        <span>WO PREVENTIVE MAINTANANCE</span>
                                    </div>
                                    <div className="col-span-4 text-[10px]">
                                        <div className="flex border-b border-slate-900 px-2 py-1">
                                            <span className="w-20 font-medium">No. Dokumen</span>
                                            <span className="font-semibold">: FMKD-314-10.3.3-A12</span>
                                        </div>
                                        <div className="flex border-b border-slate-900 px-2 py-1">
                                            <span className="w-20 font-medium">Revisi</span>
                                            <span className="font-semibold">: 01</span>
                                        </div>
                                        <div className="flex px-2 py-1">
                                            <span className="w-20 font-medium">Tanggal</span>
                                            <span className="font-semibold">: {data.period.label}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <table className="mb-4 w-full border-collapse border border-slate-900 text-[10px]">
                                <thead>
                                    <tr className="bg-white text-center font-bold text-slate-900">
                                        <th className="w-10 border border-slate-900 px-2 py-1">NO</th>
                                        <th className="w-20 border border-slate-900 px-2 py-1">WONUM</th>
                                        <th className="border border-slate-900 px-3 py-1 text-center">DESCRIPTION</th>
                                        <th className="w-24 border border-slate-900 px-2 py-1">REPORT DATE</th>
                                        <th className="w-24 border border-slate-900 px-2 py-1">SCHED START</th>
                                        <th className="w-24 border border-slate-900 px-2 py-1">SCHED FINISH</th>
                                        <th className="w-16 border border-slate-900 px-2 py-1">STATUS</th>
                                        <th className="w-24 border border-slate-900 px-2 py-1">WORK GROUP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {woPm.length > 0 ? (
                                        woPm.map((r, idx) => (
                                            <tr key={idx} className="hover:bg-slate-50">
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{idx + 1}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center font-semibold">{r.wonum}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-left">{r.description || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.report_date || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.sched_start || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.sched_finish || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.status || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.work_group || '—'}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={8} className="border border-slate-900 p-4 text-center italic text-slate-500">
                                                Tidak ada data Work Order PM pada periode ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* 15. WO PDM (WO PREDICTIVE MAINTANANCE - FMKD-314-10.3.3-A13) */}
                        <div className="break-before" id="sec-wo-pdm">
                            {/* Kop Standard PLN NP */}
                            <div className="mb-3 border border-slate-900 font-sans text-xs">
                                <div className="flex border-b border-slate-900">
                                    <div className="flex w-44 items-center justify-center border-r border-slate-900 p-2">
                                        <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="h-8" />
                                    </div>
                                    <div className="flex flex-1 flex-col items-center justify-center p-2 text-center">
                                        <span className="text-sm font-bold tracking-wider">PLN NUSANTARA POWER</span>
                                        <span className="text-xs font-bold">UP KENDARI</span>
                                    </div>
                                    <div className="flex w-24 items-center justify-center border-l border-slate-900 p-2">
                                        <img src="/logo/k3.png" alt="K3" className="h-10" />
                                    </div>
                                </div>
                                <div className="border-b border-slate-900 bg-white py-1 text-center text-[11px] font-bold tracking-wider">
                                    INTEGRATED MANAGEMENT SYSTEM
                                </div>
                                <div className="grid grid-cols-12">
                                    <div className="col-span-8 flex flex-col items-center justify-center border-r border-slate-900 bg-[#7fa9d8] p-2 text-center text-xs font-bold leading-tight text-slate-900">
                                        <span>WO PREDICTIVE MAINTANANCE</span>
                                    </div>
                                    <div className="col-span-4 text-[10px]">
                                        <div className="flex border-b border-slate-900 px-2 py-1">
                                            <span className="w-20 font-medium">No. Dokumen</span>
                                            <span className="font-semibold">: FMKD-314-10.3.3-A13</span>
                                        </div>
                                        <div className="flex border-b border-slate-900 px-2 py-1">
                                            <span className="w-20 font-medium">Revisi</span>
                                            <span className="font-semibold">: 01</span>
                                        </div>
                                        <div className="flex px-2 py-1">
                                            <span className="w-20 font-medium">Tanggal</span>
                                            <span className="font-semibold">: {data.period.label}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="mb-1 text-[11px] font-bold text-slate-900">WO PdM YANG TERBIT BULAN INI</div>

                            <table className="mb-3 w-full border-collapse border border-slate-900 text-[10px]">
                                <thead>
                                    <tr className="bg-white text-center font-bold text-slate-900">
                                        <th className="w-10 border border-slate-900 px-2 py-1">NO</th>
                                        <th className="w-20 border border-slate-900 px-2 py-1">WONUM</th>
                                        <th className="border border-slate-900 px-3 py-1 text-center">DESCRIPTION</th>
                                        <th className="w-24 border border-slate-900 px-2 py-1">REPORT DATE</th>
                                        <th className="w-24 border border-slate-900 px-2 py-1">SCHED START</th>
                                        <th className="w-24 border border-slate-900 px-2 py-1">SCHED FINISH</th>
                                        <th className="w-16 border border-slate-900 px-2 py-1">STATUS</th>
                                        <th className="w-24 border border-slate-900 px-2 py-1">WORK GROUP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {woPdm.length > 0 ? (
                                        woPdm.map((r, idx) => (
                                            <tr key={idx} className="hover:bg-slate-50">
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{idx + 1}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center font-semibold">{r.wonum}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-left">{r.description || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.report_date || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.sched_start || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.sched_finish || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.status || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.work_group || '—'}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        Array.from({ length: 16 }).map((_, idx) => (
                                            <tr key={idx} className="h-6">
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{idx + 1}</td>
                                                <td className="border border-slate-900 px-2 py-0.5"></td>
                                                <td className="border border-slate-900 px-2 py-0.5"></td>
                                                <td className="border border-slate-900 px-2 py-0.5"></td>
                                                <td className="border border-slate-900 px-2 py-0.5"></td>
                                                <td className="border border-slate-900 px-2 py-0.5"></td>
                                                <td className="border border-slate-900 px-2 py-0.5"></td>
                                                <td className="border border-slate-900 px-2 py-0.5"></td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>

                            <div className="mb-4 text-[10px] leading-relaxed text-slate-700">
                                <div className="font-bold italic">Keterangan:</div>
                                <div className="italic"><span className="font-semibold">Inprogres</span> : WO dalam proses pelaksanaan pekerjaan oleh eksekutor</div>
                                <div className="italic"><span className="font-semibold">Close</span> : Scope pekerjaan WO sudah diselesaikan, dan proses transaksi kebutuhan material/spare part/tools oleh Warehouse telah selesai</div>
                                <div className="italic"><span className="font-semibold">Inplanning</span> : WO dalam proses perencanaan</div>
                                <div className="italic"><span className="font-semibold">Proses SCM</span> : WO dalam proses pada stream Supply Chain Management (SCM)</div>
                                <div className="italic"><span className="font-semibold">Waiting Plant Condition</span> : WO menunggu kondisi unit atau peralatan</div>
                            </div>
                        </div>

                        {/* 16. WO CM (WO CORRECTIVE MAINTANANCE - FMKD-314-10.3.3-A14) */}
                        <div className="break-before" id="sec-wo-cm">
                            {/* Kop Standard PLN NP */}
                            <div className="mb-3 border border-slate-900 font-sans text-xs">
                                <div className="flex border-b border-slate-900">
                                    <div className="flex w-44 items-center justify-center border-r border-slate-900 p-2">
                                        <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="h-8" />
                                    </div>
                                    <div className="flex flex-1 flex-col items-center justify-center p-2 text-center">
                                        <span className="text-sm font-bold tracking-wider">PLN NUSANTARA POWER</span>
                                        <span className="text-xs font-bold">UP KENDARI</span>
                                    </div>
                                    <div className="flex w-24 items-center justify-center border-l border-slate-900 p-2">
                                        <img src="/logo/k3.png" alt="K3" className="h-10" />
                                    </div>
                                </div>
                                <div className="border-b border-slate-900 bg-white py-1 text-center text-[11px] font-bold tracking-wider">
                                    INTEGRATED MANAGEMENT SYSTEM
                                </div>
                                <div className="grid grid-cols-12">
                                    <div className="col-span-8 flex flex-col items-center justify-center border-r border-slate-900 bg-[#7fa9d8] p-2 text-center text-xs font-bold leading-tight text-slate-900">
                                        <span>WO CORRECTIVE MAINTANANCE</span>
                                    </div>
                                    <div className="col-span-4 text-[10px]">
                                        <div className="flex border-b border-slate-900 px-2 py-1">
                                            <span className="w-20 font-medium">No. Dokumen</span>
                                            <span className="font-semibold">: FMKD-314-10.3.3-A14</span>
                                        </div>
                                        <div className="flex border-b border-slate-900 px-2 py-1">
                                            <span className="w-20 font-medium">Revisi</span>
                                            <span className="font-semibold">: 01</span>
                                        </div>
                                        <div className="flex px-2 py-1">
                                            <span className="w-20 font-medium">Tanggal</span>
                                            <span className="font-semibold">: {data.period.label}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <table className="mb-3 w-full border-collapse border border-slate-900 text-[10px]">
                                <thead>
                                    <tr className="bg-white text-center font-bold text-slate-900">
                                        <th className="w-10 border border-slate-900 px-2 py-1">NO</th>
                                        <th className="w-20 border border-slate-900 px-2 py-1">WONUM</th>
                                        <th className="border border-slate-900 px-3 py-1 text-center">DESCRIPTION</th>
                                        <th className="w-24 border border-slate-900 px-2 py-1">REPORT DATE</th>
                                        <th className="w-24 border border-slate-900 px-2 py-1">SCHED START</th>
                                        <th className="w-24 border border-slate-900 px-2 py-1">SCHED FINISH</th>
                                        <th className="w-16 border border-slate-900 px-2 py-1">STATUS</th>
                                        <th className="w-24 border border-slate-900 px-2 py-1">WORK GROUP</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {woCm.length > 0 ? (
                                        woCm.map((r, idx) => (
                                            <tr key={idx} className="hover:bg-slate-50">
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{idx + 1}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center font-semibold">{r.wonum}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-left">{r.description || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.report_date || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.sched_start || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.sched_finish || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.status || '—'}</td>
                                                <td className="border border-slate-900 px-2 py-0.5 text-center">{r.work_group || '—'}</td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={8} className="border border-slate-900 p-4 text-center italic text-slate-500">
                                                Tidak ada data Work Order CM pada periode ini.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>

                            {woCm.filter((r) => r.status && r.status.toUpperCase() !== 'CLOSE').length > 0 && (
                                <div className="mb-4 text-[10px] leading-relaxed text-slate-700">
                                    {woCm
                                        .filter((r) => r.status && r.status.toUpperCase() !== 'CLOSE')
                                        .map((r) => (
                                            <div key={r.wonum}>- {r.wonum} : {r.status} {r.description ? `(${r.description})` : ''}</div>
                                        ))}
                                </div>
                            )}
                        </div>

                        {/* 17. WO ENJI */}
                        <SectionTitle n={17} title="Work Order ENJI (Engineering)" id="sec-wo-enji" />
                        <WoTable rows={woEnji} />

                        {/* 18. WO WAITING SHUTDOWN */}
                        <SectionTitle n={18} title="Work Order Waiting Shutdown" id="sec-wait-sd" breakBefore />
                        <WaitingTable rows={waitingShutdown} />

                        {/* 19. WO WAITING MATERIAL & JASA */}
                        <SectionTitle n={19} title="Work Order Waiting Material & Jasa" id="sec-wait-mj" />
                        <WaitingTable rows={waitingMaterialJasa} />

                        {/* 20. LAMPIRAN */}
                        <SectionTitle n={20} title="Lampiran" id="sec-attachments" breakBefore />
                        {data.attachments.length === 0 ? (
                            <p className="text-slate-500">Belum ada lampiran foto.</p>
                        ) : (
                            <div className="grid grid-cols-2 gap-3">
                                {data.attachments.map((a, index) => (
                                    <figure key={index} className="break-inside-avoid border p-2">
                                        <img src={a.url} alt={a.title} className="mb-1 max-h-64 w-full object-contain" />
                                        <figcaption className="text-[10px]">
                                            <span className="font-semibold">{a.title}</span>
                                            {a.engine ? ` · ${a.engine}` : ''}
                                            {a.taken_date ? ` · ${a.taken_date}` : ''}
                                            {a.caption ? <div className="text-slate-600">{a.caption}</div> : null}
                                        </figcaption>
                                    </figure>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
