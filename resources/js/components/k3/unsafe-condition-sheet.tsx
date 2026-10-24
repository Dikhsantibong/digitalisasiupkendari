import { Head, router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ChevronDown, Clock, Download, FileSpreadsheet, Plus, Printer, Save, ShieldAlert, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { ChoiceChips } from '@/components/mobile/choice-chips';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { EvidencePhotoField } from '@/components/operasi/evidence-photo-field';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmCellSelect } from '@/components/pdm/cell-select';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { downloadUnsafeConditionWorkbook } from '@/lib/unsafe-condition-excel';
import { cn } from '@/lib/utils';
import type { IdName } from '@/types';

export type UnsafeConditionRow = {
    id: number;
    periode: string;
    kategori: string;
    temuan: string;
    kondisi: string;
    tindak_lanjut: string;
    rekomendasi: string;
    lokasi: string;
    keterangan: 'open' | 'close';
    foto_sebelum: string | null;
    foto_sebelum_url: string | null;
    foto_sesudah: string | null;
    foto_sesudah_url: string | null;
};

type Filters = { unit_id: number; month: number; year: number };

export type UnsafeConditionSheetProps = {
    unit: { id: number; name: string };
    filters: Filters;
    rows: UnsafeConditionRow[];
    summary: {
        total: number;
        unsafe_action_count: number;
        unsafe_condition_count: number;
        open_count: number;
        close_count: number;
    };
    options: { units: IdName[]; years: number[] };
    can_write: boolean;
};

/** A row being edited: `id` is null until the new finding is saved. */
type SheetRow = Omit<UnsafeConditionRow, 'id'> & {
    id: number | null;
    key: string;
    foto_sebelum_file?: File | null;
    foto_sesudah_file?: File | null;
};

type PhotoColumn = 'foto_sebelum' | 'foto_sesudah';

const PERIODE_OPTIONS = ['MINGGU KE - 1', 'MINGGU KE - 2', 'MINGGU KE - 3', 'MINGGU KE - 4', 'MINGGU KE - 5'];
const KATEGORI = ['UNSAFE ACTION', 'UNSAFE CONDITION'];
const PHOTOS: [PhotoColumn, string][] = [
    ['foto_sebelum', 'Sebelum'],
    ['foto_sesudah', 'Sesudah'],
];

const cellInput = 'h-8 rounded-none border-0 bg-transparent px-1 text-xs shadow-none focus-visible:ring-1';
const cellText = 'min-h-8 rounded-none border-0 bg-transparent px-1 py-1.5 text-xs shadow-none focus-visible:ring-1 md:text-xs';
const th = 'border border-black p-1.5 text-center font-bold text-black dark:border-white';
const td = 'border border-black dark:border-border';

let nextKey = 0;
const toSheetRow = (row: UnsafeConditionRow): SheetRow => ({ ...row, key: `saved-${row.id}` });
const blankRow = (): SheetRow => ({
    id: null,
    key: `new-${++nextKey}`,
    periode: 'MINGGU KE - 1',
    kategori: 'UNSAFE CONDITION',
    temuan: '',
    kondisi: '',
    tindak_lanjut: '',
    rekomendasi: '',
    lokasi: '',
    keterangan: 'open',
    foto_sebelum: null,
    foto_sebelum_url: null,
    foto_sesudah: null,
    foto_sesudah_url: null,
});

const kategoriTone = (kategori: string) =>
    kategori === 'UNSAFE ACTION' ? 'border-amber-500 bg-amber-500 text-white' : 'border-rose-600 bg-rose-600 text-white';
const statusTone = (status: string) => (status === 'CLOSE' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-amber-500 bg-amber-500 text-white');

/**
 * Laporan Unsafe Action & Unsafe Condition of a unit & month, filled like the
 * other input sheets: rows edited in the table, "Tambah Baris" for a new
 * finding and one Simpan for the whole month. Used by Input Operasi and Input
 * Pemeliharaan (both write har_unsafe_conditions, which feeds the K3 module).
 */
export function UnsafeConditionSheet({
    unit,
    filters,
    rows: savedRows,
    summary,
    options,
    can_write,
    routes,
}: UnsafeConditionSheetProps & {
    routes: { index: string; sync: string; pdf: (query: Filters) => string };
}) {
    const [rows, setRows] = useState<SheetRow[]>(() => savedRows.map(toSheetRow));
    const [deletedIds, setDeletedIds] = useState<number[]>([]);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [exporting, setExporting] = useState(false);
    const [openRow, setOpenRow] = useState<string | null>(null);
    const compact = useCompactLayout();

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;
    const periodLabel = `${monthName} ${filters.year}`;

    const visit = (patch: Partial<Filters>) => {
        if (dirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(routes.index, { ...filters, ...patch }, { preserveScroll: true });
    };

    const update = (index: number, patch: Partial<SheetRow>) => {
        setRows((current) => current.map((row, i) => (i === index ? { ...row, ...patch } : row)));
        setDirty(true);
    };

    /** A new photo replaces the saved one; removing clears both. */
    const pickPhoto = (index: number, column: PhotoColumn, file: File) => update(index, { [`${column}_file`]: file });
    const removePhoto = (index: number, column: PhotoColumn) => update(index, { [column]: null, [`${column}_url`]: null, [`${column}_file`]: null });

    const addRow = () => {
        const row = blankRow();
        setRows((current) => [...current, row]);
        setOpenRow(row.key);
        setDirty(true);
    };

    const removeRow = (index: number) => {
        const row = rows[index];

        if (row.id !== null && !window.confirm(`Hapus temuan "${row.temuan || `baris ${index + 1}`}"? Temuan terhapus saat Simpan ditekan.`)) {
            return;
        }

        if (row.id !== null) {
            setDeletedIds((current) => [...current, row.id as number]);
        }

        setRows((current) => current.filter((_, i) => i !== index));
        setErrors({});
        setDirty(true);
    };

    const save = () => {
        setSaving(true);
        setErrors({});
        router.post(
            routes.sync,
            {
                ...filters,
                deleted_ids: deletedIds,
                rows: rows.map((row) => ({
                    id: row.id,
                    periode: row.periode,
                    kategori: row.kategori,
                    temuan: row.temuan,
                    kondisi: row.kondisi,
                    tindak_lanjut: row.tindak_lanjut,
                    rekomendasi: row.rekomendasi,
                    lokasi: row.lokasi,
                    keterangan: row.keterangan,
                    // The saved photo path is kept; a chosen file is uploaded (the request becomes multipart).
                    foto_sebelum: row.foto_sebelum ?? null,
                    foto_sesudah: row.foto_sesudah ?? null,
                    ...(row.foto_sebelum_file ? { foto_sebelum_file: row.foto_sebelum_file } : {}),
                    ...(row.foto_sesudah_file ? { foto_sesudah_file: row.foto_sesudah_file } : {}),
                })),
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    // Take the saved rows (with their ids & photo URLs) back from the server.
                    setRows((page.props as unknown as UnsafeConditionSheetProps).rows.map(toSheetRow));
                    setDeletedIds([]);
                    setOpenRow(null);
                    setDirty(false);
                },
                onError: (errs) => {
                    setErrors(errs);
                    toast.error(Object.values(errs)[0] ?? 'Periksa kembali isian temuan.');
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const exportExcel = async () => {
        setExporting(true);

        try {
            await downloadUnsafeConditionWorkbook(
                unit.name,
                periodLabel,
                savedRows,
                summary,
                `Laporan_Unsafe_Action_Condition_${unit.name.replace(/\s+/g, '_')}_${filters.month}_${filters.year}.xlsx`,
            );
        } catch {
            toast.error('Gagal membuat file Excel.');
        } finally {
            setExporting(false);
        }
    };

    const rowError = (index: number) => errors[`rows.${index}.temuan`] ?? errors[`rows.${index}.id`];

    const filterSelects = (
        <>
            <OperasiSelect
                label="Unit"
                value={String(filters.unit_id)}
                onChange={(value) => visit({ unit_id: Number(value) })}
                options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                className={compact ? 'w-full' : 'w-56'}
            />
            <OperasiSelect
                label="Bulan"
                value={String(filters.month)}
                onChange={(value) => visit({ month: Number(value) })}
                options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                className={compact ? 'w-full' : undefined}
            />
            <OperasiSelect
                label="Tahun"
                value={String(filters.year)}
                onChange={(value) => visit({ year: Number(value) })}
                options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                className={compact ? 'w-full' : 'w-28'}
            />
        </>
    );

    const kpis = (
        <div className="no-print grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Kpi label="Total Temuan" icon={<ShieldAlert className="size-4 text-primary" />} note="Periode terpilih">
                <span className="text-2xl font-bold text-foreground">{summary.total}</span>
            </Kpi>
            <Kpi label="Unsafe Action" icon={<AlertTriangle className="size-4 text-amber-500" />} note="Tindakan tidak aman">
                <span className="text-2xl font-bold text-amber-600 dark:text-amber-400">{summary.unsafe_action_count}</span>
            </Kpi>
            <Kpi label="Unsafe Condition" icon={<AlertTriangle className="size-4 text-rose-500" />} note="Kondisi berbahaya">
                <span className="text-2xl font-bold text-rose-600 dark:text-rose-400">{summary.unsafe_condition_count}</span>
            </Kpi>
            <Kpi
                label="Status Temuan"
                icon={
                    <span className="flex items-center gap-1">
                        <CheckCircle2 className="size-3.5 text-emerald-500" />
                        <Clock className="size-3.5 text-amber-500" />
                    </span>
                }
                note="Penyelesaian temuan"
            >
                <span className="flex items-baseline gap-2">
                    <span className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{summary.close_count}</span>
                    <span className="text-xs text-muted-foreground">Close</span>
                    <span className="text-sm font-semibold text-muted-foreground">/</span>
                    <span className="text-2xl font-bold text-amber-600 dark:text-amber-400">{summary.open_count}</span>
                    <span className="text-xs text-muted-foreground">Open</span>
                </span>
            </Kpi>
        </div>
    );

    if (compact) {
        return (
            <>
                <Head title={`Unsafe Action & Condition - ${unit.name}`} />
                <div className={cn('flex flex-col gap-3 p-4', can_write && 'pb-28')}>
                    <PageHeader title="Unsafe Action & Condition" description={`${unit.name} · ${periodLabel}`} />

                    <div className="grid grid-cols-2 gap-3 rounded-xl border border-border bg-card p-3 [&>*:first-child]:col-span-2">{filterSelects}</div>

                    {kpis}

                    <p className="px-0.5 text-[12px] text-muted-foreground">Baris baru tanpa uraian temuan tidak ikut disimpan.</p>

                    {rows.length === 0 && (
                        <p className="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">Belum ada temuan untuk periode ini.</p>
                    )}

                    {rows.map((row, index) => {
                        const open = openRow === row.key;
                        const error = rowError(index);

                        return (
                            <div key={row.key} className={cn('rounded-xl border bg-card', error ? 'border-destructive' : 'border-border')}>
                                <button type="button" onClick={() => setOpenRow(open ? null : row.key)} className="flex w-full items-center gap-3 p-3 text-left">
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-[13px] font-bold text-primary">{index + 1}</span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-[14px] font-medium text-foreground">{row.temuan || 'Temuan baru'}</span>
                                        <span className="block truncate text-[12px] text-muted-foreground">
                                            {row.kategori} · {row.periode}
                                            {row.lokasi ? ` · ${row.lokasi}` : ''}
                                        </span>
                                    </span>
                                    <span
                                        className={cn(
                                            'shrink-0 rounded-md px-1.5 py-0.5 text-[10.5px] font-bold uppercase',
                                            row.keterangan === 'close' ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400' : 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
                                        )}
                                    >
                                        {row.keterangan}
                                    </span>
                                    <ChevronDown className={cn('size-4 shrink-0 text-muted-foreground transition', open && 'rotate-180')} />
                                </button>
                                {open && (
                                    <div className="flex flex-col gap-3 border-t border-border p-3">
                                        <MobileField label="Periode">
                                            <ChoiceChips options={PERIODE_OPTIONS} labels={Object.fromEntries(PERIODE_OPTIONS.map((p) => [p, p.replace('MINGGU KE - ', 'Minggu ')]))} value={row.periode} onChange={(value) => value && update(index, { periode: value })} disabled={!can_write} />
                                        </MobileField>
                                        <MobileField label="Kategori">
                                            <ChoiceChips options={KATEGORI} value={row.kategori} onChange={(value) => value && update(index, { kategori: value })} disabled={!can_write} tone={kategoriTone} />
                                        </MobileField>
                                        <MobileField label="Uraian temuan" error={error}>
                                            <Textarea value={row.temuan} onChange={(e) => update(index, { temuan: e.target.value })} rows={2} placeholder="Kebocoran line pipa dan valve BBM…" disabled={!can_write} />
                                        </MobileField>
                                        <div className="grid grid-cols-2 gap-3">
                                            <MobileField label="Kondisi">
                                                <Input value={row.kondisi} onChange={(e) => update(index, { kondisi: e.target.value })} placeholder="Bocor / Rusak" disabled={!can_write} />
                                            </MobileField>
                                            <MobileField label="Lokasi">
                                                <Input value={row.lokasi} onChange={(e) => update(index, { lokasi: e.target.value })} placeholder="Area mesin #6" disabled={!can_write} />
                                            </MobileField>
                                        </div>
                                        <MobileField label="Tindak lanjut">
                                            <Textarea value={row.tindak_lanjut} onChange={(e) => update(index, { tindak_lanjut: e.target.value })} rows={2} disabled={!can_write} />
                                        </MobileField>
                                        <MobileField label="Rekomendasi">
                                            <Textarea value={row.rekomendasi} onChange={(e) => update(index, { rekomendasi: e.target.value })} rows={2} disabled={!can_write} />
                                        </MobileField>
                                        <MobileField label="Keterangan">
                                            <ChoiceChips
                                                options={['OPEN', 'CLOSE']}
                                                value={row.keterangan.toUpperCase()}
                                                onChange={(value) => value && update(index, { keterangan: value === 'CLOSE' ? 'close' : 'open' })}
                                                disabled={!can_write}
                                                tone={statusTone}
                                            />
                                        </MobileField>
                                        <div className="grid grid-cols-2 gap-3">
                                            {PHOTOS.map(([column, label]) => (
                                                <MobileField key={column} label={`Eviden ${label.toLowerCase()}`}>
                                                    <EvidencePhotoField
                                                        label={label}
                                                        url={row[`${column}_url`]}
                                                        file={row[`${column}_file`] ?? null}
                                                        onPick={(file) => pickPhoto(index, column, file)}
                                                        onRemove={() => removePhoto(index, column)}
                                                        disabled={!can_write}
                                                    />
                                                </MobileField>
                                            ))}
                                        </div>
                                        {can_write && (
                                            <Button variant="ghost" size="sm" onClick={() => removeRow(index)} className="self-start text-destructive">
                                                <Trash2 className="size-4" />
                                                Hapus temuan ini
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}

                    <div className="grid grid-cols-2 gap-2">
                        <Button variant="outline" onClick={exportExcel} disabled={exporting || savedRows.length === 0}>
                            <FileSpreadsheet className="size-4 text-emerald-600" />
                            {exporting ? 'Menyiapkan…' : 'Excel'}
                        </Button>
                        <Button variant="outline" onClick={() => window.open(routes.pdf(filters), '_blank')}>
                            <Download className="size-4 text-rose-600" />
                            PDF
                        </Button>
                    </div>
                </div>

                {can_write && (
                    <StickyActionBar>
                        <Button size="lg" variant="outline" onClick={addRow}>
                            <Plus className="size-4" />
                            Tambah
                        </Button>
                        <Button size="lg" onClick={save} disabled={saving || !dirty}>
                            <Save className="size-4" />
                            {saving ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </StickyActionBar>
                )}
            </>
        );
    }

    return (
        <>
            <Head title={`Laporan Unsafe Action & Unsafe Condition - ${unit.name}`} />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Laporan Unsafe Action & Unsafe Condition"
                    description="Identifikasi, pelaporan, dan evaluasi tindak lanjut temuan tindakan tidak aman (unsafe action) serta kondisi berbahaya (unsafe condition)."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" onClick={() => window.print()} className="gap-1.5">
                                <Printer className="size-4" />
                                Cetak
                            </Button>
                            <Button
                                variant="outline"
                                onClick={exportExcel}
                                disabled={exporting}
                                className="gap-1.5 text-emerald-600 hover:text-emerald-700 dark:text-emerald-400"
                            >
                                <FileSpreadsheet className="size-4" />
                                {exporting ? 'Mengunduh...' : 'Unduh Excel'}
                            </Button>
                            <Button variant="outline" onClick={() => window.open(routes.pdf(filters), '_blank')} className="gap-1.5">
                                <Download className="size-4" />
                                Cetak PDF
                            </Button>
                            {can_write && (
                                <Button onClick={save} disabled={saving || !dirty} className="gap-1.5">
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan…' : 'Simpan'}
                                </Button>
                            )}
                        </div>
                    }
                />

                <Card className="no-print flex flex-row flex-wrap items-end gap-3 p-3 py-3 shadow-xs">
                    {filterSelects}
                    {dirty && <span className="pb-2 text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>}
                </Card>

                {kpis}

                <div className="overflow-hidden rounded-lg border border-border bg-card p-4 shadow-xs md:p-6">
                    <div className="mb-4 overflow-hidden rounded border-2 border-black dark:border-white">
                        <table className="w-full border-collapse">
                            <tbody>
                                <tr>
                                    <td className="w-40 border-r border-black p-3 text-center align-middle dark:border-white">
                                        <img src="/logo/sidebar-logo.png" alt="PLN Nusantara Power" className="mx-auto max-h-12 max-w-[140px] object-contain" />
                                    </td>
                                    <td className="p-3 text-center align-middle">
                                        <div className="text-xs font-bold tracking-wider text-foreground uppercase">JASA PENDUKUNG TEKNIS UP KENDARI 11 SITE -KIT</div>
                                        <div className="mt-1 text-sm font-bold tracking-wide text-foreground uppercase">LAPORAN PROJECT {unit.name}</div>
                                        <div className="mt-1 text-sm font-extrabold tracking-wide text-primary uppercase">LAPORAN UNSAFE ACTION DAN UNSAFE CONDITION</div>
                                        <div className="mt-0.5 text-[11px] font-medium text-muted-foreground">Periode: {periodLabel}</div>
                                    </td>
                                    <td className="w-40 border-l border-black p-3 text-center align-middle dark:border-white">
                                        <img src="/logo/mkp.jpg" alt="Mitra Karya Prima" className="mx-auto max-h-12 max-w-[140px] object-contain" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1500px] border-collapse border-2 border-black text-xs dark:border-white">
                            <thead className="bg-[#ed7d31] dark:bg-[#ea7315]">
                                <tr>
                                    <th rowSpan={2} className={cn(th, 'w-10')}>NO</th>
                                    <th rowSpan={2} className={cn(th, 'w-32')}>PERIODE</th>
                                    <th rowSpan={2} className={cn(th, 'w-36')}>KATEGORI</th>
                                    <th rowSpan={2} className={cn(th, 'min-w-56')}>TEMUAN</th>
                                    <th rowSpan={2} className={cn(th, 'w-28')}>KONDISI</th>
                                    <th rowSpan={2} className={cn(th, 'min-w-48')}>TINDAK LANJUT</th>
                                    <th rowSpan={2} className={cn(th, 'min-w-48')}>REKOMENDASI</th>
                                    <th rowSpan={2} className={cn(th, 'w-36')}>LOKASI</th>
                                    <th rowSpan={2} className={cn(th, 'w-24')}>KETERANGAN</th>
                                    <th colSpan={2} className={th}>EVIDEN</th>
                                    {can_write && <th rowSpan={2} className={cn(th, 'no-print w-9')} />}
                                </tr>
                                <tr>
                                    {PHOTOS.map(([column, label]) => (
                                        <th key={column} className={cn(th, 'w-20')}>{label.toUpperCase()}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 && (
                                    <tr>
                                        <td colSpan={can_write ? 12 : 11} className={cn(td, 'p-6 text-center text-[13px] text-muted-foreground')}>
                                            Belum ada temuan untuk periode ini.{can_write && ' Tekan Tambah Baris untuk mengisi.'}
                                        </td>
                                    </tr>
                                )}
                                {rows.map((row, index) => {
                                    const error = rowError(index);

                                    return (
                                        <tr key={row.key} className="align-top hover:bg-muted/30">
                                            <td className={cn(td, 'p-1.5 text-center font-medium')}>{index + 1}</td>
                                            <td className={cn(td, 'p-0')}>
                                                <PdmCellSelect value={row.periode} onChange={(value) => update(index, { periode: value || 'MINGGU KE - 1' })} options={PERIODE_OPTIONS} className="h-8 justify-center" disabled={!can_write} />
                                            </td>
                                            <td className={cn(td, 'p-0')}>
                                                <PdmCellSelect
                                                    value={row.kategori}
                                                    onChange={(value) => update(index, { kategori: value || 'UNSAFE CONDITION' })}
                                                    options={KATEGORI}
                                                    className={cn('h-8 justify-center font-semibold', row.kategori === 'UNSAFE ACTION' ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400')}
                                                    disabled={!can_write}
                                                />
                                            </td>
                                            <td className={cn(td, 'p-0', error && 'bg-destructive/5 ring-1 ring-destructive ring-inset')} title={error}>
                                                <Textarea value={row.temuan} onChange={(e) => update(index, { temuan: e.target.value })} rows={1} className={cellText} placeholder="Uraian temuan…" disabled={!can_write} />
                                            </td>
                                            <td className={cn(td, 'p-0')}>
                                                <Input value={row.kondisi} onChange={(e) => update(index, { kondisi: e.target.value })} className={cn(cellInput, 'text-center')} placeholder="Bocor / Rusak" disabled={!can_write} />
                                            </td>
                                            <td className={cn(td, 'p-0')}>
                                                <Textarea value={row.tindak_lanjut} onChange={(e) => update(index, { tindak_lanjut: e.target.value })} rows={1} className={cellText} disabled={!can_write} />
                                            </td>
                                            <td className={cn(td, 'p-0')}>
                                                <Textarea value={row.rekomendasi} onChange={(e) => update(index, { rekomendasi: e.target.value })} rows={1} className={cellText} disabled={!can_write} />
                                            </td>
                                            <td className={cn(td, 'p-0')}>
                                                <Input value={row.lokasi} onChange={(e) => update(index, { lokasi: e.target.value })} className={cn(cellInput, 'text-center')} disabled={!can_write} />
                                            </td>
                                            <td className={cn(td, 'p-0')}>
                                                <PdmCellSelect
                                                    value={row.keterangan.toUpperCase()}
                                                    onChange={(value) => update(index, { keterangan: value === 'CLOSE' ? 'close' : 'open' })}
                                                    options={['OPEN', 'CLOSE']}
                                                    className={cn('h-8 justify-center font-semibold', row.keterangan === 'close' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400')}
                                                    disabled={!can_write}
                                                />
                                            </td>
                                            {PHOTOS.map(([column, label]) => (
                                                <td key={column} className={cn(td, 'p-1')}>
                                                    <div className="flex justify-center">
                                                        <EvidencePhotoField
                                                            label={label}
                                                            url={row[`${column}_url`]}
                                                            file={row[`${column}_file`] ?? null}
                                                            onPick={(file) => pickPhoto(index, column, file)}
                                                            onRemove={() => removePhoto(index, column)}
                                                            disabled={!can_write}
                                                            size="sm"
                                                        />
                                                    </div>
                                                </td>
                                            ))}
                                            {can_write && (
                                                <td className={cn(td, 'no-print p-0 text-center align-middle')}>
                                                    <Button variant="ghost" size="icon" className="size-7 text-muted-foreground hover:text-destructive" onClick={() => removeRow(index)} title="Hapus baris">
                                                        <Trash2 className="size-3.5" />
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {can_write && (
                        <div className="no-print mt-3 flex flex-wrap items-center gap-3">
                            <Button variant="outline" size="sm" className="gap-1.5" onClick={addRow}>
                                <Plus className="size-4" />
                                Tambah Baris
                            </Button>
                            <span className="text-[12px] text-muted-foreground">Baris baru tanpa uraian temuan tidak ikut disimpan. Temuan baru dikirim ke modul K3 saat disimpan.</span>
                        </div>
                    )}

                    <div className="mt-6 w-full max-w-sm">
                        <table className="w-full border-collapse border-2 border-black text-xs dark:border-white">
                            <thead className="bg-[#ed7d31] dark:bg-[#ea7315]">
                                <tr>
                                    <th className={cn(th, 'w-10')}>NO</th>
                                    <th className={cn(th, 'text-left')}>TEMUAN KONDISI</th>
                                    <th className={cn(th, 'w-20')}>JUMLAH</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td className={cn(td, 'p-2 text-center')}>1</td>
                                    <td className={cn(td, 'p-2 font-medium')}>UNSAFE ACTION</td>
                                    <td className={cn(td, 'p-2 text-center font-bold text-amber-600 dark:text-amber-400')}>{summary.unsafe_action_count}</td>
                                </tr>
                                <tr>
                                    <td className={cn(td, 'p-2 text-center')}>2</td>
                                    <td className={cn(td, 'p-2 font-medium')}>UNSAFE CONDITION</td>
                                    <td className={cn(td, 'p-2 text-center font-bold text-rose-600 dark:text-rose-400')}>{summary.unsafe_condition_count}</td>
                                </tr>
                                <tr className="bg-muted/50 font-bold">
                                    <td colSpan={2} className={cn(td, 'p-2 text-right')}>TOTAL TEMUAN:</td>
                                    <td className={cn(td, 'p-2 text-center font-extrabold text-foreground')}>{summary.total}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

function Kpi({ label, icon, note, children }: { label: string; icon: ReactNode; note: string; children: ReactNode }) {
    return (
        <Card className="gap-0 p-4 py-4 shadow-xs">
            <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-muted-foreground">{label}</span>
                {icon}
            </div>
            <div className="mt-2">{children}</div>
            <p className="mt-1 text-[11px] text-muted-foreground">{note}</p>
        </Card>
    );
}

function MobileField({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <div className="flex min-w-0 flex-col gap-1 text-[12px] text-muted-foreground">
            {label}
            {children}
            {error && <span className="text-[12px] text-destructive">{error}</span>}
        </div>
    );
}
