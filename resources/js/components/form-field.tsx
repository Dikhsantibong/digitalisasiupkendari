import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

/**
 * Label above the control, helper text below it, error last — the form pattern
 * used across the application.
 */
export function FormField({
    label,
    htmlFor,
    required = false,
    hint,
    error,
    children,
}: {
    label: string;
    htmlFor?: string;
    required?: boolean;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor={htmlFor} className="text-[13px] font-medium">
                {label}
                {required && (
                    <span
                        className="ml-0.5 text-destructive"
                        aria-hidden="true"
                    >
                        *
                    </span>
                )}
            </Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
    );
}
