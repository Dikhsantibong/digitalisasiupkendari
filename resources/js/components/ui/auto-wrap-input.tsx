import type { KeyboardEvent } from 'react';
import { cn } from '@/lib/utils';

type AutoWrapCellInputProps = {
    value: string;
    onChange?: (value: string) => void;
    onBlur?: () => void;
    placeholder?: string;
    readOnly?: boolean;
    disabled?: boolean;
    emptyFallback?: string;
    className?: string;
    wrapperClassName?: string;
    ariaLabel?: string;
    allowNewlines?: boolean;
};

/**
 * Auto-wrapping table cell text editor backed by CSS grid mirroring so text
 * wraps onto new lines when it reaches the column edge instead of horizontally
 * clipping inside a single-line `<input>`.
 */
export function AutoWrapCellInput({
    value,
    onChange,
    onBlur,
    placeholder,
    readOnly = false,
    disabled = false,
    emptyFallback = '',
    className,
    wrapperClassName,
    ariaLabel,
    allowNewlines = false,
}: AutoWrapCellInputProps) {
    const safeValue = value ?? '';
    const sharedTextClasses = cn(
        'px-1.5 py-0.5 text-xs leading-snug whitespace-pre-wrap [overflow-wrap:anywhere]',
        className,
    );

    if (readOnly) {
        return (
            <span className={cn('block', sharedTextClasses)}>
                {safeValue || emptyFallback}
            </span>
        );
    }

    const mirrorText = safeValue
        ? safeValue.endsWith('\n')
            ? `${safeValue} `
            : safeValue
        : ' ';

    const handleKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
        if (!allowNewlines && e.key === 'Enter') {
            e.preventDefault();
        }
    };

    return (
        <div className={cn('grid w-full min-w-0', wrapperClassName)}>
            <span
                aria-hidden="true"
                className={cn(
                    'invisible col-start-1 row-start-1 min-w-0 select-none print:visible',
                    sharedTextClasses,
                )}
            >
                {mirrorText}
            </span>
            <textarea
                rows={1}
                value={safeValue}
                placeholder={placeholder}
                disabled={disabled}
                aria-label={ariaLabel}
                onChange={(e) => {
                    const next = allowNewlines
                        ? e.target.value
                        : e.target.value.replace(/[\r\n]+/g, ' ');
                    onChange?.(next);
                }}
                onBlur={onBlur}
                onKeyDown={handleKeyDown}
                className={cn(
                    'col-start-1 row-start-1 min-h-0 w-full min-w-0 resize-none overflow-hidden rounded-none border-0 bg-transparent text-foreground focus:rounded focus:bg-background focus:ring-1 focus:ring-primary focus:outline-none disabled:cursor-default disabled:opacity-100 print:hidden',
                    sharedTextClasses,
                )}
            />
        </div>
    );
}
