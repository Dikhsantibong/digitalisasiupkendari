import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { OperasiSelect } from '@/components/operasi/filter-select';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { formatNumber } from '@/lib/format';
import { dashboard } from '@/routes';
import cost from '@/routes/har/input/cost';
import type { IdName } from '@/types';

const MONTHS = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

type Money = { service: number; material: number; total: number };

type Props = {
    filters: { unit_id: number; month: number; year: number };
    auto: Money;
    manual: {
        service_cost: string | null;
        material_cost: string | null;
        use_manual: boolean;
        keterangan: string | null;
    };
    effective: Money & { source: string };
    ytd: number;
    options: { units: IdName[]; years: number[] };
    can_write: boolean;
};

const rupiah = (value: number | string | null) =>
    value === null ? '—' : `Rp ${formatNumber(String(value))}`;

function Card({ label, value, tone }: { label: string; value: string; tone?: 'info' }) {
    return (
        <div className="rounded-md border border-border bg-card p-3">
            <p className="text-[13px] text-muted-foreground">{label}</p>
            <p className={`mt-1 text-lg font-semibold tabular-nums ${tone === 'info' ? 'text-primary' : ''}`}>
                {value}
            </p>
        </div>
    );
}

export default function CostInput({ filters, auto, manual, effective, ytd, options, can_write }: Props) {
    const [useManual, setUseManual] = useState(manual.use_manual);
    const [service, setService] = useState(manual.service_cost ?? '');
    const [material, setMaterial] = useState(manual.material_cost ?? '');
    const [keterangan, setKeterangan] = useState(manual.keterangan ?? '');
    const [saving, setSaving] = useState(false);

    const visit = (patch: Partial<Props['filters']>) => {
        router.get(
            cost.index().url,
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const save = () => {
        setSaving(true);
        router.post(
            cost.store().url,
            {
                unit_id: filters.unit_id,
                month: filters.month,
                year: filters.year,
                use_manual: useManual,
                service_cost: service === '' ? null : service,
                material_cost: material === '' ? null : material,
                keterangan: keterangan === '' ? null : keterangan,
            },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <>
            <Head title="Input HAR — Biaya" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Biaya Pemeliharaan"
                    description="Default dihitung dari total biaya Work Order periode ini. Bisa di-override manual per bulan."
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

                <div>
                    <div className="mb-2 flex items-center gap-2 text-[13px] text-muted-foreground">
                        Otomatis dari Work Order
                    </div>
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Card label="Jasa (WO)" value={rupiah(auto.service)} />
                        <Card label="Material (WO)" value={rupiah(auto.material)} />
                        <Card label="Total (WO)" value={rupiah(auto.total)} />
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-md border border-border bg-card p-3">
                        <div className="flex items-center justify-between">
                            <p className="text-[13px] text-muted-foreground">Efektif (dipakai)</p>
                            <StatusBadge tone={effective.source === 'manual' ? 'warning' : 'success'}>
                                {effective.source === 'manual' ? 'Manual' : 'Otomatis'}
                            </StatusBadge>
                        </div>
                        <p className="mt-1 text-lg font-semibold tabular-nums">{rupiah(effective.total)}</p>
                    </div>
                    <Card label="Akumulasi s/d bulan ini (YTD)" value={rupiah(ytd)} tone="info" />
                </div>

                <div className="rounded-md border border-border bg-card p-4">
                    <h2 className="mb-3 text-base font-semibold">Override Manual (per bulan)</h2>
                    <label className="mb-3 flex items-center gap-2 text-[13px]">
                        <Checkbox checked={useManual} onCheckedChange={(v) => setUseManual(Boolean(v))} />
                        Gunakan nilai manual (menimpa perhitungan otomatis)
                    </label>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField label="Biaya Jasa">
                            <Input type="number" min="0" step="0.01" value={service} onChange={(e) => setService(e.target.value)} disabled={!useManual} />
                        </FormField>
                        <FormField label="Biaya Material">
                            <Input type="number" min="0" step="0.01" value={material} onChange={(e) => setMaterial(e.target.value)} disabled={!useManual} />
                        </FormField>
                        <FormField label="Keterangan">
                            <Input value={keterangan} onChange={(e) => setKeterangan(e.target.value)} autoComplete="off" />
                        </FormField>
                    </div>
                    {can_write && (
                        <div className="mt-4 flex justify-end">
                            <Button onClick={save} disabled={saving}>
                                {saving ? 'Menyimpan…' : 'Simpan'}
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

CostInput.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Biaya', href: cost.index() },
    ],
};
