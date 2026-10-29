import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Clock,
    Download,
    FileCheck,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { MobileRowEditor } from '@/components/mobile/row-editor';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import operasiInput from '@/routes/operasi/input';
import permitToWorkRoutes from '@/routes/operasi/input/permit-to-work';
import type { IdName } from '@/types';

type PtwRow = {
    id?: number;
    no_urut: number;
    uraian: string;
    tanggal: string;
    status: 'open' | 'close';
};

type Props = {
    filters: { unit_id: number; month: number; year: number };
    rows: PtwRow[];
    summary: {
        total_ptw: number;
        total_open: number;
        total_close: number;
        completion_rate: number;
    };
    options: {
        units: IdName[];
        years: number[];
    };
    can_write: boolean;
};

const MONTHS = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

/** Spreadsheet grid of the desktop table (black lines, yellow headers like the other input sheets). */
const xlTh = 'border border-black bg-yellow-200 px-1.5 py-1.5 text-center text-[11px] font-bold text-black uppercase dark:border-white dark:bg-yellow-800 dark:text-yellow-50';
const xlTd = 'h-8 border border-black p-0 dark:border-white/60';
const xlInput =
    'h-8 w-full bg-transparent px-2 text-xs outline-none focus:bg-yellow-50 focus:ring-2 focus:ring-primary focus:ring-inset dark:focus:bg-yellow-950/40';

export default function PermitToWorkInput({
    filters,
    rows: initialRows,
    options,
    can_write,
}: Props) {
    const [rows, setRows] = useState<PtwRow[]>(initialRows);
    const [saving, setSaving] = useState(false);
    const [isDirty, setIsDirty] = useState(false);
    const compact = useCompactLayout();

    // Take the saved rows again whenever the server sends new ones (save / other period).
    const [syncedRows, setSyncedRows] = useState(initialRows);

    if (syncedRows !== initialRows) {
        setSyncedRows(initialRows);
        setRows(initialRows);
        setIsDirty(false);
    }

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            permitToWorkRoutes.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleRowChange = <K extends keyof PtwRow>(
        index: number,
        field: K,
        value: PtwRow[K],
    ) => {
        const updated = [...rows];
        updated[index] = {
            ...updated[index],
            [field]: value,
        };
        setRows(updated);
        setIsDirty(true);
    };

    const handleAddRow = () => {
        const nextNo = rows.length + 1;
        setRows([
            ...rows,
            {
                no_urut: nextNo,
                uraian: '',
                tanggal: '',
                status: 'open',
            },
        ]);
        setIsDirty(true);
    };

    const handleClearRow = (index: number) => {
        const updated = [...rows];
        updated[index] = {
            ...updated[index],
            uraian: '',
            tanggal: '',
            status: 'open',
        };
        setRows(updated);
        setIsDirty(true);
    };

    const handleSave = () => {
        setSaving(true);
        router.post(
            permitToWorkRoutes.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows,
            },
            {
                preserveScroll: true,
                onSuccess: () => setIsDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const handlePdf = () => {
        const url = permitToWorkRoutes.pdf({
            query: {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
            },
        }).url;
        window.open(url, '_blank');
    };

    const activeRows = rows.filter(
        (r) => r.uraian.trim() !== '' || r.tanggal !== '',
    );
    const totalOpen = activeRows.filter((r) => r.status === 'open').length;
    const totalClose = activeRows.filter((r) => r.status === 'close').length;
    const totalPtw = activeRows.length;
    const completionRate =
        totalPtw > 0 ? Math.round((totalClose / totalPtw) * 100) : 0;

    return (
        <>
            <Head title="Input Laporan Permit to Work (PTW)" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Laporan Permit to Work (PTW) Pembangkit"
                    description="Pencatatan dan pemantauan status izin kerja (Permit to Work) untuk kegiatan pemeliharaan dan operasional pembangkit."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" onClick={handlePdf}>
                                <Download className="size-4" />
                                Cetak PDF
                            </Button>
                            {can_write && (
                                <>
                                    <Button
                                        variant="outline"
                                        onClick={handleAddRow}
                                        className="gap-1.5"
                                    >
                                        <Plus className="size-4" />
                                        Tambah Baris
                                    </Button>
                                    <Button
                                        onClick={handleSave}
                                        disabled={saving || !isDirty}
                                        className="gap-2"
                                    >
                                        <Save className="size-4" />
                                        {saving
                                            ? 'Menyimpan...'
                                            : 'Simpan Data'}
                                    </Button>
                                </>
                            )}
                        </div>
                    }
                />

                {/* Filters */}
                <div className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({
                            value: String(u.id),
                            label: u.name,
                        }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={MONTHS.map((label, index) => ({
                            value: String(index + 1),
                            label,
                        }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({
                            value: String(y),
                            label: String(y),
                        }))}
                    />
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">
                                Total PTW Terbit
                            </span>
                            <FileCheck className="size-4 text-primary" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-foreground">
                            {totalPtw}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Dokumen izin kerja aktif
                        </p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">
                                Status Open
                            </span>
                            <Clock className="size-4 text-amber-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
                            {totalOpen}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Pekerjaan masih berjalan
                        </p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">
                                Status Close
                            </span>
                            <CheckCircle2 className="size-4 text-emerald-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            {totalClose}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Pekerjaan telah selesai
                        </p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">
                                Tingkat Penyelesaian
                            </span>
                            <CheckCircle2 className="size-4 text-primary" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-primary">
                            {completionRate}%
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Rasio PTW close
                        </p>
                    </div>
                </div>

                {/* Table matching Excel template */}
                <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                    <div className="flex items-center justify-between border-b border-border bg-[#ea7315]/10 px-4 py-2.5">
                        <div className="flex items-center gap-2">
                            <FileCheck className="size-4 text-[#ea7315]" />
                            <span className="text-xs font-bold tracking-wider text-foreground uppercase">
                                Laporan PTW Pembangkit
                            </span>
                        </div>
                        {isDirty && (
                            <span className="animate-pulse text-xs font-medium text-amber-600 dark:text-amber-400">
                                ● Terdapat perubahan belum disimpan
                            </span>
                        )}
                    </div>

                    {compact ? (
                        <div className="bg-muted/30 p-2">
                            <MobileRowEditor
                                rows={rows}
                                canWrite={can_write}
                                rowKey={(row) => row.no_urut}
                                title={(row) => row.uraian || 'Izin kerja baru'}
                                subtitle={(row) =>
                                    `${row.tanggal || 'Tanggal belum diisi'} · ${row.status.toUpperCase()}`
                                }
                                onChange={(index, key, value) =>
                                    handleRowChange(index, key, value)
                                }
                                onRemove={handleClearRow}
                                removeLabel="Kosongkan baris"
                                fields={[
                                    {
                                        key: 'uraian',
                                        label: 'Uraian pekerjaan',
                                        type: 'textarea',
                                    },
                                    {
                                        key: 'tanggal',
                                        label: 'Tanggal',
                                        type: 'date',
                                    },
                                    {
                                        key: 'status',
                                        label: 'Status',
                                        type: 'select',
                                        options: ['OPEN', 'CLOSE'],
                                        parse: (value) =>
                                            value === 'CLOSE'
                                                ? 'close'
                                                : 'open',
                                        display: (row) =>
                                            row.status.toUpperCase(),
                                    },
                                ]}
                            />
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse border-2 border-black text-xs dark:border-white">
                                <thead>
                                    <tr>
                                        <th rowSpan={2} className={cn(xlTh, 'w-14')}>NO</th>
                                        <th rowSpan={2} className={cn(xlTh, 'min-w-[320px] text-left')}>URAIAN</th>
                                        <th rowSpan={2} className={cn(xlTh, 'w-40')}>TANGGAL</th>
                                        <th colSpan={2} className={xlTh}>STATUS</th>
                                        {can_write && <th rowSpan={2} className={cn(xlTh, 'w-12')} />}
                                    </tr>
                                    <tr>
                                        <th className={cn(xlTh, 'w-28 bg-yellow-100 dark:bg-yellow-900')}>OPEN</th>
                                        <th className={cn(xlTh, 'w-28 bg-yellow-100 dark:bg-yellow-900')}>CLOSE</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map((row, index) => {
                                        const hasData = row.uraian.trim() !== '' || row.tanggal !== '';

                                        return (
                                            <tr key={row.no_urut} className="hover:bg-muted/30">
                                                <td className={cn(xlTd, 'text-center font-bold text-muted-foreground')}>{row.no_urut}</td>
                                                <td className={xlTd}>
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={row.uraian}
                                                            onChange={(e) => handleRowChange(index, 'uraian', e.target.value)}
                                                            className={xlInput}
                                                            placeholder="Uraian pekerjaan PTW..."
                                                            aria-label={`Uraian baris ${row.no_urut}`}
                                                        />
                                                    ) : (
                                                        <span className="block px-2">{row.uraian || '-'}</span>
                                                    )}
                                                </td>
                                                <td className={xlTd}>
                                                    {can_write ? (
                                                        <input
                                                            type="date"
                                                            value={row.tanggal}
                                                            onChange={(e) => handleRowChange(index, 'tanggal', e.target.value)}
                                                            className={cn(xlInput, 'text-center')}
                                                            aria-label={`Tanggal baris ${row.no_urut}`}
                                                        />
                                                    ) : (
                                                        <span className="block px-2 text-center">{row.tanggal || '-'}</span>
                                                    )}
                                                </td>
                                                {(['open', 'close'] as const).map((status) => {
                                                    const ticked = hasData && row.status === status;

                                                    return (
                                                        <td
                                                            key={status}
                                                            onClick={can_write ? () => handleRowChange(index, 'status', status) : undefined}
                                                            className={cn(
                                                                xlTd,
                                                                'text-center text-base font-bold',
                                                                can_write && 'cursor-pointer hover:bg-yellow-50 dark:hover:bg-yellow-950/40',
                                                                ticked && (status === 'open' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300'),
                                                            )}
                                                            title={can_write ? `Klik untuk tandai ${status.toUpperCase()}` : undefined}
                                                            role={can_write ? 'checkbox' : undefined}
                                                            aria-checked={can_write ? ticked : undefined}
                                                            aria-label={`${status.toUpperCase()} baris ${row.no_urut}`}
                                                        >
                                                            {ticked ? '✓' : ''}
                                                        </td>
                                                    );
                                                })}
                                                {can_write && (
                                                    <td className={cn(xlTd, 'text-center')}>
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-7 text-muted-foreground hover:text-destructive"
                                                            onClick={() => handleClearRow(index)}
                                                            title="Kosongkan Baris"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>
                                        );
                                    })}
                                    <tr className="bg-yellow-200 font-bold text-black dark:bg-yellow-800 dark:text-yellow-50">
                                        <td colSpan={3} className={cn(xlTd, 'text-center')}>TOTAL</td>
                                        <td className={cn(xlTd, 'text-center text-amber-700 dark:text-amber-200')}>{totalOpen}</td>
                                        <td className={cn(xlTd, 'text-center text-emerald-700 dark:text-emerald-200')}>{totalClose}</td>
                                        {can_write && <td className={xlTd} />}
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    )}

                    {can_write && (
                        <div className="flex items-center justify-between border-t border-border bg-card p-3">
                            <span className="text-xs text-muted-foreground">
                                * Pilih status OPEN untuk pekerjaan berjalan
                                atau CLOSE untuk pekerjaan selesai
                            </span>
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleAddRow}
                                    className="gap-1.5"
                                >
                                    <Plus className="size-4" />
                                    Tambah Baris
                                </Button>
                                <Button
                                    onClick={handleSave}
                                    disabled={saving || !isDirty}
                                    size="sm"
                                    className="gap-2"
                                >
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan...' : 'Simpan Data'}
                                </Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

PermitToWorkInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: operasiInput.index() },
        { title: 'Permit to Work', href: permitToWorkRoutes.index() },
    ],
};
