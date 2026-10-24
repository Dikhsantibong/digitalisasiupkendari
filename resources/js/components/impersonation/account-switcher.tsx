import { router, usePage } from '@inertiajs/react';
import { KeyRound, Loader2, Search, UserRound, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { onOpenAccountSwitcher } from '@/lib/impersonation';
import type { SwitchableUser } from '@/lib/impersonation';
import { cn } from '@/lib/utils';
import impersonation from '@/routes/admin/impersonation';
import type { Auth } from '@/types';

type Chosen = Pick<SwitchableUser, 'id' | 'name' | 'email'>;

/**
 * Pindah Akun (Super Admin): search an account and work as that user. Opened
 * from the user menu, the Kelola Pengguna list or Ctrl+Shift+K. The server
 * decides everything (who may switch, to whom, the password) — this only asks.
 */
export function AccountSwitcher() {
    const auth = usePage().props.auth as Auth | undefined;
    const enabled = auth?.canImpersonate ?? false;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [users, setUsers] = useState<SwitchableUser[]>([]);
    const [loading, setLoading] = useState(false);
    const [passwordConfirmed, setPasswordConfirmed] = useState(false);
    const [chosen, setChosen] = useState<Chosen | null>(null);
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const request = useRef(0);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const offOpen = onOpenAccountSwitcher((user) => {
            setChosen(user);
            setPassword('');
            setError(null);
            setOpen(true);
        });
        const onKey = (event: KeyboardEvent) => {
            if (
                (event.ctrlKey || event.metaKey) &&
                event.shiftKey &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setChosen(null);
                setError(null);
                setOpen(true);
            }
        };
        window.addEventListener('keydown', onKey);

        return () => {
            offOpen();
            window.removeEventListener('keydown', onKey);
        };
    }, [enabled]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const id = ++request.current;
        const timer = window.setTimeout(async () => {
            setLoading(true);

            try {
                const response = await fetch(
                    impersonation.users({ query: { q: query } }).url,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    },
                );
                const data = (await response.json()) as {
                    users?: SwitchableUser[];
                    password_confirmed?: boolean;
                };

                if (id === request.current) {
                    setUsers(data.users ?? []);
                    setPasswordConfirmed(Boolean(data.password_confirmed));
                }
            } catch {
                if (id === request.current) {
                    setUsers([]);
                }
            } finally {
                if (id === request.current) {
                    setLoading(false);
                }
            }
        }, 250);

        return () => window.clearTimeout(timer);
    }, [open, query]);

    if (!enabled) {
        return null;
    }

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!chosen) {
            return;
        }

        setProcessing(true);
        setError(null);
        router.post(
            impersonation.start(chosen.id).url,
            passwordConfirmed ? {} : { password },
            {
                onError: (errors) =>
                    setError(
                        errors.password ??
                            Object.values(errors)[0] ??
                            'Gagal pindah akun.',
                    ),
                onSuccess: () => setOpen(false),
                onFinish: () => {
                    setProcessing(false);
                    setPassword('');
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="max-w-lg">
                <DialogTitle className="flex items-center gap-2">
                    <Users className="size-5 text-primary" />
                    Pindah Akun
                </DialogTitle>
                <DialogDescription>
                    Masuk sebagai pengguna lain untuk melihat dan menguji
                    aplikasi dari sisi mereka. Aktivitas tetap tercatat atas
                    nama Anda, sesi berakhir otomatis setelah 60 menit, dan
                    pengaturan akun mereka (kata sandi, 2FA, profil) tidak dapat
                    diubah.
                </DialogDescription>

                <form onSubmit={submit} className="flex min-w-0 flex-col gap-3">
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            autoFocus
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Cari nama atau email…"
                            className="pl-9"
                            aria-label="Cari pengguna"
                        />
                    </div>

                    <div className="max-h-72 overflow-y-auto rounded-md border border-border">
                        {loading && users.length === 0 ? (
                            <p className="flex items-center justify-center gap-2 p-4 text-[13px] text-muted-foreground">
                                <Loader2 className="size-4 animate-spin" />{' '}
                                Memuat…
                            </p>
                        ) : users.length === 0 ? (
                            <p className="p-4 text-center text-[13px] text-muted-foreground">
                                Tidak ada pengguna yang cocok.
                            </p>
                        ) : (
                            users.map((user) => (
                                <button
                                    key={user.id}
                                    type="button"
                                    onClick={() => {
                                        setChosen(user);
                                        setError(null);
                                    }}
                                    className={cn(
                                        'flex w-full items-start gap-2 border-b border-border px-3 py-2 text-left last:border-b-0 hover:bg-muted/60',
                                        chosen?.id === user.id &&
                                            'bg-primary/10',
                                    )}
                                >
                                    <UserRound className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                    <span className="min-w-0">
                                        <span className="block truncate text-[13px] font-medium text-foreground">
                                            {user.name}
                                        </span>
                                        <span className="block truncate text-[12px] text-muted-foreground">
                                            {user.email}
                                            {user.roles.length > 0 &&
                                                ` · ${user.roles.join(', ')}`}
                                        </span>
                                    </span>
                                </button>
                            ))
                        )}
                    </div>

                    {chosen && (
                        <p className="rounded-md bg-muted/60 px-3 py-2 text-[13px]">
                            Masuk sebagai <b>{chosen.name}</b>{' '}
                            <span className="text-muted-foreground">
                                ({chosen.email})
                            </span>
                        </p>
                    )}

                    {chosen && !passwordConfirmed && (
                        <label className="flex flex-col gap-1 text-[13px]">
                            <span className="flex items-center gap-1 font-medium">
                                <KeyRound className="size-3.5" /> Kata sandi
                                Super Admin
                            </span>
                            <Input
                                type="password"
                                autoComplete="current-password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                            />
                            <span className="text-[12px] text-muted-foreground">
                                Diminta sekali, lalu berlaku 3 jam untuk pindah
                                akun berikutnya.
                            </span>
                        </label>
                    )}
                    <InputError message={error ?? undefined} />

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => setOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                !chosen ||
                                processing ||
                                (!passwordConfirmed && password === '')
                            }
                        >
                            {processing ? 'Memproses…' : 'Masuk sebagai'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
