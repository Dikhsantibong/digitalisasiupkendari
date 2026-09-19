import { OPERASI_MONTHS, OperasiSelect } from '@/components/operasi/filter-select';
import type { IdName } from '@/types';

export type PdmInputFilters = { unit_id: number; month: number; year: number };

/**
 * Unit / bulan / tahun filter bar shared by the PdM input pages.
 */
export function PdmInputToolbar({
    filters,
    options,
    onChange,
    dirty = false,
}: {
    filters: PdmInputFilters;
    options: { units: IdName[]; years: number[] };
    onChange: (patch: Partial<PdmInputFilters>) => void;
    dirty?: boolean;
}) {
    return (
        <div className="flex flex-wrap items-end gap-3 rounded-md border border-border bg-card p-3">
            <OperasiSelect
                label="Unit"
                value={String(filters.unit_id)}
                onChange={(value) => onChange({ unit_id: Number(value) })}
                options={options.units.map((unit) => ({ value: String(unit.id), label: unit.name }))}
                className="w-56"
            />
            <OperasiSelect
                label="Bulan"
                value={String(filters.month)}
                onChange={(value) => onChange({ month: Number(value) })}
                options={OPERASI_MONTHS.map((label, index) => ({ value: String(index + 1), label }))}
            />
            <OperasiSelect
                label="Tahun"
                value={String(filters.year)}
                onChange={(value) => onChange({ year: Number(value) })}
                options={options.years.map((year) => ({ value: String(year), label: String(year) }))}
                className="w-28"
            />
            {dirty && <span className="pb-2 text-[13px] text-amber-600">Ada perubahan belum disimpan.</span>}
        </div>
    );
}
