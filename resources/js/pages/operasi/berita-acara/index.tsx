import { Head, router } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import beritaAcara from '@/routes/operasi/berita-acara';
import type { IdName } from '@/types';

type DocType = { value: string; label: string; title: string };

type Filters = { unit_id: number; month: number; year: number };

type Props = {
    filters: Filters;
    types: DocType[];
    options: { units: IdName[]; years: number[] };
    can_create: boolean;
};

export default function BeritaAcaraIndex({ filters, types, options }: Props) {
    const visit = (patch: Partial<Filters>) => {
        router.get(
            beritaAcara.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const open = (type: DocType) => {
        router.get(
            beritaAcara.show(type.value, {
                query: {
                    unit_id: filters.unit_id,
                    month: filters.month,
                    year: filters.year,
                },
            }).url,
        );
    };

    return (
        <>
            <Head title="Berita Acara" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Berita Acara"
                    description="Pilih unit dan periode, lalu satu klik untuk melihat & mencetak dokumen resmi."
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

                <div className="grid gap-3 md:grid-cols-3">
                    {types.map((type) => (
                        <div
                            key={type.value}
                            className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-4"
                        >
                            <div>
                                <h2 className="text-base font-semibold text-foreground">
                                    {type.label}
                                </h2>
                                <p className="mt-1 text-[13px] text-muted-foreground">
                                    {type.title}
                                </p>
                            </div>
                            <Button onClick={() => open(type)}>
                                <FileText className="size-4" />
                                Buka &amp; Edit
                            </Button>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

BeritaAcaraIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Berita Acara', href: beritaAcara.index() },
    ],
};
