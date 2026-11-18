import {
    Eye,
    Keyboard,
    LoaderCircle,
    PencilLine,
    RefreshCw,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { ReactNode, TextareaHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Building blocks of the visual Instruksi Kerja editors (K3 Dokumen IK and the
 * shared Pemeliharaan / Operasi IK): the document is edited in place on one A4
 * portrait sheet that looks like the print.
 */

/** Editable text that looks like the printed document: no frame, a soft highlight on hover and focus. */
export const EDITABLE =
    'w-full resize-none overflow-hidden rounded-sm bg-transparent px-1 outline-none transition-colors placeholder:text-neutral-400 placeholder:italic hover:bg-sky-50 focus:bg-yellow-50 focus:ring-1 focus:ring-sky-400 disabled:text-inherit disabled:hover:bg-transparent';

/** The dashed frame of a part while it is hovered / edited. */
export const PART_FRAME =
    'hover:outline hover:outline-1 hover:outline-offset-4 hover:outline-dashed hover:outline-neutral-300 focus-within:outline focus-within:outline-1 focus-within:outline-offset-4 focus-within:outline-dashed focus-within:outline-sky-300';

/** A part's floating tools: always visible on phones, on hover / focus on wider screens. */
export const PART_TOOLS =
    'flex shrink-0 flex-wrap items-center gap-0.5 rounded-md border border-neutral-200 bg-white p-0.5 text-neutral-700 shadow-sm transition-opacity sm:opacity-0 sm:group-focus-within/section:opacity-100 sm:group-hover/section:opacity-100';

/**
 * One A4 portrait sheet (210 × 297 mm) with the print margins, on a grey desk.
 * `padding` is the sheet margin on wider screens (e.g. "sm:p-[14mm]").
 */
export function A4Sheet({
    padding,
    children,
}: {
    padding: string;
    children: ReactNode;
}) {
    return (
        <div className="overflow-x-auto rounded-md bg-neutral-200 p-2 sm:p-6 dark:bg-neutral-800">
            <div
                className={cn(
                    'mx-auto flex w-full max-w-[210mm] flex-col bg-white p-3 text-black shadow-[0_2px_10px_rgba(0,0,0,0.25)] sm:min-h-[297mm]',
                    padding,
                )}
            >
                {children}
            </div>
        </div>
    );
}

type AutoTextProps = Omit<
    TextareaHTMLAttributes<HTMLTextAreaElement>,
    'value' | 'onChange'
> & {
    value: string;
    onValue: (value: string) => void;
    ref?: (element: HTMLTextAreaElement | null) => void;
};

/** A one-row textarea that grows with its text (wraps like the printed line). */
export function AutoText({
    value,
    onValue,
    ref,
    className,
    ...rest
}: AutoTextProps) {
    const local = useRef<HTMLTextAreaElement | null>(null);

    useLayoutEffect(() => {
        const element = local.current;

        if (element) {
            element.style.height = '0px';
            element.style.height = `${element.scrollHeight}px`;
        }
    }, [value]);

    return (
        <textarea
            {...rest}
            ref={(element) => {
                local.current = element;
                ref?.(element);
            }}
            rows={1}
            value={value}
            onChange={(event) => onValue(event.target.value)}
            className={cn('block', className)}
        />
    );
}

export type FocusTarget = { id: string; at: 'start' | 'end' };

/**
 * Field registry + caret moves: `register(id)` as the field's ref, `focusNow`
 * for a rendered field, `focusAfterRender` for a field the current change
 * creates or refills.
 */
export function useFieldFocus() {
    const fields = useRef(
        new Map<string, HTMLTextAreaElement | HTMLInputElement>(),
    );
    const pending = useRef<FocusTarget | null>(null);

    const focusNow = ({ id, at }: FocusTarget) => {
        const field = fields.current.get(id);

        if (field) {
            field.focus();
            const position = at === 'end' ? field.value.length : 0;
            field.setSelectionRange(position, position);
        }
    };

    useEffect(() => {
        if (pending.current) {
            focusNow(pending.current);
            pending.current = null;
        }
    });

    return {
        register:
            (id: string) =>
            (element: HTMLTextAreaElement | HTMLInputElement | null) => {
                if (element) {
                    fields.current.set(id, element);
                } else {
                    fields.current.delete(id);
                }
            },
        focusNow,
        focusAfterRender: (target: FocusTarget) => {
            pending.current = target;
        },
    };
}

/** Ctrl/Cmd + S runs the latest `save`. */
export function useSaveShortcut(save: () => void) {
    const saveRef = useRef(save);

    useEffect(() => {
        saveRef.current = save;
    });

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === 's'
            ) {
                event.preventDefault();
                saveRef.current();
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, []);
}

/** Closing the tab with unsaved changes asks first. */
export function useUnsavedWarning(dirty: boolean) {
    useEffect(() => {
        if (!dirty) {
            return;
        }

        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);
}

export function ToolButton({
    label,
    onClick,
    disabled = false,
    danger = false,
    active = false,
    children,
}: {
    label: string;
    onClick: () => void;
    disabled?: boolean;
    danger?: boolean;
    active?: boolean;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={label}
            aria-label={label}
            aria-pressed={active || undefined}
            className={cn(
                'flex h-6 min-w-6 items-center justify-center rounded px-1 text-[12px] font-semibold disabled:opacity-30',
                active
                    ? 'bg-sky-600 text-white'
                    : danger
                      ? 'text-neutral-600 hover:bg-red-50 hover:text-red-600'
                      : 'text-neutral-600 hover:bg-neutral-100',
            )}
        >
            {children}
        </button>
    );
}

export function ToolDivider() {
    return <span className="mx-0.5 h-4 w-px bg-neutral-200" />;
}

/** The keyboard tips above the sheet (desktop). */
export function EditorTips({ extra }: { extra?: ReactNode }) {
    return (
        <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
            <Keyboard className="size-3.5" />
            <span>Klik teks pada dokumen untuk mengubah.</span>
            <span>
                <Kbd>Enter</Kbd> poin baru
            </span>
            <span>
                <Kbd>Backspace</Kbd> di awal poin = gabung / hapus poin
            </span>
            <span>Tempel beberapa baris = beberapa poin</span>
            {extra}
            <span>
                <Kbd>Ctrl</Kbd>+<Kbd>S</Kbd> simpan
            </span>
        </p>
    );
}

export function Kbd({ children }: { children: ReactNode }) {
    return (
        <kbd className="rounded border border-border bg-muted px-1 font-mono text-[10px] text-foreground">
            {children}
        </kbd>
    );
}

export type EditorView = 'edit' | 'pdf';

/** "Edit Dokumen" / "Pratinjau PDF" switch above the sheet. */
export function EditorViewTabs({
    view,
    onChange,
}: {
    view: EditorView;
    onChange: (view: EditorView) => void;
}) {
    return (
        <div
            className="flex w-full gap-1 rounded-md bg-muted p-1 sm:w-fit"
            role="tablist"
        >
            {(
                [
                    ['edit', 'Edit Dokumen', PencilLine],
                    ['pdf', 'Pratinjau PDF', Eye],
                ] as const
            ).map(([key, label, Icon]) => (
                <button
                    key={key}
                    type="button"
                    role="tab"
                    aria-selected={view === key}
                    onClick={() => onChange(key)}
                    className={cn(
                        'flex flex-1 items-center justify-center gap-1.5 rounded px-4 py-1.5 text-sm font-medium transition sm:flex-none',
                        view === key
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    <Icon className="size-4" />
                    {label}
                </button>
            ))}
        </div>
    );
}

/** The Laravel XSRF cookie for a fetch POST (Inertia sends it the same way). */
const xsrfToken = (): string =>
    decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );

type PreviewResult = { key: string; url: string | null; error: string | null };

/**
 * The real PDF (rendered by the server, the same view as the print) shown in
 * the browser's PDF viewer. `previewUrl` + `payload`: the document as
 * currently edited (POST, not saved). `savedUrl`: the stored PDF (GET), used
 * when nothing changed or the account may not post (read-only).
 */
export function PdfPreview({
    previewUrl,
    payload,
    savedUrl,
    useSaved,
}: {
    previewUrl: string;
    payload: unknown;
    savedUrl: string | null;
    useSaved: boolean;
}) {
    const body = JSON.stringify(payload);
    const key = useSaved && savedUrl ? `saved:${savedUrl}` : `draft:${body}`;
    const [reload, setReload] = useState(0);
    const [result, setResult] = useState<PreviewResult | null>(null);
    const requestKey = `${key}#${reload}`;

    useEffect(() => {
        if (useSaved && savedUrl) {
            return;
        }

        let cancelled = false;
        let objectUrl: string | null = null;

        void (async () => {
            try {
                const response = await fetch(previewUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body,
                });

                if (cancelled) {
                    return;
                }

                if (!response.ok) {
                    const data =
                        response.status === 422
                            ? ((await response.json()) as { message?: string })
                            : null;
                    setResult({
                        key: requestKey,
                        url: null,
                        error:
                            data?.message ??
                            `Pratinjau gagal dimuat (HTTP ${response.status}).`,
                    });

                    return;
                }

                objectUrl = URL.createObjectURL(await response.blob());

                if (!cancelled) {
                    setResult({ key: requestKey, url: objectUrl, error: null });
                }
            } catch {
                if (!cancelled) {
                    setResult({
                        key: requestKey,
                        url: null,
                        error: 'Pratinjau gagal dimuat. Periksa koneksi lalu coba lagi.',
                    });
                }
            }
        })();

        return () => {
            cancelled = true;

            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
            }
        };
    }, [previewUrl, body, useSaved, savedUrl, requestKey]);

    const src =
        useSaved && savedUrl
            ? savedUrl
            : result?.key === requestKey
              ? result.url
              : null;
    const error =
        useSaved && savedUrl
            ? null
            : result?.key === requestKey
              ? result.error
              : null;
    const loading = !src && !error;

    return (
        <div className="flex flex-col gap-2">
            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                <span>
                    {useSaved && savedUrl
                        ? 'PDF dokumen tersimpan — sama dengan hasil cetak.'
                        : 'Pratinjau dari isi yang sedang diedit (belum disimpan) — sama dengan hasil cetak.'}
                </span>
                {!(useSaved && savedUrl) && (
                    <button
                        type="button"
                        onClick={() => setReload((n) => n + 1)}
                        className="inline-flex items-center gap-1 rounded px-1.5 py-0.5 hover:bg-muted hover:text-foreground"
                    >
                        <RefreshCw className="size-3.5" />
                        Muat ulang
                    </button>
                )}
            </div>
            <div className="relative h-[80vh] min-h-[480px] overflow-hidden rounded-md border border-border bg-neutral-200 dark:bg-neutral-800">
                {src && (
                    <iframe
                        key={src}
                        src={src}
                        title="Pratinjau PDF"
                        className="size-full"
                    />
                )}
                {loading && (
                    <div className="absolute inset-0 flex items-center justify-center gap-2 text-sm text-muted-foreground">
                        <LoaderCircle className="size-5 animate-spin" />
                        Menyiapkan PDF…
                    </div>
                )}
                {error && (
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 p-6 text-center text-sm text-red-700 dark:text-red-300">
                        <TriangleAlert className="size-6" />
                        {error}
                    </div>
                )}
            </div>
        </div>
    );
}
