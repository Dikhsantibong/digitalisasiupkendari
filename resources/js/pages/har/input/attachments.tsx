import { Head, router } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import AttachmentController from '@/actions/App/Http/Controllers/Har/AttachmentController';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormField } from '@/components/form-field';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import attachment from '@/routes/har/input/attachment';
import type { IdName } from '@/types';

const MONTHS = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

type Attachment = {
    id: number;
    title: string;
    caption: string | null;
    taken_date: string | null;
    engine_name: string | null;
    wonum: string | null;
    url: string;
};

type Props = {
    filters: { unit_id: number; month: number; year: number };
    attachments: Attachment[];
    options: { units: IdName[]; years: number[]; machines: IdName[]; work_orders: { id: number; wonum: string }[] };
    can_write: boolean;
};

export default function AttachmentsInput({ filters, attachments, options, can_write }: Props) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [form, setForm] = useState({ title: '', caption: '', taken_date: '', engine_id: '', wo_id: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            attachment.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }));

    const upload = () => {
        const file = fileRef.current?.files?.[0];

        if (!file) {
            setErrors({ photo: 'Pilih file foto dulu.' });

            return;
        }

        setSaving(true);
        router.post(
            attachment.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                title: form.title,
                caption: form.caption || null,
                taken_date: form.taken_date || null,
                engine_id: form.engine_id ? Number(form.engine_id) : null,
                wo_id: form.wo_id ? Number(form.wo_id) : null,
                photo: file,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setForm({ title: '', caption: '', taken_date: '', engine_id: '', wo_id: '' });
                    setErrors({});

                    if (fileRef.current) {
                        fileRef.current.value = '';
                    }
                },
                onError: (e) => setErrors(e),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <>
            <Head title="Input HAR — Lampiran Foto" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Lampiran Foto"
                    description="Unggah foto pekerjaan penting untuk lampiran laporan HAR."
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
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

                {can_write && (
                    <div className="rounded-md border border-border bg-card p-4">
                        <h2 className="mb-3 text-base font-semibold">Unggah Foto</h2>
                        <div className="grid gap-4 md:grid-cols-3">
                            <FormField label="Judul" required error={errors.title}>
                                <Input value={form.title} onChange={(e) => set('title', e.target.value)} autoComplete="off" />
                            </FormField>
                            <FormField label="Tanggal Foto" error={errors.taken_date}>
                                <Input type="date" value={form.taken_date} onChange={(e) => set('taken_date', e.target.value)} />
                            </FormField>
                            <FormField label="Foto" required error={errors.photo}>
                                <Input ref={fileRef} type="file" accept="image/*" />
                            </FormField>
                            <FormField label="Mesin" error={errors.engine_id}>
                                <Select value={form.engine_id || undefined} onValueChange={(v) => set('engine_id', v)}>
                                    <SelectTrigger className="w-full"><SelectValue placeholder="Pilih mesin (opsional)" /></SelectTrigger>
                                    <SelectContent>
                                        {options.machines.map((m) => (
                                            <SelectItem key={m.id} value={String(m.id)}>{m.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField label="Work Order" error={errors.wo_id}>
                                <Select value={form.wo_id || undefined} onValueChange={(v) => set('wo_id', v)}>
                                    <SelectTrigger className="w-full"><SelectValue placeholder="Kaitkan WO (opsional)" /></SelectTrigger>
                                    <SelectContent>
                                        {options.work_orders.map((w) => (
                                            <SelectItem key={w.id} value={String(w.id)}>{w.wonum}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField label="Keterangan" error={errors.caption}>
                                <Input value={form.caption} onChange={(e) => set('caption', e.target.value)} autoComplete="off" />
                            </FormField>
                        </div>
                        <div className="mt-4 flex justify-end">
                            <Button onClick={upload} disabled={saving}>
                                <Upload className="size-4" />
                                {saving ? 'Mengunggah…' : 'Unggah'}
                            </Button>
                        </div>
                    </div>
                )}

                {attachments.length === 0 ? (
                    <EmptyState title="Belum ada foto" description="Unggah foto pekerjaan untuk periode ini." />
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {attachments.map((a) => (
                            <div key={a.id} className="overflow-hidden rounded-md border border-border bg-card">
                                <img src={a.url} alt={a.title} className="h-44 w-full object-cover" />
                                <div className="flex items-start justify-between gap-2 p-3">
                                    <div>
                                        <p className="text-sm font-semibold">{a.title}</p>
                                        <p className="text-[12px] text-muted-foreground">
                                            {[a.engine_name, a.wonum, a.taken_date].filter(Boolean).join(' · ') || '—'}
                                        </p>
                                        {a.caption && <p className="mt-1 text-[12px]">{a.caption}</p>}
                                    </div>
                                    {can_write && (
                                        <ConfirmDeleteDialog
                                            action={AttachmentController.destroy.form(a.id)}
                                            title="Hapus foto?"
                                            description="Foto lampiran ini akan dihapus permanen."
                                        />
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

AttachmentsInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Lampiran Foto', href: attachment.index() },
    ],
};
