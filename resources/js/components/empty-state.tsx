import type { ReactNode } from 'react';

/**
 * Shown in place of a table body when there is nothing to display. Says what is
 * missing and offers the next step, without illustrations.
 */
export function EmptyState({
    title,
    description,
    action,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center">
            <p className="text-sm font-medium text-foreground">{title}</p>
            {description && (
                <p className="max-w-md text-[13px] text-muted-foreground">
                    {description}
                </p>
            )}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
