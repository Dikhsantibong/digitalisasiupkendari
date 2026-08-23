/**
 * A single figure with its label and optional supporting line. Deliberately
 * plain: no icons, gradients, or shadows.
 */
export function SummaryCard({
    label,
    value,
    hint,
    unit,
}: {
    label: string;
    value: string | number;
    hint?: string;
    unit?: string;
}) {
    return (
        <div className="rounded-md border border-border bg-card p-4">
            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <p className="mt-2 text-2xl font-semibold text-foreground tabular-nums">
                {value}
                {unit && (
                    <span className="ml-1 text-sm font-normal text-muted-foreground">
                        {unit}
                    </span>
                )}
            </p>
            {hint && (
                <p className="mt-1 text-[13px] text-muted-foreground">{hint}</p>
            )}
        </div>
    );
}
