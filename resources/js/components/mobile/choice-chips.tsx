import { cn } from '@/lib/utils';

/** A labelled choice chip for the phone input layouts (select-like options). */
export function ChoiceChips({
    options,
    value,
    onChange,
    disabled,
    tone,
    labels,
}: {
    options: string[];
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    /** Colour of the selected chip per option; defaults to primary. */
    tone?: (option: string) => string;
    /** Text shown for an option (e.g. "✓ Keluar oli" for "v"); defaults to the option. */
    labels?: Record<string, string>;
}) {
    return (
        <div className="flex flex-wrap gap-1.5">
            {options.map((option) => {
                const active = value === option;

                return (
                    <button
                        key={option}
                        type="button"
                        disabled={disabled}
                        onClick={() => onChange(active ? '' : option)}
                        className={cn(
                            'min-h-9 rounded-lg border px-3 text-[13px] font-medium transition active:scale-95 disabled:cursor-not-allowed disabled:opacity-60',
                            active
                                ? (tone?.(option) ??
                                      'border-primary bg-primary text-primary-foreground')
                                : 'border-border bg-background text-foreground',
                        )}
                    >
                        {labels?.[option] ?? option}
                    </button>
                );
            })}
        </div>
    );
}
