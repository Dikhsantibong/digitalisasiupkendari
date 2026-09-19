import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    Pencil,
    PhoneCall,
    Plus,
    Printer,
    RotateCcw,
    Save,
    Trash2,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
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
import jadwal from '@/routes/k3/jadwal';
import onCall from '@/routes/k3/jadwal/on-call';
import type { IdName } from '@/types';
import { toast } from 'sonner';

type DayInfo = {
    date: string; // "YYYY-MM-DD"
    day: number;
    month: number;
    year: number;
    dow: string;
    is_weekend: boolean;
    is_holiday: boolean;
    is_red: boolean;
    holiday?: string | null;
};

type ServerRow = {
    id: number | null;
    employee_id: number | null;
    nama: string;
    kode_prk: string;
    jabatan: string;
    schedule: Record<string, string>;
    total: number;
    nilai: number;
    rupiah: number;
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
    filters: {
        unit_id: number;
        month: number;
        year: number;
    };
    options: {
        units: IdName[];
        years: number[];
        employees: { id: number; name: string; position: string | null }[];
    };
    days: DayInfo[];
    rows: ServerRow[];
    can_write: boolean;
};

type SelectedCell = {
    rowKey: number;
    date: string;
    dayNum: number;
    personName: string;
    currentValue: string;
};

const SHIFT_OPTIONS = [
    { code: 'DT', label: 'DT (DAY TIME 08:00 - 16:00)', bg: 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200' },
    { code: 'P', label: 'P (SHIFT PAGI 07:30 - 15:30)', bg: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200' },
    { code: 'S', label: 'S (SHIFT SORE 15:00 - 22:30)', bg: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' },
    { code: 'M', label: 'M (SHIFT MALAM 22:30 - 07:30)', bg: 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-200' },
    { code: 'L', label: 'L (LIBUR KERJA)', bg: 'bg-rose-100 text-rose-800 font-bold dark:bg-rose-900/50 dark:text-rose-200' },
    { code: '1', label: '1 (ON CALL AKTIF)', bg: 'bg-sky-100 text-sky-800 font-bold dark:bg-sky-900/50 dark:text-sky-200' },
    { code: 'CT', label: 'CT (CUTI)', bg: 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-200' },
    { code: 'SD', label: 'SD (SURAT DOKTER / SAKIT)', bg: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200' },
    { code: 'I', label: 'I (IZIN)', bg: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' },
    { code: 'A', label: 'A (ALFA / MANGKIR)', bg: 'bg-zinc-200 text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100' },
    { code: 'D', label: 'D (DISPENSASI)', bg: 'bg-teal-100 text-teal-800 dark:bg-teal-900/50 dark:text-teal-200' },
    { code: 'DL', label: 'DL (DINAS LUAR)', bg: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/50 dark:text-cyan-200' },
];

const INACTIVE_CODES = ['L', 'OFF', 'CT', 'A', 'KTA', ''];

function calculateTotal(schedule: Record<string, string>): number {
    let count = 0;
    for (const key in schedule) {
        const val = schedule[key]?.trim()?.toUpperCase() ?? '';
        if (val && !INACTIVE_CODES.includes(val)) {
            count++;
        }
    }
    return count;
}

const PRINT_CSS = `
@media print {
    @page { size: A4 landscape; margin: 6mm 8mm; }
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
        font-size: 7px !important;
    }
    .print-table th, .print-table td {
        border: 1px solid #000 !important;
        padding: 2px 1px !important;
        text-align: center !important;
        vertical-align: middle !important;
    }
    .print-thead th {
        background-color: #fff !important;
        color: #000 !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-weekend {
        background-color: #fef08a !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-holiday {
        background-color: #fecaca !important;
        color: #b91c1c !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .print-total {
        background-color: #fef08a !important;
        font-weight: bold !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
`;

const hydrate = (rows: ServerRow[]): Row[] =>
    rows.map((r, i) => ({
        ...r,
        schedule: r.schedule || {},
        _key: i,
    }));

export default function JadwalOnCallPage({
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

    // Modal state for Add & Edit Personil
    const [personModalOpen, setPersonModalOpen] = useState(false);
    const [editingPersonKey, setEditingPersonKey] = useState<number | null>(null);
    const [personForm, setPersonForm] = useState({
        employee_id: '',
        nama: '',
        kode_prk: '',
        jabatan: 'Petugas K3L',
        nilai: '100000',
    });

    // Cell Shift Picker state
    const [activeCell, setActiveCell] = useState<SelectedCell | null>(null);

    const signature = `${filters.unit_id}-${filters.month}-${filters.year}`;
    const [lastSignature, setLastSignature] = useState(signature);
    if (signature !== lastSignature) {
        setLastSignature(signature);
        setRows(hydrate(initialRows));
        setNextKey(initialRows.length);
        setDirty(false);
        setActiveCell(null);
    }

    const monthLabel = useMemo(
        () => OPERASI_MONTHS[filters.month - 1] ?? `Bulan ${filters.month}`,
        [filters.month],
    );

    const prevMonthLabel = useMemo(() => {
        const prevIdx = filters.month === 1 ? 11 : filters.month - 2;
        return OPERASI_MONTHS[prevIdx] ?? '';
    }, [filters.month]);

    const prevYear = useMemo(() => {
        return filters.month === 1 ? filters.year - 1 : filters.year;
    }, [filters.month, filters.year]);

    const monthOptions = useMemo(
        () =>
            OPERASI_MONTHS.map((label, idx) => ({
                value: String(idx + 1),
                label,
            })),
        [],
    );

    const totalNilaiAll = useMemo(
        () => rows.reduce((sum, r) => sum + (Number(r.nilai) || 0), 0),
        [rows],
    );

    const totalRupiahAll = useMemo(
        () => rows.reduce((sum, r) => sum + (Number(r.rupiah) || 0), 0),
        [rows],
    );

    const totalOnCallAll = useMemo(
        () => rows.reduce((sum, r) => sum + (Number(r.total) || 0), 0),
        [rows],
    );

    const handleFilterChange = (key: 'unit_id' | 'month' | 'year', val: number) => {
        router.get(
            onCall.index().url,
            { ...filters, [key]: val },
            { preserveState: false, preserveScroll: true },
        );
    };

    const handleSave = () => {
        if (!can_write) return;
        setSaving(true);
        router.post(
            onCall.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows: rows.map((r, i) => ({
                    id: r.id,
                    employee_id: r.employee_id,
                    nama: r.nama,
                    kode_prk: r.kode_prk || null,
                    jabatan: r.jabatan || null,
                    schedule: r.schedule,
                    total: r.total,
                    nilai: r.nilai,
                    rupiah: r.rupiah,
                    sort_order: i,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDirty(false);
                    toast.success('Jadwal on call K3L berhasil disimpan.');
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
        setActiveCell(null);
        toast.info('Perubahan dibatalkan ke data terakhir yang tersimpan.');
    };

    const openAddPersonModal = () => {
        setEditingPersonKey(null);
        setPersonForm({
            employee_id: '',
            nama: '',
            kode_prk: '',
            jabatan: 'Petugas K3L',
            nilai: '100000',
        });
        setPersonModalOpen(true);
    };

    const openEditPersonModal = (row: Row) => {
        setEditingPersonKey(row._key);
        setPersonForm({
            employee_id: row.employee_id ? String(row.employee_id) : '',
            nama: row.nama,
            kode_prk: row.kode_prk || '',
            jabatan: row.jabatan || '',
            nilai: String(row.nilai || 100000),
        });
        setPersonModalOpen(true);
    };

    const handleEmployeeSelect = (empIdStr: string) => {
        if (!empIdStr) return;
        const emp = options.employees.find((e) => String(e.id) === empIdStr);
        if (emp) {
            setPersonForm({
                ...personForm,
                employee_id: String(emp.id),
                nama: emp.name,
                jabatan: emp.position || personForm.jabatan,
            });
        }
    };

    const handlePersonSubmit = () => {
        if (!personForm.nama.trim()) {
            toast.error('Nama personil wajib diisi.');
            return;
        }

        const nilaiNum = parseFloat(personForm.nilai) || 100000;
        const empId = personForm.employee_id ? parseInt(personForm.employee_id, 10) : null;

        if (editingPersonKey === null) {
            // New row
            const newRow: Row = {
                _key: nextKey,
                id: null,
                employee_id: empId,
                nama: personForm.nama.trim(),
                kode_prk: personForm.kode_prk.trim(),
                jabatan: personForm.jabatan.trim(),
                schedule: {},
                total: 0,
                nilai: nilaiNum,
                rupiah: 0,
                sort_order: rows.length,
            };
            setRows([...rows, newRow]);
            setNextKey((k) => k + 1);
        } else {
            // Update personil info
            setRows(
                rows.map((r) => {
                    if (r._key !== editingPersonKey) return r;
                    const nextTotal = r.total;
                    const nextRupiah = nextTotal * nilaiNum;
                    return {
                        ...r,
                        employee_id: empId,
                        nama: personForm.nama.trim(),
                        kode_prk: personForm.kode_prk.trim(),
                        jabatan: personForm.jabatan.trim(),
                        nilai: nilaiNum,
                        rupiah: nextRupiah,
                    };
                }),
            );
        }

        setDirty(true);
        setPersonModalOpen(false);
        toast.success(editingPersonKey === null ? 'Personil berhasil ditambahkan.' : 'Data personil berhasil diperbarui.');
    };

    const handleDeletePerson = (key: number) => {
        setRows(rows.filter((r) => r._key !== key));
        setDirty(true);
        if (activeCell?.rowKey === key) {
            setActiveCell(null);
        }
        toast.info('Personil dihapus dari tabel sementara.');
    };

    const handleCellClick = (rowKey: number, date: string, dayNum: number, personName: string) => {
        if (!can_write) return;
        const row = rows.find((r) => r._key === rowKey);
        const curVal = row?.schedule[date] || '';
        setActiveCell({
            rowKey,
            date,
            dayNum,
            personName,
            currentValue: curVal,
        });
    };

    const applyShiftCode = (code: string) => {
        if (!activeCell) return;
        const { rowKey, date } = activeCell;

        setRows((prev) =>
            prev.map((r) => {
                if (r._key !== rowKey) return r;
                const nextSchedule = { ...r.schedule };
                if (code) {
                    nextSchedule[date] = code;
                } else {
                    delete nextSchedule[date];
                }
                const newTotal = calculateTotal(nextSchedule);
                const newRupiah = newTotal * (r.nilai || 100000);
                return {
                    ...r,
                    schedule: nextSchedule,
                    total: newTotal,
                    rupiah: newRupiah,
                };
            }),
        );

        setDirty(true);
        setActiveCell(null);
    };

    const handlePrint = () => {
        window.print();
    };

    const pdfUrl = `${onCall.pdf().url}?unit_id=${filters.unit_id}&month=${filters.month}&year=${filters.year}`;

    return (
        <>
            <Head title={`Jadwal On Call K3L - ${unit.name} - Periode 16 ${prevMonthLabel} s/d 15 ${monthLabel} ${filters.year}`} />
            <style>{PRINT_CSS}</style>

            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6 print:hidden">
                <PageHeader
                    title="Jadwal On Call K3L PT Mitra Karya Prima"
                    description={`Jadwal piket & kesiapan personil K3L periode cut-off (16 ${prevMonthLabel} ${prevYear} s/d 15 ${monthLabel} ${filters.year}) - ${unit.name}.`}
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
                                onClick={() => router.get(jadwal.index().url)}
                            >
                                <ArrowLeft className="mr-1.5 size-4" />
                                Kembali ke Jadwal K3
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
                            label="Bulan Periode (Akhir Cut-off: tgl 15)"
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
                                    onClick={openAddPersonModal}
                                    className="gap-1.5"
                                >
                                    <Plus className="size-4" />
                                    Tambah Personil
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
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => window.open(pdfUrl, '_blank')}
                            className="gap-1.5 text-blue-600 hover:text-blue-700 dark:text-blue-400"
                        >
                            <Download className="size-4" />
                            Cetak PDF
                        </Button>
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

                {/* Quick Hint */}
                <div className="flex items-center gap-2 rounded-md bg-muted/60 px-3 py-2 text-xs text-muted-foreground">
                    <PhoneCall className="size-4 text-primary" />
                    <span>
                        Klik pada sel tanggal untuk memilih kode shift (<strong>DT, P, S, M, L, CT, SD, I, DL, dll</strong>). Total hari on-call dan rupiah otomatis terhitung.
                    </span>
                </div>

                {/* Official Spreadsheet Layout */}
                <div className="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
                    {/* Header Kop Resmi */}
                    <div className="grid grid-cols-[140px_1fr_140px] items-center border-b border-border bg-muted/30 p-4 text-center">
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
                            <h1 className="text-sm font-extrabold uppercase tracking-wide text-foreground md:text-base">
                                ONCALL K3L PT MITRA KARYA PRIMA
                            </h1>
                            <h2 className="text-xs font-semibold uppercase tracking-wider text-primary md:text-sm">
                                Periode 16 {prevMonthLabel} {prevYear} - 15 {monthLabel} {filters.year}
                            </h2>
                            <p className="text-xs font-bold uppercase tracking-wider text-muted-foreground">
                                {unit.name}
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
                                <tr className="border-b border-slate-300 bg-slate-100 text-center font-semibold text-slate-800 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-200">
                                    <th className="w-10 border-r border-slate-300 px-2 py-2 dark:border-slate-700">NO</th>
                                    <th className="min-w-[170px] border-r border-slate-300 px-3 py-2 text-left dark:border-slate-700">NAMA</th>
                                    <th className="min-w-[90px] border-r border-slate-300 px-2 py-2 dark:border-slate-700">KODE PRK</th>
                                    <th className="min-w-[130px] border-r border-slate-300 px-2 py-2 text-left dark:border-slate-700">JABATAN</th>

                                    {/* Date headers (16..30/31 then 1..15) */}
                                    {days.map((d) => {
                                        const isHoliday = d.is_holiday;
                                        const isWeekend = d.is_weekend;
                                        return (
                                            <th
                                                key={d.date}
                                                className={`min-w-[28px] max-w-[32px] border-r border-slate-300 px-0.5 py-1 text-center font-bold dark:border-slate-700 ${
                                                    isHoliday
                                                        ? 'bg-red-200 text-red-900 dark:bg-red-950 dark:text-red-200'
                                                        : isWeekend
                                                          ? 'bg-yellow-200 text-yellow-900 dark:bg-yellow-950 dark:text-yellow-200'
                                                          : ''
                                                }`}
                                                title={d.holiday ? `${d.date}: ${d.holiday}` : d.date}
                                            >
                                                <div className="text-[11px] leading-tight">{d.day}</div>
                                                <div className="text-[9px] font-normal text-muted-foreground">{d.dow}</div>
                                            </th>
                                        );
                                    })}

                                    <th className="w-16 border-r border-slate-300 bg-yellow-300 px-2 py-2 text-center font-bold text-yellow-950 dark:bg-yellow-600 dark:text-yellow-100">
                                        TOTAL
                                    </th>
                                    <th className="min-w-[95px] border-r border-slate-300 bg-orange-300 px-2 py-2 text-center font-bold text-orange-950 dark:bg-orange-600 dark:text-orange-100">
                                        NILAI
                                    </th>
                                    <th className="min-w-[105px] border-r border-slate-300 px-2 py-2 text-center font-bold">
                                        RUPIAH
                                    </th>
                                    {can_write && (
                                        <th className="w-20 px-2 py-2 text-center">
                                            AKSI
                                        </th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {rows.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={days.length + (can_write ? 8 : 7)}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            Belum ada data personil on call untuk periode ini. Klik <strong>Tambah Personil</strong> di atas untuk memulai.
                                        </td>
                                    </tr>
                                ) : (
                                    rows.map((row, index) => (
                                        <tr
                                            key={row._key}
                                            className="transition-colors hover:bg-muted/30"
                                        >
                                            <td className="border-r border-border px-2 py-2 text-center font-medium text-muted-foreground">
                                                {index + 1}
                                            </td>
                                            <td className="border-r border-border px-3 py-2 font-semibold text-foreground">
                                                {row.nama}
                                            </td>
                                            <td className="border-r border-border px-2 py-2 text-center font-mono text-[11px] text-muted-foreground">
                                                {row.kode_prk || '-'}
                                            </td>
                                            <td className="border-r border-border px-2 py-2 text-muted-foreground">
                                                {row.jabatan || '-'}
                                            </td>

                                            {/* Date cells */}
                                            {days.map((d) => {
                                                const val = row.schedule[d.date] || '';
                                                const isHoliday = d.is_holiday;
                                                const isWeekend = d.is_weekend;
                                                const isLibur = val.toUpperCase() === 'L';
                                                const isShift = Boolean(val && !isLibur);

                                                return (
                                                    <td
                                                        key={d.date}
                                                        onClick={() => handleCellClick(row._key, d.date, d.day, row.nama)}
                                                        className={`border-r border-border p-0 text-center font-mono text-xs font-bold transition-all ${
                                                            can_write ? 'cursor-pointer hover:ring-2 hover:ring-primary/50' : ''
                                                        } ${
                                                            isHoliday
                                                                ? 'bg-red-50 dark:bg-red-950/30'
                                                                : isWeekend
                                                                  ? 'bg-yellow-50/50 dark:bg-yellow-950/20'
                                                                  : ''
                                                        }`}
                                                    >
                                                        <div
                                                            className={`flex size-full min-h-[30px] items-center justify-center ${
                                                                isLibur
                                                                    ? 'text-rose-600 font-extrabold'
                                                                    : isShift
                                                                      ? 'text-blue-700 dark:text-blue-300'
                                                                      : 'text-muted-foreground/30'
                                                            }`}
                                                        >
                                                            {val || ''}
                                                        </div>
                                                    </td>
                                                );
                                            })}

                                            {/* Total */}
                                            <td className="border-r border-border bg-yellow-100/70 px-2 py-2 text-center font-mono font-bold text-yellow-900 dark:bg-yellow-950/40 dark:text-yellow-200">
                                                {row.total}
                                            </td>

                                            {/* Nilai */}
                                            <td className="border-r border-border px-2 py-2 text-right font-mono text-xs">
                                                {row.nilai ? row.nilai.toLocaleString('id-ID') : '0'}
                                            </td>

                                            {/* Rupiah */}
                                            <td className="border-r border-border bg-muted/20 px-2 py-2 text-right font-mono font-bold text-foreground">
                                                {row.rupiah > 0 ? row.rupiah.toLocaleString('id-ID') : '-'}
                                            </td>

                                            {/* Aksi */}
                                            {can_write && (
                                                <td className="px-2 py-2 text-center">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-7 text-muted-foreground hover:text-foreground"
                                                            onClick={() => openEditPersonModal(row)}
                                                            title="Ubah data personil"
                                                        >
                                                            <Pencil className="size-3.5" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-7 text-destructive hover:bg-destructive/10"
                                                            onClick={() => handleDeletePerson(row._key)}
                                                            title="Hapus personil"
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
                                    <tr className="border-t-2 border-border bg-muted/60 font-bold">
                                        <td
                                            colSpan={days.length + 4}
                                            className="border-r border-border px-4 py-2.5 text-right uppercase tracking-wider text-muted-foreground"
                                        >
                                            TOTAL KESELURUHAN:
                                        </td>
                                        <td className="border-r border-border bg-yellow-200 px-2 py-2.5 text-center font-mono font-bold text-yellow-950 dark:bg-yellow-900 dark:text-yellow-100">
                                            {totalOnCallAll}
                                        </td>
                                        <td className="border-r border-border px-2 py-2.5 text-right font-mono text-xs">
                                            {totalNilaiAll.toLocaleString('id-ID')}
                                        </td>
                                        <td className="border-r border-border bg-primary/10 px-2 py-2.5 text-right font-mono text-xs font-bold text-primary">
                                            {totalRupiahAll > 0 ? totalRupiahAll.toLocaleString('id-ID') : '-'}
                                        </td>
                                        {can_write && <td></td>}
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </div>
                </div>

                {/* Keterangan & Legenda Kode Shift */}
                <div className="rounded-lg border border-border bg-card p-4 text-xs space-y-3">
                    <div className="font-bold uppercase tracking-wider text-foreground">
                        Keterangan &amp; Kode Shift:
                    </div>
                    <div className="grid gap-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                        <div className="flex items-center gap-2">
                            <span className="inline-block rounded bg-blue-100 px-2 py-0.5 font-mono font-bold text-blue-800 dark:bg-blue-900/60 dark:text-blue-200">DT</span>
                            <span>DAY TIME (08:00 - 16:00)</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="inline-block rounded bg-emerald-100 px-2 py-0.5 font-mono font-bold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">P</span>
                            <span>SHIFT PAGI (07:30 - 15:30)</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="inline-block rounded bg-amber-100 px-2 py-0.5 font-mono font-bold text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">S</span>
                            <span>SHIFT SORE (15:00 - 22:30)</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="inline-block rounded bg-purple-100 px-2 py-0.5 font-mono font-bold text-purple-800 dark:bg-purple-900/60 dark:text-purple-200">M</span>
                            <span>SHIFT MALAM (22:30 - 07:30)</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="inline-block rounded bg-rose-100 px-2 py-0.5 font-mono font-bold text-rose-800 dark:bg-rose-900/60 dark:text-rose-200">L</span>
                            <span>LIBUR KERJA</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="inline-block rounded bg-sky-100 px-2 py-0.5 font-mono font-bold text-sky-800 dark:bg-sky-900/60 dark:text-sky-200">1</span>
                            <span>ON CALL AKTIF</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="inline-block rounded bg-orange-100 px-2 py-0.5 font-mono font-bold text-orange-800 dark:bg-orange-900/60 dark:text-orange-200">CT</span>
                            <span>CUTI</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="inline-block rounded bg-red-100 px-2 py-0.5 font-mono font-bold text-red-800 dark:bg-red-900/60 dark:text-red-200">SD</span>
                            <span>SURAT DOKTER / SAKIT</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="inline-block rounded bg-indigo-100 px-2 py-0.5 font-mono font-bold text-indigo-800 dark:bg-indigo-900/60 dark:text-indigo-200">I</span>
                            <span>IZIN</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="inline-block rounded bg-zinc-200 px-2 py-0.5 font-mono font-bold text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200">A</span>
                            <span>ALFA / MANGKIR</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="inline-block rounded bg-teal-100 px-2 py-0.5 font-mono font-bold text-teal-800 dark:bg-teal-900/60 dark:text-teal-200">D</span>
                            <span>DISPENSASI</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="inline-block rounded bg-cyan-100 px-2 py-0.5 font-mono font-bold text-cyan-800 dark:bg-cyan-900/60 dark:text-cyan-200">DL</span>
                            <span>DINAS LUAR</span>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-4 pt-2 border-t border-border text-[11px] text-muted-foreground">
                        <div className="flex items-center gap-1.5">
                            <span className="size-3.5 rounded bg-red-200 border border-red-300 dark:bg-red-950"></span>
                            <span>Hari Libur Nasional</span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <span className="size-3.5 rounded bg-yellow-200 border border-yellow-300 dark:bg-yellow-950"></span>
                            <span>Akhir Pekan (Sabtu / Minggu)</span>
                        </div>
                        <div>
                            • Periode cut-off resmi: <strong>16 {prevMonthLabel} {prevYear} s/d 15 {monthLabel} {filters.year}</strong>
                        </div>
                    </div>
                </div>
            </div>

            {/* Shift Picker Dialog Modal */}
            <Dialog open={activeCell !== null} onOpenChange={(open) => !open && setActiveCell(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <PhoneCall className="size-5 text-primary" />
                            Pilih Status / Kode Shift
                        </DialogTitle>
                        <DialogDescription>
                            {activeCell && (
                                <span>
                                    Personil: <strong>{activeCell.personName}</strong> · Tanggal: <strong>{activeCell.date}</strong> (Tgl {activeCell.dayNum})
                                </span>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid grid-cols-2 gap-2 py-2">
                        {SHIFT_OPTIONS.map((opt) => (
                            <button
                                key={opt.code}
                                type="button"
                                onClick={() => applyShiftCode(opt.code)}
                                className={`flex items-center justify-between rounded-lg border border-border p-2.5 text-left text-xs transition-all hover:ring-2 hover:ring-primary ${
                                    activeCell?.currentValue === opt.code ? 'ring-2 ring-primary' : ''
                                } ${opt.bg}`}
                            >
                                <span className="font-extrabold text-sm">{opt.code}</span>
                                <span className="text-[10px] font-medium leading-tight opacity-90">{opt.label}</span>
                            </button>
                        ))}
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            onClick={() => applyShiftCode('')}
                            className="gap-1.5"
                        >
                            <X className="size-4" />
                            Kosongkan Sel
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setActiveCell(null)}
                        >
                            Tutup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Add / Edit Personil Modal */}
            <Dialog open={personModalOpen} onOpenChange={setPersonModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <PhoneCall className="size-5 text-primary" />
                            {editingPersonKey === null ? 'Tambah Personil On Call' : 'Ubah Personil On Call'}
                        </DialogTitle>
                        <DialogDescription>
                            Pilih atau masukkan data personil K3L untuk jadwal on call.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-3 py-2">
                        {/* Pilih dari daftar pegawai unit jika ada */}
                        {options.employees.length > 0 && editingPersonKey === null && (
                            <div className="space-y-1.5">
                                <Label>Pilih Pegawai Unit</Label>
                                <select
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-xs"
                                    value={personForm.employee_id}
                                    onChange={(e) => handleEmployeeSelect(e.target.value)}
                                >
                                    <option value="">-- Pilih dari data pegawai unit --</option>
                                    {options.employees.map((emp) => (
                                        <option key={emp.id} value={emp.id}>
                                            {emp.name} {emp.position ? `(${emp.position})` : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        )}

                        <div className="space-y-1.5">
                            <Label htmlFor="nama">Nama Personil</Label>
                            <Input
                                id="nama"
                                value={personForm.nama}
                                onChange={(e) => setPersonForm({ ...personForm, nama: e.target.value })}
                                placeholder="Nama lengkap personil"
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="kode_prk">Kode PRK</Label>
                            <Input
                                id="kode_prk"
                                value={personForm.kode_prk}
                                onChange={(e) => setPersonForm({ ...personForm, kode_prk: e.target.value })}
                                placeholder="Contoh: PRK-01"
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="jabatan">Jabatan</Label>
                            <Input
                                id="jabatan"
                                value={personForm.jabatan}
                                onChange={(e) => setPersonForm({ ...personForm, jabatan: e.target.value })}
                                placeholder="Contoh: Petugas K3L / Keamanan"
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label htmlFor="nilai">Nilai Tarif On Call (Rp)</Label>
                            <Input
                                id="nilai"
                                type="number"
                                value={personForm.nilai}
                                onChange={(e) => setPersonForm({ ...personForm, nilai: e.target.value })}
                                placeholder="100000"
                            />
                        </div>
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setPersonModalOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button type="button" onClick={handlePersonSubmit}>
                            {editingPersonKey === null ? 'Tambahkan Personil' : 'Simpan Perubahan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Print View Container */}
            <div className="print-container hidden print:block">
                <table style={{ width: '100%', borderCollapse: 'collapse', border: '1.5px solid #000', marginBottom: '6px' }}>
                    <tbody>
                        <tr>
                            <td style={{ width: '120px', textAlign: 'center', padding: '3px', border: '1px solid #000' }}>
                                <img src="/logo/sidebar-logo.png" alt="PLN" style={{ maxHeight: '38px', maxWidth: '110px' }} />
                            </td>
                            <td style={{ textAlign: 'center', padding: '3px 6px', border: '1px solid #000' }}>
                                <div style={{ fontSize: '9.5px', fontWeight: 'bold', textTransform: 'uppercase' }}>
                                    ONCALL K3L PT MITRA KARYA PRIMA
                                </div>
                                <div style={{ fontSize: '8.5px', fontWeight: 'bold', textTransform: 'uppercase', marginTop: '1.5px' }}>
                                    PERIODE 16 {prevMonthLabel} {prevYear} - 15 {monthLabel} {filters.year}
                                </div>
                                <div style={{ fontSize: '8.5px', fontWeight: 'bold', textTransform: 'uppercase', marginTop: '1.5px' }}>
                                    {unit.name.toUpperCase()}
                                </div>
                            </td>
                            <td style={{ width: '120px', textAlign: 'center', padding: '3px', border: '1px solid #000' }}>
                                <img src="/logo/mkp.jpg" alt="MKP" style={{ maxHeight: '38px', maxWidth: '110px' }} />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table className="print-table">
                    <thead className="print-thead">
                        <tr>
                            <th style={{ width: '16px' }}>NO</th>
                            <th style={{ width: '110px', textAlign: 'left', paddingLeft: '4px' }}>NAMA</th>
                            <th style={{ width: '45px' }}>KODE PRK</th>
                            <th style={{ width: '75px', textAlign: 'left', paddingLeft: '4px' }}>JABATAN</th>
                            {days.map((d) => (
                                <th
                                    key={d.date}
                                    className={d.is_holiday ? 'print-holiday' : d.is_weekend ? 'print-weekend' : ''}
                                    style={{ width: '13px' }}
                                >
                                    {d.day}
                                </th>
                            ))}
                            <th className="print-total" style={{ width: '25px' }}>TOTAL</th>
                            <th style={{ width: '45px', textAlign: 'right', paddingRight: '3px' }}>NILAI</th>
                            <th style={{ width: '50px', textAlign: 'right', paddingRight: '3px' }}>RUPIAH</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r, i) => (
                            <tr key={r._key}>
                                <td>{i + 1}</td>
                                <td style={{ textAlign: 'left', paddingLeft: '4px', fontWeight: 600 }}>{r.nama}</td>
                                <td>{r.kode_prk}</td>
                                <td style={{ textAlign: 'left', paddingLeft: '4px' }}>{r.jabatan}</td>
                                {days.map((d) => {
                                    const val = r.schedule[d.date] || '';
                                    return (
                                        <td
                                            key={d.date}
                                            className={d.is_holiday ? 'print-holiday' : d.is_weekend ? 'print-weekend' : ''}
                                            style={{
                                                fontWeight: val.toUpperCase() === 'L' ? 'bold' : 'normal',
                                                color: val.toUpperCase() === 'L' ? '#dc2626' : '#000',
                                            }}
                                        >
                                            {val}
                                        </td>
                                    );
                                })}
                                <td className="print-total">{r.total}</td>
                                <td style={{ textAlign: 'right', paddingRight: '3px' }}>
                                    {r.nilai ? r.nilai.toLocaleString('id-ID') : '0'}
                                </td>
                                <td style={{ textAlign: 'right', paddingRight: '3px', fontWeight: 'bold' }}>
                                    {r.rupiah > 0 ? r.rupiah.toLocaleString('id-ID') : '-'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                    {rows.length > 0 && (
                        <tfoot>
                            <tr style={{ fontWeight: 'bold' }}>
                                <td colSpan={days.length + 4} style={{ textAlign: 'right', paddingRight: '6px' }}>
                                    TOTAL
                                </td>
                                <td className="print-total">{totalOnCallAll}</td>
                                <td style={{ textAlign: 'right', paddingRight: '3px' }}>{totalNilaiAll.toLocaleString('id-ID')}</td>
                                <td style={{ textAlign: 'right', paddingRight: '3px' }}>{totalRupiahAll > 0 ? totalRupiahAll.toLocaleString('id-ID') : '-'}</td>
                            </tr>
                        </tfoot>
                    )}
                </table>
            </div>
        </>
    );
}

JadwalOnCallPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Jadwal K3 & Lingkungan', href: jadwal.index() },
        { title: 'Jadwal On Call K3L', href: onCall.index() },
    ],
};
