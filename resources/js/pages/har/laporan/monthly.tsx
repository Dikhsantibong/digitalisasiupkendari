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
    sr_summary: { total: number; open: number; close: number; by_category: { category: string; count: number }[] };
    wo_summary: { total: number; complete: number; open: number; percent: number };
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
    'Executive Summary',
    'Daftar Isi',
    'Istilah dan Definisi',
    'Isi Laporan',
    'Work Order Summary (Fix)',
    'Akumulasi Biaya Pemeliharaan',
    'Rekapitulasi Work Order Task',
    'Work Order PM (Preventive Maintenance)',
    'Work Order PdM (Predictive Maintenance)',
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
    const woEnji = groupFor(data, 'ENJI');
    const waitingShutdown = waitingFor(data, 'shutdown');
    const waitingMaterialJasa = waitingFor(data, 'material', 'jasa');
    const waitingCount = data.wo_waiting.reduce((sum, g) => sum + g.rows.length, 0);
    const totalTasks = data.activities.reduce((sum, a) => sum + a.tasks.length, 0);

    return (
        <>
            <Head title={`Laporan HAR — ${data.unit.name}`} />
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
                        <div className="report-cover mb-8 flex min-h-[1000px] flex-col items-center justify-between border-4 border-double border-slate-800 p-10 text-center">
                            <div className="flex flex-col items-center gap-3">
                                <img src="/logo/sidebar-logo.png" alt="PLN" className="h-16" />
                                <p className="text-base font-semibold uppercase tracking-wide text-slate-800">PT PLN Nusantara Power</p>
                                <p className="text-xs uppercase tracking-wide text-slate-500">
                                    {data.unit.service_unit ?? 'Unit Pelaksana Pengendalian Pembangkitan Kendari'}
                                </p>
                            </div>

                            <div className="flex flex-col items-center gap-5">
                                <span className="h-1 w-28 rounded bg-slate-800" />
                                <h1 className="text-3xl font-bold uppercase leading-tight tracking-wide text-slate-900">
                                    Laporan Kinerja
                                    <br />
                                    Pemeliharaan
                                </h1>
                                <div className="rounded bg-slate-800 px-8 py-3 text-xl font-semibold uppercase tracking-wide text-white">
                                    {data.unit.name}
                                </div>
                                <p className="text-base text-slate-700">
                                    Periode <span className="font-semibold">{data.period.label}</span>
                                </p>
                                <span className="h-1 w-28 rounded bg-slate-800" />
                            </div>

                            <div className="flex flex-col items-center gap-1">
                                <p className="text-xl font-bold uppercase tracking-widest text-slate-900">UP Kendari</p>
                                <p className="text-[11px] uppercase tracking-wide text-slate-500">Unit Pelaksana Pengendalian Pembangkitan Kendari</p>
                            </div>
                        </div>

                        {/* 2. EXECUTIVE SUMMARY */}
                        <SectionTitle n={2} title="Executive Summary" id="sec-exec" />
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

                        {/* 3. DAFTAR ISI */}
                        <SectionTitle n={3} title="Daftar Isi" id="sec-toc" />
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

                        {/* 4. ISTILAH DAN DEFINISI */}
                        <SectionTitle n={4} title="Istilah dan Definisi" id="sec-glossary" />
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

                        {/* 5. ISI LAPORAN */}
                        <SectionTitle n={5} title="Isi Laporan" id="sec-body" breakBefore />
                        <p className="mb-3 text-justify leading-relaxed">
                            Bagian ini memuat rincian pelaksanaan pemeliharaan {data.unit.name} periode {data.period.label},
                            meliputi ringkasan Service Request, rencana versus realisasi pemeliharaan, dan log kegiatan HARMES.
                        </p>

                        <h3 className="mb-1 mt-3 text-[13px] font-semibold text-slate-800">5.1 Ringkasan Service Request</h3>
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

                        <h3 className="mb-1 mt-3 text-[13px] font-semibold text-slate-800">5.2 Rencana vs Realisasi</h3>
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

                        <h3 className="mb-1 mt-3 text-[13px] font-semibold text-slate-800">5.3 Log Kegiatan HARMES</h3>
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

                        {/* 6. WORK ORDER SUMMARY (FIX) */}
                        <SectionTitle n={6} title="Work Order Summary (Fix)" id="sec-wo-summary" breakBefore />
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

                        {/* 7. AKUMULASI BIAYA PEMELIHARAAN */}
                        <SectionTitle n={7} title="Akumulasi Biaya Pemeliharaan" id="sec-cost" />
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

                        {/* 8. REKAPITULASI WORK ORDER TASK */}
                        <SectionTitle n={8} title="Rekapitulasi Work Order Task" id="sec-recap" />
                        <table className="mb-2 w-full border-collapse text-[11px]">
                            <thead>
                                <tr className="bg-slate-100">
                                    <th className="border px-2 py-1 text-left">Jenis Work Order</th>
                                    <th className="border px-2 py-1">Jumlah WO</th>
                                    <th className="border px-2 py-1">Porsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.wo_by_type.length === 0 ? (
                                    <tr>
                                        <td className="border px-2 py-1 text-center text-slate-500" colSpan={3}>
                                            Tidak ada Work Order.
                                        </td>
                                    </tr>
                                ) : (
                                    data.wo_by_type.map((g) => (
                                        <tr key={g.type}>
                                            <td className="border px-2 py-1 text-left">{g.type}</td>
                                            <td className="border px-2 py-1 text-center">{g.rows.length}</td>
                                            <td className="border px-2 py-1 text-center">
                                                {data.wo_summary.total > 0
                                                    ? `${Math.round((g.rows.length / data.wo_summary.total) * 100)}%`
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))
                                )}
                                <tr className="bg-slate-50 font-semibold">
                                    <td className="border px-2 py-1 text-left">Total</td>
                                    <td className="border px-2 py-1 text-center">{data.wo_summary.total}</td>
                                    <td className="border px-2 py-1 text-center">100%</td>
                                </tr>
                            </tbody>
                        </table>
                        <p className="mb-4 text-[10px] text-slate-500">
                            Total uraian task (dari log kegiatan HARMES): <span className="font-semibold">{totalTasks}</span> item pada{' '}
                            {data.activities.length} kegiatan.
                        </p>

                        {/* 9. WO PM */}
                        <SectionTitle n={9} title="Work Order PM (Preventive Maintenance)" id="sec-wo-pm" breakBefore />
                        <WoTable rows={woPm} />

                        {/* 10. WO PDM */}
                        <SectionTitle n={10} title="Work Order PdM (Predictive Maintenance)" id="sec-wo-pdm" />
                        <WoTable rows={woPdm} />

                        {/* 11. WO ENJI */}
                        <SectionTitle n={11} title="Work Order ENJI (Engineering)" id="sec-wo-enji" />
                        <WoTable rows={woEnji} />

                        {/* 12. WO WAITING SHUTDOWN */}
                        <SectionTitle n={12} title="Work Order Waiting Shutdown" id="sec-wait-sd" breakBefore />
                        <WaitingTable rows={waitingShutdown} />

                        {/* 13. WO WAITING MATERIAL & JASA */}
                        <SectionTitle n={13} title="Work Order Waiting Material & Jasa" id="sec-wait-mj" />
                        <WaitingTable rows={waitingMaterialJasa} />

                        {/* 14. LAMPIRAN */}
                        <SectionTitle n={14} title="Lampiran" id="sec-attachments" breakBefore />
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
