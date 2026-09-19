import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCheck,
    Download,
    Pencil,
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
import jadwal from '@/routes/k3/jadwal';
import pekerjaanRutin from '@/routes/k3/jadwal/pekerjaan-rutin';
import type { IdName } from '@/types';
import { toast } from 'sonner';

type DayInfo = { day: number; dow: string; is_red: boolean; holiday?: string | null };

type ServerRow = {
    id: number | null;
    no_urut: number | null;
    uraian: string;
    rencana: number[];
    realisasi: number[];
    paraf: string | null;
    sort_order: number;
};

type Row = ServerRow & { _key: number };

type Props = {
    unit: { id: number; name: string; service_unit_id: number | null; service_unit_name: string | null };
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
    days: DayInfo[];
    rows: ServerRow[];
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
    .print-table th, .print-table td { border: 1px solid #000 !important; padding: 1.5px 1px !important; text-align: center !important; vertical-align: middle !important; }
    .print-thead th { background-color: #fff !important; color: #000 !important; font-weight: bold !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-red-text { color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-red-cell { background-color: #ff0000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-green-cell { background-color: #22c55e !important; color: #000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-yellow-cell { background-color: #fde047 !important; color: #000 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
`;

const hydrate = (rows: ServerRow[]): Row[] => rows.map((r, i) => ({ ...r, _key: i }));

export default function K3PekerjaanRutinPage({
    unit,
    filters,
    options,
    days,
    rows: initialRows,
    can_write,
}: Props) {
    const [rows, setRows] = useState<Row[]>(() => hydrate(initialRows));
    const [nextKey, setNextKey] = useState(initialRows.length);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    // Modal state for Add & Edit
    const [modalOpen, setModalOpen] = useState(false);
    const [editingKey, setEditingKey] = useState<number | null>(null);
    const [form, setForm] = useState({ uraian: '', paraf: 'Empty' });

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);
    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(hydrate(initialRows));
        setNextKey(initialRows.length);
        setDirty(false);
    }

    const monthName = useMemo(() => OPERASI_MONTHS[filters.month - 1] ?? '', [filters.month]);
    const workingDaysList = useMemo(() => days.filter((d) => !d.is_red).map((d) => d.day), [days]);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            pekerjaanRutin.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const toggleCell = (key: number, category: Category, day: number) => {
        if (!can_write) return;
        setRows((prev) =>
            prev.map((r) => {
                if (r._key !== key) return r;
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

    const handleMarkPlanWorkingDays = () => {
        if (!can_write) return;
        setRows((prev) => prev.map((r) => ({ ...r, rencana: [...workingDaysList] })));
        setDirty(true);
        toast.success('Rencana seluruh hari kerja telah ditandai.');
    };

    const openCreateModal = () => {
        setEditingKey(null);
        setForm({ uraian: '', paraf: 'Empty' });
        setModalOpen(true);
    };

    const openEditModal = (row: Row) => {
        setEditingKey(row._key);
        setForm({ uraian: row.uraian, paraf: row.paraf || 'Empty' });
        setModalOpen(true);
    };

    const handleModalSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const trimmedUraian = form.uraian.trim();
        if (!trimmedUraian) {
            toast.error('Uraian pekerjaan wajib diisi.');
            return;
        }

        if (editingKey !== null) {
            setRows((prev) =>
                prev.map((r) =>
                    r._key === editingKey
                        ? { ...r, uraian: trimmedUraian, paraf: form.paraf.trim() || 'Empty' }
                        : r,
                ),
            );
        } else {
            setRows((prev) => [
                ...prev,
                {
                    _key: nextKey,
                    id: null,
                    no_urut: prev.length + 1,
                    uraian: trimmedUraian,
                    rencana: [],
                    realisasi: [],
                    paraf: form.paraf.trim() || 'Empty',
                    sort_order: prev.length,
                },
            ]);
            setNextKey((k) => k + 1);
        }

        setDirty(true);
        setModalOpen(false);
    };

    const handleRemove = (key: number) => {
        if (!can_write) return;
        const target = rows.find((r) => r._key === key);
        if (!window.confirm(`Hapus pekerjaan "${target?.uraian || 'ini'}"?`)) {
            return;
        }
        setRows((prev) => prev.filter((r) => r._key !== key));
        setDirty(true);
    };

    const handleReset = () => {
        setRows(hydrate(initialRows));
        setDirty(false);
    };

    const handleSave = () => {
        if (!can_write) return;
        setSaving(true);
        router.post(
            pekerjaanRutin.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((r, idx) => ({
                    id: r.id,
                    no_urut: idx + 1,
                    uraian: r.uraian,
                    rencana: r.rencana,
                    realisasi: r.realisasi,
                    paraf: r.paraf,
                    sort_order: idx,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDirty(false);
                    toast.success('Jadwal Pekerjaan Rutin berhasil disimpan.');
                },
                onError: () => {
                    toast.error('Gagal menyimpan ke server.');
                },
                onFinish: () => setSaving(false),
            },
        );
    };

    // Overall stats
    const { totalTarget, totalReal, overallKinerja } = useMemo(() => {
        let tTarget = 0;
        let tReal = 0;
        rows.forEach((r) => {
            tTarget += r.rencana.length;
            tReal += r.realisasi.length;
        });
        const pct = tTarget > 0 ? Math.round((tReal / tTarget) * 100) : 0;
        return { totalTarget: tTarget, totalReal: tReal, overallKinerja: pct };
    }, [rows]);

    const dayCells = (row: Row, category: Category) =>
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
            let color = '';
            if (on) {
                if (category === 'realisasi') {
                    color = 'bg-emerald-400 text-black print-green-cell font-bold';
                } else {
                    color = realized
                        ? 'bg-emerald-400 text-black print-green-cell font-bold'
                        : 'bg-yellow-300 text-black print-yellow-cell font-bold';
                }
            }
            return (
                <td
                    key={`${category}-${d.day}`}
                    onClick={() => toggleCell(row._key, category, d.day)}
                    className={`border border-black text-center text-[11px] transition-colors select-none ${
                        color || 'hover:bg-muted/40'
                    } ${can_write ? 'cursor-pointer' : ''}`}
                    title={`Tanggal ${d.day} (${d.dow}) — ${
                        category === 'rencana' ? 'Rencana' : 'Realisasi'
                    }: klik untuk ${on ? 'batalkan' : 'tandai (1)'}`}
                >
                    {on ? '1' : ''}
                </td>
            );
        });

    return (
        <>
            <Head title={`Jadwal Pekerjaan Rutin K3L & Lingkungan 11 KIT — ${unit.name}`} />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                {/* Header bar */}
                <div className="no-print flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => router.get(jadwal.index().url)}
                            title="Kembali ke Daftar Jadwal"
                        >
                            <ArrowLeft className="size-4" />
                        </Button>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="text-xl font-bold text-foreground">
                                    Jadwal Pekerjaan Rutin K3L &amp; Lingkungan 11 KIT
                                </h1>
                                <Badge variant="outline" className="border-primary/30 bg-primary/10 text-primary">
                                    {unit.name}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Klik kotak tanggal pada baris RENC / REAL untuk menandai jadwal (1). Target = jumlah rencana, Kinerja = realisasi ÷ target.
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
                                    onClick={handleMarkPlanWorkingDays}
                                    className="gap-1.5 text-xs"
                                    title="Isi rencana untuk seluruh hari kerja"
                                >
                                    <CheckCheck className="size-3.5 text-primary" />
                                    Tandai Rencana Hari Kerja
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={openCreateModal}
                                    className="gap-1.5 text-xs"
                                >
                                    <Plus className="size-3.5" />
                                    Tambah Pekerjaan
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
                        <a
                            href={`${pekerjaanRutin.pdf().url}?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Button variant="outline" size="sm" className="gap-1.5 text-xs" title="Unduh format PDF resmi">
                                <Download className="size-3.5 text-rose-600" />
                                PDF
                            </Button>
                        </a>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => window.print()}
                            className="gap-1.5 text-xs"
                            title="Cetak Lanskap"
                        >
                            <Printer className="size-3.5" />
                            Cetak
                        </Button>
                    </div>
                </div>

                {/* Filters & summary */}
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

                    <div className="mx-1 hidden h-9 w-px self-center bg-border lg:block" />

                    <div className="flex items-center gap-3 pb-1 text-xs">
                        <div>
                            <span className="text-muted-foreground">Total Target:</span>{' '}
                            <span className="font-semibold text-foreground">{totalTarget}</span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">Total Real:</span>{' '}
                            <span className="font-semibold text-foreground">{totalReal}</span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">Rata-rata Kinerja:</span>{' '}
                            <span
                                className={`font-semibold ${
                                    overallKinerja >= 100
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-amber-600 dark:text-amber-400'
                                }`}
                            >
                                {totalTarget > 0 ? `${overallKinerja}%` : '0%'}
                            </span>
                        </div>
                    </div>

                    {dirty && (
                        <div className="ml-auto flex items-center gap-1.5 pb-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                            <span className="size-2 animate-pulse rounded-full bg-amber-500" />
                            Ada perubahan belum disimpan
                        </div>
                    )}
                </div>

                {/* Printable Document Card */}
                <div className="print-container overflow-hidden rounded-md border border-border bg-card shadow-xs">
                    {/* Header with Logos */}
                    <div className="border-b border-border p-4">
                        <div className="grid grid-cols-12 items-stretch border border-black dark:border-border">
                            <div className="col-span-3 flex items-center justify-center border-r border-black p-2 dark:border-border">
                                <img
                                    src="/logo/sidebar-logo.png"
                                    alt="PLN Nusantara Power"
                                    className="max-h-12 object-contain"
                                    onError={(e) => {
                                        (e.target as HTMLElement).style.display = 'none';
                                    }}
                                />
                            </div>
                            <div className="col-span-6 flex flex-col justify-center p-2 text-center">
                                <div className="text-xs font-bold tracking-wide text-foreground uppercase">
                                    JASA PENDUKUNG TEKNIS 11 &amp; 6 SITE - KIT
                                </div>
                                <div className="text-xs font-bold text-foreground uppercase">
                                    PLN NP UP KENDARI - {unit.name.toUpperCase()}
                                </div>
                                <div className="mt-0.5 text-[11px] font-semibold text-foreground uppercase">
                                    LAPORAN PROJECT
                                </div>
                                <div className="text-[11px] font-bold text-foreground uppercase">
                                    JADWAL PEKERJAAN RUTIN K3L BULAN {monthName.toUpperCase()} {filters.year}
                                </div>
                            </div>
                            <div className="col-span-3 flex items-center justify-center border-l border-black p-2 dark:border-border">
                                <img
                                    src="/logo/mkp.jpg"
                                    alt="Mitra Karya Prima"
                                    className="max-h-12 object-contain"
                                    onError={(e) => {
                                        (e.target as HTMLElement).style.display = 'none';
                                    }}
                                />
                            </div>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="overflow-x-auto">
                        <table className="print-table w-full border-collapse text-xs">
                            <thead className="print-thead bg-muted/60 dark:bg-muted/30">
                                <tr>
                                    <th rowSpan={2} className="w-8 border border-black p-1 text-center font-bold text-foreground">
                                        No
                                    </th>
                                    <th rowSpan={2} className="w-72 min-w-60 border border-black p-1 text-left font-bold text-foreground">
                                        URAIAN
                                    </th>
                                    <th rowSpan={2} className="w-12 border border-black p-1 text-center font-bold text-foreground">
                                        STATUS
                                    </th>
                                    <th
                                        colSpan={days.length}
                                        className="border border-black p-1 text-center font-bold tracking-wider text-foreground uppercase"
                                    >
                                        TANGGAL
                                    </th>
                                    <th rowSpan={2} className="w-14 border border-black p-1 text-center font-bold text-foreground">
                                        TARGET
                                    </th>
                                    <th rowSpan={2} className="w-14 border border-black p-1 text-center font-bold text-foreground">
                                        REAL
                                    </th>
                                    <th rowSpan={2} className="w-16 border border-black p-1 text-center font-bold text-foreground">
                                        KINERJA
                                    </th>
                                    <th rowSpan={2} className="w-20 border border-black p-1 text-center font-bold text-foreground">
                                        PARAF
                                    </th>
                                    {can_write && (
                                        <th rowSpan={2} className="no-print w-16 border border-black p-1 text-center font-bold text-foreground">
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                                <tr>
                                    {days.map((d) => (
                                        <th
                                            key={d.day}
                                            className={`w-6 border border-black p-0.5 text-center text-[10px] font-bold ${
                                                d.is_red ? 'print-red-text text-red-600' : 'text-foreground'
                                            }`}
                                            title={`${d.day} (${d.dow})${d.holiday ? ` - ${d.holiday}` : ''}`}
                                        >
                                            {String(d.day).padStart(2, '0')}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row, idx) => {
                                    const target = row.rencana.length;
                                    const real = row.realisasi.length;
                                    const kinerja = target > 0 ? `${Math.round((real / target) * 100)}%` : '0%';

                                    return (
                                        <Fragment key={row._key}>
                                            <tr className="hover:bg-muted/10">
                                                {/* Baris RENC */}
                                                <td rowSpan={2} className="border border-black text-center font-medium">
                                                    {idx + 1}
                                                </td>
                                                <td rowSpan={2} className="border border-black px-2 py-1 text-left font-semibold text-foreground">
                                                    {row.uraian}
                                                </td>
                                                <td className="border border-black bg-muted/40 text-center font-bold text-[10px]">
                                                    RENC
                                                </td>
                                                {dayCells(row, 'rencana')}
                                                <td rowSpan={2} className="border border-black text-center font-bold">
                                                    {target}
                                                </td>
                                                <td rowSpan={2} className="border border-black text-center font-bold">
                                                    {real}
                                                </td>
                                                <td rowSpan={2} className="border border-black text-center font-bold text-foreground">
                                                    {kinerja}
                                                </td>
                                                <td rowSpan={2} className="border border-black text-center text-xs text-muted-foreground">
                                                    {row.paraf || 'Empty'}
                                                </td>
                                                {can_write && (
                                                    <td rowSpan={2} className="no-print border border-black p-1 text-center">
                                                        <div className="flex items-center justify-center gap-1">
                                                            <button
                                                                type="button"
                                                                onClick={() => openEditModal(row)}
                                                                className="rounded p-1 text-primary hover:bg-primary/10 transition-colors"
                                                                title="Edit Uraian / Paraf"
                                                            >
                                                                <Pencil className="size-3.5" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleRemove(row._key)}
                                                                className="rounded p-1 text-destructive hover:bg-destructive/10 transition-colors"
                                                                title="Hapus Pekerjaan"
                                                            >
                                                                <Trash2 className="size-3.5" />
                                                            </button>
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                            <tr className="hover:bg-muted/10">
                                                <td className="border border-black bg-muted/40 text-center font-bold text-[10px]">
                                                    REAL
                                                </td>
                                                {dayCells(row, 'realisasi')}
                                            </tr>
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {/* Keterangan & Catatan */}
                    <div className="border-t border-border p-3 text-xs">
                        <div className="font-semibold text-foreground">Keterangan :</div>
                        <div className="mt-1 flex flex-col gap-0.5 text-muted-foreground">
                            <div>v : kondisi baik</div>
                            <div>x : kondisi tidak baik</div>
                            <div className="mt-1 text-[11px] text-muted-foreground/80">
                                💡 Catatan: Angka 1 pada baris RENC menandakan jadwal rencana, angka 1 pada baris REAL menandakan realisasi.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal Dialog Tambah / Edit Pekerjaan */}
            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editingKey !== null ? 'Edit Pekerjaan Rutin' : 'Tambah Pekerjaan Rutin'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingKey !== null
                                ? 'Perbarui uraian pekerjaan atau paraf di bawah ini.'
                                : 'Masukkan uraian tugas kegiatan rutin K3L baru ke dalam jadwal.'}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={handleModalSubmit} className="space-y-4 py-2">
                        <div className="space-y-1.5">
                            <Label htmlFor="uraian">
                                Uraian Pekerjaan <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="uraian"
                                placeholder="Contoh: CEK DAN BERSIHKAN OIL TRAP"
                                value={form.uraian}
                                onChange={(e) => setForm((prev) => ({ ...prev, uraian: e.target.value }))}
                                required
                                autoFocus
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="paraf">Paraf / Status</Label>
                            <Input
                                id="paraf"
                                placeholder="Empty"
                                value={form.paraf}
                                onChange={(e) => setForm((prev) => ({ ...prev, paraf: e.target.value }))}
                            />
                        </div>

                        <DialogFooter className="pt-2 gap-2 sm:gap-0">
                            <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>
                                Batal
                            </Button>
                            <Button type="submit">
                                {editingKey !== null ? 'Perbarui' : 'Tambahkan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

K3PekerjaanRutinPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal K3', href: jadwal.index() },
        { title: 'Pekerjaan Rutin K3L & Lingkungan 11 KIT', href: pekerjaanRutin.index() },
    ],
};
