import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useSimplifiedMobileInput } from '@/hooks/use-mobile-module';

/** The unit & period filters a phone input page goes without (the account's unit, the current period). */
const PERIOD_FILTERS = ['Unit', 'Unit Pembangkit', 'Bulan', 'Tahun'];

/**
 * A labelled dropdown used by the operasi input toolbars (unit / mesin / bulan
 * / tahun). Shared so every input tab reads the same. On a phone input page
 * the unit / bulan / tahun filters render as a hidden marker; a wrapper left
 * with only such markers is hidden too (`[data-mobile-hidden]` in app.css).
 */
export function OperasiSelect({
    label,
    value,
    onChange,
    options,
    className = 'w-44',
    labelClassName = '',
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
    /** The trigger's width classes. */
    className?: string;
    /** Placement of the whole field in its grid, e.g. col-span-2 (no wrapper div, so a dropped filter leaves none behind). */
    labelClassName?: string;
}) {
    const simplified = useSimplifiedMobileInput();

    if (simplified && PERIOD_FILTERS.includes(label)) {
        return <span data-mobile-hidden hidden />;
    }

    return (
        <label className={`flex flex-col gap-1 text-[13px] ${labelClassName}`}>
            <span className="text-muted-foreground">{label}</span>
            <Select value={value || undefined} onValueChange={onChange}>
                <SelectTrigger className={`h-9 ${className}`}>
                    <SelectValue placeholder={`Pilih ${label.toLowerCase()}`} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </label>
    );
}

export const OPERASI_MONTHS = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];
