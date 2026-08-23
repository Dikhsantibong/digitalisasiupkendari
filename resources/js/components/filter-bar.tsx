import { Search, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

/**
 * The toolbar that sits above a table: one search box, then filters, then a
 * reset when anything is applied.
 */
export function FilterBar({
    searchValue,
    onSearchChange,
    searchPlaceholder = 'Cari…',
    isFiltered,
    onReset,
    children,
}: {
    searchValue: string;
    onSearchChange: (value: string) => void;
    searchPlaceholder?: string;
    isFiltered: boolean;
    onReset: () => void;
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2 border-b border-border bg-secondary px-3 py-2.5 sm:flex-row sm:items-center">
            <div className="relative w-full sm:max-w-xs">
                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    value={searchValue}
                    onChange={(event) => onSearchChange(event.target.value)}
                    placeholder={searchPlaceholder}
                    aria-label={searchPlaceholder}
                    className="h-8 bg-card pl-8 text-[13px]"
                />
            </div>

            <div className="flex flex-wrap items-center gap-2">
                {children}

                {isFiltered && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onReset}
                        className="h-8 text-[13px]"
                    >
                        <X className="size-4" />
                        Reset
                    </Button>
                )}
            </div>
        </div>
    );
}

/**
 * A select used as a table filter, with an explicit "all" entry.
 */
export function FilterSelect({
    value,
    onChange,
    placeholder,
    allLabel,
    options,
}: {
    value: string | undefined;
    onChange: (value: string | undefined) => void;
    placeholder: string;
    allLabel: string;
    options: { value: string; label: string }[];
}) {
    return (
        <Select
            value={value ?? 'all'}
            onValueChange={(next) =>
                onChange(next === 'all' ? undefined : next)
            }
        >
            <SelectTrigger
                size="sm"
                className="h-8 min-w-[9rem] bg-card text-[13px]"
                aria-label={placeholder}
            >
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">{allLabel}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
