import { router, usePage } from '@inertiajs/react';
import { LogIn, ShieldAlert } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import impersonation from '@/routes/impersonation';
import type { Auth } from '@/types';

/** Back to the Super Admin from a "masuk sebagai" session. */
export function stopImpersonation(onFinish?: () => void): void {
    router.post(impersonation.stop().url, {}, { onFinish });
}

/**
 * Shown on every page while a Super Admin works as another user (Pindah
 * Akun): whose account it is, the time left, and the way back.
 */
export function ImpersonationBanner() {
    const auth = usePage().props.auth as Auth | undefined;
    const [leaving, setLeaving] = useState(false);
    const impersonator = auth?.impersonator;
    const banner = useRef<HTMLDivElement>(null);

    // The fixed sidebar and the sticky phone header sit below the banner (--impersonation-h).
    useEffect(() => {
        const element = banner.current;
        const root = document.documentElement;

        if (!element) {
            return;
        }

        const apply = () => root.style.setProperty('--impersonation-h', `${element.offsetHeight}px`);
        apply();
        const observer = new ResizeObserver(apply);
        observer.observe(element);

        return () => {
            observer.disconnect();
            root.style.removeProperty('--impersonation-h');
        };
    }, [impersonator]);

    if (!auth || !impersonator) {
        return null;
    }

    return (
        <div
            ref={banner}
            role="status"
            className="sticky top-0 z-50 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-amber-500 px-3 py-1.5 text-[13px] font-medium text-amber-950"
        >
            <span className="flex items-center gap-1.5">
                <ShieldAlert className="size-4 shrink-0" />
                <span>
                    {impersonator.name} sedang masuk sebagai{' '}
                    <b>{auth.user.name}</b> · sisa {impersonator.minutes_left}{' '}
                    menit
                </span>
            </span>
            <Button
                size="sm"
                variant="secondary"
                className="h-7 bg-amber-950 text-amber-50 hover:bg-amber-900"
                disabled={leaving}
                onClick={() => {
                    setLeaving(true);
                    stopImpersonation(() => setLeaving(false));
                }}
            >
                <LogIn className="size-3.5" />
                {leaving ? 'Kembali…' : 'Kembali ke Super Admin'}
            </Button>
        </div>
    );
}
