import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Download,
    Eye,
    Pencil,
    Plus,
    ShieldAlert,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import harInput from '@/routes/har/input';
import unsafeConditionRoutes from '@/routes/har/input/unsafe-condition';
import type { IdName } from '@/types';

type UnsafeConditionRow = {
    id: number;
    periode: string;
    kategori: string;
    temuan: string;
    kondisi: string;
    tindak_lanjut: string;
    rekomendasi: string;
    lokasi: string;
    keterangan: 'open' | 'close';
    foto_sebelum: string | null;
    foto_sebelum_url: string | null;
    foto_sesudah: string | null;
    foto_sesudah_url: string | null;
};

type Props = {
    filters: { unit_id: number; month: number; year: number };
    rows: UnsafeConditionRow[];
    summary: {
        total: number;
        unsafe_action_count: number;
        unsafe_condition_count: number;
        open_count: number;
        close_count: number;
    };
    options: {
        units: IdName[];
        years: number[];
    };
    can_write: boolean;
};

const MONTHS = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

const PERIODE_OPTIONS = [
    'MINGGU KE - 1',
    'MINGGU KE - 2',
    'MINGGU KE - 3',
    'MINGGU KE - 4',
    'MINGGU KE - 5',
];

export default function UnsafeConditionsInput({
    filters,
    rows,
    summary,
    options,
    can_write,
}: Props) {
    const [openModal, setOpenModal] = useState(false);
    const [editing, setEditing] = useState<UnsafeConditionRow | null>(null);
    const [previewImage, setPreviewImage] = useState<{ title: string; url: string } | null>(null);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            unsafeConditionRoutes.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleCreate = () => {
        setEditing(null);
        setOpenModal(true);
    };

    const handleEdit = (item: UnsafeConditionRow) => {
        setEditing(item);
        setOpenModal(true);
    };

    const handlePdf = () => {
        const url = unsafeConditionRoutes.pdf({
            query: {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
            },
        }).url;
        window.open(url, '_blank');
    };

    return (
        <>
            <Head title="Input Unsafe Action & Unsafe Condition" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="Laporan Unsafe Action & Unsafe Condition"
                    description="Identifikasi, pelaporan, dan evaluasi tindak lanjut temuan tindakan tidak aman (unsafe action) serta kondisi berbahaya (unsafe condition)."
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button variant="outline" onClick={handlePdf}>
                                <Download className="size-4" />
                                Cetak PDF
                            </Button>
                            {can_write && (
                                <Button onClick={handleCreate}>
                                    <Plus className="size-4" />
                                    Tambah Temuan
                                </Button>
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
                        options={options.units.map((u) => ({ value: String(u.id), label: u.name }))}
                    />
                    <OperasiSelect
                        label="Bulan"
                        value={String(filters.month)}
                        onChange={(value) => visit({ month: Number(value) })}
                        options={MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                    />
                </div>

                {/* KPI Cards */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Total Temuan</span>
                            <ShieldAlert className="size-4 text-primary" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-foreground">{summary.total}</div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Periode terpilih</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Unsafe Action</span>
                            <AlertTriangle className="size-4 text-amber-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-amber-600 dark:text-amber-400">
                            {summary.unsafe_action_count}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Tindakan tidak aman</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Unsafe Condition</span>
                            <AlertTriangle className="size-4 text-rose-500" />
                        </div>
                        <div className="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">
                            {summary.unsafe_condition_count}
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Kondisi berbahaya</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-muted-foreground">Status Temuan</span>
                            <div className="flex items-center gap-1">
                                <CheckCircle2 className="size-3.5 text-emerald-500" />
                                <Clock className="size-3.5 text-amber-500" />
                            </div>
                        </div>
                        <div className="mt-2 flex items-baseline gap-2">
                            <span className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                                {summary.close_count}
                            </span>
                            <span className="text-xs text-muted-foreground">Close</span>
                            <span className="text-sm font-semibold text-muted-foreground">/</span>
                            <span className="text-2xl font-bold text-amber-600 dark:text-amber-400">
                                {summary.open_count}
                            </span>
                            <span className="text-xs text-muted-foreground">Open</span>
                        </div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Penyelesaian temuan</p>
                    </div>
                </div>

                {/* Main Table */}
                <div className="overflow-hidden rounded-lg border border-border bg-card shadow-xs">
                    {rows.length === 0 ? (
                        <EmptyState
                            title="Belum ada data temuan"
                            description="Tambahkan temuan unsafe action atau unsafe condition untuk unit dan periode ini."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-primary/5 hover:bg-primary/5">
                                        <TableHead className="w-12 text-center font-bold">NO</TableHead>
                                        <TableHead className="w-32 text-center font-bold">PERIODE</TableHead>
                                        <TableHead className="w-40 text-center font-bold">KATEGORI</TableHead>
                                        <TableHead className="min-w-[200px] font-bold">TEMUAN</TableHead>
                                        <TableHead className="w-28 text-center font-bold">KONDISI</TableHead>
                                        <TableHead className="min-w-[180px] font-bold">TINDAK LANJUT</TableHead>
                                        <TableHead className="min-w-[180px] font-bold">REKOMENDASI</TableHead>
                                        <TableHead className="w-36 text-center font-bold">LOKASI</TableHead>
                                        <TableHead className="w-24 text-center font-bold">STATUS</TableHead>
                                        <TableHead className="w-24 text-center font-bold">SEBELUM</TableHead>
                                        <TableHead className="w-24 text-center font-bold">SESUDAH</TableHead>
                                        {can_write && (
                                            <TableHead className="w-24 text-center font-bold">AKSI</TableHead>
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((row, index) => (
                                        <TableRow key={row.id}>
                                            <TableCell className="text-center font-medium">
                                                {index + 1}
                                            </TableCell>
                                            <TableCell className="text-center text-xs font-medium">
                                                {row.periode}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Badge
                                                    variant={row.kategori === 'UNSAFE ACTION' ? 'outline' : 'secondary'}
                                                    className={
                                                        row.kategori === 'UNSAFE ACTION'
                                                            ? 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                            : 'border-rose-500/30 bg-rose-500/10 text-rose-600 dark:text-rose-400'
                                                    }
                                                >
                                                    {row.kategori}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {row.temuan}
                                            </TableCell>
                                            <TableCell className="text-center text-xs">
                                                {row.kondisi || '-'}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {row.tindak_lanjut || '-'}
                                            </TableCell>
                                            <TableCell className="text-xs text-muted-foreground">
                                                {row.rekomendasi || '-'}
                                            </TableCell>
                                            <TableCell className="text-center text-xs">
                                                {row.lokasi || '-'}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        row.keterangan === 'close'
                                                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 uppercase dark:text-emerald-400'
                                                            : 'border-amber-500/30 bg-amber-500/10 text-amber-600 uppercase dark:text-amber-400'
                                                    }
                                                >
                                                    {row.keterangan}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                {row.foto_sebelum_url ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setPreviewImage({
                                                                title: `Eviden Sebelum - ${row.temuan}`,
                                                                url: row.foto_sebelum_url!,
                                                            })
                                                        }
                                                        className="group relative inline-block overflow-hidden rounded border border-border transition-all hover:ring-2 hover:ring-primary/40"
                                                    >
                                                        <img
                                                            src={row.foto_sebelum_url}
                                                            alt="Sebelum"
                                                            className="size-12 object-cover transition-transform group-hover:scale-105"
                                                        />
                                                        <div className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                                                            <Eye className="size-4 text-white" />
                                                        </div>
                                                    </button>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                {row.foto_sesudah_url ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setPreviewImage({
                                                                title: `Eviden Sesudah - ${row.temuan}`,
                                                                url: row.foto_sesudah_url!,
                                                            })
                                                        }
                                                        className="group relative inline-block overflow-hidden rounded border border-border transition-all hover:ring-2 hover:ring-primary/40"
                                                    >
                                                        <img
                                                            src={row.foto_sesudah_url}
                                                            alt="Sesudah"
                                                            className="size-12 object-cover transition-transform group-hover:scale-105"
                                                        />
                                                        <div className="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                                                            <Eye className="size-4 text-white" />
                                                        </div>
                                                    </button>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">-</span>
                                                )}
                                            </TableCell>
                                            {can_write && (
                                                <TableCell className="text-center">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <Button
                                                            size="icon"
                                                            variant="ghost"
                                                            className="size-7"
                                                            onClick={() => handleEdit(row)}
                                                            title="Edit Temuan"
                                                        >
                                                            <Pencil className="size-3.5" />
                                                        </Button>
                                                        <ConfirmDeleteDialog
                                                            title="Hapus Temuan"
                                                            description={`Apakah Anda yakin ingin menghapus temuan "${row.temuan}"? Tindakan ini tidak dapat dibatalkan.`}
                                                            action={{
                                                                action: unsafeConditionRoutes.destroy(row.id).url,
                                                                method: 'delete',
                                                            }}
                                                        >
                                                            <Button
                                                                size="icon"
                                                                variant="ghost"
                                                                className="size-7 text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                                title="Hapus Temuan"
                                                            >
                                                                <Trash2 className="size-3.5" />
                                                            </Button>
                                                        </ConfirmDeleteDialog>
                                                    </div>
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </div>

                {/* Bottom Summary Table matching Excel template */}
                <div className="w-full max-w-sm rounded-lg border border-border bg-card p-4 shadow-xs">
                    <h3 className="mb-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                        Rekapitulasi Temuan Kondisi
                    </h3>
                    <div className="overflow-hidden rounded-md border border-border">
                        <table className="w-full text-xs">
                            <thead className="bg-primary/10 text-foreground font-semibold">
                                <tr>
                                    <th className="border-b border-r border-border p-2 text-center w-10">NO</th>
                                    <th className="border-b border-r border-border p-2 text-left">TEMUAN KONDISI</th>
                                    <th className="border-b border-border p-2 text-center w-16">JUMLAH</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                <tr>
                                    <td className="border-r border-border p-2 text-center">1</td>
                                    <td className="border-r border-border p-2 font-medium">UNSAFE ACTION</td>
                                    <td className="p-2 text-center font-bold text-amber-600 dark:text-amber-400">
                                        {summary.unsafe_action_count}
                                    </td>
                                </tr>
                                <tr>
                                    <td className="border-r border-border p-2 text-center">2</td>
                                    <td className="border-r border-border p-2 font-medium">UNSAFE CONDITION</td>
                                    <td className="p-2 text-center font-bold text-rose-600 dark:text-rose-400">
                                        {summary.unsafe_condition_count}
                                    </td>
                                </tr>
                                <tr className="bg-muted/50 font-bold">
                                    <td colSpan={2} className="border-r border-border p-2 text-right">TOTAL:</td>
                                    <td className="p-2 text-center text-foreground">{summary.total}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {/* Modal Dialog Form Create / Edit */}
            {openModal && (
                <UnsafeConditionDialog
                    open={openModal}
                    onOpenChange={setOpenModal}
                    filters={filters}
                    editing={editing}
                />
            )}

            {/* Photo Preview Dialog */}
            {previewImage && (
                <Dialog open={!!previewImage} onOpenChange={() => setPreviewImage(null)}>
                    <DialogContent className="max-w-2xl">
                        <DialogHeader>
                            <DialogTitle className="text-base">{previewImage.title}</DialogTitle>
                        </DialogHeader>
                        <div className="flex items-center justify-center p-2">
                            <img
                                src={previewImage.url}
                                alt="Eviden"
                                className="max-h-[75vh] w-auto rounded object-contain"
                            />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}

function UnsafeConditionDialog({
    open,
    onOpenChange,
    filters,
    editing,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    filters: Props['filters'];
    editing: UnsafeConditionRow | null;
}) {
    const fotoSebelumRef = useRef<HTMLInputElement>(null);
    const fotoSesudahRef = useRef<HTMLInputElement>(null);

    const [form, setForm] = useState({
        periode: editing?.periode ?? 'MINGGU KE - 1',
        kategori: editing?.kategori ?? 'UNSAFE CONDITION',
        temuan: editing?.temuan ?? '',
        kondisi: editing?.kondisi ?? '',
        tindak_lanjut: editing?.tindak_lanjut ?? '',
        rekomendasi: editing?.rekomendasi ?? '',
        lokasi: editing?.lokasi ?? '',
        keterangan: editing?.keterangan ?? 'close',
    });

    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const set = (key: keyof typeof form, value: string) =>
        setForm((prev) => ({ ...prev, [key]: value }));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        const formData = new FormData();
        formData.append('unit_id', String(filters.unit_id));
        formData.append('month', String(filters.month));
        formData.append('year', String(filters.year));
        formData.append('periode', form.periode);
        formData.append('kategori', form.kategori);
        formData.append('temuan', form.temuan);
        formData.append('kondisi', form.kondisi);
        formData.append('tindak_lanjut', form.tindak_lanjut);
        formData.append('rekomendasi', form.rekomendasi);
        formData.append('lokasi', form.lokasi);
        formData.append('keterangan', form.keterangan);

        if (fotoSebelumRef.current?.files?.[0]) {
            formData.append('foto_sebelum', fotoSebelumRef.current.files[0]);
        }
        if (fotoSesudahRef.current?.files?.[0]) {
            formData.append('foto_sesudah', fotoSesudahRef.current.files[0]);
        }

        if (editing) {
            router.post(unsafeConditionRoutes.update(editing.id).url, formData, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: (errs) => setErrors(errs),
                onFinish: () => setSaving(false),
            });
        } else {
            router.post(unsafeConditionRoutes.store().url, formData, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onError: (errs) => setErrors(errs),
                onFinish: () => setSaving(false),
            });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {editing ? 'Ubah Temuan Unsafe Action / Condition' : 'Tambah Temuan Unsafe Action / Condition'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 py-2">
                    <div className="grid grid-cols-2 gap-4">
                        <FormField label="Periode" error={errors.periode} required>
                            <Select value={form.periode} onValueChange={(val) => set('periode', val)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih Periode" />
                                </SelectTrigger>
                                <SelectContent>
                                    {PERIODE_OPTIONS.map((opt) => (
                                        <SelectItem key={opt} value={opt}>
                                            {opt}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>

                        <FormField label="Kategori" error={errors.kategori} required>
                            <Select value={form.kategori} onValueChange={(val) => set('kategori', val)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih Kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="UNSAFE CONDITION">UNSAFE CONDITION</SelectItem>
                                    <SelectItem value="UNSAFE ACTION">UNSAFE ACTION</SelectItem>
                                </SelectContent>
                            </Select>
                        </FormField>
                    </div>

                    <FormField label="Uraian Temuan" error={errors.temuan} required>
                        <textarea
                            value={form.temuan}
                            onChange={(e) => set('temuan', e.target.value)}
                            placeholder="Contoh: Kebocoran line pipa dan valve BBM..."
                            className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            required
                        />
                    </FormField>

                    <div className="grid grid-cols-2 gap-4">
                        <FormField label="Kondisi" error={errors.kondisi}>
                            <Input
                                value={form.kondisi}
                                onChange={(e) => set('kondisi', e.target.value)}
                                placeholder="Contoh: Bocor, Patah, Rusak"
                            />
                        </FormField>

                        <FormField label="Lokasi" error={errors.lokasi}>
                            <Input
                                value={form.lokasi}
                                onChange={(e) => set('lokasi', e.target.value)}
                                placeholder="Contoh: Area samping Cummins #6"
                            />
                        </FormField>
                    </div>

                    <FormField label="Tindak Lanjut" error={errors.tindak_lanjut}>
                        <textarea
                            value={form.tindak_lanjut}
                            onChange={(e) => set('tindak_lanjut', e.target.value)}
                            placeholder="Contoh: Dilakukan perbaikan dan penambalan sementara..."
                            className="flex min-h-[50px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                    </FormField>

                    <FormField label="Rekomendasi" error={errors.rekomendasi}>
                        <textarea
                            value={form.rekomendasi}
                            onChange={(e) => set('rekomendasi', e.target.value)}
                            placeholder="Contoh: Pengelasan line BBM yang bocor dan penggantian valve baru..."
                            className="flex min-h-[50px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                    </FormField>

                    <FormField label="Keterangan / Status" error={errors.keterangan} required>
                        <Select value={form.keterangan} onValueChange={(val) => set('keterangan', val)}>
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="close">CLOSE (Selesai Ditindaklanjuti)</SelectItem>
                                <SelectItem value="open">OPEN (Belum Selesai)</SelectItem>
                            </SelectContent>
                        </Select>
                    </FormField>

                    <div className="grid grid-cols-2 gap-4 pt-2 border-t border-border">
                        <div>
                            <label className="text-xs font-medium text-foreground block mb-1">
                                Eviden Sebelum (Foto)
                            </label>
                            {editing?.foto_sebelum_url && (
                                <div className="mb-2">
                                    <img
                                        src={editing.foto_sebelum_url}
                                        alt="Sebelum"
                                        className="h-16 w-auto rounded border object-cover"
                                    />
                                    <span className="text-[11px] text-muted-foreground">Foto saat ini</span>
                                </div>
                            )}
                            <Input
                                ref={fotoSebelumRef}
                                type="file"
                                accept="image/*"
                                className="cursor-pointer text-xs"
                            />
                            {errors.foto_sebelum && (
                                <p className="mt-1 text-xs text-destructive">{errors.foto_sebelum}</p>
                            )}
                        </div>

                        <div>
                            <label className="text-xs font-medium text-foreground block mb-1">
                                Eviden Sesudah (Foto)
                            </label>
                            {editing?.foto_sesudah_url && (
                                <div className="mb-2">
                                    <img
                                        src={editing.foto_sesudah_url}
                                        alt="Sesudah"
                                        className="h-16 w-auto rounded border object-cover"
                                    />
                                    <span className="text-[11px] text-muted-foreground">Foto saat ini</span>
                                </div>
                            )}
                            <Input
                                ref={fotoSesudahRef}
                                type="file"
                                accept="image/*"
                                className="cursor-pointer text-xs"
                            />
                            {errors.foto_sesudah && (
                                <p className="mt-1 text-xs text-destructive">{errors.foto_sesudah}</p>
                            )}
                        </div>
                    </div>

                    <DialogFooter className="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={saving}
                        >
                            Batal
                        </Button>
                        <Button type="submit" disabled={saving}>
                            {saving ? 'Menyimpan...' : editing ? 'Simpan Perubahan' : 'Tambah Temuan'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

UnsafeConditionsInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Input Pemeliharaan', href: harInput.index() },
        { title: 'Unsafe Action & Condition', href: unsafeConditionRoutes.index() },
    ],
};
