import type { InputHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Shared cells of the Akses 2 — Pengusahaan K3 input sheets (APAR/APAB, APAT,
 * Hydrant …): table-cell dropdowns and inputs with one readable font size, and
 * the DD/MM/YYYY ↔ date-picker conversion the sheets store dates in.
 */

/** Tailwind classes of a value judged good / not good in a condition column. */
export const TONE_GOOD = 'text-emerald-700 dark:text-emerald-400';
export const TONE_BAD = 'font-semibold text-destructive';

/** Chip colours for the phone layout (MobileRowEditor `optionTone`). */
export const chipTone = (good: string[]) => (option: string) =>
    good.includes(option.toLowerCase())
        ? 'border-emerald-600 bg-emerald-600 text-white'
        : 'border-destructive bg-destructive text-white';

/** Options of a column, plus the saved value when it is not one of them (older free-text data stays visible). */
export function withCurrent(options: string[], value: string): string[] {
    return value && !options.includes(value) ? [...options, value] : options;
}

/** "5/8/2026" or "05/08/2026" → "2026-08-05" (for <input type="date">); '' when it cannot be read. */
export function dmyToIso(value: string): string {
    const match = (value ?? '').trim().match(/^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$/);

    if (!match) {
        return '';
    }

    return `${match[3]}-${match[2].padStart(2, '0')}-${match[1].padStart(2, '0')}`;
}

/** "2026-08-05" → "05/08/2026", the format the sheets store and print. */
export function isoToDmy(value: string): string {
    const match = (value ?? '').match(/^(\d{4})-(\d{2})-(\d{2})$/);

    return match ? `${match[3]}/${match[2]}/${match[1]}` : value;
}

const CELL = 'w-full rounded-sm bg-transparent px-1.5 py-1 text-sm focus:bg-background focus:outline-none focus:ring-1 focus:ring-primary';

/** A dropdown inside a sheet cell; the chosen value is coloured good / not good. */
export function CellSelect({
    value,
    options,
    good,
    onChange,
    ariaLabel,
    className,
}: {
    value: string;
    options: string[];
    /** Values shown in green; any other value is shown in red. Omit for a neutral column. */
    good?: string[];
    onChange: (value: string) => void;
    ariaLabel: string;
    className?: string;
}) {
    const tone = good === undefined ? '' : good.includes((value ?? '').toLowerCase()) ? TONE_GOOD : TONE_BAD;

    return (
        <select
            value={value ?? ''}
            onChange={(e) => onChange(e.target.value)}
            aria-label={ariaLabel}
            className={cn(CELL, 'cursor-pointer text-center', tone, className)}
        >
            {withCurrent(options, value).map((option) => (
                <option key={option} value={option} className="text-foreground">
                    {option}
                </option>
            ))}
        </select>
    );
}

/** A text / number input inside a sheet cell. */
export function CellInput({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
    return <input {...props} className={cn(CELL, className)} />;
}

/**
 * A date cell: a date picker when the stored DD/MM/YYYY value can be read (or
 * is empty), otherwise the raw text so an unusual value can still be fixed.
 */
export function CellDate({
    value,
    onChange,
    ariaLabel,
    className,
}: {
    value: string;
    onChange: (value: string) => void;
    ariaLabel: string;
    className?: string;
}) {
    const iso = dmyToIso(value);

    if (value && !iso) {
        return <CellInput value={value} onChange={(e) => onChange(e.target.value)} placeholder="DD/MM/YYYY" aria-label={ariaLabel} className={cn('text-center', className)} />;
    }

    return (
        <input
            type="date"
            value={iso}
            onChange={(e) => onChange(isoToDmy(e.target.value))}
            aria-label={ariaLabel}
            className={cn(CELL, 'text-center', className)}
        />
    );
}

/** Print rules shared by the sheets: dropdowns & date pickers print as plain text. */
export const PRINT_FORM_CONTROLS = `
@media print {
    select, input[type="date"] { appearance: none !important; -webkit-appearance: none !important; border: none !important; background: transparent !important; padding: 0 !important; font-size: inherit !important; color: #000 !important; }
    input[type="date"]::-webkit-calendar-picker-indicator { display: none !important; }
    input, select, textarea { font-size: inherit !important; }
}
`;

/** Condition options per sheet column (lower-case where the sheet stores lower-case). */
export const K3_OPTIONS = {
    apar: {
        jenis: ['Powder', 'CO2', 'Foam', 'Gas Cair', 'Air'],
        tabung: ['baik', 'penyok', 'berkarat', 'rusak'],
        nozzle: ['baik', 'retak', 'tersumbat', 'rusak'],
        tekanan: ['ok', 'kurang', 'lebih'],
        pin: ['baik', 'hilang', 'rusak'],
        good: ['baik', 'ok'],
        merk: ['Fire Venom', 'Chubb', 'Yamato', 'Servvo', 'Appron', 'Gunnebo', 'Tonata'],
        berat: ['1', '2', '3', '3.5', '4.5', '5', '6', '9', '12', '25', '50', '68', '75'],
    },
    apat: {
        kondisi: ['Baik', 'Kurang', 'Rusak', 'Tidak Ada'],
        good: ['baik'],
        alat: ['Karung Goni', 'Pasir', 'Ember', 'Sekop', 'Drum Air', 'Gantol / Pengait', 'Kapak', 'Selimut Api'],
    },
    hydrant: {
        hose: ['Normal', 'Bocor', 'Rusak', 'Tidak Ada'],
        nozzle: ['Normal', 'Rusak', 'Tidak Ada'],
        box: ['Baik', 'Kotor', 'Rusak'],
        tekanan: ['Baik', 'Kurang', 'Tidak Ada'],
        good: ['normal', 'baik'],
    },
};
