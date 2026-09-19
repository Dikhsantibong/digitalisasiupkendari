import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';

/** Radix Select forbids an empty item value, so "kosongkan" uses this sentinel. */
const EMPTY = '__kosong__';

/**
 * A compact dropdown sized for a table cell in the PdM input grids. Keeps a
 * saved value that is no longer among the choices, and offers "—" to clear.
 */
export function PdmCellSelect({
    value,
    onChange,
    options,
    disabled = false,
    placeholder = '',
    className,
}: {
    value: string;
    onChange: (value: string) => void;
    options: string[];
    disabled?: boolean;
    placeholder?: string;
    className?: string;
}) {
    const choices = value !== '' && !options.includes(value) ? [value, ...options] : options;

    return (
        <Select value={value === '' ? EMPTY : value} onValueChange={(next) => onChange(next === EMPTY ? '' : next)} disabled={disabled}>
            <SelectTrigger
                size="sm"
                className={cn(
                    'h-7 w-full min-w-0 gap-1 rounded-none border-0 bg-transparent px-1 text-[11px] shadow-none focus-visible:ring-1 data-[size=sm]:h-7 dark:bg-transparent [&_svg:not([class*=size-])]:size-3',
                    className,
                )}
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={EMPTY} className="text-xs text-muted-foreground">
                    —
                </SelectItem>
                {choices.map((choice) => (
                    <SelectItem key={choice} value={choice} className="text-xs">
                        {choice}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
