import { Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    ClipboardCheck,
    FileText,
    HardHat,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
} from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
    K3MobileFilterCard,
    K3MobileHeader,
} from '@/components/k3/mobile-page-shell';
import { MobileEntryCard, MobileFormScreen } from '@/components/mobile/entry-flow';
import { StickyActionBar } from '@/components/mobile/sticky-action-bar';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
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
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { dashboard } from '@/routes';
import k3Input from '@/routes/k3/input';
import k3KesiapanApd from '@/routes/k3/input/kesiapan-apd';
import type { IdName } from '@/types';

type ServerRow = {
    id: number | null;
    kelompok: string;
    no_urut: string | null;
    inspeksi: string;
    apd_jumlah: string | null;
    kelayakan_apd: string | null;
    peralatan_jumlah: string | null;
    peralatan_kelayakan: string | null;
    sop_pnp: string | null;
    sop_vendor: string | null;
    p3k_ada: string | null;
    p3k_memenuhi: string | null;
    cara_kerja: string | null;
    keterangan: string | null;
    sort_order?: number;
};

type Row = ServerRow & { _key: number };

type Props = {
    unit: { id: number; name: string };
    filters: { unit_id: number; month: number; year: number };
    groups: string[];
    options: {
        units: IdName[];
        years: number[];
        answers: {
            kelayakan_apd: string[];
            peralatan_kelayakan: string[];
            sop_pnp: string[];
            sop_vendor: string[];
            p3k_ada: string[];
            p3k_memenuhi: string[];
            cara_kerja: string[];
        };
    };
    rows: ServerRow[];
    meta: {
        catatan: string;
    };
    has_saved: boolean;
    can_write: boolean;
};

const hydrate = (rows: ServerRow[]): Row[] =>
    rows.map((r, i) => ({ ...r, _key: i }));

export default function K3KesiapanApdPage({
    unit,
    filters,
    groups,
    options,
    rows: initialRows,
    meta: initialMeta,
    has_saved,
    can_write,
}: Props) {
    const [rows, setRows] = useState<Row[]>(() => hydrate(initialRows));
    const [catatan, setCatatan] = useState<string>(initialMeta?.catatan ?? '');
    const [nextKey, setNextKey] = useState<number>(initialRows.length + 10);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    // Phone (user, 2026-11-27): one item's form on its own screen.
    const [openKey, setOpenKey] = useState<number | null>(null);
    const compact = useCompactLayout();

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

    // Determine ordered groups (including any custom group in rows)
    const orderedGroups = useMemo(() => {
        const rowGroups = rows.map((r) => r.kelompok);
        const all = [
            ...groups,
            ...rowGroups.filter((g) => !groups.includes(g)),
        ];

        return all.filter((g, i, a) => a.indexOf(g) === i);
    }, [groups, rows]);

    const monthName =
        OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            k3KesiapanApd.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const updateCell = (key: number, field: keyof ServerRow, value: string) => {
        setRows((prev) =>
            prev.map((r) => (r._key === key ? { ...r, [field]: value } : r)),
        );
        setDirty(true);
    };

    const removeRow = (key: number) => {
        setRows((prev) => prev.filter((r) => r._key !== key));
        setDirty(true);
    };

    const addItem = (kelompok: string) => {
        const groupRows = rows.filter((r) => r.kelompok === kelompok);
        const newNo = String(groupRows.length + 1);
        const newRow: Row = {
            _key: nextKey,
            id: null,
            kelompok,
            no_urut: newNo,
            inspeksi: '',
            apd_jumlah: kelompok.includes('APD') ? '1 buah' : '-',
            kelayakan_apd: kelompok.includes('APD') ? 'Layak' : '-',
            peralatan_jumlah: kelompok.includes('Peralatan') ? '1 buah' : '-',
            peralatan_kelayakan: kelompok.includes('Peralatan')
                ? 'Layak'
                : kelompok.includes('Area')
                  ? 'Aman'
                  : '-',
            sop_pnp:
                kelompok.includes('Prosedur') || kelompok.includes('IK')
                    ? 'Memenuhi'
                    : '-',
            sop_vendor:
                kelompok.includes('Prosedur') || kelompok.includes('IK')
                    ? 'Memenuhi'
                    : '-',
            p3k_ada: kelompok.includes('P3K') ? 'Ada' : '-',
            p3k_memenuhi: kelompok.includes('P3K') ? 'Memenuhi' : '-',
            cara_kerja:
                kelompok.includes('Cara Kerja') || kelompok.includes('Ergonomi')
                    ? 'Ergonomi'
                    : '-',
            keterangan: '',
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

    const save = (onSaved?: () => void) => {
        if (!can_write) {
            return;
        }

        setSaving(true);

        const orderedRows = orderedGroups.flatMap((g) =>
            rows.filter((r) => r.kelompok === g),
        );

        router.post(
            k3KesiapanApd.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: orderedRows.map((r, idx) => ({
                    kelompok: r.kelompok,
                    no_urut: r.no_urut || String(idx + 1),
                    inspeksi: r.inspeksi.trim() || '-',
                    apd_jumlah: r.apd_jumlah || '-',
                    kelayakan_apd: r.kelayakan_apd || '-',
                    peralatan_jumlah: r.peralatan_jumlah || '-',
                    peralatan_kelayakan: r.peralatan_kelayakan || '-',
                    sop_pnp: r.sop_pnp || '-',
                    sop_vendor: r.sop_vendor || '-',
                    p3k_ada: r.p3k_ada || '-',
                    p3k_memenuhi: r.p3k_memenuhi || '-',
                    cara_kerja: r.cara_kerja || '-',
                    keterangan: r.keterangan || null,
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
                    onSaved?.();
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    // Quick helpers
    const setAllApdLayak = () => {
        setRows((prev) =>
            prev.map((r) =>
                r.kelompok.includes('APD') && r.inspeksi !== '-'
                    ? { ...r, kelayakan_apd: 'Layak' }
                    : r,
            ),
        );
        setDirty(true);
        toast.success('Semua status kelayakan APD diatur menjadi "Layak".');
    };

    // Helper for dropdown styling
    const getSelectClass = (val: string | null) => {
        const base =
            'h-7 w-full text-xs font-medium transition-colors disabled:opacity-60';

        if (
            val === 'Layak' ||
            val === 'Aman' ||
            val === 'Memenuhi' ||
            val === 'Ergonomi' ||
            val === 'Ada'
        ) {
            return `${base} border-emerald-300 bg-emerald-50/60 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300`;
        }

        if (
            val === 'Tidak Layak' ||
            val === 'Tidak memenuhi' ||
            val === 'Tidak ada' ||
            val === 'Tdk Ergonomi'
        ) {
            return `${base} border-rose-300 bg-rose-50/60 text-rose-800 dark:border-rose-800 dark:bg-rose-950/30 dark:text-rose-300`;
        }

        return `${base} border-input bg-background text-muted-foreground`;
    };

    // Calculate summary statistics
    const stats = useMemo(() => {
        const apdItems = rows.filter((r) => r.kelompok.includes('APD'));
        const apdLayak = apdItems.filter(
            (r) => r.kelayakan_apd === 'Layak',
        ).length;
        const apdTotal = apdItems.filter(
            (r) => r.apd_jumlah && r.apd_jumlah !== '-',
        ).length;

        const alatItems = rows.filter((r) => r.kelompok.includes('Peralatan'));
        const alatLayak = alatItems.filter(
            (r) => r.peralatan_kelayakan === 'Layak',
        ).length;

        return {
            totalItems: rows.length,
            apdLayak,
            apdTotal,
            alatLayak,
        };
    }, [rows]);

    return (
        <>
            <Head title={`Form Inspeksi K3 (Kesiapan APD) - ${unit.name}`} />
            {compact ? (
                <div
                    className={`flex flex-1 flex-col gap-3 p-3 print:hidden ${can_write ? 'pb-24' : 'pb-20'}`}
                >
                    <K3MobileHeader
                        title="Inspeksi K3 (Kesiapan APD)"
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

                    {can_write && (
                        <div className="grid grid-cols-2 gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={setAllApdLayak}
                                className="h-9 justify-center gap-1.5 text-xs"
                            >
                                <CheckCircle2 className="size-3.5 text-emerald-600" />
                                Set APD Layak
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => window.print()}
                                className="h-9 justify-center gap-1.5 text-xs"
                            >
                                <Printer className="size-3.5" />
                                Cetak
                            </Button>
                        </div>
                    )}

                    <div className="flex flex-col gap-4">
                        {orderedGroups.map((group) => {
                            const groupItems = rows.filter(
                                (r) => r.kelompok === group,
                            );
                            const isApd = group.includes('APD');
                            const isAlatOrArea =
                                group.includes('Peralatan') ||
                                group.includes('Area');
                            const isSop =
                                group.includes('Prosedur') ||
                                group.includes('IK');
                            const isP3k = group.includes('P3K');
                            const isErgonomi =
                                group.includes('Cara Kerja') ||
                                group.includes('Ergonomi');

                            return (
                                <div
                                    key={group}
                                    className="flex flex-col gap-2"
                                >
                                    <p className="text-[12px] font-semibold tracking-wide text-muted-foreground uppercase">
                                        {group}
                                    </p>
                                    {groupItems.length === 0 ? (
                                        <p className="rounded-xl border border-dashed border-border p-4 text-center text-xs text-muted-foreground">
                                            Belum ada item dalam kelompok ini.
                                        </p>
                                    ) : (
                                        <div className="flex flex-col gap-2.5">
                                            {groupItems.map((row) => (
                                                <Fragment key={row._key}>
                                                    <MobileEntryCard
                                                        leading={row.no_urut ?? '-'}
                                                        title={row.inspeksi || 'Item belum diisi'}
                                                        subtitle={[row.kelayakan_apd, row.peralatan_kelayakan, row.keterangan].filter((v) => v && v !== '-').join(' · ') || undefined}
                                                        onClick={() => setOpenKey(row._key)}
                                                    />
                                                    {openKey === row._key && (
                                                        <MobileFormScreen
                                                            title={row.inspeksi || 'Item baru'}
                                                            subtitle={group}
                                                            onBack={() => setOpenKey(null)}
                                                            canWrite={can_write}
                                                            onSave={() => save(() => setOpenKey(null))}
                                                            saving={saving}
                                                            saveDisabled={!dirty}
                                                        >
                                                <div
                                                    className="flex flex-col gap-2.5 rounded-xl border border-border bg-card p-3"
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <span className="w-6 shrink-0 text-center text-xs font-semibold text-muted-foreground">
                                                            {row.no_urut ?? '-'}
                                                        </span>
                                                        <Input
                                                            value={row.inspeksi}
                                                            onChange={(e) =>
                                                                updateCell(
                                                                    row._key,
                                                                    'inspeksi',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            disabled={
                                                                !can_write
                                                            }
                                                            placeholder="Nama item inspeksi…"
                                                            className="h-9 flex-1 text-xs font-medium"
                                                        />
                                                        {can_write && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() =>
                                                                    removeRow(
                                                                        row._key,
                                                                    )
                                                                }
                                                                className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                                                                aria-label="Hapus item"
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        )}
                                                    </div>

                                                    {isApd && (
                                                        <div className="grid grid-cols-2 gap-2">
                                                            <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                                                Jumlah Item
                                                                <Input
                                                                    value={
                                                                        row.apd_jumlah ??
                                                                        '-'
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        updateCell(
                                                                            row._key,
                                                                            'apd_jumlah',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    disabled={
                                                                        !can_write
                                                                    }
                                                                    className="h-9 text-xs"
                                                                />
                                                            </label>
                                                            <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                                                Kelayakan APD
                                                                <ApdCellSelect
                                                                    value={
                                                                        row.kelayakan_apd
                                                                    }
                                                                    onChange={(
                                                                        val,
                                                                    ) =>
                                                                        updateCell(
                                                                            row._key,
                                                                            'kelayakan_apd',
                                                                            val,
                                                                        )
                                                                    }
                                                                    options={
                                                                        options
                                                                            .answers
                                                                            .kelayakan_apd
                                                                    }
                                                                    className={
                                                                        getSelectClass(
                                                                            row.kelayakan_apd,
                                                                        ) +
                                                                        ' h-9'
                                                                    }
                                                                />
                                                            </label>
                                                        </div>
                                                    )}

                                                    {!isApd && isAlatOrArea && (
                                                        <div className="grid grid-cols-2 gap-2">
                                                            {group.includes(
                                                                'Peralatan',
                                                            ) && (
                                                                <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                                                    Jumlah Item
                                                                    <Input
                                                                        value={
                                                                            row.peralatan_jumlah ??
                                                                            '-'
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateCell(
                                                                                row._key,
                                                                                'peralatan_jumlah',
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            !can_write
                                                                        }
                                                                        className="h-9 text-xs"
                                                                    />
                                                                </label>
                                                            )}
                                                            <label
                                                                className={`flex flex-col gap-1 text-[11.5px] text-muted-foreground ${!group.includes('Peralatan') ? 'col-span-2' : ''}`}
                                                            >
                                                                Kelayakan /
                                                                Kondisi
                                                                <ApdCellSelect
                                                                    value={
                                                                        row.peralatan_kelayakan
                                                                    }
                                                                    onChange={(
                                                                        val,
                                                                    ) =>
                                                                        updateCell(
                                                                            row._key,
                                                                            'peralatan_kelayakan',
                                                                            val,
                                                                        )
                                                                    }
                                                                    options={
                                                                        options
                                                                            .answers
                                                                            .peralatan_kelayakan
                                                                    }
                                                                    className={
                                                                        getSelectClass(
                                                                            row.peralatan_kelayakan,
                                                                        ) +
                                                                        ' h-9'
                                                                    }
                                                                />
                                                            </label>
                                                        </div>
                                                    )}

                                                    {isSop && (
                                                        <div className="grid grid-cols-2 gap-2">
                                                            <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                                                SOP PNP
                                                                <ApdCellSelect
                                                                    value={
                                                                        row.sop_pnp
                                                                    }
                                                                    onChange={(
                                                                        val,
                                                                    ) =>
                                                                        updateCell(
                                                                            row._key,
                                                                            'sop_pnp',
                                                                            val,
                                                                        )
                                                                    }
                                                                    options={
                                                                        options
                                                                            .answers
                                                                            .sop_pnp
                                                                    }
                                                                    className={
                                                                        getSelectClass(
                                                                            row.sop_pnp,
                                                                        ) +
                                                                        ' h-9'
                                                                    }
                                                                />
                                                            </label>
                                                            <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                                                SOP Vendor
                                                                <ApdCellSelect
                                                                    value={
                                                                        row.sop_vendor
                                                                    }
                                                                    onChange={(
                                                                        val,
                                                                    ) =>
                                                                        updateCell(
                                                                            row._key,
                                                                            'sop_vendor',
                                                                            val,
                                                                        )
                                                                    }
                                                                    options={
                                                                        options
                                                                            .answers
                                                                            .sop_vendor
                                                                    }
                                                                    className={
                                                                        getSelectClass(
                                                                            row.sop_vendor,
                                                                        ) +
                                                                        ' h-9'
                                                                    }
                                                                />
                                                            </label>
                                                        </div>
                                                    )}

                                                    {isP3k && (
                                                        <div className="grid grid-cols-2 gap-2">
                                                            <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                                                Ketersediaan P3K
                                                                <ApdCellSelect
                                                                    value={
                                                                        row.p3k_ada
                                                                    }
                                                                    onChange={(
                                                                        val,
                                                                    ) =>
                                                                        updateCell(
                                                                            row._key,
                                                                            'p3k_ada',
                                                                            val,
                                                                        )
                                                                    }
                                                                    options={
                                                                        options
                                                                            .answers
                                                                            .p3k_ada
                                                                    }
                                                                    className={
                                                                        getSelectClass(
                                                                            row.p3k_ada,
                                                                        ) +
                                                                        ' h-9'
                                                                    }
                                                                />
                                                            </label>
                                                            <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                                                Standar P3K
                                                                <ApdCellSelect
                                                                    value={
                                                                        row.p3k_memenuhi
                                                                    }
                                                                    onChange={(
                                                                        val,
                                                                    ) =>
                                                                        updateCell(
                                                                            row._key,
                                                                            'p3k_memenuhi',
                                                                            val,
                                                                        )
                                                                    }
                                                                    options={
                                                                        options
                                                                            .answers
                                                                            .p3k_memenuhi
                                                                    }
                                                                    className={
                                                                        getSelectClass(
                                                                            row.p3k_memenuhi,
                                                                        ) +
                                                                        ' h-9'
                                                                    }
                                                                />
                                                            </label>
                                                        </div>
                                                    )}

                                                    {isErgonomi && (
                                                        <label className="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                                                            Cara Kerja Ergonomi
                                                            <ApdCellSelect
                                                                value={
                                                                    row.cara_kerja
                                                                }
                                                                onChange={(
                                                                    val,
                                                                ) =>
                                                                    updateCell(
                                                                        row._key,
                                                                        'cara_kerja',
                                                                        val,
                                                                    )
                                                                }
                                                                options={
                                                                    options
                                                                        .answers
                                                                        .cara_kerja
                                                                }
                                                                className={
                                                                    getSelectClass(
                                                                        row.cara_kerja,
                                                                    ) + ' h-9'
                                                                }
                                                            />
                                                        </label>
                                                    )}

                                                    <Input
                                                        value={
                                                            row.keterangan ?? ''
                                                        }
                                                        onChange={(e) =>
                                                            updateCell(
                                                                row._key,
                                                                'keterangan',
                                                                e.target.value,
                                                            )
                                                        }
                                                        disabled={!can_write}
                                                        placeholder="Keterangan…"
                                                        className="h-9 text-xs"
                                                    />
                                                </div>
                                                        </MobileFormScreen>
                                                    )}
                                                </Fragment>
                                            ))}
                                        </div>
                                    )}
                                    {can_write && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setOpenKey(nextKey);
                                                addItem(group);
                                            }}
                                            className="h-9 w-full justify-center gap-1.5 text-xs"
                                        >
                                            <Plus className="size-4" />
                                            Tambah — {group}
                                        </Button>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <span className="text-[13px] font-semibold text-foreground">
                            Catatan
                        </span>
                        <Textarea
                            value={catatan}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                setDirty(true);
                            }}
                            disabled={!can_write}
                            placeholder="Catatan inspeksi kesiapan APD…"
                            rows={3}
                            className="text-xs"
                        />
                    </div>

                    {can_write && (
                        <StickyActionBar>
                            {dirty ? (
                                <Button
                                    variant="outline"
                                    onClick={resetChanges}
                                    disabled={saving}
                                    className="gap-1.5"
                                >
                                    <RotateCcw className="size-4" />
                                    Reset
                                </Button>
                            ) : (
                                <div aria-hidden="true" />
                            )}
                            <Button
                                onClick={() => save()}
                                disabled={saving || !dirty}
                                className="gap-1.5"
                            >
                                <Save className="size-4" />
                                {saving ? 'Menyimpan…' : 'Simpan'}
                            </Button>
                        </StickyActionBar>
                    )}
                </div>
            ) : (
                <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                    <PageHeader
                        title="Form Inspeksi K3 (Kesiapan APD)"
                        description="Pemeriksaan kelaikan APD, peralatan kerja, SOP/IK, kesiapan P3K, kondisi area kerja, dan cara kerja ergonomi."
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
                                        onClick={() => save()}
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
                                    onClick={setAllApdLayak}
                                    className="h-8 gap-1.5 text-xs"
                                    title="Set semua APD berstatus Layak"
                                >
                                    <CheckCircle2 className="size-3.5 text-emerald-600" />
                                    Set APD Layak
                                </Button>
                            )}
                            {dirty ? (
                                <span className="flex items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                                    <AlertCircle className="size-3.5" />
                                    Ada perubahan belum disimpan
                                </span>
                            ) : has_saved ? (
                                <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                    <ClipboardCheck className="size-3.5 text-emerald-600" />
                                    Data tersimpan di server
                                </span>
                            ) : (
                                <span className="text-xs text-muted-foreground">
                                    Menggunakan format template standar
                                </span>
                            )}
                        </div>
                    </Card>

                    {/* Quick Info Cards */}
                    <div className="no-print grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <Card className="flex flex-row items-center gap-3 p-3 py-3">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <HardHat className="size-5" />
                            </div>
                            <div>
                                <div className="text-[11px] font-medium text-muted-foreground uppercase">
                                    Total Item APD
                                </div>
                                <div className="text-base font-bold text-foreground">
                                    {stats.apdTotal} Item Aktif
                                </div>
                            </div>
                        </Card>
                        <Card className="flex flex-row items-center gap-3 p-3 py-3">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600">
                                <CheckCircle2 className="size-5" />
                            </div>
                            <div>
                                <div className="text-[11px] font-medium text-muted-foreground uppercase">
                                    APD Kondisi Layak
                                </div>
                                <div className="text-base font-bold text-emerald-600 dark:text-emerald-400">
                                    {stats.apdLayak} Item
                                </div>
                            </div>
                        </Card>
                        <Card className="flex flex-row items-center gap-3 p-3 py-3">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-blue-500/10 text-blue-600">
                                <ClipboardCheck className="size-5" />
                            </div>
                            <div>
                                <div className="text-[11px] font-medium text-muted-foreground uppercase">
                                    Peralatan Layak
                                </div>
                                <div className="text-base font-bold text-blue-600 dark:text-blue-400">
                                    {stats.alatLayak} Unit
                                </div>
                            </div>
                        </Card>
                        <Card className="flex flex-row items-center gap-3 p-3 py-3">
                            <div className="flex size-9 items-center justify-center rounded-lg bg-purple-500/10 text-purple-600">
                                <FileText className="size-5" />
                            </div>
                            <div>
                                <div className="text-[11px] font-medium text-muted-foreground uppercase">
                                    Total Baris Checklist
                                </div>
                                <div className="text-base font-bold text-foreground">
                                    {stats.totalItems} Baris
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
                                Form Inspeksi K3 Periode {monthName}{' '}
                                {filters.year}
                            </div>
                            <div className="text-[11px] text-muted-foreground uppercase">
                                (Kesiapan Alat Pelindung Diri, Peralatan Kerja,
                                SOP/IK, P3K & Ergonomi)
                            </div>
                        </div>

                        {/* Inspection Matrix Table */}
                        <div className="overflow-x-auto">
                            <table className="excel-sheet w-full min-w-[1550px] border-collapse text-xs">
                                <thead>
                                    <tr className="bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-200 [&>th]:border [&>th]:border-border [&>th]:p-2 [&>th]:text-center [&>th]:font-bold">
                                        <th rowSpan={2} className="w-12">
                                            No
                                        </th>
                                        <th
                                            rowSpan={2}
                                            className="min-w-[220px] text-left"
                                        >
                                            Inspeksi
                                        </th>
                                        <th
                                            colSpan={2}
                                            className="bg-blue-50/70 text-blue-900 dark:bg-blue-950/40 dark:text-blue-200"
                                        >
                                            Alat Pelindung Diri
                                        </th>
                                        <th
                                            colSpan={2}
                                            className="bg-amber-50/70 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
                                        >
                                            Peralatan Kerja / Area Kerja
                                        </th>
                                        <th
                                            colSpan={2}
                                            className="bg-emerald-50/70 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200"
                                        >
                                            SOP / IK
                                        </th>
                                        <th
                                            colSpan={2}
                                            className="bg-rose-50/70 text-rose-900 dark:bg-rose-950/40 dark:text-rose-200"
                                        >
                                            P3K
                                        </th>
                                        <th className="bg-violet-50/70 text-violet-900 dark:bg-violet-950/40 dark:text-violet-200">
                                            Cara Kerja
                                        </th>
                                        <th
                                            rowSpan={2}
                                            className="min-w-[200px] text-left"
                                        >
                                            Keterangan
                                        </th>
                                        {can_write && (
                                            <th
                                                rowSpan={2}
                                                className="no-print w-12"
                                            >
                                                Aksi
                                            </th>
                                        )}
                                    </tr>
                                    <tr className="bg-slate-50 text-[11px] text-slate-700 dark:bg-slate-900/80 dark:text-slate-300 [&>th]:border [&>th]:border-border [&>th]:p-1.5 [&>th]:text-center [&>th]:font-semibold">
                                        {/* APD Subcolumns */}
                                        <th className="w-28">
                                            Jml Item (Memenuhi/Tdk)
                                        </th>
                                        <th className="w-28">
                                            Layak / Tdk Layak
                                        </th>
                                        {/* Peralatan / Area Kerja Subcolumns */}
                                        <th className="w-28">Jml Item</th>
                                        <th className="w-32">
                                            Layak / Tdk Layak / Aman
                                        </th>
                                        {/* SOP/IK Subcolumns */}
                                        <th className="w-36">
                                            Semua Bidang PNP
                                        </th>
                                        <th className="w-36">
                                            Semua Bidang Vendor
                                        </th>
                                        {/* P3K Subcolumns */}
                                        <th className="w-28">Ada / Tdk Ada</th>
                                        <th className="w-32">
                                            Memenuhi / Tdk Memenuhi
                                        </th>
                                        {/* Cara Kerja Subcolumn */}
                                        <th className="w-32">
                                            Ergonomi / Tdk Ergonomi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {orderedGroups.map((group) => {
                                        const groupItems = rows.filter(
                                            (r) => r.kelompok === group,
                                        );

                                        return (
                                            <Fragment key={group}>
                                                {/* Group Category Header Row */}
                                                <tr className="bg-muted/70 font-bold text-foreground">
                                                    <td
                                                        colSpan={
                                                            can_write ? 13 : 12
                                                        }
                                                        className="border border-border px-3 py-2 text-xs"
                                                    >
                                                        <div className="flex items-center justify-between">
                                                            <span>{group}</span>
                                                            {can_write && (
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        addItem(
                                                                            group,
                                                                        )
                                                                    }
                                                                    className="no-print h-6 gap-1 px-2 text-[11px] text-primary hover:bg-background"
                                                                >
                                                                    <Plus className="size-3" />
                                                                    Tambah Item
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>

                                                {/* Rows inside this group */}
                                                {groupItems.length === 0 ? (
                                                    <tr>
                                                        <td
                                                            colSpan={
                                                                can_write
                                                                    ? 13
                                                                    : 12
                                                            }
                                                            className="border border-border p-3 text-center text-xs text-muted-foreground italic"
                                                        >
                                                            Belum ada item dalam
                                                            kelompok ini.
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    groupItems.map((row) => (
                                                        <tr
                                                            key={row._key}
                                                            className="transition-colors hover:bg-muted/30 [&>td]:border [&>td]:border-border [&>td]:p-1.5"
                                                        >
                                                            {/* No Urut */}
                                                            <td className="text-center font-medium">
                                                                {can_write ? (
                                                                    <input
                                                                        type="text"
                                                                        value={
                                                                            row.no_urut ??
                                                                            ''
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateCell(
                                                                                row._key,
                                                                                'no_urut',
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        className="w-8 rounded bg-transparent text-center focus:bg-background focus:outline-primary"
                                                                    />
                                                                ) : (
                                                                    row.no_urut
                                                                )}
                                                            </td>

                                                            {/* Nama Inspeksi / Peralatan */}
                                                            <td>
                                                                {can_write ? (
                                                                    <input
                                                                        type="text"
                                                                        value={
                                                                            row.inspeksi
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateCell(
                                                                                row._key,
                                                                                'inspeksi',
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        placeholder="Nama item inspeksi..."
                                                                        className="w-full rounded bg-transparent px-1 font-medium focus:bg-background focus:outline-primary"
                                                                    />
                                                                ) : (
                                                                    <span className="px-1 font-medium">
                                                                        {
                                                                            row.inspeksi
                                                                        }
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* APD: Jumlah Memenuhi/Tdk Memenuhi */}
                                                            <td className="text-center">
                                                                {can_write ? (
                                                                    <input
                                                                        type="text"
                                                                        value={
                                                                            row.apd_jumlah ??
                                                                            '-'
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateCell(
                                                                                row._key,
                                                                                'apd_jumlah',
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        className="w-full rounded bg-transparent text-center focus:bg-background focus:outline-primary"
                                                                    />
                                                                ) : (
                                                                    <span>
                                                                        {row.apd_jumlah ??
                                                                            '-'}
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* APD: Layak / Tdk Layak */}
                                                            <td>
                                                                {can_write ? (
                                                                    <ApdCellSelect
                                                                        value={
                                                                            row.kelayakan_apd
                                                                        }
                                                                        onChange={(
                                                                            val,
                                                                        ) =>
                                                                            updateCell(
                                                                                row._key,
                                                                                'kelayakan_apd',
                                                                                val,
                                                                            )
                                                                        }
                                                                        options={
                                                                            options
                                                                                .answers
                                                                                .kelayakan_apd
                                                                        }
                                                                        className={getSelectClass(
                                                                            row.kelayakan_apd,
                                                                        )}
                                                                    />
                                                                ) : (
                                                                    <span className="block text-center">
                                                                        {row.kelayakan_apd ??
                                                                            '-'}
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* Peralatan: Jumlah */}
                                                            <td className="text-center">
                                                                {can_write ? (
                                                                    <Input
                                                                        type="text"
                                                                        value={
                                                                            row.peralatan_jumlah ??
                                                                            '-'
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateCell(
                                                                                row._key,
                                                                                'peralatan_jumlah',
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        className="h-7 w-full text-center text-xs"
                                                                    />
                                                                ) : (
                                                                    <span>
                                                                        {row.peralatan_jumlah ??
                                                                            '-'}
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* Peralatan: Kelayakan / Kondisi Area */}
                                                            <td>
                                                                {can_write ? (
                                                                    <ApdCellSelect
                                                                        value={
                                                                            row.peralatan_kelayakan
                                                                        }
                                                                        onChange={(
                                                                            val,
                                                                        ) =>
                                                                            updateCell(
                                                                                row._key,
                                                                                'peralatan_kelayakan',
                                                                                val,
                                                                            )
                                                                        }
                                                                        options={
                                                                            options
                                                                                .answers
                                                                                .peralatan_kelayakan
                                                                        }
                                                                        className={getSelectClass(
                                                                            row.peralatan_kelayakan,
                                                                        )}
                                                                    />
                                                                ) : (
                                                                    <span className="block text-center">
                                                                        {row.peralatan_kelayakan ??
                                                                            '-'}
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* SOP/IK: PNP */}
                                                            <td>
                                                                {can_write ? (
                                                                    <ApdCellSelect
                                                                        value={
                                                                            row.sop_pnp
                                                                        }
                                                                        onChange={(
                                                                            val,
                                                                        ) =>
                                                                            updateCell(
                                                                                row._key,
                                                                                'sop_pnp',
                                                                                val,
                                                                            )
                                                                        }
                                                                        options={
                                                                            options
                                                                                .answers
                                                                                .sop_pnp
                                                                        }
                                                                        className={getSelectClass(
                                                                            row.sop_pnp,
                                                                        )}
                                                                    />
                                                                ) : (
                                                                    <span className="block text-center">
                                                                        {row.sop_pnp ??
                                                                            '-'}
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* SOP/IK: Vendor */}
                                                            <td>
                                                                {can_write ? (
                                                                    <ApdCellSelect
                                                                        value={
                                                                            row.sop_vendor
                                                                        }
                                                                        onChange={(
                                                                            val,
                                                                        ) =>
                                                                            updateCell(
                                                                                row._key,
                                                                                'sop_vendor',
                                                                                val,
                                                                            )
                                                                        }
                                                                        options={
                                                                            options
                                                                                .answers
                                                                                .sop_vendor
                                                                        }
                                                                        className={getSelectClass(
                                                                            row.sop_vendor,
                                                                        )}
                                                                    />
                                                                ) : (
                                                                    <span className="block text-center">
                                                                        {row.sop_vendor ??
                                                                            '-'}
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* P3K: Ada / Tdk Ada */}
                                                            <td>
                                                                {can_write ? (
                                                                    <ApdCellSelect
                                                                        value={
                                                                            row.p3k_ada
                                                                        }
                                                                        onChange={(
                                                                            val,
                                                                        ) =>
                                                                            updateCell(
                                                                                row._key,
                                                                                'p3k_ada',
                                                                                val,
                                                                            )
                                                                        }
                                                                        options={
                                                                            options
                                                                                .answers
                                                                                .p3k_ada
                                                                        }
                                                                        className={getSelectClass(
                                                                            row.p3k_ada,
                                                                        )}
                                                                    />
                                                                ) : (
                                                                    <span className="block text-center">
                                                                        {row.p3k_ada ??
                                                                            '-'}
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* P3K: Memenuhi / Tdk Memenuhi */}
                                                            <td>
                                                                {can_write ? (
                                                                    <ApdCellSelect
                                                                        value={
                                                                            row.p3k_memenuhi
                                                                        }
                                                                        onChange={(
                                                                            val,
                                                                        ) =>
                                                                            updateCell(
                                                                                row._key,
                                                                                'p3k_memenuhi',
                                                                                val,
                                                                            )
                                                                        }
                                                                        options={
                                                                            options
                                                                                .answers
                                                                                .p3k_memenuhi
                                                                        }
                                                                        className={getSelectClass(
                                                                            row.p3k_memenuhi,
                                                                        )}
                                                                    />
                                                                ) : (
                                                                    <span className="block text-center">
                                                                        {row.p3k_memenuhi ??
                                                                            '-'}
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* Cara Kerja: Ergonomi / Tdk Ergonomi */}
                                                            <td>
                                                                {can_write ? (
                                                                    <ApdCellSelect
                                                                        value={
                                                                            row.cara_kerja
                                                                        }
                                                                        onChange={(
                                                                            val,
                                                                        ) =>
                                                                            updateCell(
                                                                                row._key,
                                                                                'cara_kerja',
                                                                                val,
                                                                            )
                                                                        }
                                                                        options={
                                                                            options
                                                                                .answers
                                                                                .cara_kerja
                                                                        }
                                                                        className={getSelectClass(
                                                                            row.cara_kerja,
                                                                        )}
                                                                    />
                                                                ) : (
                                                                    <span className="block text-center">
                                                                        {row.cara_kerja ??
                                                                            '-'}
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* Keterangan */}
                                                            <td>
                                                                {can_write ? (
                                                                    <input
                                                                        type="text"
                                                                        value={
                                                                            row.keterangan ??
                                                                            ''
                                                                        }
                                                                        onChange={(
                                                                            e,
                                                                        ) =>
                                                                            updateCell(
                                                                                row._key,
                                                                                'keterangan',
                                                                                e
                                                                                    .target
                                                                                    .value,
                                                                            )
                                                                        }
                                                                        placeholder="Keterangan..."
                                                                        className="w-full rounded bg-transparent px-1 focus:bg-background focus:outline-primary"
                                                                    />
                                                                ) : (
                                                                    <span className="px-1">
                                                                        {row.keterangan ||
                                                                            '-'}
                                                                    </span>
                                                                )}
                                                            </td>

                                                            {/* Aksi Hapus */}
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
                                            </Fragment>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    {/* Notes / Catatan Card (No Signatures) */}
                    <Card className="gap-2 p-4 py-4">
                        <div className="flex items-center gap-2">
                            <FileText className="size-4 text-primary" />
                            <Label className="text-sm font-semibold text-foreground">
                                Catatan / Keterangan Tambahan:
                            </Label>
                        </div>
                        <Textarea
                            value={catatan}
                            onChange={(e) => {
                                setCatatan(e.target.value);
                                setDirty(true);
                            }}
                            disabled={!can_write}
                            placeholder="Tuliskan catatan inspeksi, temuan kelaikan peralatan, APD kadaluarsa, atau catatan tindak lanjut di lapangan..."
                            rows={3}
                            className="text-xs"
                        />
                    </Card>

                    {/* Bottom Action Footer for Quick Save */}
                    {can_write && (
                        <Card className="no-print flex flex-row items-center justify-between gap-3 p-3 py-3">
                            <div className="text-xs text-muted-foreground">
                                {dirty ? (
                                    <span className="font-medium text-amber-600 dark:text-amber-400">
                                        Perubahan belum disimpan. Klik tombol
                                        Simpan untuk menyimpan ke database.
                                    </span>
                                ) : (
                                    <span>
                                        Semua perubahan telah disimpan ke
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
                                    onClick={() => save()}
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
        </>
    );
}

function ApdCellSelect({
    value,
    onChange,
    options,
    className,
}: {
    value: string | null;
    onChange: (val: string) => void;
    options: string[];
    className?: string;
}) {
    const val = value ?? '-';
    const choices = options.includes(val) ? options : [val, ...options];

    return (
        <Select value={val} onValueChange={onChange}>
            <SelectTrigger size="sm" className={className}>
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

K3KesiapanApdPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input K3 & Keamanan', href: k3Input.index() },
        {
            title: 'Kesiapan APD & Form Inspeksi K3',
            href: k3KesiapanApd.index(),
        },
    ],
};
