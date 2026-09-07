import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/**
 * A labelled dropdown used by the operasi input toolbars (unit / mesin / bulan
 * / tahun). Shared so every input tab reads the same.
 */
export function OperasiSelect({
    label,
    value,
    onChange,
    options,
    className = 'w-44',
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
    className?: string;
}) {
    return (
        <label className="flex flex-col gap-1 text-[13px]">
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
