import { router } from '@inertiajs/react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import type { IdName } from '@/types';

export type UlFiltersValue = { ul_id: number; month: number; year: number };

/**
 * UL / Bulan / Tahun of the Laporan UL pages (the UL select only when the
 * account sees several, e.g. Super Admin).
 */
export function UlFilters({
    url,
    filters,
    options,
}: {
    /** The page's own URL (without query). */
    url: string;
    filters: UlFiltersValue;
    options: { uls: IdName[]; years: number[] };
}) {
    const visit = (patch: Partial<UlFiltersValue>) =>
        router.get(
            url,
            { ...filters, ...patch },
            { preserveScroll: true, replace: true },
        );

    return (
        <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-secondary p-3">
            {options.uls.length > 1 && (
                <OperasiSelect
                    label="Unit Layanan (UL)"
                    value={String(filters.ul_id)}
                    onChange={(value) => visit({ ul_id: Number(value) })}
                    className="w-60"
                    options={options.uls.map((ul) => ({
                        value: String(ul.id),
                        label: ul.name,
                    }))}
                />
            )}
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
                options={options.years.map((year) => ({
                    value: String(year),
                    label: String(year),
                }))}
            />
        </div>
    );
}
