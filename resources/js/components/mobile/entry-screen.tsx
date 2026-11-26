import { ArrowLeft, ChevronRight, Save, Trash2 } from 'lucide-react';
import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/*
 * The phone entry-flow pieces that hold no page logic (split from
 * entry-flow.tsx so the row editor can use them without an import cycle):
 * the entry card, its status chip and the full-screen form.
 */

export function MobileEntryCard({
    title,
    subtitle,
    badge,
    meta,
    leading,
    onClick,
    footer,
}: {
    title: ReactNode;
    subtitle?: ReactNode;
    /** Status chip at the top right. */
    badge?: ReactNode;
    /** Label–value facts under the title (empty values are skipped). */
    meta?: [label: string, value: ReactNode][];
    /** Small square at the start (e.g. the row number). */
    leading?: ReactNode;
    onClick?: () => void;
    footer?: ReactNode;
}) {
    const facts = (meta ?? []).filter(
        ([, value]) =>
            value !== null &&
            value !== undefined &&
            value !== '' &&
            value !== '—',
    );
    const body = (
        <>
            <div className="flex items-start gap-3">
                {leading !== undefined && (
                    <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-[13px] font-bold text-primary">
                        {leading}
                    </span>
                )}
                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <p className="min-w-0 text-[14px] leading-snug font-semibold text-foreground">
                            {title}
                        </p>
                        {badge && <div className="shrink-0">{badge}</div>}
                    </div>
                    {subtitle && (
                        <p className="mt-0.5 line-clamp-2 text-[12.5px] text-muted-foreground">
                            {subtitle}
                        </p>
                    )}
                </div>
                {onClick && (
                    <ChevronRight className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                )}
            </div>
            {facts.length > 0 && (
                <dl className="grid grid-cols-2 gap-x-3 gap-y-1.5 border-t border-border/70 pt-2 text-[12px]">
                    {facts.map(([label, value]) => (
                        <div key={label} className="min-w-0">
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="truncate font-medium text-foreground">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
            )}
            {footer}
        </>
    );

    if (!onClick) {
        return (
            <div className="flex flex-col gap-2 rounded-xl border border-border bg-card p-3.5">
                {body}
            </div>
        );
    }

    return (
        <button
            type="button"
            onClick={onClick}
            className="flex w-full flex-col gap-2 rounded-xl border border-border bg-card p-3.5 text-left transition active:scale-[0.99] active:bg-muted/50"
        >
            {body}
        </button>
    );
}

/** A small status chip for MobileEntryCard. */
export function EntryBadge({
    children,
    tone = 'neutral',
}: {
    children: ReactNode;
    tone?: 'neutral' | 'open' | 'done' | 'danger' | 'info';
}) {
    const tones = {
        neutral: 'bg-muted text-muted-foreground',
        open: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
        done: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
        danger: 'bg-rose-500/15 text-rose-700 dark:text-rose-400',
        info: 'bg-primary/10 text-primary',
    };

    return (
        <span
            className={cn(
                'inline-flex rounded-md px-1.5 py-0.5 text-[10.5px] font-bold uppercase',
                tones[tone],
            )}
        >
            {children}
        </span>
    );
}

export function MobileFormScreen({
    title,
    subtitle,
    onBack,
    children,
    onSave,
    saving = false,
    saveDisabled = false,
    saveLabel = 'Simpan',
    formId,
    onDelete,
    deleteLabel = 'Hapus',
    canWrite = true,
    extraAction,
}: {
    title: ReactNode;
    subtitle?: ReactNode;

    /** Back to the list (the top-left arrow and the Kembali button). */
    onBack: () => void;
    children: ReactNode;
    /** Simpan: save, then the page returns to the list on success. */
    onSave?: () => void;
    saving?: boolean;
    saveDisabled?: boolean;
    saveLabel?: string;
    /** Instead of onSave: Simpan submits this <form id>. */
    formId?: string;
    onDelete?: () => void;
    deleteLabel?: string;
    canWrite?: boolean;
    /** A second action beside Simpan (e.g. Serahkan on the Lembar Mutasi); Kembali then becomes an icon. */
    extraAction?: ReactNode;
}) {
    // The list page behind must not scroll while the form is open.
    useEffect(() => {
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previous;
        };
    }, []);

    return (
        <div
            role="dialog"
            aria-modal="true"
            className="fixed inset-0 z-40 flex flex-col bg-background"
        >
            <header className="flex h-14 shrink-0 items-center gap-2 border-b border-border bg-background px-2 pt-[var(--impersonation-h,0px)]">
                <button
                    type="button"
                    onClick={onBack}
                    className="flex size-10 items-center justify-center rounded-full text-foreground active:bg-muted"
                    aria-label="Kembali ke daftar"
                >
                    <ArrowLeft className="size-5" />
                </button>
                <div className="min-w-0 flex-1">
                    <p className="truncate text-[15px] leading-tight font-semibold text-foreground">
                        {title}
                    </p>
                    {subtitle && (
                        <p className="truncate text-[11px] text-muted-foreground">
                            {subtitle}
                        </p>
                    )}
                </div>
                {canWrite && onDelete && (
                    <button
                        type="button"
                        onClick={onDelete}
                        className="flex size-10 items-center justify-center rounded-full text-destructive active:bg-muted"
                        aria-label={deleteLabel}
                        title={deleteLabel}
                    >
                        <Trash2 className="size-5" />
                    </button>
                )}
            </header>

            <div className="flex-1 overflow-y-auto overscroll-contain bg-muted/40 p-4 pb-28">
                <div className="flex min-w-0 flex-col gap-3 overflow-hidden rounded-xl border border-border bg-card p-3.5">
                    {children}
                </div>
            </div>

            <div
                className={cn(
                    'fixed inset-x-0 bottom-0 z-40 grid gap-2 border-t border-border bg-background/95 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur',
                    extraAction ? 'grid-cols-[auto_1fr_1fr]' : 'grid-cols-2',
                )}
            >
                <Button
                    size="lg"
                    variant="outline"
                    onClick={onBack}
                    aria-label="Kembali"
                    className={extraAction ? 'px-3' : undefined}
                >
                    <ArrowLeft className="size-4" />
                    {!extraAction && 'Kembali'}
                </Button>
                {canWrite && (onSave || formId) ? (
                    <Button
                        size="lg"
                        type={formId ? 'submit' : 'button'}
                        form={formId}
                        onClick={formId ? undefined : onSave}
                        disabled={saving || saveDisabled}
                    >
                        <Save className="size-4" />
                        {saving ? 'Menyimpan…' : saveLabel}
                    </Button>
                ) : (
                    <span />
                )}
                {extraAction}
            </div>
        </div>
    );
}
