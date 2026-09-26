import type { ReactNode } from 'react';

export type MobileRecord = {
    key: string | number;
    title: ReactNode;
    /** Badges / status shown next to the title. */
    badge?: ReactNode;
    /** Label–value lines under the title; empty values are skipped. */
    meta?: [label: string, value: ReactNode][];
    /** Buttons (edit, delete …) at the end of the card. */
    actions?: ReactNode;
    onClick?: () => void;
};

/**
 * Phone layout of a list table (log entries, WO, receipts …): one card per
 * record with its key facts, instead of a wide table.
 */
export function MobileRecordList({ records, empty }: { records: MobileRecord[]; empty?: ReactNode }) {
    if (records.length === 0) {
        return <>{empty ?? <p className="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">Belum ada data.</p>}</>;
    }

    return (
        <ul className="flex flex-col gap-2">
            {records.map((record) => {
                const meta = (record.meta ?? []).filter(([, value]) => value !== null && value !== undefined && value !== '' && value !== '—');
                const body = (
                    <>
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0 text-[14px] font-medium text-foreground">{record.title}</div>
                            {record.badge && <div className="flex shrink-0 flex-wrap justify-end gap-1">{record.badge}</div>}
                        </div>
                        {meta.length > 0 && (
                            <dl className="grid grid-cols-2 gap-x-3 gap-y-1 text-[12px]">
                                {meta.map(([label, value]) => (
                                    <div key={label} className="min-w-0">
                                        <dt className="text-muted-foreground">{label}</dt>
                                        <dd className="truncate text-foreground">{value}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </>
                );

                return (
                    <li key={record.key} className="flex flex-col gap-2 rounded-xl border border-border bg-card p-3">
                        {record.onClick ? (
                            <button type="button" onClick={record.onClick} className="flex flex-col gap-2 text-left">
                                {body}
                            </button>
                        ) : (
                            body
                        )}
                        {record.actions && <div className="flex items-center justify-end gap-1 border-t border-border pt-2">{record.actions}</div>}
                    </li>
                );
            })}
        </ul>
    );
}
