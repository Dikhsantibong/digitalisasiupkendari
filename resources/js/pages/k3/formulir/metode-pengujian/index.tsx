import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    ClipboardCheck,
    Plus,
    Trash2,
    Wrench,
    XCircle,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
    CompactSelect,
    FormulirDocumentLayout,
    MetaField,
    useFormulirDocument,
} from '@/components/k3/formulir-document';
import type { FormulirDocumentProps } from '@/components/k3/formulir-document';
import { OPERASI_MONTHS } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import { dashboard } from '@/routes';
import formulir from '@/routes/k3/formulir';
import metodePengujianRoutes from '@/routes/k3/formulir/metode-pengujian';
import type { IdName } from '@/types';

export type ServerRow = {
    id: number | null;
    no_urut: string | null;
    nama_peralatan: string;
    no_pengesahan: string | null;
    nama_kategori_alat: string | null;
    uji_visual: string | null;
    uji_fungsi: string | null;
    uji_beban: string | null;
    uji_hydro: string | null;
    ndt: string | null;
    uji_ultrasonic_thickness: string | null;
    uji_ketahanan: string | null;
    sertifikasi_terakhir: string | null;
    sertifikasi_ulang: string | null;
    keterangan: string | null;
    sort_order?: number;
};

type Row = ServerRow & { _key: number };

type HistoryItem = {
    year: number;
    month: number;
    items: number;
    updated_at: string | null;
};

type Props = FormulirDocumentProps & {
    unit: { id: number; name: string; service_unit_name: string | null };
    filters: { unit_id: number; month: number; year: number };
    options: {
        units: IdName[];
        years: number[];
        answers: string[];
    };
    rows: ServerRow[];
    meta: {
        catatan?: string | null;
    };
    has_saved: boolean;
    history: HistoryItem[];
    can_write: boolean;
};

const DEFAULT_METODE_OPTIONS = ['-', 'Memenuhi', 'Tidak Memenuhi', 'N/A'];

const TEST_FIELDS = [
    { field: 'uji_visual', label: 'Uji Visual' },
    { field: 'uji_fungsi', label: 'Uji Fungsi' },
    { field: 'uji_beban', label: 'Uji Beban' },
    { field: 'uji_hydro', label: 'Uji Hydro' },
    { field: 'ndt', label: 'NDT' },
    { field: 'uji_ultrasonic_thickness', label: 'Uji Ultrasonic Thickness' },
    { field: 'uji_ketahanan', label: 'Uji Ketahanan' },
] as const satisfies readonly { field: keyof ServerRow; label: string }[];

export default function MetodePengujianPeralatanPage(props: Props) {
    const { unit, filters, options, rows: initialRows, meta, has_saved, history, can_write } = props;

    const [keyCounter, setKeyCounter] = useState<number>(() => initialRows.length + 10);
    const [rows, setRows] = useState<Row[]>(() =>
        initialRows.map((r, i) => ({ ...r, _key: i + 1 })),
    );
    const [catatan, setCatatan] = useState<string>(() => meta.catatan ?? '');
    const [dirty, setDirty] = useState<boolean>(false);
    const [saving, setSaving] = useState<boolean>(false);
    const doc = useFormulirDocument(props, () => setDirty(true));

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;
    const testingOptions = options.answers?.length ? options.answers : DEFAULT_METODE_OPTIONS;

    const visit = (patch: Partial<Props['filters']>) => {
        if (dirty && !window.confirm('Ada perubahan belum disimpan. Yakin ingin berpindah halaman?')) {
            return;
        }

        router.get(
            metodePengujianRoutes.index().url,
            { ...filters, ...patch },
            { preserveState: false, preserveScroll: true },
        );
    };

    const goBack = () => {
        if (dirty && !window.confirm('Ada perubahan belum disimpan. Yakin ingin kembali?')) {
            return;
        }

        router.get(formulir.index().url, filters);
    };

    const updateRow = <K extends keyof ServerRow>(key: number, field: K, val: ServerRow[K]) => {
        setRows((prev) =>
            prev.map((r) => (r._key === key ? { ...r, [field]: val } : r)),
        );
        setDirty(true);
    };

    const addRow = () => {
        const nextKey = keyCounter + 1;
        setKeyCounter(nextKey);
        const newRow: Row = {
            id: null,
            _key: nextKey,
            no_urut: String(rows.length + 1),
            nama_peralatan: '',
            no_pengesahan: '-',
            nama_kategori_alat: '-',
            uji_visual: '-',
            uji_fungsi: '-',
            uji_beban: '-',
            uji_hydro: '-',
            ndt: '-',
            uji_ultrasonic_thickness: '-',
            uji_ketahanan: '-',
            sertifikasi_terakhir: '-',
            sertifikasi_ulang: '-',
            keterangan: '',
            sort_order: rows.length,
        };
        setRows((prev) => [...prev, newRow]);
        setDirty(true);
        toast.info('Baris peralatan baru ditambahkan.');
    };

    const deleteRow = (key: number) => {
        if (rows.length <= 1) {
            toast.error('Minimal harus ada satu baris data.');

            return;
        }

        setRows((prev) => {
            const filtered = prev.filter((r) => r._key !== key);

            return filtered.map((r, idx) => ({ ...r, no_urut: String(idx + 1) }));
        });
        setDirty(true);
        toast.success('Baris berhasil dihapus.');
    };

    const resetChanges = () => {
        if (window.confirm('Batalkan seluruh perubahan dan kembalikan ke data tersimpan?')) {
            setRows(initialRows.map((r, i) => ({ ...r, _key: i + 1 })));
            setCatatan(meta.catatan ?? '');
            doc.reset();
            setDirty(false);
            toast.info('Perubahan dibatalkan.');
        }
    };

    const save = (onSuccess?: () => void) => {
        if (!can_write) {
            return;
        }

        setSaving(true);

        router.post(
            metodePengujianRoutes.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((r, idx) => ({
                    no_urut: r.no_urut || String(idx + 1),
                    nama_peralatan: r.nama_peralatan.trim() || `Peralatan ${idx + 1}`,
                    no_pengesahan: r.no_pengesahan || '-',
                    nama_kategori_alat: r.nama_kategori_alat || '-',
                    uji_visual: r.uji_visual || '-',
                    uji_fungsi: r.uji_fungsi || '-',
                    uji_beban: r.uji_beban || '-',
                    uji_hydro: r.uji_hydro || '-',
                    ndt: r.ndt || '-',
                    uji_ultrasonic_thickness: r.uji_ultrasonic_thickness || '-',
                    uji_ketahanan: r.uji_ketahanan || '-',
                    sertifikasi_terakhir: r.sertifikasi_terakhir || '-',
                    sertifikasi_ulang: r.sertifikasi_ulang || '-',
                    keterangan: r.keterangan || null,
                    sort_order: idx,
                })),
                meta: {
                    catatan: catatan.trim(),
                },
                ...doc.payload(),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setDirty(false);
                    doc.refreshPreview();
                    onSuccess?.();
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const stats = useMemo(() => {
        let totalMemenuhi = 0;
        let totalTidakMemenuhi = 0;

        rows.forEach((r) => {
            TEST_FIELDS.forEach(({ field }) => {
                const val = r[field];

                if (val === 'Memenuhi') {
                    totalMemenuhi++;
                } else if (val === 'Tidak Memenuhi') {
                    totalTidakMemenuhi++;
                }
            });
        });

        return { totalPeralatan: rows.length, totalMemenuhi, totalTidakMemenuhi };
    }, [rows]);

    return (
        <>
            <Head title={`Formulir Metode Pengujian Peralatan - ${unit.name}`} />

            <FormulirDocumentLayout
                title="Formulir Metode Pengujian Peralatan"
                description={`Metode pemeriksaan fisik, ketahanan, dan sertifikasi peralatan keselamatan kerja di ${unit.name}. Atur penandatangan & layout, lalu cetak PDF resmi.`}
                unitName={unit.name}
                serviceUnitName={unit.service_unit_name}
                periodLabel={`${monthName} ${filters.year}`}
                hasSaved={has_saved}
                canWrite={can_write}
                dirty={dirty}
                saving={saving}
                doc={doc}
                documentProps={props}
                onSave={save}
                onReset={resetChanges}
                onBack={goBack}
                history={history.map((item) => ({
                    key: `${item.year}-${item.month}`,
                    label: `${OPERASI_MONTHS[item.month - 1]} ${item.year}`,
                    description: `${item.items} peralatan • diperbarui ${item.updated_at ?? '-'}`,
                    onOpen: () => visit({ month: item.month, year: item.year }),
                }))}
                periodControls={
                    <>
                        <MetaField label="Unit Pembangkit">
                            <CompactSelect
                                value={String(filters.unit_id)}
                                onChange={(v) => visit({ unit_id: Number(v) })}
                                options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                                disabled={options.units.length <= 1}
                            />
                        </MetaField>
                        <div className="grid grid-cols-2 gap-2">
                            <MetaField label="Bulan">
                                <CompactSelect
                                    value={String(filters.month)}
                                    onChange={(v) => visit({ month: Number(v) })}
                                    options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                                />
                            </MetaField>
                            <MetaField label="Tahun">
                                <CompactSelect
                                    value={String(filters.year)}
                                    onChange={(v) => visit({ year: Number(v) })}
                                    options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                                />
                            </MetaField>
                        </div>
                    </>
                }
            >
                {/* KPI ringkas */}
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <StatTile icon={<Wrench className="size-4" />} tone="bg-primary/10 text-primary" label="Total Peralatan" value={`${stats.totalPeralatan} Alat`} />
                    <StatTile icon={<CheckCircle2 className="size-4" />} tone="bg-emerald-500/10 text-emerald-600" label="Memenuhi" value={`${stats.totalMemenuhi} Uji`} />
                    <StatTile icon={<XCircle className="size-4" />} tone="bg-rose-500/10 text-rose-600" label="Tidak Memenuhi" value={`${stats.totalTidakMemenuhi} Uji`} />
                    <StatTile icon={<ClipboardCheck className="size-4" />} tone="bg-blue-500/10 text-blue-600" label="Periode" value={`${monthName} ${filters.year}`} />
                </div>

                {/* Toolbar */}
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border bg-muted/20 p-2.5 text-xs">
                    <div className="flex items-center gap-2">
                        <Badge variant="outline" className="border-primary/20 bg-primary/5 text-[10px] font-semibold text-primary">
                            MATRIKS METODE PEMERIKSAAN
                        </Badge>
                        <span className="text-muted-foreground">{rows.length} baris peralatan</span>
                    </div>
                    {can_write && (
                        <Button type="button" size="sm" variant="outline" onClick={addRow} className="h-7 gap-1 text-[11px]">
                            <Plus className="size-3.5" />
                            Tambah Peralatan
                        </Button>
                    )}
                </div>

                {/* Matrix Table */}
                <div className="overflow-x-auto rounded-md border border-border">
                    <table className="w-full min-w-[1500px] border-collapse text-xs">
                        <thead>
                            <tr className="bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-200 [&>th]:border [&>th]:border-border [&>th]:p-2 [&>th]:text-center [&>th]:font-bold">
                                <th rowSpan={2} className="w-12">No.</th>
                                <th rowSpan={2} className="min-w-[180px] text-left">Nama Peralatan</th>
                                <th rowSpan={2} className="min-w-[140px] text-left">No. Pengesahan</th>
                                <th rowSpan={2} className="min-w-[180px] text-left">Nama Kategori Alat</th>
                                <th colSpan={TEST_FIELDS.length} className="bg-blue-50/70 text-blue-900 dark:bg-blue-950/40 dark:text-blue-200">
                                    Metode Pemeriksaan
                                </th>
                                <th colSpan={2} className="bg-emerald-50/70 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                                    Sertifikasi
                                </th>
                                <th rowSpan={2} className="min-w-[200px] text-left">Keterangan</th>
                                {can_write && <th rowSpan={2} className="w-12">Aksi</th>}
                            </tr>
                            <tr className="bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-200 [&>th]:border [&>th]:border-border [&>th]:p-2 [&>th]:text-center [&>th]:text-[11px] [&>th]:font-semibold">
                                {TEST_FIELDS.map(({ field, label }) => (
                                    <th key={field} className="min-w-[110px] bg-blue-50/40 dark:bg-blue-950/20">{label}</th>
                                ))}
                                <th className="min-w-[110px] bg-emerald-50/40 dark:bg-emerald-950/20">Terakhir</th>
                                <th className="min-w-[110px] bg-emerald-50/40 dark:bg-emerald-950/20">Ulang</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row, idx) => (
                                <tr
                                    key={row._key}
                                    className="transition-colors hover:bg-muted/40 [&>td]:border [&>td]:border-border [&>td]:p-1.5 [&>td]:align-middle"
                                >
                                    <td className="text-center font-medium text-muted-foreground">
                                        {can_write ? (
                                            <Input
                                                value={row.no_urut ?? String(idx + 1)}
                                                onChange={(e) => updateRow(row._key, 'no_urut', e.target.value)}
                                                className="h-7 w-12 text-center text-xs"
                                            />
                                        ) : (
                                            row.no_urut ?? idx + 1
                                        )}
                                    </td>
                                    <td>
                                        <CellText
                                            value={row.nama_peralatan}
                                            onChange={(v) => updateRow(row._key, 'nama_peralatan', v)}
                                            placeholder="Nama peralatan..."
                                            canWrite={can_write}
                                            className="font-semibold"
                                        />
                                    </td>
                                    <td>
                                        <CellText
                                            value={row.no_pengesahan ?? '-'}
                                            onChange={(v) => updateRow(row._key, 'no_pengesahan', v)}
                                            placeholder="No. pengesahan..."
                                            canWrite={can_write}
                                        />
                                    </td>
                                    <td>
                                        <CellText
                                            value={row.nama_kategori_alat ?? '-'}
                                            onChange={(v) => updateRow(row._key, 'nama_kategori_alat', v)}
                                            placeholder="Kategori alat..."
                                            canWrite={can_write}
                                        />
                                    </td>
                                    {TEST_FIELDS.map(({ field }) => (
                                        <td key={field}>
                                            <MetodeCellSelect
                                                value={row[field]}
                                                onChange={(val) => updateRow(row._key, field, val)}
                                                options={testingOptions}
                                                disabled={!can_write}
                                            />
                                        </td>
                                    ))}
                                    <td>
                                        <CellText
                                            value={row.sertifikasi_terakhir ?? '-'}
                                            onChange={(v) => updateRow(row._key, 'sertifikasi_terakhir', v)}
                                            placeholder="-"
                                            canWrite={can_write}
                                            className="text-center"
                                        />
                                    </td>
                                    <td>
                                        <CellText
                                            value={row.sertifikasi_ulang ?? '-'}
                                            onChange={(v) => updateRow(row._key, 'sertifikasi_ulang', v)}
                                            placeholder="-"
                                            canWrite={can_write}
                                            className="text-center"
                                        />
                                    </td>
                                    <td>
                                        <CellText
                                            value={row.keterangan ?? ''}
                                            onChange={(v) => updateRow(row._key, 'keterangan', v)}
                                            placeholder="Catatan / keterangan..."
                                            canWrite={can_write}
                                        />
                                    </td>
                                    {can_write && (
                                        <td className="text-center">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => deleteRow(row._key)}
                                                className="size-7 text-muted-foreground hover:text-destructive"
                                                title="Hapus baris"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Legenda & Catatan */}
                <div className="grid gap-3 md:grid-cols-2">
                    <div className="space-y-1.5 rounded-md border border-border bg-muted/20 p-3 text-xs">
                        <span className="font-semibold text-foreground">Legenda Hasil Uji:</span>
                        <div className="flex flex-wrap gap-2 text-muted-foreground">
                            <span className="rounded border border-emerald-300 bg-emerald-50 px-2 py-0.5 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">Memenuhi</span>
                            <span className="rounded border border-rose-300 bg-rose-50 px-2 py-0.5 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300">Tidak Memenuhi</span>
                            <span className="rounded border border-amber-300 bg-amber-50 px-2 py-0.5 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">N/A</span>
                            <span className="rounded border border-input px-2 py-0.5">- = tidak diuji</span>
                        </div>
                    </div>
                    <div className="space-y-1.5 text-xs">
                        <Label htmlFor="catatan" className="font-semibold">
                            Catatan Tambahan &amp; Rekomendasi:
                        </Label>
                        {can_write ? (
                            <Textarea
                                id="catatan"
                                value={catatan}
                                onChange={(e) => {
                                    setCatatan(e.target.value);
                                    setDirty(true);
                                }}
                                placeholder="Tuliskan catatan khusus atau rekomendasi pemeliharaan/sertifikasi ulang..."
                                rows={3}
                                className="text-xs"
                            />
                        ) : (
                            <p className="italic text-muted-foreground">{catatan || 'Tidak ada catatan tambahan.'}</p>
                        )}
                    </div>
                </div>
            </FormulirDocumentLayout>
        </>
    );
}

function StatTile({ icon, tone, label, value }: { icon: React.ReactNode; tone: string; label: string; value: string }) {
    return (
        <Card className="flex flex-row items-center gap-3 p-3 py-3 shadow-none">
            <div className={`flex size-8 items-center justify-center rounded-lg ${tone}`}>{icon}</div>
            <div className="min-w-0">
                <div className="text-[10px] font-medium uppercase text-muted-foreground">{label}</div>
                <div className="truncate text-sm font-bold text-foreground">{value}</div>
            </div>
        </Card>
    );
}

function CellText({
    value,
    onChange,
    placeholder,
    canWrite,
    className = '',
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    canWrite: boolean;
    className?: string;
}) {
    if (!canWrite) {
        return <div className={className}>{value || '-'}</div>;
    }

    return (
        <Input
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder={placeholder}
            className={`h-7 text-xs ${className}`}
        />
    );
}

function MetodeCellSelect({
    value,
    onChange,
    options,
    disabled = false,
}: {
    value: string | null | undefined;
    onChange: (val: string) => void;
    options: string[];
    disabled?: boolean;
}) {
    const val = value && value.trim() !== '' ? value : '-';
    const choices = options.includes(val) ? options : [val, ...options];

    const getTriggerStyle = (v: string) => {
        const base =
            'h-7 w-full min-w-[95px] text-xs font-medium justify-center text-center transition-colors disabled:opacity-60';

        if (v === 'Memenuhi') {
            return `${base} border-emerald-300 bg-emerald-50/80 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300`;
        }

        if (v === 'Tidak Memenuhi') {
            return `${base} border-rose-300 bg-rose-50/80 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300`;
        }

        if (v === 'N/A') {
            return `${base} border-amber-300 bg-amber-50/80 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300`;
        }

        return `${base} border-input bg-background text-muted-foreground`;
    };

    return (
        <Select value={val} onValueChange={onChange} disabled={disabled}>
            <SelectTrigger size="sm" className={getTriggerStyle(val)}>
                <SelectValue placeholder="-" />
            </SelectTrigger>
            <SelectContent>
                {choices.map((opt) => (
                    <SelectItem key={opt} value={opt} className="text-xs">
                        {opt}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

MetodePengujianPeralatanPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Formulir K3 & Keamanan', href: formulir.index() },
        { title: 'Formulir Metode Pengujian Peralatan', href: metodePengujianRoutes.index() },
    ],
};
