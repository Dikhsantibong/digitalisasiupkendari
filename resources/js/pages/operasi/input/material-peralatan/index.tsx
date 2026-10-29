import type { Page } from '@inertiajs/core';
import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCheck,
    ClipboardCheck,
    Download,
    Package,
    Plus,
    Save,
    Trash2,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import { MobileRowEditor } from '@/components/mobile/row-editor';
import type { RowField } from '@/components/mobile/row-editor';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { PdmCellSelect } from '@/components/pdm/cell-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import harInput from '@/routes/operasi/input';
import materialPeralatanRoutes from '@/routes/operasi/input/material-peralatan';
import type { IdName } from '@/types';

type Kategori = 'PERALATAN' | 'MATERIAL';
type Kelengkapan = 'lengkap' | 'tidak_lengkap' | 'tidak_ada';

type MaterialItem = {
    id?: number;
    kategori: Kategori;
    kode_material: string;
    stok_code: string;
    nama_item: string;
    stok_awal: number;
    masuk: number;
    keluar: number;
    stok_akhir: number;
    satuan: string;
    harga_satuan: number;
    pemakaian_rata_rata: number;
    safety_stock: number;
    ilt: number;
    rop: number;
    roq: number;
    sort_order?: number;
    /** The operator's check: lengkap = normal; otherwise the uraian tells the abnormal condition. */
    kelengkapan: Kelengkapan | null;
    uraian_kondisi: string | null;
    checked_by_name?: string | null;
    checked_at?: string | null;
};

type NumericField =
    | 'stok_awal'
    | 'masuk'
    | 'keluar'
    | 'harga_satuan'
    | 'pemakaian_rata_rata'
    | 'safety_stock'
    | 'ilt'
    | 'rop'
    | 'roq';

const NUMERIC_FIELDS: NumericField[] = [
    'stok_awal',
    'masuk',
    'keluar',
    'harga_satuan',
    'pemakaian_rata_rata',
    'safety_stock',
    'ilt',
    'rop',
    'roq',
];

/** Phone form fields of a material / peralatan item (same columns as the table). */
const MATERIAL_FIELDS: RowField<MaterialItem>[] = [
    { key: 'nama_item', label: 'Nama material / consumable', type: 'textarea' },
    { key: 'kode_material', label: 'Kode material' },
    { key: 'stok_code', label: 'Stok code' },
    { key: 'satuan', label: 'Satuan' },
    { key: 'stok_awal', label: 'Stok awal', type: 'number', group: 'Stok' },
    { key: 'masuk', label: 'Material masuk', type: 'number', group: 'Stok' },
    { key: 'keluar', label: 'Material keluar', type: 'number', group: 'Stok' },
    { key: 'stok_akhir', label: 'Stok akhir', type: 'display', group: 'Stok' },
    { key: 'harga_satuan', label: 'Harga satuan', type: 'number', group: 'Perencanaan' },
    { key: 'pemakaian_rata_rata', label: 'Pemakaian rata-rata', type: 'number', group: 'Perencanaan' },
    { key: 'safety_stock', label: 'Safety stock', type: 'number', group: 'Perencanaan' },
    { key: 'ilt', label: 'ILT', type: 'number', group: 'Perencanaan' },
    { key: 'rop', label: 'ROP', type: 'number', group: 'Perencanaan' },
    { key: 'roq', label: 'RoQ', type: 'number', group: 'Perencanaan' },
];

/** Spreadsheet grid of the desktop table (black lines, yellow headers like the other input sheets). */
const xlTh = 'h-auto border border-black bg-yellow-200 px-1.5 py-1.5 text-center text-[11px] font-bold whitespace-normal text-black uppercase dark:border-white dark:bg-yellow-800 dark:text-yellow-50';
const xlTd = 'h-8 border border-black p-0 dark:border-white/60';
const xlInput =
    'h-8 rounded-none border-0 bg-transparent px-1.5 text-xs shadow-none focus:bg-yellow-50 focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-inset md:text-xs dark:bg-transparent dark:focus:bg-yellow-950/40';

const GROUP_LABEL: Record<Kategori, string> = { PERALATAN: 'A. Peralatan', MATERIAL: 'B. Material' };

const KELENGKAPAN: Kelengkapan[] = ['lengkap', 'tidak_lengkap', 'tidak_ada'];

const kelengkapanTone = (value: string) =>
    value === 'lengkap'
        ? 'border-emerald-600 bg-emerald-600 text-white'
        : value === 'tidak_lengkap'
          ? 'border-amber-500 bg-amber-500 text-white'
          : 'border-rose-600 bg-rose-600 text-white';

const isAbnormal = (item: MaterialItem) =>
    item.kelengkapan !== null && item.kelengkapan !== 'lengkap';

type Props = {
    filters: { unit_id: number; month: number; year: number };
    peralatan: MaterialItem[];
    material: MaterialItem[];
    summary: {
        total_peralatan: number;
        total_material: number;
        total_nilai_stok: number;
        low_stock_count: number;
        checked_count: number;
        tidak_normal_count: number;
    };
    options: {
        units: IdName[];
        years: number[];
        kelengkapan: Record<Kelengkapan, string>;
    };
    /** Prepares the list & stock (Koordinator / Project Leader). */
    can_manage: boolean;
    /** Checks each item's kelengkapan (operators, and whoever manages). */
    can_check: boolean;
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

/**
 * Input Operasi "Laporan Material dan Peralatan". The Koordinator / Project
 * Leader prepares the items and their stock; the operators then check each
 * item — Lengkap (kondisi normal), Tidak Lengkap or Tidak Ada with the uraian
 * of the abnormal condition.
 */
export default function MaterialPeralatanInput({
    filters,
    peralatan: initialPeralatan,
    material: initialMaterial,
    options,
    can_manage,
    can_check,
}: Props) {
    const [peralatan, setPeralatan] = useState<MaterialItem[]>(initialPeralatan);
    const [material, setMaterial] = useState<MaterialItem[]>(initialMaterial);
    const [saving, setSaving] = useState(false);
    const [isDirty, setIsDirty] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const compact = useCompactLayout();
    const canSave = can_manage || can_check;
    const allItems = [...peralatan, ...material];

    /** Takes the saved sheet back from the server after a save or delete. */
    const reload = (page: Page) => {
        const props = page.props as unknown as Props;
        setPeralatan(props.peralatan);
        setMaterial(props.material);
        setIsDirty(false);
    };

    const visit = (patch: Partial<Props['filters']>) => {
        if (isDirty && !window.confirm('Perubahan belum disimpan akan hilang. Lanjutkan?')) {
            return;
        }

        router.get(
            materialPeralatanRoutes.index().url,
            { ...filters, ...patch },
            { preserveScroll: true, replace: true },
        );
    };

    const setList = (category: Kategori, update: (list: MaterialItem[]) => MaterialItem[]) => {
        if (category === 'PERALATAN') {
            setPeralatan(update);
        } else {
            setMaterial(update);
        }

        setIsDirty(true);
    };

    const updateItem = (category: Kategori, index: number, patch: Partial<MaterialItem>) =>
        setList(category, (list) =>
            list.map((item, i) => {
                if (i !== index) {
                    return item;
                }

                const next = { ...item, ...patch };
                next.stok_akhir = Math.max(
                    0,
                    (Number(next.stok_awal) || 0) + (Number(next.masuk) || 0) - (Number(next.keluar) || 0),
                );

                return next;
            }),
        );

    const handleCellChange = (category: Kategori, index: number, field: keyof MaterialItem, value: string) =>
        updateItem(category, index, {
            [field]: (NUMERIC_FIELDS as string[]).includes(field) ? parseFloat(value) || 0 : value,
        });

    /** Lengkap = normal (the uraian is cleared); unchecking clears it too. */
    const setKelengkapan = (category: Kategori, index: number, value: string) => {
        const kelengkapan = (KELENGKAPAN as string[]).includes(value) ? (value as Kelengkapan) : null;
        updateItem(category, index, {
            kelengkapan,
            ...(kelengkapan === null || kelengkapan === 'lengkap' ? { uraian_kondisi: null } : {}),
        });
    };

    /** Marks every item not checked yet as Lengkap. */
    const markUncheckedLengkap = () => {
        const fill = (list: MaterialItem[]) =>
            list.map((item) => (item.kelengkapan === null ? { ...item, kelengkapan: 'lengkap' as const, uraian_kondisi: null } : item));
        setPeralatan(fill);
        setMaterial(fill);
        setIsDirty(true);
    };

    /** Moves a row to the other group (it lands at the end of that group). */
    const moveToGroup = (from: Kategori, index: number, to: Kategori) => {
        if (from === to) {
            return;
        }

        const item = (from === 'PERALATAN' ? peralatan : material)[index];
        setList(from, (list) => list.filter((_, i) => i !== index));
        setList(to, (list) => [...list, { ...item, kategori: to }]);
    };

    const [newRowGroup, setNewRowGroup] = useState<Kategori>('MATERIAL');

    const handleAddItem = (category: Kategori) =>
        setList(category, (list) => [
            ...list,
            {
                kategori: category,
                kode_material: '',
                stok_code: '',
                nama_item: category === 'PERALATAN' ? 'Item Peralatan Baru' : 'Item Material Baru',
                stok_awal: 0,
                masuk: 0,
                keluar: 0,
                stok_akhir: 0,
                satuan: category === 'PERALATAN' ? 'Unit' : 'Pcs',
                harga_satuan: 0,
                pemakaian_rata_rata: 0,
                safety_stock: 2,
                ilt: 3,
                rop: 2,
                roq: 2,
                kelengkapan: null,
                uraian_kondisi: null,
            },
        ]);

    const handleDeleteItem = (category: Kategori, index: number) => {
        const item = (category === 'PERALATAN' ? peralatan : material)[index];

        if (!item.id) {
            setList(category, (list) => list.filter((_, i) => i !== index));

            return;
        }

        const warning = isDirty ? ' Perubahan lain yang belum disimpan akan hilang.' : '';

        if (!window.confirm(`Hapus "${item.nama_item}" dari daftar?${warning}`)) {
            return;
        }

        router.delete(materialPeralatanRoutes.destroy(item.id).url, {
            preserveScroll: true,
            onSuccess: reload,
        });
    };

    const handleSave = () => {
        setSaving(true);
        setErrors({});

        const period = { unit_id: filters.unit_id, month: filters.month, year: filters.year };
        const onError = (errs: Record<string, string>) => {
            setErrors(errs);
            toast.error(Object.values(errs)[0] ?? 'Periksa kembali isian.');
        };

        if (can_manage) {
            router.post(
                materialPeralatanRoutes.store().url,
                { ...period, items: allItems },
                { preserveScroll: true, onSuccess: reload, onError, onFinish: () => setSaving(false) },
            );

            return;
        }

        router.post(
            materialPeralatanRoutes.check().url,
            {
                ...period,
                items: allItems
                    .filter((item) => item.id)
                    .map(({ id, kelengkapan, uraian_kondisi }) => ({ id, kelengkapan, uraian_kondisi })),
            },
            { preserveScroll: true, onSuccess: reload, onError, onFinish: () => setSaving(false) },
        );
    };

    const handlePdf = () => {
        const url = materialPeralatanRoutes.pdf({
            query: { unit_id: filters.unit_id, month: filters.month, year: filters.year },
        }).url;
        window.open(url, '_blank');
    };

    /** The server indexes the items as sent: peralatan first, then material. */
    const errorOf = (category: Kategori, index: number) =>
        errors[`items.${category === 'PERALATAN' ? index : peralatan.length + index}.uraian_kondisi`];

    const totalNilaiStok = allItems.reduce(
        (acc, item) => acc + (Number(item.stok_akhir) || 0) * (Number(item.harga_satuan) || 0),
        0,
    );
    const lowStockItems = allItems.filter(
        (i) => (Number(i.rop) || 0) > 0 && (Number(i.stok_akhir) || 0) <= (Number(i.rop) || 0),
    ).length;
    const checkedCount = allItems.filter((i) => i.kelengkapan !== null).length;
    const abnormalCount = allItems.filter(isAbnormal).length;
    const uncheckedCount = allItems.length - checkedCount;

    const checkFields: RowField<MaterialItem>[] = [
        {
            key: 'kelengkapan',
            label: 'Kelengkapan',
            type: 'select',
            options: KELENGKAPAN,
            optionLabels: options.kelengkapan,
            optionTone: kelengkapanTone,
            group: 'Pengecekan operator',
        },
        {
            key: 'uraian_kondisi',
            label: 'Uraian kondisi tidak normal',
            type: 'textarea',
            placeholder: 'Contoh: tinggal 1 dari 2, gagang patah…',
            group: 'Pengecekan operator',
            hidden: (item) => !isAbnormal(item),
        },
    ];

    // The operator checks first; the list & stock (prepared by the Koordinator / PL) are shown read-only.
    const mobileFields: RowField<MaterialItem>[] = can_manage
        ? [
              { key: 'kategori', label: 'Grup', type: 'select', options: ['PERALATAN', 'MATERIAL'], optionLabels: GROUP_LABEL },
              ...MATERIAL_FIELDS,
              ...checkFields,
          ]
        : [
              ...checkFields,
              ...MATERIAL_FIELDS.map((field) => ({ ...field, type: 'display' as const, group: field.group ?? 'Data item' })),
          ];

    const mobileChange = (category: Kategori) => (index: number, key: keyof MaterialItem & string, value: never) => {
        if (key === 'kelengkapan') {
            setKelengkapan(category, index, String(value ?? ''));
        } else if (key === 'uraian_kondisi') {
            updateItem(category, index, { uraian_kondisi: String(value ?? '') });
        } else if (key === 'kategori') {
            if (value) {
                moveToGroup(category, index, value as Kategori);
            }
        } else if (can_manage) {
            handleCellChange(category, index, key, String(value ?? ''));
        }
    };

    const statusLabel = (item: MaterialItem) =>
        item.kelengkapan === null
            ? 'Belum dicek'
            : item.kelengkapan === 'lengkap'
              ? 'Lengkap · Normal'
              : `${options.kelengkapan[item.kelengkapan]} · Tidak normal`;

    const mobileSection = (category: Kategori, title: string, items: MaterialItem[]) => (
        <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between px-0.5">
                <p className="text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">
                    {title} · {items.length} item
                </p>
                {can_manage && (
                    <Button variant="outline" size="sm" onClick={() => handleAddItem(category)}>
                        <Plus className="size-4" />
                        Tambah
                    </Button>
                )}
            </div>
            <MobileRowEditor
                rows={items}
                fields={mobileFields}
                canWrite={canSave}
                rowKey={(item, index) => item.id ?? `new-${index}`}
                title={(item) => item.nama_item || 'Item baru'}
                subtitle={(item) => `${statusLabel(item)} · Stok ${item.stok_akhir} ${item.satuan}`}
                onChange={mobileChange(category)}
                onRemove={can_manage ? (index) => handleDeleteItem(category, index) : undefined}
                removeLabel="Hapus item"
            />
        </div>
    );

    const numberCell = (category: Kategori, index: number, item: MaterialItem, field: NumericField, className = '') => (
        <TableCell className={cn(xlTd, 'text-right')}>
            {can_manage ? (
                <Input
                    type="number"
                    min="0"
                    step="any"
                    value={item[field] || ''}
                    onChange={(e) => handleCellChange(category, index, field, e.target.value)}
                    className={cn(xlInput, 'text-right font-mono', className)}
                />
            ) : (
                <span className={cn('block px-1.5 font-mono text-xs', className)}>{Number(item[field]) ? item[field] : '-'}</span>
            )}
        </TableCell>
    );

    const textCell = (category: Kategori, index: number, item: MaterialItem, field: 'kode_material' | 'stok_code' | 'nama_item' | 'satuan', className = '', placeholder?: string) => (
        <TableCell className={xlTd}>
            {can_manage ? (
                <Input
                    type="text"
                    value={item[field] ?? ''}
                    onChange={(e) => handleCellChange(category, index, field, e.target.value)}
                    className={cn(xlInput, className)}
                    placeholder={placeholder}
                />
            ) : (
                <span className={cn('block px-1.5 text-xs', className)}>{item[field]}</span>
            )}
        </TableCell>
    );

    const tableRow = (category: Kategori, item: MaterialItem, index: number) => {
        const error = errorOf(category, index);

        return (
            <TableRow key={`${category}-${item.id ?? `new-${index}`}`} className="hover:bg-muted/40">
                {textCell(category, index, item, 'kode_material', 'font-mono', 'Kode...')}
                {textCell(category, index, item, 'stok_code', 'font-mono', 'Stok Code...')}
                {textCell(category, index, item, 'nama_item', 'font-medium')}
                {numberCell(category, index, item, 'stok_awal')}
                {numberCell(category, index, item, 'masuk', 'text-primary')}
                {numberCell(category, index, item, 'keluar', 'text-amber-600 dark:text-amber-400')}
                <TableCell className={cn(xlTd, 'text-center font-bold')}>
                    <span className="inline-block rounded bg-rose-50 px-2 py-0.5 font-mono text-xs text-rose-600 dark:bg-rose-950/40 dark:text-rose-400">
                        {item.stok_akhir}
                    </span>
                </TableCell>
                {textCell(category, index, item, 'satuan', 'text-center')}
                {numberCell(category, index, item, 'harga_satuan')}
                {numberCell(category, index, item, 'pemakaian_rata_rata')}
                {numberCell(category, index, item, 'safety_stock')}
                {numberCell(category, index, item, 'ilt')}
                {numberCell(category, index, item, 'rop')}
                {numberCell(category, index, item, 'roq')}
                <TableCell className={xlTd}>
                    {can_check ? (
                        <PdmCellSelect
                            value={item.kelengkapan ? options.kelengkapan[item.kelengkapan] : ''}
                            onChange={(label) =>
                                setKelengkapan(
                                    category,
                                    index,
                                    KELENGKAPAN.find((key) => options.kelengkapan[key] === label) ?? '',
                                )
                            }
                            options={KELENGKAPAN.map((key) => options.kelengkapan[key])}
                            placeholder="Pilih…"
                            className={cn(
                                'h-8 justify-center font-semibold',
                                item.kelengkapan === 'lengkap' && 'text-emerald-600 dark:text-emerald-400',
                                item.kelengkapan === 'tidak_lengkap' && 'text-amber-600 dark:text-amber-400',
                                item.kelengkapan === 'tidak_ada' && 'text-rose-600 dark:text-rose-400',
                            )}
                        />
                    ) : (
                        <span className="block px-2 text-center text-xs">
                            {item.kelengkapan ? options.kelengkapan[item.kelengkapan] : '-'}
                        </span>
                    )}
                </TableCell>
                <TableCell className={cn(xlTd, error && 'bg-destructive/10')} title={item.checked_by_name ? `Dicek ${item.checked_by_name} · ${item.checked_at}` : undefined}>
                    {item.kelengkapan === null ? (
                        <span className="block px-1 text-center text-xs text-muted-foreground">Belum dicek</span>
                    ) : item.kelengkapan === 'lengkap' ? (
                        <span className="block text-center text-xs font-semibold text-emerald-600 dark:text-emerald-400">Normal</span>
                    ) : can_check ? (
                        <Input
                            value={item.uraian_kondisi ?? ''}
                            onChange={(e) => updateItem(category, index, { uraian_kondisi: e.target.value })}
                            placeholder="Uraian kondisi tidak normal…"
                            className={cn(xlInput, 'bg-amber-50 dark:bg-amber-950/30', error && 'ring-2 ring-destructive ring-inset')}
                            aria-invalid={Boolean(error)}
                        />
                    ) : (
                        <span className="text-xs text-rose-600 dark:text-rose-400">Tidak normal: {item.uraian_kondisi}</span>
                    )}
                </TableCell>
                {can_manage && (
                    <TableCell className={xlTd}>
                        <PdmCellSelect
                            value={GROUP_LABEL[category]}
                            onChange={(label) => {
                                const target = (Object.keys(GROUP_LABEL) as Kategori[]).find((key) => GROUP_LABEL[key] === label);

                                if (target) {
                                    moveToGroup(category, index, target);
                                }
                            }}
                            options={Object.values(GROUP_LABEL)}
                            className="h-8 justify-center"
                        />
                    </TableCell>
                )}
                {can_manage && (
                    <TableCell className={cn(xlTd, 'text-center')}>
                        <Button
                            size="icon"
                            variant="ghost"
                            className="size-7 text-muted-foreground hover:text-destructive"
                            onClick={() => handleDeleteItem(category, index)}
                            title="Hapus Baris"
                        >
                            <Trash2 className="size-3.5" />
                        </Button>
                    </TableCell>
                )}
            </TableRow>
        );
    };

    const sectionRow = (category: Kategori, title: string) => (
        <TableRow className="bg-[#d4edda] font-bold hover:bg-[#d4edda] dark:bg-emerald-950/40">
            <TableCell colSpan={can_manage ? 18 : 16} className={cn(xlTd, 'px-2 py-1.5 text-emerald-900 dark:text-emerald-300')}>
                <div className="flex items-center justify-between">
                    <span>{title}</span>
                    {can_manage && (
                        <Button
                            size="sm"
                            variant="outline"
                            className="h-6 bg-white/80 text-xs dark:bg-card"
                            onClick={() => handleAddItem(category)}
                        >
                            <Plus className="size-3" /> Tambah {category === 'PERALATAN' ? 'Peralatan' : 'Material'}
                        </Button>
                    )}
                </div>
            </TableCell>
        </TableRow>
    );

    const saveLabel = saving ? 'Menyimpan...' : can_manage ? 'Simpan Perubahan' : 'Simpan Pengecekan';

    return (
        <>
            <Head title="Input Material & Peralatan" />
            <div className={cn('flex flex-1 flex-col p-4 md:p-6', compact ? 'gap-3' : 'gap-5', compact && canSave && 'pb-28')}>
                {!compact && (
                <PageHeader
                    title="Laporan Material dan Peralatan"
                    description={
                        can_manage
                            ? 'Daftar & stok disusun Koordinator / Project Leader; operator mengecek kelengkapan tiap item (Lengkap = kondisi normal).'
                            : 'Cek kelengkapan tiap item yang disusun Koordinator / Project Leader: Lengkap = kondisi normal, Tidak Lengkap / Tidak Ada wajib diisi uraiannya.'
                    }
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" onClick={handlePdf}>
                                <Download className="size-4" />
                                Cetak PDF
                            </Button>
                            {can_check && !compact && uncheckedCount > 0 && (
                                <Button variant="outline" onClick={markUncheckedLengkap} className="gap-2">
                                    <CheckCheck className="size-4 text-emerald-600" />
                                    Sisa {uncheckedCount} item Lengkap
                                </Button>
                            )}
                            {canSave && !compact && (
                                <Button onClick={handleSave} disabled={saving || !isDirty} className="gap-2">
                                    <Save className="size-4" />
                                    {saveLabel}
                                </Button>
                            )}
                        </div>
                    }
                />
                )}

                {/* Filters */}
                {!compact && (
                <div className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect
                        label="Unit"
                        value={String(filters.unit_id)}
                        onChange={(value) => visit({ unit_id: Number(value) })}
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                        className={compact ? 'w-full' : undefined}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                        className={compact ? 'w-full' : undefined}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                        className={compact ? 'w-full' : undefined}
                    />
                </div>
                )}

                {/* KPI Cards */}
                {compact ? (
                    <p className="px-0.5 text-[12px] text-muted-foreground">
                        Dicek {checkedCount} / {allItems.length} item
                        {abnormalCount > 0 && <span className="text-rose-600"> · {abnormalCount} tidak normal</span>}
                    </p>
                ) : (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <Kpi label="Item Peralatan" icon={<Wrench className="size-4 text-primary" />} note="Bagian A. Peralatan">
                        <div className="text-2xl font-bold text-foreground">{peralatan.length}</div>
                    </Kpi>
                    <Kpi label="Item Material" icon={<Package className="size-4 text-emerald-500" />} note="Bagian B. Material">
                        <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{material.length}</div>
                    </Kpi>
                    <Kpi label="Pengecekan" icon={<ClipboardCheck className="size-4 text-primary" />} note={checkedCount === 0 ? 'Belum ada item dicek' : abnormalCount > 0 ? `${abnormalCount} item tidak normal` : 'Semua yang dicek normal'}>
                        <div className="text-2xl font-bold text-foreground">
                            {checkedCount}
                            <span className="text-sm font-normal text-muted-foreground"> / {allItems.length}</span>
                        </div>
                    </Kpi>
                    <Kpi label="Total Nilai Stok" icon={<Package className="size-4 text-amber-500" />} note="Berdasarkan harga satuan">
                        <div className="text-xl font-bold text-amber-600 dark:text-amber-400">Rp {totalNilaiStok.toLocaleString('id-ID')}</div>
                    </Kpi>
                    <Kpi label="Peringatan ROP" icon={<AlertTriangle className="size-4 text-rose-500" />} note="Stok ≤ Reorder Point">
                        <div className="text-2xl font-bold text-rose-600 dark:text-rose-400">
                            {lowStockItems} <span className="text-xs font-normal text-muted-foreground">item</span>
                        </div>
                    </Kpi>
                </div>
                )}

                {/* Main Table */}
                <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                    <div className={cn('flex flex-wrap items-center justify-between gap-2 border-b border-border bg-[#ea7315]/10 px-4 py-2.5', compact && 'hidden')}>
                        <div className="flex items-center gap-2">
                            <Package className="size-4 text-[#ea7315]" />
                            <span className="text-xs font-bold tracking-wider text-foreground uppercase">Laporan Material dan Peralatan</span>
                        </div>
                        {isDirty && (
                            <span className="animate-pulse text-xs font-medium text-amber-600 dark:text-amber-400">● Terdapat perubahan belum disimpan</span>
                        )}
                    </div>

                    {compact ? (
                        <div className="flex flex-col gap-4 bg-muted/30 p-2">
                            {mobileSection('PERALATAN', 'Peralatan', peralatan)}
                            {mobileSection('MATERIAL', 'Material / Consumable', material)}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table className="border-collapse border-2 border-black text-xs dark:border-white">
                                <TableHeader>
                                    <TableRow className="hover:bg-transparent">
                                        <TableHead className={cn(xlTh, 'w-28')}>Kode Material</TableHead>
                                        <TableHead className={cn(xlTh, 'w-24')}>stok Code</TableHead>
                                        <TableHead className={cn(xlTh, 'min-w-[220px]')}>Nama Material/Consumable</TableHead>
                                        <TableHead className={cn(xlTh, 'w-24')}>Stok Awal</TableHead>
                                        <TableHead className={cn(xlTh, 'w-24')}>Material Masuk</TableHead>
                                        <TableHead className={cn(xlTh, 'w-24')}>material Keluar</TableHead>
                                        <TableHead className={cn(xlTh, 'w-24')}>Stok Akhir</TableHead>
                                        <TableHead className={cn(xlTh, 'w-20')}>Satuan</TableHead>
                                        <TableHead className={cn(xlTh, 'w-28')}>HARGA SATUAN</TableHead>
                                        <TableHead className={cn(xlTh, 'w-24')}>PEMAKAIAN RATA-RATA</TableHead>
                                        <TableHead className={cn(xlTh, 'w-24')}>SAFETY STOCK</TableHead>
                                        <TableHead className={cn(xlTh, 'w-16')}>ILT</TableHead>
                                        <TableHead className={cn(xlTh, 'w-16')}>ROP</TableHead>
                                        <TableHead className={cn(xlTh, 'w-16')}>RoQ</TableHead>
                                        <TableHead className={cn(xlTh, 'w-32')}>KELENGKAPAN</TableHead>
                                        <TableHead className={cn(xlTh, 'min-w-[220px]')}>KONDISI</TableHead>
                                        {can_manage && <TableHead className={cn(xlTh, 'w-32')}>GRUP</TableHead>}
                                        {can_manage && <TableHead className={cn(xlTh, 'w-12')}>AKSI</TableHead>}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {sectionRow('PERALATAN', 'A. PERALATAN')}
                                    {peralatan.map((item, index) => tableRow('PERALATAN', item, index))}
                                    {sectionRow('MATERIAL', 'B. MATERIAL')}
                                    {material.map((item, index) => tableRow('MATERIAL', item, index))}
                                </TableBody>
                            </Table>
                        </div>
                    )}

                    {canSave && !compact && (
                        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-card p-3">
                            {can_manage && (
                                <div className="flex items-center gap-2">
                                    <PdmCellSelect
                                        value={GROUP_LABEL[newRowGroup]}
                                        onChange={(label) => setNewRowGroup(label === GROUP_LABEL.PERALATAN ? 'PERALATAN' : 'MATERIAL')}
                                        options={Object.values(GROUP_LABEL)}
                                        className="h-8 w-36 rounded-md border border-input"
                                    />
                                    <Button variant="outline" size="sm" className="gap-1.5" onClick={() => handleAddItem(newRowGroup)}>
                                        <Plus className="size-4" />
                                        Tambah Baris
                                    </Button>
                                </div>
                            )}
                            <span className="text-xs text-muted-foreground">
                                * Stok Akhir = Stok Awal + Material Masuk - Material Keluar · Kelengkapan Lengkap = kondisi Normal
                            </span>
                            <Button onClick={handleSave} disabled={saving || !isDirty} size="sm" className="gap-2">
                                <Save className="size-4" />
                                {saveLabel}
                            </Button>
                        </div>
                    )}
                </div>
            </div>

            {compact && canSave && (
                <StickyActionBar>
                    {uncheckedCount > 0 && (
                        <Button size="lg" variant="outline" onClick={markUncheckedLengkap}>
                            <CheckCheck className="size-4 text-emerald-600" />
                            Sisa Lengkap
                        </Button>
                    )}
                    <Button size="lg" onClick={handleSave} disabled={saving || !isDirty}>
                        <Save className="size-4" />
                        {saving ? 'Menyimpan…' : 'Simpan'}
                    </Button>
                </StickyActionBar>
            )}
        </>
    );
}

function Kpi({ label, icon, note, children }: { label: string; icon: ReactNode; note: string; children: ReactNode }) {
    return (
        <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
            <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-muted-foreground">{label}</span>
                {icon}
            </div>
            <div className="mt-2">{children}</div>
            <p className="mt-1 text-[11px] text-muted-foreground">{note}</p>
        </div>
    );
}

MaterialPeralatanInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: harInput.index() },
        { title: 'Material & Peralatan', href: materialPeralatanRoutes.index() },
    ],
};
