import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Action buttons pinned to the bottom of the screen on the phone input
 * layouts (Simpan, Isi Data …). Pages using it keep bottom padding (pb-28)
 * so the last field is not hidden behind the bar.
 */
export function StickyActionBar({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <div
            className={cn(
                'fixed inset-x-0 bottom-0 z-30 grid auto-cols-fr grid-flow-col gap-2 border-t border-border bg-background/95 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur',
                className,
            )}
        >
            {children}
        </div>
    );
}
