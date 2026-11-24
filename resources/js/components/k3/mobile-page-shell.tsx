import { router } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, FileText } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    OPERASI_MONTHS,
    OperasiSelect,
} from '@/components/operasi/filter-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    useInMobileShell,
    useSimplifiedMobileInput,
} from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';
import type { IdName } from '@/types';

/**
 * Compact mobile header for K3 pages. Inside the mobile module shell, the
 * shell's top bar already displays the title and back arrow, so this returns
 * null to eliminate duplication. Outside the shell (e.g. Super Admin on a
 * phone), it shows a slim single-row back + title bar without static clutter.
 */
export function K3MobileHeader({
    title,
    backHref,
    backLabel = 'Kembali',
    right,
}: {
    title: string;
    backHref?: string;
    backLabel?: string;
    right?: ReactNode;
}) {
    const inShell = useInMobileShell();

    if (inShell) {
        return right ? (
            <div className="flex items-center justify-end gap-2">{right}</div>
        ) : null;
    }

    return (
        <div className="flex items-center gap-2">
            {backHref && (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => router.get(backHref)}
                    aria-label={backLabel}
                    className="size-8 shrink-0"
                >
                    <ArrowLeft className="size-4" />
                </Button>
            )}
            <h1 className="min-w-0 flex-1 truncate text-base font-bold text-foreground">
                {title}
            </h1>
            {right && (
                <div className="flex shrink-0 items-center gap-1.5">
                    {right}
                </div>
            )}
        </div>
    );
}

/**
 * Consistent 2-column mobile filter card for K3 pages. Uses full-width selects
 * so labels never clip at 390px and automatically omits locked period controls
 * when inside a simplified unit-scoped mobile shell.
 */
export function K3MobileFilterCard({
    unitId,
    units,
    onUnitChange,
    month,
    onMonthChange,
    year,
    years,
    onYearChange,
    unitLabel = 'Unit',
    monthLabel = 'Bulan',
    yearLabel = 'Tahun',
    extra,
    className,
}: {
    unitId?: number;
    units?: IdName[];
    onUnitChange?: (unitId: number) => void;
    month?: number;
    onMonthChange?: (month: number) => void;
    year?: number;
    years?: number[];
    onYearChange?: (year: number) => void;
    unitLabel?: string;
    monthLabel?: string;
    yearLabel?: string;
    extra?: ReactNode;
    className?: string;
}) {
    const simplified = useSimplifiedMobileInput();
    const showUnit =
        !simplified &&
        unitId !== undefined &&
        units !== undefined &&
        units.length > 0 &&
        onUnitChange !== undefined;
    const showMonth =
        !simplified && month !== undefined && onMonthChange !== undefined;
    const showYear =
        !simplified &&
        year !== undefined &&
        years !== undefined &&
        onYearChange !== undefined;

    if (!showUnit && !showMonth && !showYear && !extra) {
        return null;
    }

    const fullSpanUnit =
        showUnit &&
        ((!showMonth && !showYear && !extra) ||
            (showMonth && showYear && !extra));

    return (
        <div
            className={cn(
                'grid grid-cols-2 gap-2.5 rounded-xl border border-border bg-card p-3',
                className,
            )}
        >
            {showUnit && (
                <OperasiSelect
                    label={unitLabel}
                    value={String(unitId)}
                    onChange={(v) => onUnitChange(Number(v))}
                    options={units.map((u) => ({
                        value: String(u.id),
                        label: u.name,
                    }))}
                    className="w-full"
                    labelClassName={fullSpanUnit ? 'col-span-2' : undefined}
                />
            )}
            {showMonth && (
                <OperasiSelect
                    label={monthLabel}
                    value={String(month)}
                    onChange={(v) => onMonthChange(Number(v))}
                    options={OPERASI_MONTHS.map((label, idx) => ({
                        value: String(idx + 1),
                        label,
                    }))}
                    className="w-full"
                />
            )}
            {showYear && (
                <OperasiSelect
                    label={yearLabel}
                    value={String(year)}
                    onChange={(v) => onYearChange(Number(v))}
                    options={years.map((y) => ({
                        value: String(y),
                        label: String(y),
                    }))}
                    className="w-full"
                />
            )}
            {extra}
        </div>
    );
}

/**
 * Equal-width horizontal action strip for secondary mobile tools & exports
 * (Excel, PDF, Cetak, Reset). Ensures proportional widths across columns.
 */
export function K3MobileActionRow({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'grid auto-cols-fr grid-flow-col gap-2 [&>a]:block [&>a>button]:h-9 [&>a>button]:w-full [&>a>button]:justify-center [&>a>button]:text-xs [&>button]:h-9 [&>button]:w-full [&>button]:justify-center [&>button]:text-xs',
                className,
            )}
        >
            {children}
        </div>
    );
}

/**
 * Collapsible document metadata accordion (No. Dokumen, Revisi, Tanggal)
 * shared by K3 sheet pages on mobile — collapsed by default so field users
 * reach data inputs immediately.
 */
export function K3MobileMetaAccordion({
    title = 'Info Dokumen',
    noDokumen,
    revisi,
    tanggalDokumen,
    onNoDokumenChange,
    onRevisiChange,
    onTanggalDokumenChange,
    canWrite,
    extra,
}: {
    title?: string;
    noDokumen: string;
    revisi?: string;
    tanggalDokumen?: string;
    onNoDokumenChange: (value: string) => void;
    onRevisiChange?: (value: string) => void;
    onTanggalDokumenChange?: (value: string) => void;
    canWrite: boolean;
    extra?: ReactNode;
}) {
    const [open, setOpen] = useState(false);

    return (
        <div className="rounded-xl border border-border bg-card">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-center gap-2 p-3 text-left text-[13px] font-medium text-foreground"
            >
                <FileText className="size-4 shrink-0 text-primary" />
                <span className="shrink-0">{title}</span>
                <span className="min-w-0 flex-1 truncate text-right text-[12px] text-muted-foreground">
                    {noDokumen}
                </span>
                <ChevronDown
                    className={cn(
                        'size-4 shrink-0 text-muted-foreground transition-transform',
                        open && 'rotate-180',
                    )}
                />
            </button>
            {open && (
                <div className="grid grid-cols-2 gap-2.5 border-t border-border p-3">
                    <label
                        className={cn(
                            'flex flex-col gap-1 text-[12px] text-muted-foreground',
                            revisi === undefined && 'col-span-2',
                        )}
                    >
                        No. Dokumen
                        <Input
                            value={noDokumen}
                            onChange={(e) => onNoDokumenChange(e.target.value)}
                            disabled={!canWrite}
                            className="h-9 text-xs"
                        />
                    </label>
                    {revisi !== undefined && onRevisiChange && (
                        <label className="flex flex-col gap-1 text-[12px] text-muted-foreground">
                            Revisi
                            <Input
                                value={revisi}
                                onChange={(e) => onRevisiChange(e.target.value)}
                                disabled={!canWrite}
                                className="h-9 text-xs"
                            />
                        </label>
                    )}
                    {tanggalDokumen !== undefined && onTanggalDokumenChange && (
                        <label className="col-span-2 flex flex-col gap-1 text-[12px] text-muted-foreground">
                            Tanggal Dokumen
                            <Input
                                value={tanggalDokumen}
                                onChange={(e) =>
                                    onTanggalDokumenChange(e.target.value)
                                }
                                disabled={!canWrite}
                                className="h-9 text-xs"
                            />
                        </label>
                    )}
                    {extra}
                </div>
            )}
        </div>
    );
}
