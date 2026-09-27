import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Copy,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import k3Pengusahaan from '@/routes/k3/pengusahaan';
import k3PengusahaanEmergencyFacility from '@/routes/k3/pengusahaan/emergency-facility';
import type { IdName } from '@/types';

export type EmergencyFacilityRow = {
    _key: number;
    id?: number | null;
    grup: string;
    no_urut: number;
    nama_peralatan: string;
    jml_total: string;
    jml_ready: string;
    jml_not_ready: string;
    persen_kesiapan: string;
    lokasi: string;
    kendala: string;
    tindak_lanjut: string;
    sort_order: number;
};

type Props = {
    unit: { id: number; name: string };
    filters: {
        unit_id: number;
        month: number;
        year: number;
        periode: string;
    };
    options: {
        units: IdName[];
        years: number[];
        periods: string[];
    };
    rows: Array<Omit<EmergencyFacilityRow, '_key'>>;
    sample_rows?: Array<Omit<EmergencyFacilityRow, '_key'>>;
    metadata: {
        no_dokumen: string;
        revisi: string;
        tanggal_dokumen: string;
        halaman: string;
    };
    has_saved: boolean;
    copy_sources: {
        current_month: string[];
        prev_month: string[];
    };
    can_write: boolean;
};

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 8mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; background: #fff !important; color: #000 !important; padding: 0 !important; margin: 0 !important; font-size: 9px !important; }
    .no-print { display: none !important; }
    .ef-table th, .ef-table td { border: 1px solid #000 !important; padding: 2px 4px !important; }
    .ef-header-box { border: 1px solid #000 !important; }
    input, textarea { border: none !important; background: transparent !important; }
}
`;

export default function PengusahaanEmergencyFacilityPage(props: Props) {
    const {
        unit,
        filters,
        options,
        rows: initialRows,
        metadata: initialMetadata,
        has_saved,
        copy_sources,
        can_write,
    } = props;

    const [rows, setRows] = useState<EmergencyFacilityRow[]>(() =>
        initialRows.map((r, i) => ({ ...r, _key: i + 1 })),
    );
    const [metadata, setMetadata] = useState(initialMetadata);
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [nextKey, setNextKey] = useState(initialRows.length + 10);

    // Sync state when period / filters change without useEffect (React render-time adjustment)
    const currentFiltersKey = `${filters.unit_id}-${filters.year}-${filters.month}-${filters.periode}`;
    const [prevFiltersKey, setPrevFiltersKey] = useState(currentFiltersKey);

    if (prevFiltersKey !== currentFiltersKey) {
        setPrevFiltersKey(currentFiltersKey);
        setRows(initialRows.map((r, i) => ({ ...r, _key: i + 1 })));
        setMetadata(initialMetadata);
        setDirty(false);
    }

    const monthName = OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`;

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year' | 'periode', value: string | number) => {
        if (dirty && !confirm('Perubahan yang belum disimpan akan hilang jika berganti periode/unit. Lanjutkan?')) {
            return;
        }

        const nextFilters = {
            ...filters,
            [key]: key === 'periode' ? String(value) : Number(value),
        };
        router.get(k3PengusahaanEmergencyFacility.index().url, nextFilters, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    const loadSampleRows = () => {
        if (!props.sample_rows || props.sample_rows.length === 0) {
            return;
        }

        if (!confirm(`Muat contoh data standar pemeriksaan Emergency Facility untuk ${filters.periode}? Data baris yang belum disimpan akan digantikan.`)) {
            return;
        }

        setRows(props.sample_rows.map((r, i) => ({ ...r, _key: i + 1 })));
        setDirty(true);
    };

    const updateField = (key: number, field: keyof EmergencyFacilityRow, value: string | number) => {
        setRows((prev) =>
            prev.map((row) => {
                if (row._key !== key) {
                    return row;
                }

                const updated = { ...row, [field]: value };

                // If user edits jml_total or jml_ready and both are valid numbers, automatically suggest jml_not_ready and % kesiapan
                if (field === 'jml_total' || field === 'jml_ready') {
                    const totalStr = String(field === 'jml_total' ? value : row.jml_total).trim();
                    const readyStr = String(field === 'jml_ready' ? value : row.jml_ready).trim();
                    const totalNum = parseFloat(totalStr);
                    const readyNum = parseFloat(readyStr);

                    if (!isNaN(totalNum) && !isNaN(readyNum) && totalNum >= 0 && readyNum >= 0) {
                        const notReady = Math.max(0, totalNum - readyNum);
                        updated.jml_not_ready = String(notReady);

                        if (totalNum > 0) {
                            const pct = Math.round((readyNum / totalNum) * 100);
                            updated.persen_kesiapan = `${pct}%`;
                        } else {
                            updated.persen_kesiapan = '-';
                        }
                    }
                }

                return updated;
            }),
        );
        setDirty(true);
    };

    const addRowToGroup = (grup: string) => {
        const groupRows = rows.filter((r) => r.grup === grup);
        const maxNo = groupRows.length > 0 ? Math.max(...groupRows.map((r) => r.no_urut)) : rows.length;

        const newRow: EmergencyFacilityRow = {
            _key: nextKey,
            id: null,
            grup,
            no_urut: maxNo + 1,
            nama_peralatan: '',
            jml_total: '0',
            jml_ready: '0',
            jml_not_ready: '0',
            persen_kesiapan: '-',
            lokasi: '-',
            kendala: '',
            tindak_lanjut: '',
            sort_order: rows.length + 1,
        };

        setRows((prev) => [...prev, newRow]);
        setNextKey((k) => k + 1);
        setDirty(true);
    };

    const removeRow = (key: number) => {
        setRows((prev) => prev.filter((r) => r._key !== key));
        setDirty(true);
    };

    const resetChanges = () => {
        setRows(initialRows.map((r, i) => ({ ...r, _key: i + 1 })));
        setMetadata(initialMetadata);
        setDirty(false);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        router.post(
            k3PengusahaanEmergencyFacility.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                periode: filters.periode,
                no_dokumen: metadata.no_dokumen,
                revisi: metadata.revisi,
                tanggal_dokumen: metadata.tanggal_dokumen,
                halaman: metadata.halaman,
                rows: rows.map((r, idx) => ({
                    grup: r.grup,
                    no_urut: r.no_urut,
                    nama_peralatan: r.nama_peralatan,
                    jml_total: r.jml_total,
                    jml_ready: r.jml_ready,
                    jml_not_ready: r.jml_not_ready,
                    persen_kesiapan: r.persen_kesiapan,
                    lokasi: r.lokasi,
                    kendala: r.kendala,
                    tindak_lanjut: r.tindak_lanjut,
                    sort_order: idx + 1,
                })),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const handleCopy = (sourcePeriode: string, isPrevMonth = false) => {
        if (!confirm(`Apakah Anda yakin ingin menyalin seluruh data dari periode ${sourcePeriode} ke ${filters.periode}? Data yang ada saat ini di ${filters.periode} akan diperbarui.`)) {
            return;
        }

        const sourceMonth = isPrevMonth ? (filters.month === 1 ? 12 : filters.month - 1) : filters.month;
        const sourceYear = isPrevMonth && filters.month === 1 ? filters.year - 1 : filters.year;

        router.post(
            k3PengusahaanEmergencyFacility.copy().url,
            {
                unit_id: filters.unit_id,
                target_year: filters.year,
                target_month: filters.month,
                target_periode: filters.periode,
                source_year: sourceYear,
                source_month: sourceMonth,
                source_periode: sourcePeriode,
            },
            {
                preserveScroll: true,
            },
        );
    };

    // Grouping of rows preserve default master sequence
    const groupedRows = useMemo(() => {
        const groups: { [grup: string]: EmergencyFacilityRow[] } = {};

        for (const row of rows) {
            if (!groups[row.grup]) {
                groups[row.grup] = [];
            }

            groups[row.grup].push(row);
        }

        return groups;
    }, [rows]);

    const periodTitle = filters.periode === 'BULANAN'
        ? `BULAN ${monthName.toUpperCase()} ${filters.year}`
        : `${filters.periode} BULAN ${monthName.toUpperCase()} ${filters.year}`;

    return (
        <>
            <Head title={`Pemeriksaan Emergency Facility — ${filters.periode} ${monthName} ${filters.year}`} />
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
                                    Pemeriksaan Emergency Facility
                                </h1>
                                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">
                                    {unit.name}
                                </Badge>
                                <Badge variant="secondary" className="font-bold">
                                    {filters.periode}
                                </Badge>
                                <Badge variant="outline" className="text-xs">
                                    {monthName} {filters.year}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Akses 2 — Pengusahaan: Matriks kesiapan fasilitas darurat per periode mingguan (M1-M4) & Bulanan.
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {can_write && props.sample_rows && props.sample_rows.length > 0 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={loadSampleRows}
                                disabled={saving}
                                className="gap-1.5 text-xs text-amber-700 hover:text-amber-800 dark:text-amber-400"
                                title="Muat contoh data pemeriksaan sesuai dokumen fisik resmi SMT-FM-AK3"
                            >
                                <Sparkles className="size-3.5 text-amber-500" />
                                Muat Contoh Dokumen
                            </Button>
                        )}
                        {dirty && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={resetChanges}
                                disabled={saving}
                                className="gap-1.5 text-xs text-muted-foreground"
                            >
                                <RotateCcw className="size-3.5" />
                                Reset
                            </Button>
                        )}
                        {can_write && (
                            <Button
                                size="sm"
                                onClick={handleSubmit}
                                disabled={saving}
                                className="gap-1.5 text-xs"
                            >
                                <Save className="size-3.5" />
                                {saving ? 'Menyimpan...' : has_saved ? 'Simpan Perubahan' : 'Simpan Data'}
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => window.print()}
                            className="gap-1.5 text-xs"
                        >
                            <Printer className="size-3.5" />
                            Cetak (A4 Landscape)
                        </Button>
                    </div>
                </div>

                {/* Filter & Period Tabs */}
                <Card className="no-print p-4">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div className="flex flex-wrap items-end gap-3">
                            <OperasiSelect
                                label="Unit Layanan"
                                value={String(filters.unit_id)}
                                options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                                onChange={(val) => handleFilterChange('unit_id', val)}
                            />

                            <OperasiSelect
                                label="Bulan"
                                value={String(filters.month)}
                                options={OPERASI_MONTHS.map((label, idx) => ({ value: String(idx + 1), label }))}
                                onChange={(val) => handleFilterChange('month', val)}
                            />

                            <OperasiSelect
                                label="Tahun"
                                value={String(filters.year)}
                                options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                                onChange={(val) => handleFilterChange('year', val)}
                            />
                        </div>

                        {/* Periode Tab Buttons */}
                        <div className="flex flex-col gap-1.5">
                            <span className="text-xs font-semibold text-muted-foreground">Pilih Periode Inspeksi:</span>
                            <div className="inline-flex rounded-lg border border-border bg-muted/40 p-1">
                                {options.periods.map((p) => {
                                    const active = filters.periode === p;

                                    return (
                                        <button
                                            key={p}
                                            type="button"
                                            onClick={() => handleFilterChange('periode', p)}
                                            className={`rounded-md px-3 py-1 text-xs font-semibold transition-all ${
                                                active
                                                    ? 'bg-primary text-primary-foreground shadow-xs'
                                                    : 'text-muted-foreground hover:bg-background/80 hover:text-foreground'
                                            }`}
                                        >
                                            {p === 'BULANAN' ? 'Bulanan' : p}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    </div>

                    {/* Copy Data Options */}
                    {can_write && (copy_sources.current_month.length > 0 || copy_sources.prev_month.length > 0) && (
                        <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-border pt-3 text-xs">
                            <span className="flex items-center gap-1 font-medium text-muted-foreground">
                                <Copy className="size-3.5 text-primary" />
                                Salin data ke <strong>{filters.periode}</strong> dari:
                            </span>
                            {copy_sources.current_month.map((src) => (
                                <Button
                                    key={src}
                                    variant="outline"
                                    size="sm"
                                    className="h-6 gap-1 px-2 text-[11px]"
                                    onClick={() => handleCopy(src, false)}
                                >
                                    Periode {src}
                                </Button>
                            ))}
                            {copy_sources.prev_month.map((src) => (
                                <Button
                                    key={`prev-${src}`}
                                    variant="outline"
                                    size="sm"
                                    className="h-6 gap-1 px-2 text-[11px] text-muted-foreground"
                                    onClick={() => handleCopy(src, true)}
                                >
                                    Bulan Lalu ({src})
                                </Button>
                            ))}
                        </div>
                    )}
                </Card>

                {/* Printable container (A4 Landscape) */}
                <div className="print-container overflow-hidden rounded-md border border-border bg-card p-4 shadow-xs">
                    {/* KOP Dokumen Resmi PLN */}
                    <table className="ef-header-box w-full border-collapse border border-black dark:border-border">
                        <tbody>
                            <tr>
                                {/* Logo PLN Kiri */}
                                <td className="w-44 border-r border-black p-2 text-center align-middle dark:border-border">
                                    <img
                                        src="/logo/sidebar-logo.png"
                                        alt="PLN Nusantara Power"
                                        className="mx-auto max-h-12 object-contain"
                                        onError={(e) => {
                                            (e.target as HTMLElement).style.display = 'none';
                                        }}
                                    />
                                </td>

                                {/* Judul Tengah */}
                                <td className="p-2 text-center align-middle">
                                    <div className="text-xs font-bold tracking-wide text-foreground uppercase">
                                        PT. PLN NUSANTARA POWER
                                    </div>
                                    <div className="text-xs font-semibold text-foreground uppercase">
                                        UNIT LAYANAN {unit.name.toUpperCase().startsWith('ULPLTD') || unit.name.toUpperCase().startsWith('UP') ? unit.name.toUpperCase() : `PLTD ${unit.name.toUpperCase()}`}
                                    </div>
                                    <div className="text-sm font-extrabold text-foreground uppercase">
                                        PEMERIKSAAN EMERGENCY FACILITY
                                    </div>
                                    <div className="text-xs font-bold text-foreground">
                                        PERIODE {periodTitle}
                                    </div>
                                </td>

                                {/* Metadata Dokumen Kanan */}
                                <td className="w-56 border-l border-black p-1.5 text-left text-xs align-middle dark:border-border">
                                    <table className="w-full text-[11px]">
                                        <tbody>
                                            <tr>
                                                <td className="w-24 py-0.5 font-semibold text-muted-foreground">No. Dokumen</td>
                                                <td className="py-0.5">:</td>
                                                <td className="py-0.5 pl-1 font-mono">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={metadata.no_dokumen}
                                                            onChange={(e) => {
                                                                setMetadata((m) => ({ ...m, no_dokumen: e.target.value }));
                                                                setDirty(true);
                                                            }}
                                                            className="w-full bg-transparent text-[11px] focus:outline-none"
                                                            placeholder="SMT-FM-AK3-..."
                                                        />
                                                    ) : (
                                                        metadata.no_dokumen || '-'
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td className="py-0.5 font-semibold text-muted-foreground">Revisi</td>
                                                <td className="py-0.5">:</td>
                                                <td className="py-0.5 pl-1 font-mono">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={metadata.revisi}
                                                            onChange={(e) => {
                                                                setMetadata((m) => ({ ...m, revisi: e.target.value }));
                                                                setDirty(true);
                                                            }}
                                                            className="w-full bg-transparent text-[11px] focus:outline-none"
                                                            placeholder="00"
                                                        />
                                                    ) : (
                                                        metadata.revisi || '-'
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td className="py-0.5 font-semibold text-muted-foreground">Tanggal</td>
                                                <td className="py-0.5">:</td>
                                                <td className="py-0.5 pl-1 font-mono">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={metadata.tanggal_dokumen}
                                                            onChange={(e) => {
                                                                setMetadata((m) => ({ ...m, tanggal_dokumen: e.target.value }));
                                                                setDirty(true);
                                                            }}
                                                            className="w-full bg-transparent text-[11px] focus:outline-none"
                                                            placeholder="23 September 2019"
                                                        />
                                                    ) : (
                                                        metadata.tanggal_dokumen || '-'
                                                    )}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td className="py-0.5 font-semibold text-muted-foreground">Halaman</td>
                                                <td className="py-0.5">:</td>
                                                <td className="py-0.5 pl-1 font-mono">
                                                    {can_write ? (
                                                        <input
                                                            type="text"
                                                            value={metadata.halaman}
                                                            onChange={(e) => {
                                                                setMetadata((m) => ({ ...m, halaman: e.target.value }));
                                                                setDirty(true);
                                                            }}
                                                            className="w-full bg-transparent text-[11px] focus:outline-none"
                                                            placeholder="1 dari 1"
                                                        />
                                                    ) : (
                                                        metadata.halaman || '-'
                                                    )}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    {/* Matriks Tabel Emergency Facility */}
                    <div className="mt-3 overflow-x-auto">
                        <table className="ef-table w-full border-collapse border border-black text-xs dark:border-border">
                            <thead>
                                {/* Header Baris 1: Kolom Utama */}
                                <tr className="bg-[#fff59d] text-center font-bold text-foreground dark:bg-amber-950/40">
                                    <th className="w-10 border border-black p-1.5 dark:border-border">NO</th>
                                    <th className="w-56 border border-black p-1.5 text-left dark:border-border">NAMA PERALATAN</th>
                                    <th className="w-24 border border-black p-1.5 dark:border-border">JML TOTAL</th>
                                    <th className="w-24 border border-black p-1.5 dark:border-border">JML READY</th>
                                    <th className="w-24 border border-black p-1.5 dark:border-border">JML NOT READY</th>
                                    <th className="w-20 border border-black p-1.5 dark:border-border">% Kesiapan</th>
                                    <th className="w-52 border border-black p-1.5 text-left dark:border-border">LOKASI PENEMPATAN</th>
                                    <th className="w-48 border border-black p-1.5 text-left dark:border-border">KENDALA</th>
                                    <th className="w-52 border border-black p-1.5 text-left dark:border-border">TINDAK LANJUT</th>
                                    {can_write && <th className="no-print w-10 border border-black p-1.5 dark:border-border">AKSI</th>}
                                </tr>
                                {/* Header Baris 2: Sub-Heading Hijau Besar EMERGENCY FACILITY */}
                                <tr className="bg-[#a9dfbf] font-bold text-foreground dark:bg-emerald-950/50">
                                    <td colSpan={can_write ? 10 : 9} className="border border-black px-2 py-1 uppercase dark:border-border">
                                        EMERGENCY FACILITY
                                    </td>
                                </tr>
                            </thead>
                            <tbody>
                                {Object.entries(groupedRows).map(([grupName, groupItems]) => (
                                    <GroupRowsSection
                                        key={grupName}
                                        grupName={grupName}
                                        items={groupItems}
                                        canWrite={can_write}
                                        onUpdate={updateField}
                                        onAddRow={() => addRowToGroup(grupName)}
                                        onRemoveRow={removeRow}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}

function GroupRowsSection({
    grupName,
    items,
    canWrite,
    onUpdate,
    onAddRow,
    onRemoveRow,
}: {
    grupName: string;
    items: EmergencyFacilityRow[];
    canWrite: boolean;
    onUpdate: (key: number, field: keyof EmergencyFacilityRow, value: string | number) => void;
    onAddRow: () => void;
    onRemoveRow: (key: number) => void;
}) {
    return (
        <>
            {/* Header Kelompok (Grayish row) */}
            <tr className="bg-[#d5d8dc] font-bold text-foreground italic dark:bg-muted/70">
                <td colSpan={canWrite ? 9 : 9} className="border border-black px-2 py-1 dark:border-border">
                    {grupName}
                </td>
                {canWrite && (
                    <td className="no-print border border-black p-1 text-center dark:border-border">
                        <Button
                            variant="ghost"
                            size="icon"
                            onClick={onAddRow}
                            className="size-5 p-0 text-emerald-700 hover:text-emerald-900"
                            title={`Tambah baris ke ${grupName}`}
                        >
                            <Plus className="size-3.5" />
                        </Button>
                    </td>
                )}
            </tr>

            {/* Item-item dalam kelompok */}
            {items.map((row) => (
                <tr key={row._key} className="border-b border-black hover:bg-muted/20 dark:border-border">
                    {/* No */}
                    <td className="border-r border-black p-1 text-center font-mono dark:border-border">
                        {canWrite ? (
                            <input
                                type="number"
                                value={row.no_urut}
                                onChange={(e) => onUpdate(row._key, 'no_urut', parseInt(e.target.value) || 0)}
                                className="w-full text-center bg-transparent focus:outline-none"
                            />
                        ) : (
                            row.no_urut
                        )}
                    </td>

                    {/* Nama Peralatan */}
                    <td className="border-r border-black p-1 dark:border-border">
                        {canWrite ? (
                            <input
                                type="text"
                                value={row.nama_peralatan}
                                onChange={(e) => onUpdate(row._key, 'nama_peralatan', e.target.value)}
                                className="w-full bg-transparent focus:outline-none"
                            />
                        ) : (
                            row.nama_peralatan
                        )}
                    </td>

                    {/* Jml Total */}
                    <td className="border-r border-black p-1 text-center dark:border-border">
                        {canWrite ? (
                            <input
                                type="text"
                                value={row.jml_total}
                                onChange={(e) => onUpdate(row._key, 'jml_total', e.target.value)}
                                className="w-full text-center bg-transparent focus:bg-primary/5 focus:outline-none font-medium"
                                placeholder="0"
                            />
                        ) : (
                            <span>{row.jml_total || '-'}</span>
                        )}
                    </td>

                    {/* Jml Ready */}
                    <td className="border-r border-black p-1 text-center dark:border-border">
                        {canWrite ? (
                            <input
                                type="text"
                                value={row.jml_ready}
                                onChange={(e) => onUpdate(row._key, 'jml_ready', e.target.value)}
                                className="w-full text-center bg-transparent focus:bg-primary/5 focus:outline-none font-medium"
                                placeholder="0"
                            />
                        ) : (
                            <span>{row.jml_ready || '-'}</span>
                        )}
                    </td>

                    {/* Jml Not Ready */}
                    <td className="border-r border-black p-1 text-center dark:border-border">
                        {canWrite ? (
                            <input
                                type="text"
                                value={row.jml_not_ready}
                                onChange={(e) => onUpdate(row._key, 'jml_not_ready', e.target.value)}
                                className="w-full text-center bg-transparent focus:bg-primary/5 focus:outline-none font-medium"
                                placeholder="0"
                            />
                        ) : (
                            <span>{row.jml_not_ready || '-'}</span>
                        )}
                    </td>

                    {/* % Kesiapan */}
                    <td className="border-r border-black p-1 text-center font-bold dark:border-border">
                        {canWrite ? (
                            <input
                                type="text"
                                value={row.persen_kesiapan}
                                onChange={(e) => onUpdate(row._key, 'persen_kesiapan', e.target.value)}
                                className="w-full text-center bg-transparent focus:bg-primary/5 focus:outline-none font-bold"
                                placeholder="100%"
                            />
                        ) : (
                            <span>{row.persen_kesiapan || '-'}</span>
                        )}
                    </td>

                    {/* Lokasi Penempatan */}
                    <td className="border-r border-black p-1 dark:border-border">
                        {canWrite ? (
                            <input
                                type="text"
                                value={row.lokasi}
                                onChange={(e) => onUpdate(row._key, 'lokasi', e.target.value)}
                                className="w-full bg-transparent focus:outline-none"
                                placeholder="Lokasi penempatan..."
                            />
                        ) : (
                            row.lokasi || '-'
                        )}
                    </td>

                    {/* Kendala */}
                    <td className="border-r border-black p-1 dark:border-border">
                        {canWrite ? (
                            <input
                                type="text"
                                value={row.kendala}
                                onChange={(e) => onUpdate(row._key, 'kendala', e.target.value)}
                                className="w-full bg-transparent focus:outline-none"
                                placeholder="Kendala..."
                            />
                        ) : (
                            row.kendala || '-'
                        )}
                    </td>

                    {/* Tindak Lanjut */}
                    <td className="border-r border-black p-1 dark:border-border">
                        {canWrite ? (
                            <input
                                type="text"
                                value={row.tindak_lanjut}
                                onChange={(e) => onUpdate(row._key, 'tindak_lanjut', e.target.value)}
                                className="w-full bg-transparent focus:outline-none"
                                placeholder="Tindak lanjut..."
                            />
                        ) : (
                            row.tindak_lanjut || '-'
                        )}
                    </td>

                    {/* Aksi */}
                    {canWrite && (
                        <td className="no-print border border-black p-1 text-center dark:border-border">
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => onRemoveRow(row._key)}
                                className="size-5 p-0 text-destructive hover:bg-destructive/10"
                                title="Hapus baris ini"
                            >
                                <Trash2 className="size-3" />
                            </Button>
                        </td>
                    )}
                </tr>
            ))}
        </>
    );
}
