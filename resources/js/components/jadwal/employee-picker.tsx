import { Search, UserCheck, Users, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export type EmployeeOption = {
    id: number;
    name: string;
    position?: string | null;
};

type Props = {
    employees?: EmployeeOption[];
    employeeId: number | null | undefined;
    labelText?: string | null;
    onSelect: (employeeId: number | null, employeeName: string) => void;
    disabled?: boolean;
    variant?: 'icon' | 'badge';
    placeholder?: string;
    className?: string;
};

/**
 * Reusable unit-employee PIC picker for cross-module jadwal & time-frame sheets.
 * Supports two lightweight display variants so print/PDF column counts remain unchanged:
 * - `icon`: compact button beside a text/PIC input (Logistik, K3 Time Frame).
 * - `badge`: subtle `.no-print` chip below description text (PdM, K3 Patrol/Rutin, HAR Meeting).
 */
export function EmployeePicPicker({
    employees = [],
    employeeId,
    labelText,
    onSelect,
    disabled = false,
    variant = 'badge',
    placeholder = 'Pilih PIC Pegawai',
    className,
}: Props) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const selected = useMemo(
        () =>
            employeeId != null
                ? employees.find((e) => e.id === employeeId) ?? null
                : null,
        [employees, employeeId],
    );

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) {
            return employees;
        }

        return employees.filter(
            (e) =>
                e.name.toLowerCase().includes(needle) ||
                (e.position ?? '').toLowerCase().includes(needle),
        );
    }, [employees, query]);

    if ((employees.length === 0 || disabled) && !selected) {
        return null;
    }

    const displayName = selected?.name ?? (labelText?.trim() || null);

    return (
        <DropdownMenu
            open={open}
            onOpenChange={(next: boolean) => {
                setOpen(next);
                if (!next) {
                    setQuery('');
                }
            }}
        >
            <DropdownMenuTrigger asChild>
                {variant === 'icon' ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        disabled={disabled}
                        title={
                            selected
                                ? `PIC tertaut: ${selected.name} (klik ubah)`
                                : 'Pilih pegawai penanggung jawab'
                        }
                        className={cn(
                            'no-print size-7 shrink-0 rounded-none border-l border-border/50 px-0',
                            selected
                                ? 'text-emerald-600 hover:text-emerald-700 dark:text-emerald-400'
                                : 'text-muted-foreground hover:text-foreground',
                            className,
                        )}
                    >
                        {selected ? (
                            <UserCheck className="size-3.5" />
                        ) : (
                            <Users className="size-3.5" />
                        )}
                    </Button>
                ) : (
                    <button
                        type="button"
                        disabled={disabled}
                        title="Tautkan pegawai penanggung jawab untuk notifikasi akun langsung"
                        className={cn(
                            'no-print mt-1 inline-flex max-w-full items-center gap-1 rounded px-1.5 py-0.5 text-[10.5px] font-medium transition disabled:cursor-default disabled:opacity-70',
                            selected
                                ? 'bg-emerald-500/15 text-emerald-800 hover:bg-emerald-500/25 dark:text-emerald-300'
                                : 'bg-muted/80 text-muted-foreground hover:bg-muted hover:text-foreground',
                            className,
                        )}
                    >
                        {selected ? (
                            <UserCheck className="size-3 shrink-0" />
                        ) : (
                            <Users className="size-3 shrink-0" />
                        )}
                        <span className="truncate">
                            {selected ? `PIC: ${displayName}` : placeholder}
                        </span>
                    </button>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="w-72 p-2"
                collisionPadding={8}
            >
                <div className="flex flex-col gap-2">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-2.5 left-2.5 size-3.5 text-muted-foreground" />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={(e) => e.stopPropagation()}
                            placeholder="Cari nama atau jabatan..."
                            className="h-8 pl-8 text-xs"
                            autoFocus
                        />
                    </div>
                    <div className="max-h-56 overflow-y-auto">
                        {filtered.length === 0 ? (
                            <p className="py-3 text-center text-xs text-muted-foreground">
                                Pegawai tidak ditemukan.
                            </p>
                        ) : (
                            <div className="flex flex-col gap-0.5">
                                {filtered.map((emp) => {
                                    const active = emp.id === employeeId;
                                    return (
                                        <button
                                            key={emp.id}
                                            type="button"
                                            onClick={() => {
                                                onSelect(emp.id, emp.name);
                                                setOpen(false);
                                            }}
                                            className={cn(
                                                'flex w-full flex-col items-start rounded-md px-2 py-1.5 text-left text-xs transition hover:bg-muted',
                                                active &&
                                                    'bg-primary/10 font-semibold text-primary',
                                            )}
                                        >
                                            <span className="truncate font-medium">
                                                {emp.name}
                                            </span>
                                            {emp.position && (
                                                <span className="truncate text-[10.5px] text-muted-foreground">
                                                    {emp.position}
                                                </span>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                    {selected && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                onSelect(null, '');
                                setOpen(false);
                            }}
                            className="h-7 justify-start gap-1.5 text-xs text-muted-foreground hover:text-destructive"
                        >
                            <X className="size-3.5" />
                            Hapus tautan PIC
                        </Button>
                    )}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
