import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCheck,
    Info,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanTimeFrame from '@/routes/k3/pengusahaan/time-frame';
import type { IdName } from '@/types';

type DayInfo = {
    day: number;
    dow: string;
    is_red: boolean;
    holiday?: string | null;
};

type ServerRow = {
    id?: number | null;
    no_urut?: number | null;
    uraian_pelaporan: string;
    pic: string;
    rencana: number[];
    realisasi: number[];
    keterangan: string;
    sort_order?: number;
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
    .print-table { width: 100% !important; border-collapse: collapse !important; font-size: 7.5px !important; }
    .print-table th, .print-table td { border: 1px solid #000 !important; padding: 1.5px 2px !important; vertical-align: middle !important; }
    .print-thead th { background-color: #fff !important; color: #000 !important; font-weight: bold !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-sun-cell { background-color: #e2e8f0 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-red-col { background-color: #fca5a5 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-black-cell { background-color: #0f172a !important; color: #fff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-green-cell { background-color: #22c55e !important; color: #000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
`;

export default function PengusahaanTimeFrameIndex({
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
            k3PengusahaanTimeFrame.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const toggleCell = (index: number, category: Category, day: number) => {
        if (!can_write) {
return;
}

        setRows((prev) =>
            prev.map((r, i) => {
                if (i !== index) {
return r;
}

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
        index: number,
        field: 'uraian_pelaporan' | 'pic' | 'keterangan',
        value: string,
    ) => {
        if (!can_write) {
return;
}

        setRows((prev) =>
            prev.map((r, i) => (i === index ? { ...r, [field]: value } : r)),
        );
        setDirty(true);
    };

    const handleAddRow = () => {
        if (!can_write) {
return;
}

        setRows((prev) => [
            ...prev,
            {
                id: null,
                no_urut: prev.length + 1,
                uraian_pelaporan: '',
                pic: 'K3 & Keamanan',
                rencana: [],
                realisasi: [],
                keterangan: '',
                sort_order: prev.length + 1,
            },
        ]);
        setDirty(true);
    };

    const handleDeleteRow = (index: number) => {
        if (!can_write) {
return;
}

        setRows((prev) =>
            prev
                .filter((_, i) => i !== index)
                .map((r, idx) => ({ ...r, no_urut: idx + 1, sort_order: idx + 1 })),
        );
        setDirty(true);
    };

    const handleMarkPlanWorkingDays = () => {
        if (!can_write) {
return;
}

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
        if (!can_write) {
return;
}

        setSaving(true);
        router.post(
            k3PengusahaanTimeFrame.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((r, index) => ({
                    no_urut: index + 1,
                    uraian_pelaporan: r.uraian_pelaporan,
                    pic: r.pic,
                    rencana: r.rencana,
                    realisasi: r.realisasi,
                    keterangan: r.keterangan,
                    sort_order: index + 1,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const dayCells = (row: ServerRow, rowIndex: number, category: Category) =>
        days.map((d) => {
            const on = (row[category] || []).includes(d.day);

            let bgClass = '';

            if (on) {
                bgClass =
                    category === 'rencana'
                        ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-black print-black-cell'
                        : 'bg-emerald-500 text-black font-extrabold print-green-cell';
            } else if (d.is_red) {
                bgClass = d.holiday ? 'bg-red-200/60 dark:bg-red-950/40 print-red-col' : 'bg-muted/40 dark:bg-muted/20 print-sun-cell';
            }

            return (
                <td
                    key={`${category}-${d.day}`}
                    onClick={() => toggleCell(rowIndex, category, d.day)}
                    className={`border border-black p-0 text-center text-[10px] font-bold transition-colors select-none ${bgClass} ${
                        can_write ? 'cursor-pointer hover:opacity-80' : ''
                    }`}
                    title={`Tanggal ${d.day} (${d.dow})${d.holiday ? ` [Libur: ${d.holiday}]` : ''} — ${category === 'rencana' ? 'Rencana' : 'Realisasi'}: klik untuk ${on ? 'hapus' : 'tandai'}`}
                >
                    {on ? '✓' : ''}
                </td>
            );
        });

    return (
        <>
            <Head title={`Time Frame Kinerja K3 & Keamanan — ${unit.name}`} />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Header bar */}
                <div className="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => router.get(k3Pengusahaan.index('input').url)}
                            title="Kembali ke Input Pengusahaan K3"
                        >
                            <ArrowLeft className="size-4" />
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-foreground">
                                    Time Frame Pengusahaan K3 &amp; Keamanan
                                </h1>
                                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">
                                    {unit.name}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Matriks rencana (RENC) dan realisasi (REAL) time frame kinerja K3 dan keamanan bulanan.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {dirty && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleReset}
                                disabled={saving}
                                className="gap-1.5 text-xs text-muted-foreground"
                            >
                                <RotateCcw className="size-3.5" />
                                Reset
                            </Button>
                        )}
                        {can_write && (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleAddRow}
                                    className="gap-1.5 text-xs"
                                    title="Tambah Baris Uraian"
                                >
                                    <Plus className="size-3.5" />
                                    Tambah Baris
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleMarkPlanWorkingDays}
                                    className="gap-1.5 text-xs"
                                    title="Tandai seluruh hari kerja pada rencana"
                                >
                                    <CheckCheck className="size-3.5 text-primary" />
                                    Tandai Hari Kerja
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={handleSave}
                                    disabled={saving || !dirty}
                                    className="gap-1.5 text-xs"
                                >
                                    <Save className="size-3.5" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            </>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => window.print()}
                            className="gap-1.5 text-xs"
                            title="Cetak Dokumen Lanskap"
                        >
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

                {/* Printable Document Box */}
                <div className="print-container overflow-hidden rounded-md border border-border bg-card shadow-xs">
                    {/* KOP Dokumen (sesuai dokumen fisik foto) */}
                    <div className="border-b border-border p-3">
                        <table className="w-full border-collapse border border-black dark:border-border">
                            <tbody>
                                <tr>
                                    <td className="w-48 border-r border-black p-2 text-center align-middle dark:border-border">
                                        <img
                                            src="/logo/sidebar-logo.png"
                                            alt="PLN Nusantara Power"
                                            className="mx-auto max-h-12 object-contain"
                                            onError={(e) => {
                                                (e.target as HTMLElement).style.display = 'none';
                                            }}
                                        />
                                    </td>
                                    <td className="p-2 text-center align-middle">
                                        <div className="text-xs font-bold tracking-wide text-foreground uppercase">
                                            PT. PLN NUSANTARA POWER
                                        </div>
                                        <div className="text-xs font-bold text-foreground uppercase">
                                            UNIT PEMBANGKITAN KENDARI
                                        </div>
                                        <div className="text-[11px] font-semibold text-foreground uppercase">
                                            UNIT LAYANAN PUSAT LISTRIK TENAGA DIESEL {unit.name.toUpperCase()}
                                        </div>
                                    </td>
                                    <td className="w-40 border-l border-black p-2 text-center align-middle dark:border-border">
                                        <img
                                            src="/logo/k3.png"
                                            alt="Logo K3"
                                            className="mx-auto max-h-12 object-contain"
                                            onError={(e) => {
                                                (e.target as HTMLElement).style.display = 'none';
                                            }}
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        {/* Title Bar */}
                        <div className="mt-2 border border-black p-1.5 text-center dark:border-border">
                            <h2 className="text-xs font-bold tracking-wider text-foreground uppercase md:text-sm">
                                TIME FRAME KINERJA K3 DAN KEAMANAN PERIODE {monthName.toUpperCase()} {filters.year}
                            </h2>
                        </div>
                    </div>

                    {/* Table Matrix */}
                    <div className="overflow-x-auto">
                        <table className="print-table w-full border-collapse text-xs">
                            <thead className="print-thead bg-muted/50 dark:bg-muted/30">
                                <tr>
                                    <th rowSpan={2} className="w-8 border border-black p-1 text-center font-bold text-foreground">
                                        NO
                                    </th>
                                    <th rowSpan={2} className="w-64 min-w-56 border border-black p-1 text-left font-bold text-foreground">
                                        URAIAN PELAPORAN
                                    </th>
                                    <th rowSpan={2} className="w-28 min-w-24 border border-black p-1 text-center font-bold text-foreground">
                                        PIC
                                    </th>
                                    <th rowSpan={2} className="w-14 border border-black p-1 text-center font-bold text-foreground">
                                        STATUS
                                    </th>
                                    <th
                                        colSpan={days.length}
                                        className="border border-black p-1 text-center font-bold tracking-wider text-foreground uppercase"
                                    >
                                        Timeline
                                    </th>
                                    <th rowSpan={2} className="w-44 min-w-36 border border-black p-1 text-left font-bold text-foreground">
                                        KETERANGAN
                                    </th>
                                    {can_write && (
                                        <th rowSpan={2} className="no-print w-10 border border-black p-1 text-center font-bold text-foreground">
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                                <tr>
                                    {days.map((d) => (
                                        <th
                                            key={d.day}
                                            className={`w-5 border border-black p-0.5 text-center text-[10px] font-bold ${
                                                d.is_red
                                                    ? d.holiday
                                                        ? 'bg-red-100 text-red-600 dark:bg-red-950/60 print-red-col'
                                                        : 'bg-muted/70 text-red-600 print-sun-cell'
                                                    : 'text-foreground'
                                            }`}
                                            title={`${d.day} (${d.dow})${d.holiday ? ` - ${d.holiday}` : ''}`}
                                        >
                                            {d.day}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={days.length + (can_write ? 6 : 5)}
                                            className="border border-black p-6 text-center text-xs text-muted-foreground"
                                        >
                                            Belum ada baris kegiatan. Klik tombol &quot;Tambah Baris&quot; di atas untuk menambahkan.
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row, idx) => (
                                        <Fragment key={`row-${idx}`}>
                                            {/* Baris RENC */}
                                            <tr className="transition-colors hover:bg-muted/10">
                                                <td
                                                    rowSpan={2}
                                                    className="border border-black p-1 text-center font-bold text-foreground"
                                                >
                                                    {idx + 1}
                                                </td>
                                                <td
                                                    rowSpan={2}
                                                    className="border border-black p-1 text-left align-middle text-[11px] text-foreground"
                                                >
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={row.uraian_pelaporan}
                                                            onChange={(e) =>
                                                                updateField(idx, 'uraian_pelaporan', e.target.value)
                                                            }
                                                            placeholder="Uraian pelaporan…"
                                                            className="w-full bg-transparent px-1 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                        />
                                                    ) : (
                                                        <span className="font-medium">{row.uraian_pelaporan}</span>
                                                    )}
                                                </td>
                                                <td
                                                    rowSpan={2}
                                                    className="border border-black p-1 text-center align-middle"
                                                >
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={row.pic || ''}
                                                            onChange={(e) => updateField(idx, 'pic', e.target.value)}
                                                            placeholder="PIC"
                                                            className="w-full bg-transparent px-1 py-0.5 text-center text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                        />
                                                    ) : (
                                                        <span className="text-xs">{row.pic || '-'}</span>
                                                    )}
                                                </td>
                                                <td className="border border-black bg-muted/20 p-0.5 text-center text-[10px] font-bold text-foreground">
                                                    RENC
                                                </td>
                                                {dayCells(row, idx, 'rencana')}
                                                <td
                                                    rowSpan={2}
                                                    className="border border-black p-1 text-left align-middle"
                                                >
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={row.keterangan || ''}
                                                            onChange={(e) =>
                                                                updateField(idx, 'keterangan', e.target.value)
                                                            }
                                                            placeholder="Keterangan…"
                                                            className="w-full bg-transparent px-1 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                        />
                                                    ) : (
                                                        <span className="px-1 text-xs">{row.keterangan || '-'}</span>
                                                    )}
                                                </td>
                                                {can_write && (
                                                    <td
                                                        rowSpan={2}
                                                        className="no-print border border-black p-1 text-center align-middle"
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => handleDeleteRow(idx)}
                                                            className="size-6 text-destructive hover:bg-destructive/10"
                                                            title="Hapus baris ini"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>
                                            {/* Baris REAL */}
                                            <tr className="transition-colors hover:bg-muted/10">
                                                <td className="border border-black bg-muted/20 p-0.5 text-center text-[10px] font-bold text-foreground">
                                                    REAL
                                                </td>
                                                {dayCells(row, idx, 'realisasi')}
                                            </tr>
                                        </Fragment>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Legend */}
                    <div className="border-t border-border bg-muted/20 p-3">
                        <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
                            <div className="flex items-center gap-1.5">
                                <span className="size-3.5 rounded-xs border border-black bg-slate-900 dark:bg-slate-100" />
                                <span>Rencana (RENC)</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <span className="size-3.5 rounded-xs border border-black bg-emerald-500" />
                                <span>Realisasi (REAL)</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <span className="size-3.5 rounded-xs border border-border bg-muted/70" />
                                <span>Hari Minggu / Akhir Pekan</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <span className="size-3.5 rounded-xs border border-red-400 bg-red-200" />
                                <span>Hari Libur Nasional</span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <Info className="size-3.5 text-primary" />
                                <span>Klik pada kotak tanggal untuk menandai atau membatalkan jadwal.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

PengusahaanTimeFrameIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input K3 & Keamanan', href: k3Pengusahaan.index('input').url },
        { title: 'Time Frame Pengusahaan', href: k3PengusahaanTimeFrame.index().url },
    ],
};
