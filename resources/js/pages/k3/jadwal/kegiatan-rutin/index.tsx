import { Head, router } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    CheckCheck,
    Download,
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import jadwal from '@/routes/k3/jadwal';
import kegiatanRutin from '@/routes/k3/jadwal/kegiatan-rutin';
import type { IdName } from '@/types';

type Grup = 'harian' | 'mingguan' | 'bulanan';

type DayInfo = {
    day: number;
    dow: string;
    is_weekend: boolean;
    is_holiday: boolean;
    is_red: boolean;
    holiday?: string | null;
};

type ServerRow = {
    id: number | null;
    grup: Grup;
    no_urut: number | null;
    kegiatan: string;
    target: number;
    realisasi_count: number;
    jadwal: number[];
    keterangan?: string;
    sort_order: number;
};

type Row = ServerRow & { _key: number };

type Props = {
    unit: {
        id: number;
        name: string;
        service_unit_id: number | null;
        service_unit_name: string | null;
    };
    filters: { unit_id: number; month: number; year: number };
    options: { units: IdName[]; years: number[] };
    days: DayInfo[];
    target_working_days: number;
    rows: ServerRow[];
    can_write: boolean;
};

const GROUPS: { key: Grup; roman: string; label: string }[] = [
    { key: 'harian', roman: 'I', label: 'Harian' },
    { key: 'mingguan', roman: 'II', label: 'Mingguan' },
    { key: 'bulanan', roman: 'III', label: 'Bulanan' },
];

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 6mm; }
    body * { visibility: hidden !important; }
    .print-container, .print-container * { visibility: visible !important; }
    .print-container { position: absolute !important; left: 0 !important; top: 0 !important; width: 100% !important; background: #fff !important; color: #000 !important; padding: 0 !important; margin: 0 !important; }
    .no-print { display: none !important; }
    .print-table { width: 100% !important; border-collapse: collapse !important; font-size: 7.5px !important; }
    .print-table th, .print-table td { border: 1px solid #000 !important; padding: 2px 1px !important; text-align: center !important; vertical-align: middle !important; }
    .print-thead th { background-color: #ffffff !important; color: #000 !important; font-weight: bold !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-red-text { color: #dc2626 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-red-cell { background-color: #ff0000 !important; color: #fff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .print-group-row { background-color: #e5e7eb !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
`;

const hydrate = (rows: ServerRow[]): Row[] =>
    rows.map((r, i) => ({ ...r, _key: i }));

export default function K3KegiatanRutinPage({
    unit,
    filters,
    options,
    days,
    target_working_days,
    rows: initialRows,
    can_write,
}: Props) {
    const [rows, setRows] = useState<Row[]>(() => hydrate(initialRows));
    const [nextKey, setNextKey] = useState(initialRows.length);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const [isAddOpen, setIsAddOpen] = useState(false);
    const [newGrup, setNewGrup] = useState<Grup>('harian');
    const [newKegiatan, setNewKegiatan] = useState('');
    const [newTarget, setNewTarget] = useState(target_working_days);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);
    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(hydrate(initialRows));
        setNextKey(initialRows.length);
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
            kegiatanRutin.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const toggleDay = (key: number, day: number) => {
        if (!can_write) return;
        setRows((prev) =>
            prev.map((r) => {
                if (r._key !== key) return r;
                const set = new Set(r.jadwal || []);
                if (set.has(day)) {
                    set.delete(day);
                } else {
                    set.add(day);
                }
                const jadwal = Array.from(set).sort((a, b) => a - b);
                return { ...r, jadwal, realisasi_count: jadwal.length };
            }),
        );
        setDirty(true);
    };

    const updateField = (
        key: number,
        field: 'kegiatan' | 'target' | 'keterangan',
        value: string | number,
    ) => {
        if (!can_write) return;
        setRows((prev) =>
            prev.map((r) => (r._key === key ? { ...r, [field]: value } : r)),
        );
        setDirty(true);
    };

    const handleMarkAllWorkingDays = () => {
        if (!can_write) return;
        setRows((prev) =>
            prev.map((r) => ({
                ...r,
                jadwal: [...workingDaysList],
                realisasi_count: workingDaysList.length,
            })),
        );
        setDirty(true);
    };

    const handleAddActivity = () => {
        if (!newKegiatan.trim()) return;
        setRows((prev) => [
            ...prev,
            {
                _key: nextKey,
                id: null,
                grup: newGrup,
                no_urut: null,
                kegiatan: newKegiatan.trim(),
                target: Number(newTarget) || target_working_days,
                realisasi_count: 0,
                jadwal: [],
                keterangan: '',
                sort_order: prev.length,
            },
        ]);
        setNextKey((k) => k + 1);
        setDirty(true);
        setIsAddOpen(false);
        setNewKegiatan('');
    };

    const handleRemoveRow = (key: number) => {
        if (!can_write) return;
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

        // Persist grouped and in display order so the stored sequence is stable.
        const ordered = GROUPS.flatMap((g) =>
            rows.filter((r) => r.grup === g.key),
        );

        router.post(
            kegiatanRutin.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: ordered.map((r, idx) => ({
                    id: r.id,
                    grup: r.grup,
                    no_urut: idx + 1,
                    kegiatan: r.kegiatan,
                    target: r.target,
                    jadwal: r.jadwal,
                    keterangan: r.keterangan,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const totalColsAfterNo = 1 + days.length + 3 + (can_write ? 1 : 0);

    return (
        <>
            <Head title={`Jadwal Kegiatan Rutin K3L KIT — ${unit.name}`} />
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
                                    Jadwal Kegiatan Rutin Harian, Mingguan &amp;
                                    Bulanan K3L KIT
                                </h1>
                                <Badge
                                    variant="outline"
                                    className="border-primary/30 bg-primary/10 text-primary"
                                >
                                    {unit.name}
                                </Badge>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Klik kotak tanggal untuk menandai pelaksanaan
                                (otomatis terisi 1 &amp; terakumulasi ke
                                realisasi/kinerja).
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
                                    onClick={handleMarkAllWorkingDays}
                                    className="gap-1.5 text-xs"
                                    title="Tandai seluruh hari kerja non-libur"
                                >
                                    <CheckCheck className="size-3.5 text-primary" />
                                    Tandai Semua Hari Kerja
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setIsAddOpen(true)}
                                    className="gap-1.5 text-xs"
                                >
                                    <Plus className="size-3.5" />
                                    Tambah Kegiatan
                                </Button>
                                <Button
                                    size="sm"
                                    onClick={handleSave}
                                    disabled={saving || !dirty}
                                    className="gap-1.5 text-xs"
                                >
                                    <Save className="size-3.5" />
                                    {saving ? 'Menyimpan…' : 'Simpan Jadwal'}
                                </Button>
                            </>
                        )}
                        <a
                            href={`${kegiatanRutin.pdf().url}?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-1.5 text-xs"
                                title="Unduh format PDF resmi"
                            >
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

                {/* Filters */}
                <div className="no-print flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect
                        label="Unit Pembangkit"
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
                        options={OPERASI_MONTHS.map((label, index) => ({
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
                    {dirty && (
                        <div className="flex items-center gap-1.5 pb-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                            <span className="size-2 animate-pulse rounded-full bg-amber-500" />
                            Ada perubahan belum disimpan
                        </div>
                    )}
                </div>

                {/* Printable card */}
                <div className="print-container overflow-hidden rounded-md border border-border bg-card shadow-xs">
                    {/* Document header */}
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
                                    JADWAL KEGIATAN K3L
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
                                    <th rowSpan={2} className="w-10 border border-black p-2 text-center font-bold text-foreground">
                                        No
                                    </th>
                                    <th rowSpan={2} className="w-64 min-w-56 border border-black p-2 text-left font-bold text-foreground">
                                        Nama Peralatan / Kegiatan
                                    </th>
                                    <th colSpan={days.length} className="border border-black p-1.5 text-center font-bold tracking-wider text-foreground uppercase">
                                        Tanggal
                                    </th>
                                    <th rowSpan={2} className="w-14 border border-black p-2 text-center font-bold text-foreground">
                                        TARGET
                                    </th>
                                    <th rowSpan={2} className="w-16 border border-black p-2 text-center font-bold text-foreground">
                                        REALISASI
                                    </th>
                                    <th rowSpan={2} className="w-16 border border-black p-2 text-center font-bold text-foreground">
                                        KINERJA
                                    </th>
                                    <th rowSpan={2} className="w-48 min-w-40 border border-black p-2 text-left font-bold text-foreground">
                                        Keterangan
                                    </th>
                                    {can_write && (
                                        <th rowSpan={2} className="no-print w-10 border border-black p-2 text-center font-bold text-foreground">
                                            Aksi
                                        </th>
                                    )}
                                </tr>
                                <tr>
                                    {days.map((d) => (
                                        <th
                                            key={d.day}
                                            className={`w-7 border border-black p-1 text-center font-bold ${d.is_red ? 'print-red-text text-red-600' : 'text-foreground'}`}
                                            title={`${d.day} (${d.dow})${d.holiday ? ` - ${d.holiday}` : ''}`}
                                        >
                                            {d.day}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {GROUPS.map((group) => {
                                    const groupRows = rows.filter((r) => r.grup === group.key);

                                    return (
                                        <Fragment key={group.key}>
                                            <tr className="print-group-row bg-muted/70 dark:bg-muted/40">
                                                <td className="border border-black p-1 text-center text-sm font-bold text-foreground">
                                                    {group.roman}
                                                </td>
                                                <td
                                                    colSpan={totalColsAfterNo}
                                                    className="border border-black p-1 text-left text-sm font-bold text-foreground italic"
                                                >
                                                    {group.label}
                                                </td>
                                            </tr>

                                            {groupRows.map((row, li) => {
                                                const realisasi = (row.jadwal || []).length;
                                                const target = row.target || target_working_days;
                                                const kinerja = target > 0 ? Math.round((realisasi / target) * 100) : 0;

                                                return (
                                                    <tr key={row._key} className="transition-colors hover:bg-muted/10">
                                                        <td className="border border-black p-1 text-center font-medium text-foreground">
                                                            {li + 1}
                                                        </td>
                                                        <td className="border border-black p-1 text-foreground">
                                                            {can_write ? (
                                                                <input
                                                                    type="text"
                                                                    value={row.kegiatan}
                                                                    onChange={(e) => updateField(row._key, 'kegiatan', e.target.value)}
                                                                    className="w-full bg-transparent px-1.5 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                                />
                                                            ) : (
                                                                <span className="px-1.5 text-xs">{row.kegiatan}</span>
                                                            )}
                                                        </td>
                                                        {days.map((d) => {
                                                            if (d.is_red) {
                                                                return (
                                                                    <td
                                                                        key={d.day}
                                                                        className="print-red-cell border border-black bg-[#ff0000] select-none"
                                                                        title={`${d.day} (${d.dow}): Libur / Akhir Pekan`}
                                                                    />
                                                                );
                                                            }
                                                            const isDone = row.jadwal.includes(d.day);
                                                            return (
                                                                <td
                                                                    key={d.day}
                                                                    onClick={() => toggleDay(row._key, d.day)}
                                                                    className={`border border-black text-center font-bold transition-colors select-none ${isDone ? 'bg-slate-200 text-foreground dark:bg-slate-700' : 'hover:bg-muted/40'} ${can_write ? 'cursor-pointer' : ''}`}
                                                                    title={`Tanggal ${d.day} (${d.dow}): ${isDone ? 'Terlaksana (1) - klik untuk batalkan' : 'Belum - klik untuk tandai (1)'}`}
                                                                >
                                                                    {isDone ? '1' : ''}
                                                                </td>
                                                            );
                                                        })}
                                                        <td className="border border-black p-1 text-center font-bold text-foreground">
                                                            {can_write ? (
                                                                <input
                                                                    type="number"
                                                                    min="0"
                                                                    value={row.target}
                                                                    onChange={(e) => updateField(row._key, 'target', Number(e.target.value))}
                                                                    className="w-12 bg-transparent text-center font-bold focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                                />
                                                            ) : (
                                                                row.target
                                                            )}
                                                        </td>
                                                        <td className="border border-black p-1 text-center font-extrabold text-foreground">
                                                            {realisasi}
                                                        </td>
                                                        <td className={`border border-black p-1 text-center font-extrabold ${kinerja >= 100 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}`}>
                                                            {kinerja}%
                                                        </td>
                                                        <td className="border border-black p-1 text-foreground">
                                                            {can_write ? (
                                                                <input
                                                                    type="text"
                                                                    value={row.keterangan || ''}
                                                                    onChange={(e) => updateField(row._key, 'keterangan', e.target.value)}
                                                                    placeholder="Catatan…"
                                                                    className="w-full bg-transparent px-1 py-0.5 text-xs focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none"
                                                                />
                                                            ) : (
                                                                <span className="px-1 text-xs">{row.keterangan || '-'}</span>
                                                            )}
                                                        </td>
                                                        {can_write && (
                                                            <td className="no-print border border-black p-1 text-center">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-6 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                                    onClick={() => handleRemoveRow(row._key)}
                                                                    title="Hapus Kegiatan"
                                                                >
                                                                    <Trash2 className="size-3.5" />
                                                                </Button>
                                                            </td>
                                                        )}
                                                    </tr>
                                                );
                                            })}
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {/* Legend */}
                    <div className="border-t border-border bg-muted/20 p-4">
                        <div className="flex flex-col gap-2 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-wrap items-center gap-4">
                                <div className="flex items-center gap-1.5">
                                    <span className="size-3.5 rounded-xs border border-red-700 bg-[#ff0000]" />
                                    <span>Hari Libur / Akhir Pekan</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="size-3.5 rounded-xs border border-border bg-slate-200 dark:bg-slate-700" />
                                    <span>Terlaksana (1)</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="font-semibold text-foreground">Target Hari Kerja Bulan Ini:</span>
                                    <span>{target_working_days} Hari</span>
                                </div>
                            </div>
                            <div className="flex items-center gap-1.5">
                                <Info className="size-3.5 text-primary" />
                                <span>Gunakan &quot;Tandai Semua Hari Kerja&quot; untuk mengisi otomatis.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Add activity dialog */}
            <Dialog open={isAddOpen} onOpenChange={setIsAddOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Activity className="size-5 text-primary" />
                            Tambah Kegiatan K3L
                        </DialogTitle>
                        <DialogDescription>
                            Tambahkan kegiatan baru ke salah satu grup jadwal unit {unit.name}.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-3 py-2 text-sm">
                        <div className="grid gap-1.5">
                            <Label>Grup</Label>
                            <Select value={newGrup} onValueChange={(v) => setNewGrup(v as Grup)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {GROUPS.map((g) => (
                                        <SelectItem key={g.key} value={g.key}>
                                            {g.roman}. {g.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="kegiatan-name">Nama Kegiatan</Label>
                            <Input
                                id="kegiatan-name"
                                placeholder="Contoh: Inspeksi Rambu K3L"
                                value={newKegiatan}
                                onChange={(e) => setNewKegiatan(e.target.value)}
                                autoFocus
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="kegiatan-target">Target</Label>
                            <Input
                                id="kegiatan-target"
                                type="number"
                                min="0"
                                value={newTarget}
                                onChange={(e) => setNewTarget(Number(e.target.value))}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setIsAddOpen(false)}>
                            Batal
                        </Button>
                        <Button onClick={handleAddActivity} disabled={!newKegiatan.trim()}>
                            Tambahkan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

K3KegiatanRutinPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal K3 & Lingkungan', href: jadwal.index() },
        { title: 'Kegiatan Rutin K3L KIT', href: kegiatanRutin.index() },
    ],
};
