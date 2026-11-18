import { useSimplifiedMobileInput } from '@/hooks/use-mobile-module';
import { cn } from '@/lib/utils';

export type SummaryCardPalette = {
    card?: string;
    label?: string;
    value?: string;
    unit?: string;
    hint?: string;
};

/**
 * A single figure with its label and optional supporting line. Deliberately
 * plain: no icons, gradients, or shadows.
 */
export function SummaryCard({
    label,
    value,
    hint,
    unit,
    palette,
}: {
    label: string;
    value: string | number;
    hint?: string;
    unit?: string;
    palette?: SummaryCardPalette;
}) {
    // Phone input pages go without summary cards (a grid left empty is hidden by app.css).
    if (useSimplifiedMobileInput()) {
        return <span data-mobile-hidden hidden />;
    }

    return (
        <div
            className={cn(
                'rounded-md border border-border bg-card p-4',
                palette?.card,
            )}
        >
            <p
                className={cn(
                    'text-xs font-semibold tracking-wide text-muted-foreground uppercase',
                    palette?.label,
                )}
            >
                {label}
            </p>
            <p
                className={cn(
                    'mt-2 text-2xl font-semibold text-foreground tabular-nums',
                    palette?.value,
                )}
            >
                {value}
                {unit && (
                    <span
                        className={cn(
                            'ml-1 text-sm font-normal text-muted-foreground',
                            palette?.unit,
                        )}
                    >
                        {unit}
                    </span>
                )}
            </p>
            {hint && (
                <p
                    className={cn(
                        'mt-1 text-[13px] text-muted-foreground',
                        palette?.hint,
                    )}
                >
                    {hint}
                </p>
            )}
        </div>
    );
}
