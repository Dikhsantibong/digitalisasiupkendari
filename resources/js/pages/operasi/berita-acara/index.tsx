import { Head, router } from '@inertiajs/react';
import {
    Droplet,
    FileCog,
    Fuel,
    Plus,
    Printer,
} from 'lucide-react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes';
import beritaAcara from '@/routes/operasi/berita-acara';
import documentTemplate from '@/routes/operasi/document-template';
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
    const { can } = usePermissions();

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

    const handleOpenPreview = (type: DocType) => {
        router.get(`/operasi/berita-acara/${type.value}/preview`, {
            unit_id: filters.unit_id,
            month: filters.month,
            year: filters.year,
        });
    };

    const selectedUnitName =
        options.units.find((u) => u.id === filters.unit_id)?.name ?? 'Unit Pembangkit';

    return (
        <>
            <Head title="Berita Acara" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Berita Acara"
                    description="Pilih unit dan periode, lalu buat berita acara resmi atau pratinjau dokumen cetak PDF."
                    actions={
                        can('operasi.master.manage') && (
                            <Button
                                variant="outline"
                                onClick={() => router.get(documentTemplate.index().url)}
                                className="gap-2"
                            >
                                <FileCog className="size-4" />
                                Kelola Template BA
                            </Button>
                        )
                    }
                />

                {/* Filter Unit & Periode */}
                <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3 shadow-xs">
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

                {/* Grid Dokumen Berita Acara matching Formulir layout */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {types.map((type) => {
                        const isLubricant = type.value === 'pelumas';
                        const Icon = isLubricant ? Droplet : Fuel;

                        return (
                            <div
                                key={type.value}
                                className="flex flex-col justify-between gap-4 rounded-md border border-border bg-card p-4 transition-all hover:border-primary/50 shadow-xs"
                            >
                                <div>
                                    <div className="mb-2 flex items-center justify-between gap-2">
                                        <div className="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <Icon className="size-5" />
                                        </div>
                                        <Badge
                                            variant="outline"
                                            className="border-emerald-600/30 bg-emerald-50 text-[11px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"
                                        >
                                            Tersedia
                                        </Badge>
                                    </div>
                                    <h2 className="text-base font-semibold text-foreground">
                                        {type.label}
                                    </h2>
                                    <p className="mt-1 text-[13px] text-muted-foreground line-clamp-2">
                                        {type.title}
                                    </p>
                                </div>

                                <div className="space-y-2 pt-2 border-t border-border">
                                    <div className="grid grid-cols-2 gap-2">
                                        <Button
                                            onClick={() => open(type)}
                                            className="w-full justify-center gap-1.5 text-xs font-semibold"
                                        >
                                            <Plus className="size-3.5" />
                                            Buat BA
                                        </Button>
                                        <Button
                                            variant="outline"
                                            onClick={() => handleOpenPreview(type)}
                                            className="w-full justify-center gap-1.5 text-xs"
                                        >
                                            <Printer className="size-3.5" />
                                            Pratinjau PDF
                                        </Button>
                                    </div>
                                    <p className="text-center text-[11px] text-muted-foreground">
                                        Buat dokumen, sesuaikan data &amp; cetak PDF resmi
                                    </p>
                                </div>
                            </div>
                        );
                    })}
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
