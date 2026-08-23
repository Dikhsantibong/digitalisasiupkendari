import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

/**
 * Range summary plus previous/next controls for a paginated table.
 */
export function DataPagination<T>({ meta }: { meta: Paginated<T> }) {
    const previous = meta.links.at(0)?.url ?? null;
    const next = meta.links.at(-1)?.url ?? null;

    if (meta.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-col gap-2 border-t border-border px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-[13px] text-muted-foreground">
                Menampilkan{' '}
                <span className="font-medium text-foreground tabular-nums">
                    {meta.from ?? 0}–{meta.to ?? 0}
                </span>{' '}
                dari{' '}
                <span className="font-medium text-foreground tabular-nums">
                    {meta.total}
                </span>{' '}
                data
            </p>

            <div className="flex items-center gap-2">
                <span className="text-[13px] text-muted-foreground tabular-nums">
                    Hal. {meta.current_page} / {meta.last_page}
                </span>
                <PageLink href={previous} label="Sebelumnya">
                    <ChevronLeft className="size-4" />
                </PageLink>
                <PageLink href={next} label="Berikutnya">
                    <ChevronRight className="size-4" />
                </PageLink>
            </div>
        </div>
    );
}

function PageLink({
    href,
    label,
    children,
}: {
    href: string | null;
    label: string;
    children: React.ReactNode;
}) {
    const className = cn(
        'inline-flex size-8 items-center justify-center rounded-md border border-border transition-colors',
        href
            ? 'bg-card text-foreground hover:bg-secondary'
            : 'pointer-events-none text-muted-foreground/50 opacity-50',
    );

    if (!href) {
        return (
            <span className={className} aria-disabled="true" aria-label={label}>
                {children}
            </span>
        );
    }

    return (
        <Link
            href={href}
            className={className}
            aria-label={label}
            preserveScroll
        >
            {children}
        </Link>
    );
}
