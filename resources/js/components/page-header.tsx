import type { ReactNode } from 'react';
import { useInMobileShell, useSimplifiedMobileInput } from '@/hooks/use-mobile-module';

/**
 * The standard page opening: title, optional description, and at most one
 * primary action on the right. Inside a mobile module shell the top bar already
 * carries the title, so only the description and actions are shown — and on a
 * phone input page only the actions (the user fills it straight away).
 */
export function PageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
}) {
    const inMobileShell = useInMobileShell();
    const simplified = useSimplifiedMobileInput();

    if (simplified) {
        return actions ? <div className="flex flex-col gap-2">{actions}</div> : <span data-mobile-hidden hidden />;
    }

    if (inMobileShell) {
        return (
            <div className="flex flex-col gap-3">
                {description && (
                    <p className="text-[12.5px] text-muted-foreground">
                        {description}
                    </p>
                )}
                {actions && (
                    <div className="flex flex-col gap-2">{actions}</div>
                )}
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="flex flex-col gap-1">
                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                    {title}
                </h1>
                {description && (
                    <p className="text-[13px] text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
