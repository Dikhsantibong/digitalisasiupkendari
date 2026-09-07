import { Form, Head, router } from '@inertiajs/react';
import DocumentTemplateController from '@/actions/App/Http/Controllers/Operasi/DocumentTemplateController';
import { FormField } from '@/components/form-field';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import documentTemplate from '@/routes/operasi/document-template';
import type { IdName } from '@/types';

type Template = {
    type: string;
    label: string;
    is_override: boolean;
    document_number: string;
    title: string;
    revision: string;
    revision_date: string | null;
};

type Props = {
    scope: { unit_id: number | null };
    templates: Template[];
    units: IdName[];
};

export default function DocumentTemplateIndex({ scope, templates, units }: Props) {
    const scopeValue = scope.unit_id ? String(scope.unit_id) : 'global';

    const onScopeChange = (value: string) => {
        router.get(
            documentTemplate.index().url,
            value === 'global' ? {} : { unit_id: Number(value) },
            { preserveState: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Template Dokumen — Nomor Surat BA" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Template Berita Acara"
                    description="Atur nomor surat, judul, dan revisi tiap jenis BA. Nomor tetap (bukan auto-increment); override per unit menimpa default global."
                />

                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
                    <OperasiSelect
                        label="Cakupan"
                        value={scopeValue}
                        onChange={onScopeChange}
                        className="w-56"
                        options={[
                            { value: 'global', label: 'Global (Default)' },
                            ...units.map((u) => ({
                                value: String(u.id),
                                label: `Override — ${u.name}`,
                            })),
                        ]}
                    />
                    <p className="text-[13px] text-muted-foreground">
                        {scope.unit_id
                            ? 'Menyimpan akan membuat/mengubah override khusus unit ini.'
                            : 'Anda sedang mengedit default global (berlaku untuk semua unit tanpa override).'}
                    </p>
                </div>

                <div className="grid gap-3 lg:grid-cols-3">
                    {templates.map((template) => (
                        <Form
                            key={`${scopeValue}-${template.type}`}
                            {...DocumentTemplateController.update.form(template.type)}
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-3 rounded-md border border-border bg-card p-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    {scope.unit_id !== null && (
                                        <input type="hidden" name="unit_id" value={scope.unit_id} />
                                    )}
                                    <div className="flex items-center justify-between">
                                        <h2 className="text-base font-semibold text-foreground">
                                            {template.label}
                                        </h2>
                                        {scope.unit_id !== null && (
                                            <StatusBadge tone={template.is_override ? 'info' : 'neutral'}>
                                                {template.is_override ? 'Override' : 'Ikut Global'}
                                            </StatusBadge>
                                        )}
                                    </div>

                                    <FormField label="Nomor Surat" htmlFor={`num-${template.type}`} required error={errors.document_number}>
                                        <Input
                                            id={`num-${template.type}`}
                                            name="document_number"
                                            defaultValue={template.document_number}
                                            autoComplete="off"
                                            required
                                        />
                                    </FormField>
                                    <FormField label="Judul" htmlFor={`title-${template.type}`} required error={errors.title}>
                                        <Input
                                            id={`title-${template.type}`}
                                            name="title"
                                            defaultValue={template.title}
                                            autoComplete="off"
                                            required
                                        />
                                    </FormField>
                                    <div className="grid grid-cols-2 gap-3">
                                        <FormField label="Revisi" htmlFor={`rev-${template.type}`} required error={errors.revision}>
                                            <Input
                                                id={`rev-${template.type}`}
                                                name="revision"
                                                defaultValue={template.revision}
                                                autoComplete="off"
                                                required
                                            />
                                        </FormField>
                                        <FormField label="Tgl Revisi" htmlFor={`revdate-${template.type}`} error={errors.revision_date}>
                                            <Input
                                                id={`revdate-${template.type}`}
                                                name="revision_date"
                                                type="date"
                                                defaultValue={template.revision_date ?? ''}
                                            />
                                        </FormField>
                                    </div>

                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={processing} size="sm">
                                            Simpan
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    ))}
                </div>
            </div>
        </>
    );
}

DocumentTemplateIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Template Berita Acara', href: documentTemplate.index() },
    ],
};
