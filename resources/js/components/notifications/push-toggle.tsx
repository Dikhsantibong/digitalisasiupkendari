import { usePage } from '@inertiajs/react';
import { BellOff, BellRing, Loader2, Send, Smartphone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { currentPushState, disablePush, enablePush, sendTestNotification } from '@/lib/notifications';
import type { PushState } from '@/lib/notifications';
import { cn } from '@/lib/utils';

/** This device's push state, re-read when the tab becomes visible (the user may change it in the browser). */
export function usePushState() {
    const [state, setState] = useState<PushState | null>(null);

    useEffect(() => {
        let alive = true;
        const read = () => {
            void currentPushState().then((next) => alive && setState(next));
        };

        read();
        document.addEventListener('visibilitychange', read);

        return () => {
            alive = false;
            document.removeEventListener('visibilitychange', read);
        };
    }, []);

    return [state, setState] as const;
}

const IOS = typeof navigator !== 'undefined' && /iphone|ipad|ipod/i.test(navigator.userAgent);

/**
 * "Izinkan notifikasi" for this device: asks the browser's permission and
 * subscribes the device to the signed-in account; once on, offers a test and
 * a switch-off. `compact` is the slim strip inside the bell panel.
 */
export function PushToggle({ compact = false }: { compact?: boolean }) {
    const publicKey = usePage().props.notifications?.push_public_key ?? null;
    const [state, setState] = usePushState();
    const [busy, setBusy] = useState(false);

    // The bell strip only nudges when something can be done on this device; the Notifikasi page always explains.
    const nothingToDo = state === 'subscribed' || state === 'insecure' || (state === 'unsupported' && !IOS);

    if (state === null || (compact && nothingToDo)) {
        return null;
    }

    const run = async (action: () => Promise<PushState>, success?: string) => {
        setBusy(true);

        try {
            const next = await action();
            setState(next);

            if (next === 'subscribed' && success) {
                toast.success(success);
            } else if (next === 'denied') {
                toast.error('Izin notifikasi ditolak. Aktifkan dari pengaturan situs di browser Anda.');
            }
        } catch {
            toast.error('Gagal mengatur notifikasi di perangkat ini. Coba lagi.');
        } finally {
            setBusy(false);
        }
    };

    const test = async () => {
        setBusy(true);

        try {
            const result = await sendTestNotification();
            toast[result.pushed ? 'success' : 'warning'](
                result.pushed ? 'Notifikasi uji dikirim ke perangkat Anda.' : 'Notifikasi uji tersimpan, tetapi pengiriman push gagal di server.',
            );
        } catch {
            toast.error('Gagal mengirim notifikasi uji.');
        } finally {
            setBusy(false);
        }
    };

    const message: Record<PushState, string> = {
        unsupported: IOS
            ? 'Di iPhone/iPad, tambahkan aplikasi ke Layar Utama (Bagikan → Tambah ke Layar Utama), lalu buka dari ikon tersebut untuk mengizinkan notifikasi.'
            : 'Browser ini belum mendukung notifikasi push. Gunakan Chrome, Edge, atau Firefox terbaru.',
        insecure: 'Notifikasi push hanya bisa diaktifkan saat aplikasi dibuka melalui HTTPS.',
        denied: 'Notifikasi diblokir untuk situs ini. Buka pengaturan situs di browser (ikon gembok di bilah alamat) → Notifikasi → Izinkan.',
        default: 'Izinkan notifikasi agar pengingat & kejadian penting muncul di HP/PC walaupun aplikasi sedang ditutup.',
        granted: 'Izin sudah diberikan. Aktifkan agar perangkat ini menerima pengingat.',
        subscribed: 'Perangkat ini menerima notifikasi pengingat & kejadian.',
    };
    const canEnable = (state === 'default' || state === 'granted') && publicKey !== null;

    return (
        <div
            className={cn(
                'flex gap-3 rounded-xl border',
                compact ? 'items-center border-primary/20 bg-primary/5 p-2.5' : 'flex-col border-border bg-card p-4 sm:flex-row sm:items-center',
            )}
        >
            <span
                className={cn(
                    'flex shrink-0 items-center justify-center rounded-xl',
                    compact ? 'size-9' : 'size-11',
                    state === 'subscribed' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-primary/10 text-primary',
                )}
            >
                {state === 'subscribed' ? <BellRing className="size-5" /> : <Smartphone className="size-5" />}
            </span>
            <div className="min-w-0 flex-1">
                {!compact && (
                    <p className="text-sm font-semibold text-foreground">
                        Notifikasi di perangkat ini {state === 'subscribed' ? 'aktif' : 'belum aktif'}
                    </p>
                )}
                <p className={cn('text-muted-foreground', compact ? 'text-[12px] leading-snug' : 'mt-0.5 text-[13px]')}>{message[state]}</p>
            </div>
            <div className="flex shrink-0 flex-wrap gap-2">
                {canEnable && (
                    <Button size="sm" disabled={busy} onClick={() => void run(() => enablePush(publicKey), 'Notifikasi di perangkat ini aktif.')}>
                        {busy ? <Loader2 className="size-4 animate-spin" /> : <BellRing className="size-4" />}
                        Izinkan notifikasi
                    </Button>
                )}
                {state === 'subscribed' && (
                    <>
                        <Button size="sm" variant="outline" disabled={busy} onClick={() => void test()}>
                            <Send className="size-4" />
                            Kirim uji
                        </Button>
                        <Button size="sm" variant="ghost" disabled={busy} onClick={() => void run(disablePush)} className="text-muted-foreground">
                            <BellOff className="size-4" />
                            Matikan
                        </Button>
                    </>
                )}
            </div>
        </div>
    );
}
