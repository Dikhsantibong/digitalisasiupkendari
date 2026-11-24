import { Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    Eye,
    FileText,
    Plus,
    Printer,
    RotateCcw,
    Save,
    ShieldAlert,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { DivisionFindings } from '@/components/k3/division-findings';
import type {
    DivisionFinding,
    DivisionSummary,
} from '@/components/k3/division-findings';
import {
    K3MobileFilterCard,
    K3MobileHeader,
} from '@/components/k3/mobile-page-shell';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { AutoWrapCellInput } from '@/components/ui/auto-wrap-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { dashboard } from '@/routes';
import k3Input from '@/routes/k3/input';
import k3KondisiK3 from '@/routes/k3/input/kondisi-k3';
import type { IdName } from '@/types';

type ServerRow = {
    id: number | null;
    no_urut: string | null;
    periode: string | null;
    kategori: string;
    temuan: string;
    kondisi: string | null;
    tindak_lanjut: string | null;
    rekomendasi: string | null;
    lokasi: string | null;
    keterangan: string | null;
    eviden_sebelum: string | null;
    eviden_sesudah: string | null;
    sort_order?: number;
};

type Row = ServerRow & { _key: number };

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    options: {
        units: IdName[];
        years: number[];
        categories: string[];
        statuses: string[];
    };
    rows: ServerRow[];
    meta: {
        catatan: string;
    };
    has_saved: boolean;
    can_write: boolean;
    /** Findings of Operasi, Pemeliharaan and Logistik & Gudang (read-only). */
    division_findings: DivisionFinding[];
    division_summary: DivisionSummary;
};

const hydrate = (rows: ServerRow[]): Row[] =>
    rows.map((r, i) => ({ ...r, _key: i }));

export default function K3KondisiK3Page({
    unit,
    filters,
    options,
    rows: initialRows,
    meta: initialMeta,
    has_saved,
    can_write,
    division_findings,
    division_summary,
}: Props) {
    const [rows, setRows] = useState<Row[]>(() => hydrate(initialRows));
    const [catatan, setCatatan] = useState<string>(initialMeta?.catatan ?? '');
    const [nextKey, setNextKey] = useState<number>(initialRows.length + 10);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const compact = useCompactLayout();
    const [previewImage, setPreviewImage] = useState<{
        url: string;
        title: string;
    } | null>(null);

    // Sync when filters change
    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSig, setLastSig] = useState(signature);

    if (lastSig !== signature) {
        setLastSig(signature);
        setRows(hydrate(initialRows));
        setCatatan(initialMeta?.catatan ?? '');
        setNextKey(initialRows.length + 10);
        setDirty(false);
    }

    const monthName =
        OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            k3KondisiK3.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const updateCell = (
        key: number,
        field: keyof ServerRow,
        value: string | null,
    ) => {
        setRows((prev) =>
            prev.map((r) => (r._key === key ? { ...r, [field]: value } : r)),
        );
        setDirty(true);
    };

    const removeRow = (key: number) => {
        setRows((prev) => prev.filter((r) => r._key !== key));
        setDirty(true);
    };

    const addRow = () => {
        const nextNo = String(rows.length + 1);
        const newRow: Row = {
            _key: nextKey,
            id: null,
            no_urut: nextNo,
            periode: '-',
            kategori: 'Unsafe Action',
            temuan: '',
            kondisi: '',
            tindak_lanjut: '',
            rekomendasi: '',
            lokasi: '',
            keterangan: 'Open',
            eviden_sebelum: null,
            eviden_sesudah: null,
            sort_order: rows.length,
        };
        setRows((prev) => [...prev, newRow]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    const resetChanges = () => {
        setRows(hydrate(initialRows));
        setCatatan(initialMeta?.catatan ?? '');
        setDirty(false);
        toast.info('Perubahan dibatalkan ke data awal.');
    };

    const save = () => {
        if (!can_write) {
            return;
        }

        setSaving(true);

        router.post(
            k3KondisiK3.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((r, idx) => ({
                    no_urut: r.no_urut || String(idx + 1),
                    periode: r.periode || '-',
                    kategori: r.kategori || 'Unsafe Action',
                    temuan: r.temuan,
                    kondisi: r.kondisi || null,
                    tindak_lanjut: r.tindak_lanjut || null,
                    rekomendasi: r.rekomendasi || null,
                    lokasi: r.lokasi || null,
                    keterangan: r.keterangan || 'Open',
                    eviden_sebelum: r.eviden_sebelum || null,
                    eviden_sesudah: r.eviden_sesudah || null,
                    sort_order: idx,
                })),
                meta: {
                    catatan: catatan.trim(),
                },
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDirty(false);
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    // Calculate Summary Statistics
    const stats = useMemo(() => {
        const validRows = rows.filter((r) => r.temuan.trim() !== '');
        const unsafeAction = validRows.filter(
            (r) => r.kategori === 'Unsafe Action',
        ).length;
        const unsafeCondition = validRows.filter(
            (r) => r.kategori === 'Unsafe Condition',
        ).length;
        const closed = validRows.filter((r) =>
            (r.keterangan || '').toLowerCase().includes('closed'),
        ).length;
        const open = validRows.filter((r) =>
            (r.keterangan || '').toLowerCase().includes('open'),
        ).length;

        return {
            total: validRows.length,
            unsafeAction,
            unsafeCondition,
            closed,
            open,
        };
    }, [rows]);

    return (
        <>
            <Head
                title={`Laporan Unsafe Action & Unsafe Condition - ${unit.name}`}
            />
            {compact ? (
                <div
                    className={`flex flex-1 flex-col gap-3 p-3 print:hidden ${can_write ? 'pb-24' : 'pb-20'}`}
                >
                    <K3MobileHeader
                        title="Kondisi K3 (Unsafe Action & Condition)"
                        backHref={k3Input.index().url}
                    />

                    <K3MobileFilterCard
                        unitId={filters.unit_id}
                        units={options.units}
                        onUnitChange={(v) => visit({ unit_id: v })}
                        month={filters.month}
                        onMonthChange={(v) => visit({ month: v })}
                        year={filters.year}
                        years={options.years}
                        onYearChange={(v) => visit({ year: v })}
                    />

                    {rows.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground">
                            Belum ada temuan pada periode ini.
                        </div>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {rows.map((row, idx) => (
                                <div
                                    key={row._key}
                                    className="flex flex-col gap-2.5 rounded-xl border border-border bg-card p-3"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-xs font-semibold text-primary">
                                            Temuan #{idx + 1}
                                        </span>
                                        {can_write && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    removeRow(row._key)
                                                }
                                                className="size-8 text-muted-foreground hover:text-destructive"
                                                aria-label="Hapus temuan"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        )}
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                            Kategori
                                            <Select
                                                value={row.kategori}
                                                onValueChange={(val) =>
                                                    updateCell(
                                                        row._key,
                                                        'kategori',
                                                        val,
                                                    )
                                                }
                                                disabled={!can_write}
                                            >
                                                <SelectTrigger className="h-9 text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.categories.map(
                                                        (cat) => (
                                                            <SelectItem
                                                                key={cat}
                                                                value={cat}
                                                                className="text-xs"
                                                            >
                                                                {cat}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </label>
                                        <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                            Status
                                            <Select
                                                value={row.keterangan ?? 'Open'}
                                                onValueChange={(val) =>
                                                    updateCell(
                                                        row._key,
                                                        'keterangan',
                                                        val,
                                                    )
                                                }
                                                disabled={!can_write}
                                            >
                                                <SelectTrigger className="h-9 text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {options.statuses.map(
                                                        (st) => (
                                                            <SelectItem
                                                                key={st}
                                                                value={st}
                                                                className="text-xs"
                                                            >
                                                                {st}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </label>
                                    </div>

                                    <div className="grid grid-cols-2 gap-2">
                                        <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                            Periode / Tgl
                                            <Input
                                                value={row.periode ?? ''}
                                                onChange={(e) =>
                                                    updateCell(
                                                        row._key,
                                                        'periode',
                                                        e.target.value,
                                                    )
                                                }
                                                disabled={!can_write}
                                                placeholder="01/08/2026"
                                                className="h-9 text-xs"
                                            />
                                        </label>
                                        <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                            Lokasi
                                            <Input
                                                value={row.lokasi ?? ''}
                                                onChange={(e) =>
                                                    updateCell(
                                                        row._key,
                                                        'lokasi',
                                                        e.target.value,
                                                    )
                                                }
                                                disabled={!can_write}
                                                placeholder="Lokasi temuan"
                                                className="h-9 text-xs"
                                            />
                                        </label>
                                    </div>

                                    <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                        Temuan
                                        <Textarea
                                            value={row.temuan}
                                            onChange={(e) =>
                                                updateCell(
                                                    row._key,
                                                    'temuan',
                                                    e.target.value,
                                                )
                                            }
                                            disabled={!can_write}
                                            rows={2}
                                            placeholder="Deskripsi temuan bahaya/tindakan…"
                                            className="text-xs"
                                        />
                                    </label>

                                    <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                        Kondisi &amp; Tindak Lanjut
                                        <Textarea
                                            value={row.tindak_lanjut ?? ''}
                                            onChange={(e) =>
                                                updateCell(
                                                    row._key,
                                                    'tindak_lanjut',
                                                    e.target.value,
                                                )
                                            }
                                            disabled={!can_write}
                                            rows={2}
                                            placeholder="Tindakan korektif langsung…"
                                            className="text-xs"
                                        />
                                    </label>

                                    <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                        Rekomendasi
                                        <Input
                                            value={row.rekomendasi ?? ''}
                                            onChange={(e) =>
                                                updateCell(
                                                    row._key,
                                                    'rekomendasi',
                                                    e.target.value,
                                                )
                                            }
                                            disabled={!can_write}
                                            placeholder="Rekomendasi pencegahan…"
                                            className="h-9 text-xs"
                                        />
                                    </label>

                                    <div className="grid grid-cols-2 gap-2 pt-1">
                                        <div className="flex flex-col items-center gap-1 rounded-lg border border-border bg-muted/20 p-2">
                                            <span className="text-[11px] font-medium text-muted-foreground">
                                                Eviden Sebelum
                                            </span>
                                            <EvidenCell
                                                value={row.eviden_sebelum}
                                                label="Eviden Sebelum"
                                                canWrite={can_write}
                                                onChange={(url) =>
                                                    updateCell(
                                                        row._key,
                                                        'eviden_sebelum',
                                                        url,
                                                    )
                                                }
                                                onPreview={(url, title) =>
                                                    setPreviewImage({
                                                        url,
                                                        title,
                                                    })
                                                }
                                            />
                                        </div>
                                        <div className="flex flex-col items-center gap-1 rounded-lg border border-border bg-muted/20 p-2">
                                            <span className="text-[11px] font-medium text-muted-foreground">
                                                Eviden Sesudah
                                            </span>
                                            <EvidenCell
                                                value={row.eviden_sesudah}
                                                label="Eviden Sesudah"
                                                canWrite={can_write}
                                                onChange={(url) =>
                                                    updateCell(
                                                        row._key,
                                                        'eviden_sesudah',
                                                        url,
                                                    )
                                                }
                                                onPreview={(url, title) =>
                                                    setPreviewImage({
                                                        url,
                                                        title,
                                                    })
                                                }
                                            />
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    <DivisionFindings
                        findings={division_findings}
                        summary={division_summary}
                        onPreview={setPreviewImage}
                    />

                    <div className="flex flex-col gap-1.5">
                        <span className="text-[13px] font-semibold text-foreground">
                            Catatan Evaluasi K3
                        </span>
                        <Textarea
                            value={catatan}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                setDirty(true);
                            }}
                            disabled={!can_write}
                            placeholder="Catatan evaluasi temuan…"
                            rows={3}
                            className="text-xs"
                        />
                    </div>

                    {can_write && (
                        <StickyActionBar>
                            <Button
                                onClick={save}
                                disabled={saving || !dirty}
                                className="gap-1.5"
                            >
                                <Save className="size-4" />
                                {saving ? 'Menyimpan…' : 'Simpan'}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={addRow}
                                className="gap-1.5"
                            >
                                <Plus className="size-4" />
                                Tambah
                            </Button>
                        </StickyActionBar>
                    )}
                </div>
            ) : (
                <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                    <PageHeader
                        title="Kondisi K3 (Unsafe Action & Unsafe Condition)"
                        description="Identifikasi, pelaporan temuan tindakan tidak aman dan kondisi berbahaya, tindak lanjut perbaikan, rekomendasi serta dokumentasi eviden sebelum/sesudah."
                        actions={
                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() => window.print()}
                                    className="no-print h-8 gap-1.5 text-xs"
                                >
                                    <Printer className="size-3.5" />
                                    Cetak
                                </Button>
                                {dirty && (
                                    <Button
                                        variant="outline"
                                        onClick={resetChanges}
                                        className="no-print h-8 gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                                    >
                                        <RotateCcw className="size-3.5" />
                                        Batal
                                    </Button>
                                )}
                                {can_write && (
                                    <Button
                                        onClick={save}
                                        disabled={saving || !dirty}
                                        className="no-print h-8 gap-1.5 text-xs font-semibold"
                                    >
                                        <Save className="size-3.5" />
                                        {saving ? 'Menyimpan…' : 'Simpan Data'}
                                    </Button>
                                )}
                            </div>
                        }
                    />

                    {/* Filter & Period Controls */}
                    <Card className="no-print flex flex-row flex-wrap items-end justify-between gap-3 p-3 py-3">
                        <div className="flex flex-wrap items-end gap-3">
                            <OperasiSelect
                                label="Unit Pembangkit"
                                value={String(filters.unit_id)}
                                onChange={(v) => visit({ unit_id: Number(v) })}
                                options={options.units.map((u) => ({
                                    value: String(u.id),
                                    label: u.name,
                                }))}
                            />
                            <OperasiSelect
                                label="Bulan"
                                value={String(filters.month)}
                                onChange={(v) => visit({ month: Number(v) })}
                                options={OPERASI_MONTHS.map((label, index) => ({
                                    value: String(index + 1),
                                    label,
                                }))}
                            />
                            <OperasiSelect
                                label="Tahun"
                                value={String(filters.year)}
                                onChange={(v) => visit({ year: Number(v) })}
                                options={options.years.map((y) => ({
                                    value: String(y),
                                    label: String(y),
                                }))}
                            />
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {can_write && (
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={addRow}
                                    className="h-8 gap-1.5 text-xs font-medium"
                                >
                                    <Plus className="size-3.5" />
                                    Tambah Temuan
                                </Button>
                            )}
                            {dirty ? (
                                <span className="flex items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                                    <AlertCircle className="size-3.5" />
                                    Ada perubahan belum disimpan
                                </span>
                            ) : has_saved ? (
                                <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                    <CheckCircle2 className="size-3.5 text-emerald-600" />
                                    Data tersimpan di server
                                </span>
                            ) : (
                                <span className="text-xs text-muted-foreground">
                                    Format formulir baru
                                </span>
                            )}
                        </div>
                    </Card>

                    {/* KPI Summary Cards */}
                    <div className="no-print grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <Card className="flex flex-row items-center gap-3 p-3 py-3">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <AlertTriangle className="size-5" />
                            </div>
                            <div>
                                <div className="text-[11px] font-medium text-muted-foreground uppercase">
                                    Total Temuan
                                </div>
                                <div className="text-base font-bold text-foreground">
                                    {stats.total} Kasus
                                </div>
                            </div>
                        </Card>
                        <Card className="flex flex-row items-center gap-3 p-3 py-3">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600">
                                <ShieldAlert className="size-5" />
                            </div>
                            <div>
                                <div className="text-[11px] font-medium text-muted-foreground uppercase">
                                    Unsafe Action
                                </div>
                                <div className="text-base font-bold text-amber-600 dark:text-amber-400">
                                    {stats.unsafeAction} Temuan
                                </div>
                            </div>
                        </Card>
                        <Card className="flex flex-row items-center gap-3 p-3 py-3">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-rose-500/10 text-rose-600">
                                <AlertCircle className="size-5" />
                            </div>
                            <div>
                                <div className="text-[11px] font-medium text-muted-foreground uppercase">
                                    Unsafe Condition
                                </div>
                                <div className="text-base font-bold text-rose-600 dark:text-rose-400">
                                    {stats.unsafeCondition} Temuan
                                </div>
                            </div>
                        </Card>
                        <Card className="flex flex-row items-center gap-3 p-3 py-3">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600">
                                <CheckCircle2 className="size-5" />
                            </div>
                            <div>
                                <div className="text-[11px] font-medium text-muted-foreground uppercase">
                                    Status Terselesaikan
                                </div>
                                <div className="text-base font-bold text-emerald-600 dark:text-emerald-400">
                                    {stats.closed} Closed / {stats.open} Open
                                </div>
                            </div>
                        </Card>
                    </div>

                    {/* Official Document Sheet */}
                    <Card className="gap-0 overflow-hidden p-0 py-0 shadow-xs">
                        {/* Header Banner matching the official Excel sheet */}
                        <div className="border-b border-border bg-muted/20 p-4 text-center">
                            <div className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Jasa Pendukung Teknis 11 & 6 Site - KIT
                            </div>
                            <div className="text-sm font-bold text-foreground uppercase">
                                Sentral PLTD / PLTG / PLTM {unit.name}
                            </div>
                            <div className="mt-0.5 text-base font-extrabold tracking-tight text-primary uppercase">
                                Laporan Unsafe Action dan Unsafe Condition
                                Periode Bulan {monthName} {filters.year}
                            </div>
                            <div className="text-[11px] text-muted-foreground uppercase">
                                (Identifikasi Bahaya, Tindak Lanjut, Rekomendasi
                                & Dokumentasi Eviden)
                            </div>
                        </div>

                        {/* Table */}
                        <div className="overflow-x-auto">
                            <table className="excel-sheet w-full min-w-[1600px] border-collapse text-xs">
                                <thead>
                                    <tr className="bg-amber-100/60 text-slate-800 dark:bg-amber-950/40 dark:text-slate-200 [&>th]:border [&>th]:border-border [&>th]:p-2 [&>th]:text-center [&>th]:font-bold">
                                        <th rowSpan={2} className="w-12">
                                            NO
                                        </th>
                                        <th rowSpan={2} className="w-28">
                                            PERIODE
                                        </th>
                                        <th rowSpan={2} className="w-36">
                                            KATEGORI
                                        </th>
                                        <th
                                            rowSpan={2}
                                            className="min-w-[200px] text-left"
                                        >
                                            TEMUAN
                                        </th>
                                        <th
                                            rowSpan={2}
                                            className="min-w-[180px] text-left"
                                        >
                                            KONDISI
                                        </th>
                                        <th
                                            rowSpan={2}
                                            className="min-w-[180px] text-left"
                                        >
                                            TINDAK LANJUT
                                        </th>
                                        <th
                                            rowSpan={2}
                                            className="min-w-[180px] text-left"
                                        >
                                            REKOMENDASI
                                        </th>
                                        <th
                                            rowSpan={2}
                                            className="min-w-[140px] text-left"
                                        >
                                            LOKASI
                                        </th>
                                        <th rowSpan={2} className="w-32">
                                            KETERANGAN
                                        </th>
                                        <th
                                            colSpan={2}
                                            className="bg-amber-200/60 dark:bg-amber-900/60"
                                        >
                                            EVIDEN
                                        </th>
                                        {can_write && (
                                            <th
                                                rowSpan={2}
                                                className="no-print w-12"
                                            >
                                                AKSI
                                            </th>
                                        )}
                                    </tr>
                                    <tr className="bg-amber-50 text-[11px] text-slate-700 dark:bg-slate-900/80 dark:text-slate-300 [&>th]:border [&>th]:border-border [&>th]:p-1.5 [&>th]:text-center [&>th]:font-semibold">
                                        <th className="w-36">SEBELUM</th>
                                        <th className="w-36">SESUDAH</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={can_write ? 12 : 11}
                                                className="border border-border p-6 text-center text-xs text-muted-foreground italic"
                                            >
                                                Belum ada laporan temuan. Klik
                                                "Tambah Temuan" untuk
                                                menambahkan baris baru.
                                            </td>
                                        </tr>
                                    ) : (
                                        rows.map((row, idx) => (
                                            <tr
                                                key={row._key}
                                                className="transition-colors hover:bg-muted/30 [&>td]:border [&>td]:border-border [&>td]:p-1.5"
                                            >
                                                {/* NO */}
                                                <td className="text-center font-medium">
                                                    {can_write ? (
                                                        <Input
                                                            type="text"
                                                            value={
                                                                row.no_urut ??
                                                                String(idx + 1)
                                                            }
                                                            onChange={(e) =>
                                                                updateCell(
                                                                    row._key,
                                                                    'no_urut',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="h-7 w-10 text-center text-xs"
                                                        />
                                                    ) : (
                                                        row.no_urut || idx + 1
                                                    )}
                                                </td>

                                                {/* PERIODE */}
                                                <td className="text-center">
                                                    {can_write ? (
                                                        <Input
                                                            type="text"
                                                            value={
                                                                row.periode ??
                                                                '-'
                                                            }
                                                            onChange={(e) =>
                                                                updateCell(
                                                                    row._key,
                                                                    'periode',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="01/08/2026"
                                                            className="h-7 w-full text-center text-xs"
                                                        />
                                                    ) : (
                                                        <span>
                                                            {row.periode || '-'}
                                                        </span>
                                                    )}
                                                </td>

                                                {/* KATEGORI */}
                                                <td>
                                                    {can_write ? (
                                                        <Select
                                                            value={row.kategori}
                                                            onValueChange={(
                                                                val,
                                                            ) =>
                                                                updateCell(
                                                                    row._key,
                                                                    'kategori',
                                                                    val,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger
                                                                size="sm"
                                                                className={`h-7 w-full text-xs font-medium ${
                                                                    row.kategori ===
                                                                    'Unsafe Action'
                                                                        ? 'border-amber-300 bg-amber-50/60 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
                                                                        : 'border-rose-300 bg-rose-50/60 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300'
                                                                }`}
                                                            >
                                                                <SelectValue placeholder="Pilih Kategori" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {options.categories.map(
                                                                    (cat) => (
                                                                        <SelectItem
                                                                            key={
                                                                                cat
                                                                            }
                                                                            value={
                                                                                cat
                                                                            }
                                                                            className="text-xs"
                                                                        >
                                                                            {
                                                                                cat
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className={`text-[11px] ${
                                                                row.kategori ===
                                                                'Unsafe Action'
                                                                    ? 'border-amber-400 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
                                                                    : 'border-rose-400 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300'
                                                            }`}
                                                        >
                                                            {row.kategori}
                                                        </Badge>
                                                    )}
                                                </td>

                                                {/* TEMUAN */}
                                                <td>
                                                    {can_write ? (
                                                        <Textarea
                                                            value={row.temuan}
                                                            onChange={(e) =>
                                                                updateCell(
                                                                    row._key,
                                                                    'temuan',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={2}
                                                            placeholder="Deskripsi temuan bahaya/tindakan..."
                                                            className="min-h-[48px] px-1.5 py-1 text-xs"
                                                        />
                                                    ) : (
                                                        <span className="px-1 whitespace-pre-line">
                                                            {row.temuan || '-'}
                                                        </span>
                                                    )}
                                                </td>

                                                {/* KONDISI */}
                                                <td>
                                                    {can_write ? (
                                                        <Textarea
                                                            value={
                                                                row.kondisi ??
                                                                ''
                                                            }
                                                            onChange={(e) =>
                                                                updateCell(
                                                                    row._key,
                                                                    'kondisi',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={2}
                                                            placeholder="Kondisi saat ditemukan..."
                                                            className="min-h-[48px] px-1.5 py-1 text-xs"
                                                        />
                                                    ) : (
                                                        <span className="px-1 whitespace-pre-line">
                                                            {row.kondisi || '-'}
                                                        </span>
                                                    )}
                                                </td>

                                                {/* TINDAK LANJUT */}
                                                <td>
                                                    {can_write ? (
                                                        <Textarea
                                                            value={
                                                                row.tindak_lanjut ??
                                                                ''
                                                            }
                                                            onChange={(e) =>
                                                                updateCell(
                                                                    row._key,
                                                                    'tindak_lanjut',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={2}
                                                            placeholder="Tindakan korektif langsung..."
                                                            className="min-h-[48px] px-1.5 py-1 text-xs"
                                                        />
                                                    ) : (
                                                        <span className="px-1 whitespace-pre-line">
                                                            {row.tindak_lanjut ||
                                                                '-'}
                                                        </span>
                                                    )}
                                                </td>

                                                {/* REKOMENDASI */}
                                                <td>
                                                    {can_write ? (
                                                        <Textarea
                                                            value={
                                                                row.rekomendasi ??
                                                                ''
                                                            }
                                                            onChange={(e) =>
                                                                updateCell(
                                                                    row._key,
                                                                    'rekomendasi',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={2}
                                                            placeholder="Rekomendasi pencegahan..."
                                                            className="min-h-[48px] px-1.5 py-1 text-xs"
                                                        />
                                                    ) : (
                                                        <span className="px-1 whitespace-pre-line">
                                                            {row.rekomendasi ||
                                                                '-'}
                                                        </span>
                                                    )}
                                                </td>

                                                {/* LOKASI */}
                                                <td className="align-middle whitespace-normal">
                                                    <AutoWrapCellInput
                                                        value={row.lokasi ?? ''}
                                                        onChange={(val) =>
                                                            updateCell(
                                                                row._key,
                                                                'lokasi',
                                                                val,
                                                            )
                                                        }
                                                        placeholder="Lokasi temuan..."
                                                        emptyFallback="-"
                                                        readOnly={!can_write}
                                                    />
                                                </td>

                                                {/* KETERANGAN */}
                                                <td>
                                                    {can_write ? (
                                                        <Select
                                                            value={
                                                                row.keterangan ??
                                                                'Open'
                                                            }
                                                            onValueChange={(
                                                                val,
                                                            ) =>
                                                                updateCell(
                                                                    row._key,
                                                                    'keterangan',
                                                                    val,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger
                                                                size="sm"
                                                                className={`h-7 w-full text-xs font-medium ${
                                                                    (
                                                                        row.keterangan ||
                                                                        ''
                                                                    ).toLowerCase() ===
                                                                    'closed'
                                                                        ? 'border-emerald-300 bg-emerald-50/60 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                                        : (
                                                                                row.keterangan ||
                                                                                ''
                                                                            ).toLowerCase() ===
                                                                            'on progress'
                                                                          ? 'border-blue-300 bg-blue-50/60 text-blue-800 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300'
                                                                          : 'border-amber-300 bg-amber-50/60 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
                                                                }`}
                                                            >
                                                                <SelectValue placeholder="Pilih Status" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {options.statuses.map(
                                                                    (st) => (
                                                                        <SelectItem
                                                                            key={
                                                                                st
                                                                            }
                                                                            value={
                                                                                st
                                                                            }
                                                                            className="text-xs"
                                                                        >
                                                                            {st}
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className={`text-[11px] ${
                                                                (
                                                                    row.keterangan ||
                                                                    ''
                                                                ).toLowerCase() ===
                                                                'closed'
                                                                    ? 'border-emerald-400 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                                    : (
                                                                            row.keterangan ||
                                                                            ''
                                                                        ).toLowerCase() ===
                                                                        'on progress'
                                                                      ? 'border-blue-400 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300'
                                                                      : 'border-amber-400 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300'
                                                            }`}
                                                        >
                                                            {row.keterangan ||
                                                                'Open'}
                                                        </Badge>
                                                    )}
                                                </td>

                                                {/* EVIDEN SEBELUM */}
                                                <td className="text-center">
                                                    <EvidenCell
                                                        value={
                                                            row.eviden_sebelum
                                                        }
                                                        label="Eviden Sebelum"
                                                        canWrite={can_write}
                                                        onChange={(url) =>
                                                            updateCell(
                                                                row._key,
                                                                'eviden_sebelum',
                                                                url,
                                                            )
                                                        }
                                                        onPreview={(
                                                            url,
                                                            title,
                                                        ) =>
                                                            setPreviewImage({
                                                                url,
                                                                title,
                                                            })
                                                        }
                                                    />
                                                </td>

                                                {/* EVIDEN SESUDAH */}
                                                <td className="text-center">
                                                    <EvidenCell
                                                        value={
                                                            row.eviden_sesudah
                                                        }
                                                        label="Eviden Sesudah"
                                                        canWrite={can_write}
                                                        onChange={(url) =>
                                                            updateCell(
                                                                row._key,
                                                                'eviden_sesudah',
                                                                url,
                                                            )
                                                        }
                                                        onPreview={(
                                                            url,
                                                            title,
                                                        ) =>
                                                            setPreviewImage({
                                                                url,
                                                                title,
                                                            })
                                                        }
                                                    />
                                                </td>

                                                {/* AKSI */}
                                                {can_write && (
                                                    <td className="no-print text-center">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                removeRow(
                                                                    row._key,
                                                                )
                                                            }
                                                            className="size-7 text-muted-foreground hover:text-rose-600"
                                                            title="Hapus baris ini"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    <DivisionFindings
                        findings={division_findings}
                        summary={division_summary}
                        onPreview={setPreviewImage}
                    />

                    {/* Catatan Tambahan (No Signature block) */}
                    <Card className="gap-2 p-4 py-4">
                        <div className="flex items-center gap-2">
                            <FileText className="size-4 text-primary" />
                            <Label className="text-sm font-semibold text-foreground">
                                Catatan Tambahan & Evaluasi K3:
                            </Label>
                        </div>
                        <Textarea
                            value={catatan}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                setDirty(true);
                            }}
                            disabled={!can_write}
                            placeholder="Tuliskan catatan evaluasi temuan Unsafe Action & Unsafe Condition, tindak lanjut tim manajemen, atau catatan khusus periode ini..."
                            rows={3}
                            className="text-xs"
                        />
                    </Card>

                    {/* Bottom Quick Save Footer */}
                    {can_write && (
                        <Card className="no-print flex flex-row items-center justify-between gap-3 p-3 py-3">
                            <div className="text-xs text-muted-foreground">
                                {dirty ? (
                                    <span className="font-medium text-amber-600 dark:text-amber-400">
                                        Ada perubahan yang belum disimpan ke
                                        database.
                                    </span>
                                ) : (
                                    <span>
                                        Semua data laporan telah tersimpan ke
                                        sistem.
                                    </span>
                                )}
                            </div>
                            <div className="flex items-center gap-2">
                                {dirty && (
                                    <Button
                                        variant="outline"
                                        onClick={resetChanges}
                                        className="gap-1.5 text-xs text-muted-foreground hover:text-foreground"
                                    >
                                        <RotateCcw className="size-3.5" />
                                        Batal
                                    </Button>
                                )}
                                <Button
                                    onClick={save}
                                    disabled={saving || !dirty}
                                    className="gap-1.5 text-xs font-semibold"
                                >
                                    <Save className="size-3.5" />
                                    {saving ? 'Menyimpan…' : 'Simpan Data'}
                                </Button>
                            </div>
                        </Card>
                    )}
                </div>
            )}

            {/* Lightbox / Preview Dialog */}
            <Dialog
                open={!!previewImage}
                onOpenChange={(open) => !open && setPreviewImage(null)}
            >
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="text-sm">
                            {previewImage?.title || 'Pratinjau Foto Eviden'}
                        </DialogTitle>
                    </DialogHeader>
                    {previewImage && (
                        <div className="flex justify-center p-2">
                            <img
                                src={previewImage.url}
                                alt={previewImage.title}
                                className="max-h-[70vh] rounded-md object-contain"
                            />
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

/**
 * Reusable cell component for uploading and viewing Eviden Sebelum / Sesudah.
 */
function EvidenCell({
    value,
    label,
    canWrite,
    onChange,
    onPreview,
}: {
    value: string | null;
    label: string;
    canWrite: boolean;
    onChange: (url: string | null) => void;
    onPreview: (url: string, title: string) => void;
}) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];

        if (!file) {
            return;
        }

        setUploading(true);
        const formData = new FormData();
        formData.append('file', file);

        try {
            const res = await fetch(k3KondisiK3.upload().url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find((row) => row.startsWith('XSRF-TOKEN='))
                            ?.split('=')[1] ?? '',
                    ),
                },
                body: formData,
            });

            if (!res.ok) {
                throw new Error('Upload failed');
            }

            const data = (await res.json()) as { url: string; path: string };
            onChange(data.url);
            toast.success(`Foto ${label} berhasil diunggah.`);
        } catch {
            toast.error(
                `Gagal mengunggah foto ${label}. Pastikan format gambar sesuai (maks 10MB).`,
            );
        } finally {
            setUploading(false);

            if (fileRef.current) {
                fileRef.current.value = '';
            }
        }
    };

    if (value) {
        return (
            <div className="flex items-center justify-center gap-1">
                <button
                    type="button"
                    onClick={() => onPreview(value, label)}
                    className="group relative size-10 overflow-hidden rounded border border-border bg-muted/40 hover:opacity-90"
                    title={`Klik untuk memperbesar ${label}`}
                >
                    <img
                        src={value}
                        alt={label}
                        className="size-full object-cover"
                    />
                    <div className="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition-opacity group-hover:opacity-100">
                        <Eye className="size-3.5 text-white" />
                    </div>
                </button>
                {canWrite && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => onChange(null)}
                        className="no-print size-6 text-muted-foreground hover:text-rose-600"
                        title="Hapus foto"
                    >
                        <X className="size-3" />
                    </Button>
                )}
            </div>
        );
    }

    if (!canWrite) {
        return <span className="text-muted-foreground italic">-</span>;
    }

    return (
        <div className="flex justify-center">
            <input
                ref={fileRef}
                type="file"
                accept="image/*"
                onChange={handleFileChange}
                className="hidden"
            />
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={uploading}
                onClick={() => fileRef.current?.click()}
                className="no-print h-7 gap-1 px-2 text-[11px] text-muted-foreground hover:text-foreground"
            >
                {uploading ? (
                    <span className="animate-spin text-xs">⏳</span>
                ) : (
                    <Upload className="size-3" />
                )}
                {uploading ? 'Unggah…' : 'Foto'}
            </Button>
        </div>
    );
}

K3KondisiK3Page.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input K3 & Keamanan', href: k3Input.index() },
        {
            title: 'Kondisi K3 (Unsafe Action & Unsafe Condition)',
            href: k3KondisiK3.index(),
        },
    ],
};
