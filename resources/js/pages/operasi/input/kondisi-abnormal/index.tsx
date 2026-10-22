import { Head, router } from '@inertiajs/react';
import {
    AlertOctagon,
    AlertTriangle,
    ChevronDown,
    Clock,
    Download,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { MobileRowEditor } from '@/components/mobile/row-editor';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useCompactLayout } from '@/hooks/use-mobile-module';
import { dashboard } from '@/routes';
import operasiInput from '@/routes/operasi/input';
import kondisiAbnormalRoutes from '@/routes/operasi/input/kondisi-abnormal';
import type { IdName } from '@/types';

type AbnormalRow = {
    id?: number;
    no_urut: number;
    uraian_kondisi: string;
    tanggal: string;
    is_abnormal: number;
    durasi_abnormal: number;
    is_gangguan: number;
    durasi_gangguan: number;
    parameter_terkait: string;
    /** Tindak lanjut jangka pendek: 1 = ticked. */
    tl_pendek_sr: number;
    tl_pendek_flm: number;
    tl_pendek_lainnya: number;
    tl_pendek_keterangan: string;
    tl_panjang: string;
    dampak_sistem: string;
    dampak_pembangkit: string;
};

type TindakKey = 'tl_pendek_sr' | 'tl_pendek_flm' | 'tl_pendek_lainnya';

/** The detail fields of a finding (follow-up & impact), shown under its row. */
const DETAIL_TEXTS: {
    key:
        | 'parameter_terkait'
        | 'tl_pendek_keterangan'
        | 'tl_panjang'
        | 'dampak_sistem'
        | 'dampak_pembangkit';
    label: string;
    placeholder: string;
}[] = [
    {
        key: 'parameter_terkait',
        label: 'Parameter terkait',
        placeholder: 'Mis. temperatur exhaust 520 °C, tekanan LO 2,1 bar…',
    },
    {
        key: 'tl_pendek_keterangan',
        label: 'Keterangan jangka pendek',
        placeholder: 'No. SR, tindakan FLM, atau tindakan lainnya…',
    },
    {
        key: 'tl_panjang',
        label: 'Tindak lanjut jangka panjang',
        placeholder: 'Rencana perbaikan permanen, penggantian part, overhaul…',
    },
    {
        key: 'dampak_sistem',
        label: 'Dampak terhadap sistem',
        placeholder: 'Mis. defisit daya, pemadaman bergilir, beban dialihkan…',
    },
    {
        key: 'dampak_pembangkit',
        label: 'Dampak terhadap pembangkit',
        placeholder: 'Mis. derating, mesin standby, unit trip…',
    },
];

const blankRow = (no: number): AbnormalRow => ({
    no_urut: no,
    uraian_kondisi: '',
    tanggal: '',
    is_abnormal: 0,
    durasi_abnormal: 0,
    is_gangguan: 0,
    durasi_gangguan: 0,
    parameter_terkait: '',
    tl_pendek_sr: 0,
    tl_pendek_flm: 0,
    tl_pendek_lainnya: 0,
    tl_pendek_keterangan: '',
    tl_panjang: '',
    dampak_sistem: '',
    dampak_pembangkit: '',
});

/** Whether a row has any follow-up or impact filled in. */
const hasDetail = (row: AbnormalRow) =>
    row.tl_pendek_sr === 1 ||
    row.tl_pendek_flm === 1 ||
    row.tl_pendek_lainnya === 1 ||
    DETAIL_TEXTS.some(({ key }) => row[key].trim() !== '');

type Props = {
    filters: { unit_id: number; month: number; year: number };
    rows: AbnormalRow[];
    summary: {
        total_abnormal_count: number;
        total_abnormal_durasi: number;
        total_gangguan_count: number;
        total_gangguan_durasi: number;
    };
    options: {
        units: IdName[];
        years: number[];
        /** Tindak lanjut jangka pendek column => label (SR, FLM, Lainnya). */
        tindak_pendek: Record<TindakKey, string>;
    };
    can_write: boolean;
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

export default function KondisiAbnormalInput({
    filters,
    rows: initialRows,
    options,
    can_write,
}: Props) {
    const [rows, setRows] = useState<AbnormalRow[]>(initialRows);
    const [saving, setSaving] = useState(false);
    const [isDirty, setIsDirty] = useState(false);
    const [openDetail, setOpenDetail] = useState<number | null>(null);
    const compact = useCompactLayout();
    const tindak = Object.entries(options.tindak_pendek) as [
        TindakKey,
        string,
    ][];

    // New rows from the server (another period, or after a save): start over from them.
    const [syncedRows, setSyncedRows] = useState(initialRows);

    if (syncedRows !== initialRows) {
        setSyncedRows(initialRows);
        setRows(initialRows);
        setIsDirty(false);
    }

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            kondisiAbnormalRoutes.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleRowChange = <K extends keyof AbnormalRow>(
        index: number,
        field: K,
        value: AbnormalRow[K],
    ) => {
        const updated = [...rows];
        updated[index] = {
            ...updated[index],
            [field]: value,
        };
        setRows(updated);
        setIsDirty(true);
    };

    const handleAddRow = () => {
        setRows([...rows, blankRow(rows.length + 1)]);
        setIsDirty(true);
    };

    const handleClearRow = (index: number) => {
        const updated = [...rows];
        updated[index] = {
            ...blankRow(updated[index].no_urut),
            id: updated[index].id,
        };
        setRows(updated);
        setIsDirty(true);
    };

    const handleSave = () => {
        setSaving(true);
        router.post(
            kondisiAbnormalRoutes.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                rows,
            },
            {
                preserveScroll: true,
                onSuccess: () => setIsDirty(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    const handlePdf = () => {
        const url = kondisiAbnormalRoutes.pdf({
            query: {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
            },
        }).url;
        window.open(url, '_blank');
    };

    const totalAbnormal = rows.filter((r) => r.is_abnormal === 1).length;
    const totalAbnormalDurasi = rows.reduce(
        (acc, r) => acc + (parseFloat(String(r.durasi_abnormal)) || 0),
        0,
    );
    const totalGangguan = rows.filter((r) => r.is_gangguan === 1).length;
    const totalGangguanDurasi = rows.reduce(
        (acc, r) => acc + (parseFloat(String(r.durasi_gangguan)) || 0),
        0,
    );

    return (
        <>
            <Head title="Input Kondisi Abnormal & Gangguan Pembangkit" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Laporan Kondisi Abnormal dan Gangguan Pembangkit"
                    description="Pencatatan dan pemantauan kejadian kondisi abnormal serta gangguan mesin pembangkit beserta durasi kejadian."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" onClick={handlePdf}>
                                <Download className="size-4" />
                                Cetak PDF
                            </Button>
                            {can_write && (
                                <>
                                    <Button
                                        variant="outline"
                                        onClick={handleAddRow}
                                        className="gap-1.5"
                                    >
                                        <Plus className="size-4" />
                                        Tambah Baris
                                    </Button>
                                    <Button
                                        onClick={handleSave}
                                        disabled={saving || !isDirty}
                                        className="gap-2"
                                    >
                                        <Save className="size-4" />
                                        {saving
                                            ? 'Menyimpan...'
                                            : 'Simpan Data'}
                                    </Button>
                                </>
                            )}
                        </div>
                    }
                />

                {/* Filters */}
                <div className="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-card p-3 shadow-xs">
                    <OperasiSelect
                        label="Unit"
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
                        options={MONTHS.map((label, index) => ({
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
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">
                                Kejadian Abnormal
                            </span>
                            <AlertTriangle className="size-4 text-amber-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
                            {totalAbnormal}{' '}
                            <span className="text-xs font-normal text-muted-foreground">
                                kali
                            </span>
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Kondisi abnormal mesin
                        </p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">
                                Durasi Abnormal
                            </span>
                            <Clock className="size-4 text-amber-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
                            {totalAbnormalDurasi.toFixed(1)}{' '}
                            <span className="text-xs font-normal text-muted-foreground">
                                Jam
                            </span>
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Total waktu abnormal
                        </p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">
                                Kejadian Gangguan
                            </span>
                            <AlertOctagon className="size-4 text-rose-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">
                            {totalGangguan}{' '}
                            <span className="text-xs font-normal text-muted-foreground">
                                kali
                            </span>
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Trip / gangguan operasi
                        </p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">
                                Durasi Gangguan
                            </span>
                            <Clock className="size-4 text-rose-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">
                            {totalGangguanDurasi.toFixed(1)}{' '}
                            <span className="text-xs font-normal text-muted-foreground">
                                Jam
                            </span>
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                            Total waktu gangguan
                        </p>
                    </div>
                </div>

                {/* Table matching Excel template */}
                <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                    <div className="flex items-center justify-between border-b border-border bg-[#ea7315]/10 px-4 py-2.5">
                        <div className="flex items-center gap-2">
                            <AlertOctagon className="size-4 text-[#ea7315]" />
                            <span className="text-xs font-bold tracking-wider text-foreground uppercase">
                                Laporan Kondisi Abnormal dan Gangguan Pembangkit
                            </span>
                        </div>
                        {isDirty && (
                            <span className="animate-pulse text-xs font-medium text-amber-600 dark:text-amber-400">
                                ● Terdapat perubahan belum disimpan
                            </span>
                        )}
                    </div>

                    {compact ? (
                        <div className="bg-muted/30 p-2">
                            <MobileRowEditor
                                rows={rows}
                                canWrite={can_write}
                                rowKey={(row) => row.no_urut}
                                title={(row) =>
                                    row.uraian_kondisi || 'Kondisi baru'
                                }
                                subtitle={(row) =>
                                    [
                                        row.tanggal || 'Tanggal belum diisi',
                                        row.is_abnormal
                                            ? `Abnormal ${row.durasi_abnormal} jam`
                                            : '',
                                        row.is_gangguan
                                            ? `Gangguan ${row.durasi_gangguan} jam`
                                            : '',
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')
                                }
                                onChange={(index, key, value) =>
                                    handleRowChange(index, key, value)
                                }
                                onRemove={handleClearRow}
                                removeLabel="Kosongkan baris"
                                fields={[
                                    {
                                        key: 'uraian_kondisi',
                                        label: 'Indikasi / uraian kondisi',
                                        type: 'textarea',
                                        placeholder:
                                            'Indikasi kondisi abnormal / gangguan…',
                                    },
                                    {
                                        key: 'tanggal',
                                        label: 'Tanggal',
                                        type: 'date',
                                    },
                                    {
                                        key: 'is_abnormal',
                                        label: 'Abnormal',
                                        type: 'check',
                                        group: 'Status',
                                    },
                                    {
                                        key: 'durasi_abnormal',
                                        label: 'Durasi abnormal (jam)',
                                        type: 'number',
                                        group: 'Status',
                                        hidden: (row) => row.is_abnormal !== 1,
                                    },
                                    {
                                        key: 'is_gangguan',
                                        label: 'Gangguan',
                                        type: 'check',
                                        group: 'Status',
                                    },
                                    {
                                        key: 'durasi_gangguan',
                                        label: 'Durasi gangguan (jam)',
                                        type: 'number',
                                        group: 'Status',
                                        hidden: (row) => row.is_gangguan !== 1,
                                    },
                                    {
                                        key: 'parameter_terkait',
                                        label: 'Parameter terkait',
                                        type: 'textarea',
                                        placeholder:
                                            DETAIL_TEXTS[0].placeholder,
                                    },
                                    ...tindak.map(([key, label]) => ({
                                        key,
                                        label,
                                        type: 'check' as const,
                                        group: 'Tindak lanjut jangka pendek',
                                    })),
                                    {
                                        key: 'tl_pendek_keterangan',
                                        label: 'Keterangan jangka pendek',
                                        type: 'textarea',
                                        group: 'Tindak lanjut jangka pendek',
                                        placeholder:
                                            DETAIL_TEXTS[1].placeholder,
                                    },
                                    {
                                        key: 'tl_panjang',
                                        label: 'Tindak lanjut jangka panjang',
                                        type: 'textarea',
                                        group: 'Tindak lanjut jangka panjang',
                                        placeholder:
                                            DETAIL_TEXTS[2].placeholder,
                                    },
                                    {
                                        key: 'dampak_sistem',
                                        label: 'Dampak terhadap sistem',
                                        type: 'textarea',
                                        group: 'Dampak',
                                        placeholder:
                                            DETAIL_TEXTS[3].placeholder,
                                    },
                                    {
                                        key: 'dampak_pembangkit',
                                        label: 'Dampak terhadap pembangkit',
                                        type: 'textarea',
                                        group: 'Dampak',
                                        placeholder:
                                            DETAIL_TEXTS[4].placeholder,
                                    },
                                ]}
                            />
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-[#ea7315]/15 hover:bg-[#ea7315]/15">
                                        <TableHead
                                            rowSpan={2}
                                            className="w-14 border-r border-border text-center font-bold text-foreground"
                                        >
                                            NO
                                        </TableHead>
                                        <TableHead
                                            rowSpan={2}
                                            className="min-w-[280px] border-r border-border font-bold text-foreground"
                                        >
                                            INDIKASI / URAIAN KONDISI
                                        </TableHead>
                                        <TableHead
                                            rowSpan={2}
                                            className="w-36 border-r border-border text-center font-bold text-foreground"
                                        >
                                            TANGGAL
                                        </TableHead>
                                        <TableHead
                                            colSpan={4}
                                            className="border-b border-border text-center font-bold text-foreground"
                                        >
                                            STATUS
                                        </TableHead>
                                        <TableHead
                                            rowSpan={2}
                                            className="min-w-[220px] border-l border-border text-center font-bold text-foreground"
                                        >
                                            PARAMETER, TINDAK LANJUT &amp;
                                            DAMPAK
                                        </TableHead>
                                        {can_write && (
                                            <TableHead
                                                rowSpan={2}
                                                className="w-12 border-l border-border text-center font-bold text-foreground"
                                            >
                                                AKSI
                                            </TableHead>
                                        )}
                                    </TableRow>
                                    <TableRow className="bg-[#ea7315]/15 hover:bg-[#ea7315]/15">
                                        <TableHead className="w-24 border-r border-border text-center font-bold text-foreground">
                                            ABNORMAL
                                        </TableHead>
                                        <TableHead className="w-28 border-r border-border text-center font-bold text-foreground">
                                            DURASI (JAM)
                                        </TableHead>
                                        <TableHead className="w-24 border-r border-border text-center font-bold text-foreground">
                                            GANGGUAN
                                        </TableHead>
                                        <TableHead className="w-28 text-center font-bold text-foreground">
                                            DURASI (JAM)
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((row, index) => (
                                        <Fragment key={row.no_urut}>
                                            <TableRow className="hover:bg-muted/40">
                                                <TableCell className="border-r border-border text-center font-bold text-muted-foreground">
                                                    {row.no_urut}
                                                </TableCell>
                                                <TableCell className="border-r border-border p-2">
                                                    {can_write ? (
                                                        <Input
                                                            type="text"
                                                            value={
                                                                row.uraian_kondisi
                                                            }
                                                            onChange={(e) =>
                                                                handleRowChange(
                                                                    index,
                                                                    'uraian_kondisi',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="h-8 text-xs"
                                                            placeholder="Uraian kondisi abnormal / gangguan..."
                                                        />
                                                    ) : (
                                                        <span className="text-xs">
                                                            {row.uraian_kondisi ||
                                                                '-'}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="border-r border-border p-2 text-center">
                                                    {can_write ? (
                                                        <Input
                                                            type="date"
                                                            value={row.tanggal}
                                                            onChange={(e) =>
                                                                handleRowChange(
                                                                    index,
                                                                    'tanggal',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="h-8 text-center text-xs"
                                                        />
                                                    ) : (
                                                        <span className="text-xs">
                                                            {row.tanggal || '-'}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="border-r border-border p-2 text-center">
                                                    {can_write ? (
                                                        <div className="flex items-center justify-center">
                                                            <Checkbox
                                                                checked={
                                                                    row.is_abnormal ===
                                                                    1
                                                                }
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    handleRowChange(
                                                                        index,
                                                                        'is_abnormal',
                                                                        checked
                                                                            ? 1
                                                                            : 0,
                                                                    )
                                                                }
                                                                aria-label="Abnormal"
                                                            />
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs font-bold">
                                                            {row.is_abnormal ===
                                                            1
                                                                ? '1'
                                                                : ''}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="border-r border-border p-2 text-center">
                                                    {can_write ? (
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            step="0.1"
                                                            value={
                                                                row.durasi_abnormal ||
                                                                ''
                                                            }
                                                            onChange={(e) =>
                                                                handleRowChange(
                                                                    index,
                                                                    'durasi_abnormal',
                                                                    parseFloat(
                                                                        e.target
                                                                            .value,
                                                                    ) || 0,
                                                                )
                                                            }
                                                            className="h-8 text-center font-mono text-xs"
                                                            placeholder="0"
                                                        />
                                                    ) : (
                                                        <span className="font-mono text-xs">
                                                            {row.durasi_abnormal >
                                                            0
                                                                ? row.durasi_abnormal
                                                                : ''}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="border-r border-border p-2 text-center">
                                                    {can_write ? (
                                                        <div className="flex items-center justify-center">
                                                            <Checkbox
                                                                checked={
                                                                    row.is_gangguan ===
                                                                    1
                                                                }
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    handleRowChange(
                                                                        index,
                                                                        'is_gangguan',
                                                                        checked
                                                                            ? 1
                                                                            : 0,
                                                                    )
                                                                }
                                                                aria-label="Gangguan"
                                                            />
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs font-bold">
                                                            {row.is_gangguan ===
                                                            1
                                                                ? '1'
                                                                : ''}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="p-2 text-center">
                                                    {can_write ? (
                                                        <Input
                                                            type="number"
                                                            min="0"
                                                            step="0.1"
                                                            value={
                                                                row.durasi_gangguan ||
                                                                ''
                                                            }
                                                            onChange={(e) =>
                                                                handleRowChange(
                                                                    index,
                                                                    'durasi_gangguan',
                                                                    parseFloat(
                                                                        e.target
                                                                            .value,
                                                                    ) || 0,
                                                                )
                                                            }
                                                            className="h-8 text-center font-mono text-xs"
                                                            placeholder="0"
                                                        />
                                                    ) : (
                                                        <span className="font-mono text-xs">
                                                            {row.durasi_gangguan >
                                                            0
                                                                ? row.durasi_gangguan
                                                                : ''}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="border-l border-border p-1">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setOpenDetail(
                                                                openDetail ===
                                                                    index
                                                                    ? null
                                                                    : index,
                                                            )
                                                        }
                                                        aria-expanded={
                                                            openDetail === index
                                                        }
                                                        className="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1 text-left text-xs hover:bg-muted"
                                                    >
                                                        <span className="flex min-w-0 flex-wrap gap-1">
                                                            {tindak
                                                                .filter(
                                                                    ([key]) =>
                                                                        row[
                                                                            key
                                                                        ] === 1,
                                                                )
                                                                .map(
                                                                    ([
                                                                        key,
                                                                        label,
                                                                    ]) => (
                                                                        <span
                                                                            key={
                                                                                key
                                                                            }
                                                                            className="rounded bg-primary/10 px-1.5 py-0.5 text-[10.5px] font-semibold text-primary"
                                                                        >
                                                                            {
                                                                                label
                                                                            }
                                                                        </span>
                                                                    ),
                                                                )}
                                                            {hasDetail(row) ? (
                                                                <span className="truncate text-muted-foreground">
                                                                    {row.parameter_terkait ||
                                                                        row.tl_panjang ||
                                                                        row.dampak_sistem ||
                                                                        row.dampak_pembangkit ||
                                                                        row.tl_pendek_keterangan}
                                                                </span>
                                                            ) : (
                                                                <span className="text-muted-foreground">
                                                                    {can_write
                                                                        ? 'Isi detail'
                                                                        : '-'}
                                                                </span>
                                                            )}
                                                        </span>
                                                        <ChevronDown
                                                            className={`size-3.5 shrink-0 text-muted-foreground transition ${openDetail === index ? 'rotate-180' : ''}`}
                                                        />
                                                    </button>
                                                </TableCell>
                                                {can_write && (
                                                    <TableCell className="border-l border-border p-1 text-center">
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-7 text-muted-foreground hover:text-destructive"
                                                            onClick={() =>
                                                                handleClearRow(
                                                                    index,
                                                                )
                                                            }
                                                            title="Kosongkan Baris"
                                                        >
                                                            <Trash2 className="size-3.5" />
                                                        </Button>
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                            {openDetail === index && (
                                                <TableRow className="bg-muted/20 hover:bg-muted/20">
                                                    <TableCell
                                                        colSpan={
                                                            can_write ? 9 : 8
                                                        }
                                                        className="p-3"
                                                    >
                                                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                                            <div className="flex flex-col gap-1.5 text-xs">
                                                                <span className="font-semibold text-foreground">
                                                                    Tindak
                                                                    lanjut
                                                                    jangka
                                                                    pendek
                                                                </span>
                                                                <div className="flex flex-wrap gap-3">
                                                                    {tindak.map(
                                                                        ([
                                                                            key,
                                                                            label,
                                                                        ]) => (
                                                                            <label
                                                                                key={
                                                                                    key
                                                                                }
                                                                                className="flex items-center gap-1.5"
                                                                            >
                                                                                <Checkbox
                                                                                    checked={
                                                                                        row[
                                                                                            key
                                                                                        ] ===
                                                                                        1
                                                                                    }
                                                                                    onCheckedChange={(
                                                                                        checked,
                                                                                    ) =>
                                                                                        handleRowChange(
                                                                                            index,
                                                                                            key,
                                                                                            checked
                                                                                                ? 1
                                                                                                : 0,
                                                                                        )
                                                                                    }
                                                                                    disabled={
                                                                                        !can_write
                                                                                    }
                                                                                />
                                                                                {
                                                                                    label
                                                                                }
                                                                            </label>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            </div>
                                                            {DETAIL_TEXTS.map(
                                                                ({
                                                                    key,
                                                                    label,
                                                                    placeholder,
                                                                }) => (
                                                                    <label
                                                                        key={
                                                                            key
                                                                        }
                                                                        className="flex flex-col gap-1 text-xs"
                                                                    >
                                                                        <span className="font-semibold text-foreground">
                                                                            {
                                                                                label
                                                                            }
                                                                        </span>
                                                                        <Textarea
                                                                            value={
                                                                                row[
                                                                                    key
                                                                                ]
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                handleRowChange(
                                                                                    index,
                                                                                    key,
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                            rows={
                                                                                2
                                                                            }
                                                                            placeholder={
                                                                                placeholder
                                                                            }
                                                                            disabled={
                                                                                !can_write
                                                                            }
                                                                            className="text-xs"
                                                                        />
                                                                    </label>
                                                                ),
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </Fragment>
                                    ))}
                                    <TableRow className="bg-[#ea7315]/20 font-bold hover:bg-[#ea7315]/20">
                                        <TableCell
                                            colSpan={3}
                                            className="border-r border-border text-center font-bold"
                                        >
                                            TOTAL
                                        </TableCell>
                                        <TableCell className="border-r border-border text-center font-bold text-amber-600 dark:text-amber-400">
                                            {totalAbnormal}
                                        </TableCell>
                                        <TableCell className="border-r border-border text-center font-mono font-bold">
                                            {totalAbnormalDurasi > 0
                                                ? totalAbnormalDurasi.toFixed(1)
                                                : 0}
                                        </TableCell>
                                        <TableCell className="border-r border-border text-center font-bold text-rose-600 dark:text-rose-400">
                                            {totalGangguan}
                                        </TableCell>
                                        <TableCell className="text-center font-mono font-bold">
                                            {totalGangguanDurasi > 0
                                                ? totalGangguanDurasi.toFixed(1)
                                                : 0}
                                        </TableCell>
                                        <TableCell className="border-l border-border text-xs font-semibold">
                                            {tindak
                                                .map(
                                                    ([key, label]) =>
                                                        `${label}: ${rows.filter((row) => row[key] === 1).length}`,
                                                )
                                                .join(' · ')}
                                        </TableCell>
                                        {can_write && (
                                            <TableCell className="border-l border-border" />
                                        )}
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    )}

                    {/* Bottom Instructions and Actions */}
                    <div className="flex flex-col gap-2 border-t border-border bg-card p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="text-xs leading-relaxed text-muted-foreground">
                            <span className="font-semibold text-foreground">
                                NOTE:
                            </span>
                            <ol className="mt-0.5 list-decimal space-y-0.5 pl-4">
                                <li>
                                    KOLOM ABNORMAL DAN GANGGUAN DI ISI CENTANG /
                                    ANGKA 1
                                </li>
                                <li>KOLOM DURASI DI ISI JAM</li>
                                <li>
                                    TINDAK LANJUT JANGKA PENDEK: SR (SERVICE
                                    REQUEST), FLM, ATAU LAINNYA (TULIS
                                    KETERANGANNYA)
                                </li>
                            </ol>
                        </div>
                        {can_write && (
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleAddRow}
                                    className="gap-1.5"
                                >
                                    <Plus className="size-4" />
                                    Tambah Baris
                                </Button>
                                <Button
                                    onClick={handleSave}
                                    disabled={saving || !isDirty}
                                    size="sm"
                                    className="gap-2"
                                >
                                    <Save className="size-4" />
                                    {saving ? 'Menyimpan...' : 'Simpan Data'}
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

KondisiAbnormalInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Operasi', href: operasiInput.index() },
        {
            title: 'Kondisi Abnormal & Gangguan',
            href: kondisiAbnormalRoutes.index(),
        },
    ],
};
