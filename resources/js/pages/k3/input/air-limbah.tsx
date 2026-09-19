import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    CheckCheck,
    Droplets,
    Pencil,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { K3InputExportButtons } from '@/components/k3/input-export-buttons';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import k3Input from '@/routes/k3/input';
import airLimbah from '@/routes/k3/input/air-limbah';
import type { IdName } from '@/types';
import { toast } from 'sonner';

type ServerRow = {
    id: number | null;
    no_urut: number | null;
    area_penyiraman: string;
    tanggal: string | null;
    waktu_penyiraman: string;
    metode_pemanfaatan: string;
    debit_awal: number;
    debit_akhir: number;
    debit_jumlah: number;
    frekuensi: string;
    pic: string;
    keterangan: string;
    sort_order: number;
};

type Row = ServerRow & { _key: number };

type Props = {
    unit: {
        id: number;
        name: string;
        service_unit_name: string | null;
    };
    filters: {
        unit_id: number;
        month: number;
        year: number;
    };
    options: {
        units: IdName[];
        years: number[];
    };
    rows: ServerRow[];
    defaultAreas: string[];
    can_write: boolean;
};

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 8mm 10mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        background: #fff !important;
        color: #000 !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .no-print { display: none !important; }
    .print-table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 8px !important;
    }
    .print-table th, .print-table td {
        border: 1px solid #000 !important;
        padding: 5px 3px !important;
        text-align: center !important;
        vertical-align: middle !important;
    }
    .print-thead th {
        background-color: #85b5e1 !important;
        color: #000 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
`;

const hydrate = (rows: ServerRow[]): Row[] => rows.map((r, i) => ({ ...r, _key: i }));

function formatIndoDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    try {
        const parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        const year = parts[0];
        const monthIndex = parseInt(parts[1], 10) - 1;
        const day = parseInt(parts[2], 10);
        const months = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
        ];
        return `${day} ${months[monthIndex] ?? parts[1]} ${year}`;
    } catch {
        return dateStr;
    }
}

export default function AirLimbahPage({
    unit,
    filters,
    options,
    rows: initialRows,
    defaultAreas,
    can_write,
}: Props) {
    const [rows, setRows] = useState<Row[]>(() => hydrate(initialRows));
    const [nextKey, setNextKey] = useState(initialRows.length);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    // Modal state for Create and Edit
    const [modalOpen, setModalOpen] = useState(false);
    const [editingKey, setEditingKey] = useState<number | null>(null);
    const [form, setForm] = useState({
        area_penyiraman: '',
        tanggal: '',
        waktu_penyiraman: '16.00 WITA',
        metode_pemanfaatan: 'Penyiraman Tanaman',
        debit_awal: '0',
        debit_akhir: '1',
        debit_jumlah: '1',
        frekuensi: '1',
        pic: 'K3L',
        keterangan: '',
    });

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);
    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(hydrate(initialRows));
        setNextKey(initialRows.length);
        setDirty(false);
    }

    const monthLabel = useMemo(
        () => OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`,
        [filters.month],
    );

    const monthOptions = useMemo(
        () =>
            OPERASI_MONTHS.map((label, idx) => ({
                value: String(idx + 1),
                label,
            })),
        [],
    );

    const totalDebitJumlah = useMemo(
        () => rows.reduce((sum, r) => sum + (Number(r.debit_jumlah) || 0), 0),
        [rows],
    );

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', val: number) => {
        router.get(
            airLimbah.index().url,
            { ...filters, [key]: val },
            { preserveState: false, preserveScroll: true },
        );
    };

    const handleSave = () => {
        if (!can_write) return;
        setSaving(true);
        router.post(
            airLimbah.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((r, i) => ({
                    id: r.id,
                    area_penyiraman: r.area_penyiraman,
                    tanggal: r.tanggal || null,
                    waktu_penyiraman: r.waktu_penyiraman || null,
                    metode_pemanfaatan: r.metode_pemanfaatan || null,
                    debit_awal: Number(r.debit_awal) || 0,
                    debit_akhir: Number(r.debit_akhir) || 0,
                    debit_jumlah: Number(r.debit_jumlah) || 0,
                    frekuensi: r.frekuensi || null,
                    pic: r.pic || null,
                    keterangan: r.keterangan || null,
                    sort_order: i,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDirty(false);
                    toast.success('Logbook pemantauan air limbah berhasil disimpan.');
                },
                onError: (errs) => {
                    const firstMsg = Object.values(errs)[0];
                    toast.error(typeof firstMsg === 'string' ? firstMsg : 'Gagal menyimpan data.');
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    const handleReset = () => {
        setRows(hydrate(initialRows));
        setDirty(false);
        toast.info('Perubahan dibatalkan ke data terakhir yang tersimpan.');
    };

    const openCreateModal = () => {
        setEditingKey(null);
        // Default tanggal to 1st of month/year or current
        const padMonth = String(filters.month).padStart(2, '0');
        const defaultDate = `${filters.year}-${padMonth}-01`;

        setForm({
            area_penyiraman: defaultAreas[0] || '',
            tanggal: defaultDate,
            waktu_penyiraman: '16.00 WITA',
            metode_pemanfaatan: 'Penyiraman Tanaman',
            debit_awal: '0',
            debit_akhir: '1',
            debit_jumlah: '1',
            frekuensi: '1',
            pic: 'K3L',
            keterangan: '',
        });
        setModalOpen(true);
    };

    const openEditModal = (row: Row) => {
        setEditingKey(row._key);
        setForm({
            area_penyiraman: row.area_penyiraman,
            tanggal: row.tanggal || '',
            waktu_penyiraman: row.waktu_penyiraman || '16.00 WITA',
            metode_pemanfaatan: row.metode_pemanfaatan || 'Penyiraman Tanaman',
            debit_awal: String(row.debit_awal ?? 0),
            debit_akhir: String(row.debit_akhir ?? 0),
            debit_jumlah: String(row.debit_jumlah ?? 0),
            frekuensi: row.frekuensi || '1',
            pic: row.pic || 'K3L',
            keterangan: row.keterangan || '',
        });
        setModalOpen(true);
    };

    const handleDebitChange = (field: 'debit_awal' | 'debit_akhir', value: string) => {
        const nextForm = { ...form, [field]: value };
        const awal = parseFloat(field === 'debit_awal' ? value : form.debit_awal) || 0;
        const akhir = parseFloat(field === 'debit_akhir' ? value : form.debit_akhir) || 0;
        const jumlah = Math.max(0, akhir - awal);
        nextForm.debit_jumlah = String(Math.round(jumlah * 100) / 100);
        setForm(nextForm);
    };

    const handleFormSubmit = () => {
        if (!form.area_penyiraman.trim()) {
            toast.error('Area penyiraman wajib diisi.');
            return;
        }

        const debitAwal = parseFloat(form.debit_awal) || 0;
        const debitAkhir = parseFloat(form.debit_akhir) || 0;
        const debitJumlah = Math.max(0, debitAkhir - debitAwal);

        if (editingKey === null) {
            // Create new
            const newRow: Row = {
                _key: nextKey,
                id: null,
                no_urut: rows.length + 1,
                area_penyiraman: form.area_penyiraman.trim(),
                tanggal: form.tanggal || null,
                waktu_penyiraman: form.waktu_penyiraman.trim() || '16.00 WITA',
                metode_pemanfaatan: form.metode_pemanfaatan.trim() || 'Penyiraman Tanaman',
                debit_awal: debitAwal,
                debit_akhir: debitAkhir,
                debit_jumlah: debitJumlah,
                frekuensi: form.frekuensi.trim() || '1',
                pic: form.pic.trim() || 'K3L',
                keterangan: form.keterangan.trim(),
                sort_order: rows.length,
            };
            setRows([...rows, newRow]);
            setNextKey((k) => k + 1);
        } else {
            // Update existing
            setRows(
                rows.map((r) =>
                    r._key === editingKey
                        ? {
                              ...r,
                              area_penyiraman: form.area_penyiraman.trim(),
                              tanggal: form.tanggal || null,
                              waktu_penyiraman: form.waktu_penyiraman.trim() || '16.00 WITA',
                              metode_pemanfaatan: form.metode_pemanfaatan.trim() || 'Penyiraman Tanaman',
                              debit_awal: debitAwal,
                              debit_akhir: debitAkhir,
                              debit_jumlah: debitJumlah,
                              frekuensi: form.frekuensi.trim() || '1',
                              pic: form.pic.trim() || 'K3L',
                              keterangan: form.keterangan.trim(),
                          }
                        : r,
                ),
            );
        }

        setDirty(true);
        setModalOpen(false);
        toast.success(editingKey === null ? 'Baris baru ditambahkan ke tabel.' : 'Data baris berhasil diperbarui.');
    };

    const handleDeleteRow = (key: number) => {
        setRows(rows.filter((r) => r._key !== key).map((r, idx) => ({ ...r, no_urut: idx + 1 })));
        setDirty(true);
        toast.info('Baris dihapus dari daftar sementara.');
    };

    const handlePrint = () => {
        window.print();
    };

    const pdfUrl = `${airLimbah.pdf().url}?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`;

    return (
        <>
            <Head title={`Logbook Pemantauan Air Limbah - ${unit.name} - ${monthLabel} ${filters.year}`} />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6 print:hidden">
                <PageHeader
                    title="Logbook Pemantauan Pemanfaatan Air Limbah"
                    description={`Pencatatan volume dan debit pemanfaatan air limbah untuk penyiraman taman & penghijauan - ${unit.name} (${monthLabel} ${filters.year}).`}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {dirty && (
                                <Badge variant="outline" className="border-amber-500 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                    Ada perubahan belum disimpan
                                </Badge>
                            )}
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.get(k3Input.index().url)}
                            >
                                <ArrowLeft className="mr-1.5 size-4" />
                                Kembali ke Input K3
                            </Button>
                        </div>
                    }
                />

                {/* Filter & Action Toolbar */}
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-card p-4">
                    <div className="flex flex-wrap items-center gap-3">
                        <OperasiSelect
                            label="Unit Pembangkit"
                            value={String(filters.unit_id)}
                            options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                            onChange={(val) => handleFilterChange('unit_id', Number(val))}
                        />
                        <OperasiSelect
                            label="Bulan"
                            value={String(filters.month)}
                            options={monthOptions}
                            onChange={(val) => handleFilterChange('month', Number(val))}
                        />
                        <OperasiSelect
                            label="Tahun"
                            value={String(filters.year)}
                            options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                            onChange={(val) => handleFilterChange('year', Number(val))}
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {can_write && (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={openCreateModal}
                                    className="gap-1.5"
                                >
                                    <Plus className="size-4" />
                                    Tambah Catatan
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={handleSave}
                                    disabled={!dirty || saving}
                                    className="gap-1.5"
                                >
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
                                </Button>
                                {dirty && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleReset}
                                        className="gap-1 text-muted-foreground"
                                    >
                                        <RotateCcw className="size-3.5" />
                                        Batal
                                    </Button>
                                )}
                            </>
                        )}
                        <K3InputExportButtons input="air-limbah" query={{ unit_id: filters.unit_id, month: filters.month, year: filters.year }} pdfUrl={pdfUrl} />
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handlePrint}
                            className="gap-1.5"
                        >
                            <Printer className="size-4" />
                            Print
                        </Button>
                    </div>
                </div>

                {/* Official Header Preview Container */}
                <div className="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                    {/* Official PLN & MKP Kop Header */}
                    <div className="grid grid-cols-[140px_1fr_140px] items-center border-b border-border bg-muted/40 p-4 text-center">
                        <div className="flex items-center justify-center p-1">
                            <img
                                src="/logo/sidebar-logo.png"
                                alt="PLN Nusantara Power"
                                className="max-h-12 max-w-[130px] object-contain"
                                onError={(e) => {
                                    (e.target as HTMLElement).style.display = 'none';
                                }}
                            />
                        </div>
                        <div className="space-y-0.5 px-2">
                            <h2 className="text-xs font-bold uppercase tracking-wider text-muted-foreground md:text-sm">
                                Jasa Pendukung Teknis UP Kendari {unit.name}
                            </h2>
                            <h3 className="text-xs font-semibold uppercase tracking-wide text-foreground md:text-sm">
                                Laporan Project Sentral {unit.name}
                            </h3>
                            <h1 className="text-sm font-extrabold uppercase tracking-wide text-primary md:text-base">
                                Logbook Pemantauan Pemanfaatan Air Limbah
                            </h1>
                            <p className="text-[11px] text-muted-foreground">
                                Periode: <span className="font-semibold text-foreground">{monthLabel} {filters.year}</span>
                            </p>
                        </div>
                        <div className="flex items-center justify-center p-1">
                            <img
                                src="/logo/mkp.jpg"
                                alt="MKP Mitra Karya Prima"
                                className="max-h-12 max-w-[130px] object-contain"
                                onError={(e) => {
                                    (e.target as HTMLElement).style.display = 'none';
                                }}
                            />
                        </div>
                    </div>

                    {/* Table View */}
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr className="border-b border-slate-300 bg-[#85b5e1]/30 text-center font-semibold text-slate-800 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-200">
                                    <th rowSpan={2} className="w-12 border-r border-slate-300 px-3 py-2.5 dark:border-slate-700">
                                        No
                                    </th>
                                    <th rowSpan={2} className="min-w-[200px] border-r border-slate-300 px-3 py-2.5 text-left dark:border-slate-700">
                                        Area Penyiraman
                                    </th>
                                    <th rowSpan={2} className="min-w-[120px] border-r border-slate-300 px-3 py-2.5 dark:border-slate-700">
                                        Tanggal
                                    </th>
                                    <th rowSpan={2} className="min-w-[110px] border-r border-slate-300 px-3 py-2.5 dark:border-slate-700">
                                        Waktu Penyiraman
                                    </th>
                                    <th rowSpan={2} className="min-w-[160px] border-r border-slate-300 px-3 py-2.5 dark:border-slate-700">
                                        Metode Pemanfaatan
                                    </th>
                                    <th colSpan={3} className="border-b border-r border-slate-300 px-3 py-1.5 dark:border-slate-700">
                                        Debit Air (Flow Meter)
                                    </th>
                                    <th rowSpan={2} className="min-w-[140px] border-r border-slate-300 px-3 py-2.5 dark:border-slate-700">
                                        Rotasi dan Frekuensi penyiraman (kali/bulan)
                                    </th>
                                    <th rowSpan={2} className="w-20 border-r border-slate-300 px-3 py-2.5 dark:border-slate-700">
                                        PIC
                                    </th>
                                    {can_write && (
                                        <th rowSpan={2} className="w-24 px-3 py-2.5 text-center">
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                                <tr className="border-b border-slate-300 bg-[#85b5e1]/40 text-center font-semibold text-slate-800 dark:border-slate-700 dark:bg-slate-800/90 dark:text-slate-200">
                                    <th className="w-20 border-r border-slate-300 px-2 py-1.5 dark:border-slate-700">Awal</th>
                                    <th className="w-20 border-r border-slate-300 px-2 py-1.5 dark:border-slate-700">Akhir</th>
                                    <th className="w-20 border-r border-slate-300 px-2 py-1.5 dark:border-slate-700">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={can_write ? 11 : 10}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            Belum ada catatan logbook untuk periode ini.
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row, index) => (
                                        <tr
                                            key={row._key}
                                            className="transition-colors hover:bg-muted/40"
                                        >
                                            <td className="border-r border-border px-3 py-3 text-center font-medium text-muted-foreground">
                                                {index + 1}
                                            </td>
                                            <td className="border-r border-border px-3 py-3 font-medium text-foreground">
                                                {row.area_penyiraman}
                                            </td>
                                            <td className="border-r border-border px-3 py-3 text-center text-muted-foreground">
                                                {formatIndoDate(row.tanggal)}
                                            </td>
                                            <td className="border-r border-border px-3 py-3 text-center">
                                                <Badge variant="secondary" className="font-mono text-[11px]">
                                                    {row.waktu_penyiraman || '-'}
                                                </Badge>
                                            </td>
                                            <td className="border-r border-border px-3 py-3">
                                                <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                                                    <Droplets className="size-3.5 text-blue-500" />
                                                    {row.metode_pemanfaatan}
                                                </span>
                                            </td>
                                            <td className="border-r border-border px-3 py-3 text-center font-mono">
                                                {row.debit_awal}
                                            </td>
                                            <td className="border-r border-border px-3 py-3 text-center font-mono">
                                                {row.debit_akhir}
                                            </td>
                                            <td className="border-r border-border bg-primary/5 px-3 py-3 text-center font-mono font-bold text-primary">
                                                {row.debit_jumlah}
                                            </td>
                                            <td className="border-r border-border px-3 py-3 text-center font-medium">
                                                {row.frekuensi}
                                            </td>
                                            <td className="border-r border-border px-3 py-3 text-center">
                                                <Badge variant="outline" className="font-semibold text-emerald-600 dark:text-emerald-400">
                                                    {row.pic || 'K3L'}
                                                </Badge>
                                            </td>
                                            {can_write && (
                                                <td className="px-3 py-3 text-center">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-7 text-muted-foreground hover:text-foreground"
                                                            onClick={() => openEditModal(row)}
                                                            title="Ubah data baris"
                                                        >
                                                            <Pencil className="size-3.5" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-7 text-destructive hover:bg-destructive/10"
                                                            onClick={() => handleDeleteRow(row._key)}
                                                            title="Hapus baris"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                            {rows.length > 0 && (
                                <tfoot>
                                    <tr className="border-t-2 border-border bg-muted/60 font-semibold">
                                        <td colSpan={5} className="border-r border-border px-4 py-2.5 text-right uppercase tracking-wider text-muted-foreground">
                                            Total Debit Pemanfaatan Air Limbah:
                                        </td>
                                        <td colSpan={3} className="border-r border-border bg-primary/10 px-3 py-2.5 text-center font-mono text-sm font-bold text-primary">
                                            {Math.round(totalDebitJumlah * 100) / 100}
                                        </td>
                                        <td colSpan={can_write ? 3 : 2} className="px-3 py-2.5 text-xs text-muted-foreground">
                                            ({rows.length} area terdata)
                                        </td>
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </div>
            </div>

            {/* Modal Dialog for Create & Edit */}
            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Droplets className="size-5 text-blue-500" />
                            {editingKey === null ? 'Tambah Catatan Pemanfaatan Air Limbah' : 'Ubah Catatan Air Limbah'}
                        </DialogTitle>
                        <DialogDescription>
                            Isi detail volume dan waktu pemanfaatan air limbah untuk penyiraman tanaman.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-3">
                        {/* Area Penyiraman */}
                        <div className="space-y-1.5">
                            <Label htmlFor="area_penyiraman">Area Penyiraman</Label>
                            <Input
                                id="area_penyiraman"
                                value={form.area_penyiraman}
                                onChange={(e) => setForm({ ...form, area_penyiraman: e.target.value })}
                                placeholder="Contoh: Taman Area Kantor"
                                list="default-areas-list"
                            />
                            <datalist id="default-areas-list">
                                {defaultAreas.map((area) => (
                                    <option key={area} value={area} />
                                ))}
                            </datalist>
                            <div className="flex flex-wrap gap-1 pt-1">
                                {defaultAreas.map((area) => (
                                    <button
                                        key={area}
                                        type="button"
                                        onClick={() => setForm({ ...form, area_penyiraman: area })}
                                        className="rounded bg-muted px-2 py-0.5 text-[11px] text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                                    >
                                        + {area}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Tanggal & Waktu */}
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="tanggal">Tanggal</Label>
                                <Input
                                    id="tanggal"
                                    type="date"
                                    value={form.tanggal}
                                    onChange={(e) => setForm({ ...form, tanggal: e.target.value })}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="waktu_penyiraman">Waktu Penyiraman</Label>
                                <Input
                                    id="waktu_penyiraman"
                                    value={form.waktu_penyiraman}
                                    onChange={(e) => setForm({ ...form, waktu_penyiraman: e.target.value })}
                                    placeholder="Contoh: 16.00 WITA"
                                />
                            </div>
                        </div>

                        {/* Metode Pemanfaatan */}
                        <div className="space-y-1.5">
                            <Label htmlFor="metode_pemanfaatan">Metode Pemanfaatan</Label>
                            <Input
                                id="metode_pemanfaatan"
                                value={form.metode_pemanfaatan}
                                onChange={(e) => setForm({ ...form, metode_pemanfaatan: e.target.value })}
                                placeholder="Contoh: Penyiraman Tanaman"
                            />
                        </div>

                        {/* Debit Flow Meter */}
                        <div className="rounded-lg border border-border bg-muted/30 p-3 space-y-2">
                            <Label className="text-xs font-semibold text-foreground">
                                Debit Air (Flow Meter)
                            </Label>
                            <div className="grid grid-cols-3 gap-2">
                                <div className="space-y-1">
                                    <span className="text-[11px] text-muted-foreground">Awal</span>
                                    <Input
                                        type="number"
                                        step="any"
                                        value={form.debit_awal}
                                        onChange={(e) => handleDebitChange('debit_awal', e.target.value)}
                                        placeholder="0"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <span className="text-[11px] text-muted-foreground">Akhir</span>
                                    <Input
                                        type="number"
                                        step="any"
                                        value={form.debit_akhir}
                                        onChange={(e) => handleDebitChange('debit_akhir', e.target.value)}
                                        placeholder="0"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <span className="text-[11px] font-semibold text-primary">Jumlah (Selisih)</span>
                                    <Input
                                        type="number"
                                        step="any"
                                        value={form.debit_jumlah}
                                        readOnly
                                        className="bg-primary/10 font-bold text-primary"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Frekuensi & PIC */}
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1.5">
                                <Label htmlFor="frekuensi">Rotasi & Frekuensi (kali/bulan)</Label>
                                <Input
                                    id="frekuensi"
                                    value={form.frekuensi}
                                    onChange={(e) => setForm({ ...form, frekuensi: e.target.value })}
                                    placeholder="Contoh: 1"
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="pic">PIC Pelaksana</Label>
                                <Input
                                    id="pic"
                                    value={form.pic}
                                    onChange={(e) => setForm({ ...form, pic: e.target.value })}
                                    placeholder="Contoh: K3L"
                                />
                            </div>
                        </div>
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setModalOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button type="button" onClick={handleFormSubmit}>
                            {editingKey === null ? 'Tambahkan ke Tabel' : 'Simpan Perubahan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Print View Container */}
            <div className="print-container hidden print:block">
                <table style={{ width: '100%', borderCollapse: 'collapse', border: '1.5px solid #000', marginBottom: '8px' }}>
                    <tbody>
                        <tr>
                            <td style={{ width: '130px', textAlign: 'center', padding: '4px', border: '1px solid #000' }}>
                                <img src="/logo/sidebar-logo.png" alt="PLN" style={{ maxHeight: '42px', maxWidth: '120px' }} />
                            </td>
                            <td style={{ textAlign: 'center', padding: '4px 8px', border: '1px solid #000' }}>
                                <div style={{ fontSize: '10px', fontWeight: 'bold', textTransform: 'uppercase' }}>
                                    JASA PENDUKUNG TEKNIS UP KENDARI {unit.name.toUpperCase()}
                                </div>
                                <div style={{ fontSize: '9px', fontWeight: 'bold', textTransform: 'uppercase', marginTop: '2px' }}>
                                    LAPORAN PROJECT SENTRAL {unit.name.toUpperCase()}
                                </div>
                                <div style={{ fontSize: '9.5px', fontWeight: 'bold', textTransform: 'uppercase', textDecoration: 'underline', marginTop: '2px' }}>
                                    LOGBOOK PEMANTAUAN PEMANFAATAN AIR LIMBAH
                                </div>
                                <div style={{ fontSize: '7.5px', marginTop: '2px' }}>
                                    Bulan: {monthLabel} {filters.year}
                                </div>
                            </td>
                            <td style={{ width: '130px', textAlign: 'center', padding: '4px', border: '1px solid #000' }}>
                                <img src="/logo/mkp.jpg" alt="MKP" style={{ maxHeight: '42px', maxWidth: '120px' }} />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table className="print-table">
                    <thead className="print-thead">
                        <tr>
                            <th rowSpan={2} style={{ width: '25px' }}>No</th>
                            <th rowSpan={2} style={{ width: '180px', textAlign: 'left', paddingLeft: '8px' }}>Area Penyiraman</th>
                            <th rowSpan={2} style={{ width: '90px' }}>Tanggal</th>
                            <th rowSpan={2} style={{ width: '90px' }}>Waktu Penyiraman</th>
                            <th rowSpan={2} style={{ width: '120px', textAlign: 'left', paddingLeft: '8px' }}>Metode Pemanfaatan</th>
                            <th colSpan={3}>Debit Air (Flow Meter)</th>
                            <th rowSpan={2} style={{ width: '120px' }}>Rotasi dan Frekuensi penyiraman (kali/bulan)</th>
                            <th rowSpan={2} style={{ width: '55px' }}>PIC</th>
                        </tr>
                        <tr>
                            <th style={{ width: '45px' }}>Awal</th>
                            <th style={{ width: '45px' }}>Akhir</th>
                            <th style={{ width: '45px' }}>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r, i) => (
                            <tr key={r._key}>
                                <td style={{ fontWeight: 'bold' }}>{i + 1}</td>
                                <td style={{ textAlign: 'left', paddingLeft: '8px' }}>{r.area_penyiraman}</td>
                                <td>{formatIndoDate(r.tanggal)}</td>
                                <td>{r.waktu_penyiraman}</td>
                                <td style={{ textAlign: 'left', paddingLeft: '8px' }}>{r.metode_pemanfaatan}</td>
                                <td>{r.debit_awal}</td>
                                <td>{r.debit_akhir}</td>
                                <td style={{ fontWeight: 'bold' }}>{r.debit_jumlah}</td>
                                <td>{r.frekuensi}</td>
                                <td>{r.pic}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}

AirLimbahPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input K3 & Keamanan', href: k3Input.index() },
        { title: 'Logbook Pemantauan Air Limbah', href: airLimbah.index() },
    ],
};
