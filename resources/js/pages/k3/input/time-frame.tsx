import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCheck,
    Info,
    Printer,
    RotateCcw,
    Save,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { K3InputExportButtons } from '@/components/k3/input-export-buttons';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import k3Input from '@/routes/k3/input';
import timeFrame from '@/routes/k3/input/time-frame';
import type { IdName } from '@/types';

type DayInfo = {
    day: number;
    dow: string;
    is_red: boolean;
    holiday?: string | null;
};

type ServerRow = {
    activity_type_id: number;
    uraian: string;
    pic: string;
    rencana: number[];
    realisasi: number[];
    keterangan: string;
};

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    days: DayInfo[];
    rows: ServerRow[];
    options: { units: IdName[]; years: number[] };
    can_write: boolean;
};

type Category = 'rencana' | 'realisasi';

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 6mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; background: #fff !important; color: #000 !important; padding: 0 !important; margin: 0 !important; }
    .no-print { display: none !important; }
    .print-table { width: 100% !important; border-collapse: collapse !important; font-size: 7px !important; }
    .print-table th, .print-table td { border: 1px solid #000 !important; padding: 1px !important; text-align: center !important; vertical-align: middle !important; }
    .print-thead th { background-color: #fff !important; color: #000 !important; font-weight: bold !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-red-text { color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-red-cell { background-color: #ff0000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-green-cell { background-color: #22c55e !important; color: #000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-yellow-cell { background-color: #fde047 !important; color: #000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
`;

export default function TimeFrameInput({
    unit,
    filters,
    days,
    rows: initialRows,
    options,
    can_write,
}: Props) {
    const [rows, setRows] = useState<ServerRow[]>(initialRows);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);
    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(initialRows);
        setDirty(false);
    }

    const monthName = useMemo(
        () => OPERASI_MONTHS[filters.month - 1] ?? '',
        [filters.month],
    );
    const workingDaysList = useMemo(
        () => days.filter((d) => !d.is_red).map((d) => d.day),
        [days],
    );

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            timeFrame.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const toggleCell = (activityId: number, category: Category, day: number) => {
        if (!can_write) return;
        setRows((prev) =>
            prev.map((r) => {
                if (r.activity_type_id !== activityId) return r;
                const set = new Set(r[category] || []);
                if (set.has(day)) {
                    set.delete(day);
                } else {
                    set.add(day);
                }
                return { ...r, [category]: Array.from(set).sort((a, b) => a - b) };
            }),
        );
        setDirty(true);
    };

    const updateField = (
        activityId: number,
        field: 'pic' | 'keterangan',
        value: string,
    ) => {
        if (!can_write) return;
        setRows((prev) =>
            prev.map((r) =>
                r.activity_type_id === activityId ? { ...r, [field]: value } : r,
            ),
        );
        setDirty(true);
    };

    const handleMarkPlanWorkingDays = () => {
        if (!can_write) return;
        setRows((prev) =>
            prev.map((r) => ({ ...r, rencana: [...workingDaysList] })),
        );
        setDirty(true);
    };

    const handleReset = () => {
        setRows(initialRows);
        setDirty(false);
    };

    const handleSave = () => {
        if (!can_write) return;
        setSaving(true);
        router.post(
            timeFrame.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((r) => ({
                    activity_type_id: r.activity_type_id,
                    pic: r.pic,
                    keterangan: r.keterangan,
                    rencana: r.rencana,
                    realisasi: r.realisasi,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const dayCells = (row: ServerRow, category: Category) =>
        days.map((d) => {
            if (d.is_red) {
                return (
                    <td
                        key={`${category}-${d.day}`}
                        className="print-red-cell border border-black bg-[#ff0000] select-none"
                        title={`${d.day} (${d.dow}): Libur / Akhir Pekan`}
                    />
                );
            }

            const planned = row.rencana.includes(d.day);
            const realized = row.realisasi.includes(d.day);
            const on = category === 'rencana' ? planned : realized;

            // Legend: green = Terealisasi (realised), yellow = Tidak Terealisasi
            // (planned but not realised).
            let color = '';
            if (on) {
                if (category === 'realisasi') {
                    color = 'bg-emerald-400 text-black print-green-cell';
                } else {
                    color = realized
                        ? 'bg-emerald-400 text-black print-green-cell'
                        : 'bg-yellow-300 text-black print-yellow-cell';
                }
            }

            return (
                <td
                    key={`${category}-${d.day}`}
                    onClick={() => toggleCell(row.activity_type_id, category, d.day)}
                    className={`border border-black text-center text-[11px] font-bold transition-colors select-none ${color || 'hover:bg-muted/40'} ${can_write ? 'cursor-pointer' : ''}`}
                    title={`Tanggal ${d.day} (${d.dow}) — ${category === 'rencana' ? 'Rencana' : 'Realisasi'}: klik untuk ${on ? 'batalkan' : 'tandai (1)'}`}
                >
                    {on ? '1' : ''}
                </td>
            );
        });

    return (
        <>
            <Head title={`Time Frame K3 & Lingkungan — ${unit.name}`} />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Header bar */}
                <div className="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => router.get(k3Input.index().url)}
                            title="Kembali ke Input K3"
                        >
                            <ArrowLeft className="size-4" />
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-foreground">
                                    Time Frame Kinerja K3 &amp; Lingkungan
                                </h1>
                                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">
                                    {unit.name}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Klik kotak tanggal pada baris RENC (rencana) / REAL (realisasi) — otomatis terisi 1. Target = jumlah rencana, kinerja = realisasi ÷ target.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {dirty && (
                            <Button variant="outline" size="sm" onClick={handleReset} disabled={saving} className="gap-1.5 text-xs text-muted-foreground">
                                <RotateCcw className="size-3.5" />
                                Reset
                            </Button>
                        )}
                        {can_write && (
                            <>
                                <Button variant="outline" size="sm" onClick={handleMarkPlanWorkingDays} className="gap-1.5 text-xs" title="Isi rencana untuk seluruh hari kerja">
                                    <CheckCheck className="size-3.5 text-primary" />
                                    Tandai Rencana Hari Kerja
                                </Button>
                                <Button size="sm" onClick={handleSave} disabled={saving || !dirty} className="gap-1.5 text-xs">
                                    <Save className="size-3.5" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            </>
                        )}
                        <K3InputExportButtons input="time-frame" query={{ unit_id: filters.unit_id, month: filters.month, year: filters.year }} />
                        <Button variant="outline" size="sm" onClick={() => window.print()} className="gap-1.5 text-xs" title="Cetak Lanskap">
                            <Printer className="size-3.5" />
                            Cetak
                        </Button>
                    </div>
                </div>

                {/* Filters */}
                <div className="no-print flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect
                        label="Unit Pembangkit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                    />
                    {dirty && (
                        <div className="flex items-center gap-1.5 pb-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                            <span className="size-2 animate-pulse rounded-full bg-amber-500" />
                            Ada perubahan belum disimpan
                        </div>
                    )}
                </div>

                {/* Printable card */}
                <div className="print-container overflow-hidden rounded-md border border-border bg-card shadow-xs">
                    <div className="border-b border-border p-4">
                        <div className="grid grid-cols-12 items-stretch border border-black dark:border-border">
                            <div className="col-span-3 flex items-center justify-center border-r border-black p-2 dark:border-border">
                                <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="max-h-12 object-contain" onError={(e) => { (e.target as HTMLElement).style.display = 'none'; }} />
                            </div>
                            <div className="col-span-6 flex flex-col justify-center p-2 text-center">
                                <div className="text-xs font-bold tracking-wide text-foreground uppercase">JASA PENDUKUNG TEKNIS 11 &amp; 6 SITE - KIT</div>
                                <div className="text-xs font-bold text-foreground uppercase">PLN NP UP KENDARI - {unit.name.toUpperCase()}</div>
                                <div className="mt-0.5 text-[11px] font-semibold text-foreground uppercase">LAPORAN PROJECT</div>
                                <div className="text-[11px] font-bold text-foreground uppercase">TIME FRAME KINERJA K3 DAN LINGKUNGAN BULAN {monthName.toUpperCase()} {filters.year}</div>
                            </div>
                            <div className="col-span-3 flex items-center justify-center border-l border-black p-2 dark:border-border">
                                <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" className="max-h-12 object-contain" onError={(e) => { (e.target as HTMLElement).style.display = 'none'; }} />
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="print-table w-full border-collapse text-xs">
                            <thead className="print-thead bg-muted/60 dark:bg-muted/30">
                                <tr>
                                    <th rowSpan={2} className="w-8 border border-black p-1 text-center font-bold text-foreground">No</th>
                                    <th rowSpan={2} className="w-56 min-w-48 border border-black p-1 text-left font-bold text-foreground">Uraian Pelaporan</th>
                                    <th rowSpan={2} className="w-20 border border-black p-1 text-center font-bold text-foreground">PIC</th>
                                    <th rowSpan={2} className="w-12 border border-black p-1 text-center font-bold text-foreground">Status</th>
                                    <th colSpan={days.length} className="border border-black p-1 text-center font-bold tracking-wider text-foreground uppercase">Time line</th>
                                    <th rowSpan={2} className="w-12 border border-black p-1 text-center font-bold text-foreground">TARGET</th>
                                    <th rowSpan={2} className="w-14 border border-black p-1 text-center font-bold text-foreground">REALISASI</th>
                                    <th rowSpan={2} className="w-14 border border-black p-1 text-center font-bold text-foreground">KINERJA</th>
                                    <th rowSpan={2} className="w-40 min-w-32 border border-black p-1 text-left font-bold text-foreground">KETERANGAN</th>
                                </tr>
                                <tr>
                                    {days.map((d) => (
                                        <th key={d.day} className={`w-6 border border-black p-0.5 text-center text-[10px] font-bold ${d.is_red ? 'print-red-text text-red-600' : 'text-foreground'}`} title={`${d.day} (${d.dow})${d.holiday ? ` - ${d.holiday}` : ''}`}>
                                            {d.day}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={days.length + 8} className="border border-black p-6 text-center text-xs text-muted-foreground">
                                            Belum ada master jenis kegiatan K3. Tambahkan di Master K3 &amp; Keamanan → Jenis Kegiatan.
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row, idx) => {
                                        const target = row.rencana.length;
                                        const realisasi = row.realisasi.length;
                                        const kinerja = target > 0 ? Math.round((realisasi / target) * 100) : 0;

                                        return (
                                            <Fragment key={row.activity_type_id}>
                                                {/* RENC */}
                                                <tr className="transition-colors hover:bg-muted/10">
                                                    <td rowSpan={2} className="border border-black p-1 text-center font-medium text-foreground">{idx + 1}</td>
                                                    <td rowSpan={2} className="border border-black p-1 text-left align-top text-[11px] text-foreground">{row.uraian}</td>
                                                    <td rowSpan={2} className="border border-black p-1 text-center align-top">
                                                        {can_write ? (
                                                            <input
                                                                type="text"
                                                                value={row.pic || ''}
                                                                onChange={(e) => updateField(row.activity_type_id, 'pic', e.target.value)}
                                                                className="w-full bg-transparent px-1 py-0.5 text-center text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                            />
                                                        ) : (
                                                            <span className="text-xs">{row.pic || '-'}</span>
                                                        )}
                                                    </td>
                                                    <td className="border border-black bg-muted/20 p-0.5 text-center text-[10px] font-bold text-foreground">RENC</td>
                                                    {dayCells(row, 'rencana')}
                                                    <td rowSpan={2} className="border border-black p-1 text-center align-middle font-bold text-foreground">{target}</td>
                                                    <td rowSpan={2} className="border border-black p-1 text-center align-middle font-extrabold text-foreground">{realisasi}</td>
                                                    <td rowSpan={2} className={`border border-black p-1 text-center align-middle font-extrabold ${kinerja >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>{kinerja}%</td>
                                                    <td rowSpan={2} className="border border-black p-1 text-left align-top">
                                                        {can_write ? (
                                                            <input
                                                                type="text"
                                                                value={row.keterangan || ''}
                                                                onChange={(e) => updateField(row.activity_type_id, 'keterangan', e.target.value)}
                                                                placeholder="Catatan…"
                                                                className="w-full bg-transparent px-1 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                            />
                                                        ) : (
                                                            <span className="px-1 text-xs">{row.keterangan || '-'}</span>
                                                        )}
                                                    </td>
                                                </tr>
                                                {/* REAL */}
                                                <tr className="transition-colors hover:bg-muted/10">
                                                    <td className="border border-black bg-muted/20 p-0.5 text-center text-[10px] font-bold text-foreground">REAL</td>
                                                    {dayCells(row, 'realisasi')}
                                                </tr>
                                            </Fragment>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Legend */}
                    <div className="border-t border-border bg-muted/20 p-4">
                        <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                            <div className="flex items-center gap-1.5">
                                <span className="size-3.5 rounded-xs border border-emerald-700 bg-emerald-400" />
                                <span>Terealisasi</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <span className="size-3.5 rounded-xs border border-yellow-500 bg-yellow-300" />
                                <span>Tidak Terealisasi (rencana belum terealisasi)</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <span className="size-3.5 rounded-xs border border-red-700 bg-[#ff0000]" />
                                <span>Hari Libur / Akhir Pekan</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <Info className="size-3.5 text-primary" />
                                <span>Baris RENC = rencana, REAL = realisasi.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

TimeFrameInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input K3 & Keamanan', href: k3Input.index() },
        { title: 'Time Frame K3 & Lingkungan', href: timeFrame.index() },
    ],
};
