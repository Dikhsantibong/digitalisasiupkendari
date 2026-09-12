import { Head, router, useForm } from '@inertiajs/react';
import { FileText, Trash2, Upload } from 'lucide-react';
import { useRef } from 'react';
import { ConfirmDeleteDialog } from '@/components/confirm-delete-dialog';
import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import attachment from '@/routes/k3/input/attachment';
import type { IdName } from '@/types';

type Attachment = { id: number; title: string; category: string | null; url: string };

type Props = {
    filters: { unit_id: number; month: number; year: number };
    attachments: Attachment[];
    options: { units: IdName[]; years: number[] };
    can_write: boolean;
};

const isImage = (url: string) => /\.(jpe?g|png|webp)$/i.test(url.split('?')[0]);

export default function K3Attachments({ filters, attachments, options, can_write }: Props) {
    const fileRef = useRef<HTMLInputElement>(null);
    const form = useForm<{ title: string; category: string; file: File | null }>({
        title: '',
        category: '',
        file: null,
    });

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            attachment.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            unit_id: filters.unit_id,
            month: filters.month,
            year: filters.year,
        }));
        form.post(attachment.store().url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset();

                if (fileRef.current) {
                    fileRef.current.value = '';
                }
            },
        });
    };

    return (
        <>
            <Head title="Input K3 — Lampiran" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Lampiran K3"
                    description="Unggah dokumen/foto pendukung per periode (JPG/PNG/PDF, maks 10MB). Tersimpan di storage, bukan blob."
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
                        options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
                    />
                    <OperasiSelect
                        label="Tahun"
                        value={String(filters.year)}
                        onChange={(value) => visit({ year: Number(value) })}
                        options={options.years.map((y) => ({ value: String(y), label: String(y) }))}
                    />
                </div>

                {can_write && (
                    <form onSubmit={submit} className="grid gap-3 rounded-md border border-border bg-card p-3 md:grid-cols-4">
                        <label className="flex flex-col gap-1 text-[13px]">
                            <span className="text-muted-foreground">Judul</span>
                            <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} required />
                            {form.errors.title && <span className="text-[12px] text-destructive">{form.errors.title}</span>}
                        </label>
                        <label className="flex flex-col gap-1 text-[13px]">
                            <span className="text-muted-foreground">Kategori</span>
                            <Input value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} />
                        </label>
                        <label className="flex flex-col gap-1 text-[13px]">
                            <span className="text-muted-foreground">Berkas</span>
                            <Input
                                ref={fileRef}
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,.pdf"
                                onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)}
                                required
                            />
                            {form.errors.file && <span className="text-[12px] text-destructive">{form.errors.file}</span>}
                        </label>
                        <div className="flex items-end">
                            <Button type="submit" disabled={form.processing}>
                                <Upload className="size-4" />
                                {form.processing ? 'Mengunggah…' : 'Unggah'}
                            </Button>
                        </div>
                    </form>
                )}

                {attachments.length === 0 ? (
                    <div className="rounded-md border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                        Belum ada lampiran pada periode ini.
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        {attachments.map((a) => (
                            <div key={a.id} className="flex flex-col overflow-hidden rounded-md border border-border bg-card">
                                <a href={a.url} target="_blank" rel="noreferrer" className="flex aspect-video items-center justify-center bg-muted">
                                    {isImage(a.url) ? (
                                        <img src={a.url} alt={a.title} className="size-full object-cover" />
                                    ) : (
                                        <FileText className="size-10 text-muted-foreground" />
                                    )}
                                </a>
                                <div className="flex items-start justify-between gap-2 p-2">
                                    <div className="min-w-0">
                                        <div className="truncate text-[13px] font-medium text-foreground">{a.title}</div>
                                        {a.category && <div className="truncate text-[12px] text-muted-foreground">{a.category}</div>}
                                    </div>
                                    {can_write && (
                                        <ConfirmDeleteDialog
                                            action={attachment.destroy.form(a.id)}
                                            title="Hapus lampiran?"
                                            description="Berkas akan dihapus permanen dari storage."
                                            trigger={
                                                <button type="button" aria-label="Hapus" className="text-destructive">
                                                    <Trash2 className="size-4" />
                                                </button>
                                            }
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

K3Attachments.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Lampiran K3', href: attachment.index() },
    ],
};
