import { Link } from '@inertiajs/react';
import { Bell, CheckCheck, Loader2 } from 'lucide-react';
import { useState } from 'react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import { NotificationItem } from '@/components/notifications/notification-item';
import { PushToggle } from '@/components/notifications/push-toggle';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useNotifications } from '@/hooks/use-notifications';
import { cn } from '@/lib/utils';

/**
 * The bell (desktop header & phone shell): unread badge, and a panel with the
 * latest notifications, "Tandai semua dibaca", the push prompt for this
 * device and a link to the Notifikasi page.
 */
export function NotificationBell({ variant = 'default' }: { variant?: 'default' | 'onDark' | 'muted' }) {
    const { unread, items, loading, refresh, read, readAll } = useNotifications();
    const [open, setOpen] = useState(false);
    const [now, setNow] = useState(() => Date.now());

    const toggle = (next: boolean) => {
        setOpen(next);

        if (next) {
            setNow(Date.now());
            void refresh();
        }
    };

    return (
        <DropdownMenu open={open} onOpenChange={toggle}>
            <DropdownMenuTrigger
                className={cn(
                    'relative flex size-10 shrink-0 items-center justify-center rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring active:scale-95',
                    variant === 'onDark' && 'bg-white/15 text-white ring-1 ring-white/25',
                    variant === 'muted' && 'bg-muted text-foreground',
                    variant === 'default' && 'size-9 text-foreground hover:bg-accent',
                )}
                aria-label={unread > 0 ? `Notifikasi, ${unread} belum dibaca` : 'Notifikasi'}
            >
                <Bell className="size-5" />
                {unread > 0 && (
                    <span
                        className={cn(
                            'absolute -top-1 -right-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10.5px] leading-none font-bold text-white tabular-nums',
                            variant === 'onDark' ? 'ring-2 ring-[#0b6aa2]' : 'ring-2 ring-background',
                        )}
                    >
                        {unread > 99 ? '99+' : unread}
                    </span>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" collisionPadding={8} sideOffset={8} className="flex max-h-[min(36rem,calc(100dvh-5rem))] w-[min(24rem,calc(100vw-1rem))] flex-col overflow-hidden p-0">
                <div className="flex items-center justify-between gap-2 border-b border-border px-4 py-3">
                    <div>
                        <p className="text-[15px] font-semibold text-foreground">Notifikasi</p>
                        <p className="text-[12px] text-muted-foreground">{unread > 0 ? `${unread} belum dibaca` : 'Semua sudah dibaca'}</p>
                    </div>
                    {unread > 0 && (
                        <Button size="sm" variant="ghost" onClick={() => void readAll()} className="h-8 gap-1.5 text-[12.5px]">
                            <CheckCheck className="size-4" />
                            Tandai dibaca
                        </Button>
                    )}
                </div>

                <div className="px-3 pt-3 empty:hidden">
                    <PushToggle compact />
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-1.5">
                    {items === null ? (
                        <div className="flex items-center justify-center gap-2 py-10 text-[13px] text-muted-foreground">
                            <Loader2 className="size-4 animate-spin" />
                            Memuat…
                        </div>
                    ) : items.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 px-6 py-10 text-center">
                            <span className="flex size-11 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                                <Bell className="size-5" />
                            </span>
                            <p className="text-[13px] text-muted-foreground">Belum ada notifikasi. Pengingat jadwal & absen akan muncul di sini.</p>
                        </div>
                    ) : (
                        <div className={cn('flex flex-col gap-0.5', loading && 'opacity-80')}>
                            {items.map((item) => (
                                <NotificationItem
                                    key={item.id}
                                    notification={item}
                                    now={now}
                                    dense
                                    onOpen={(n) => {
                                        setOpen(false);

                                        if (!n.read) {
                                            void read(n.id);
                                        }
                                    }}
                                />
                            ))}
                        </div>
                    )}
                </div>

                <Link
                    href={NotificationController.index().url}
                    onClick={() => setOpen(false)}
                    className="border-t border-border px-4 py-3 text-center text-[13px] font-medium text-primary hover:bg-muted/60"
                >
                    Lihat semua notifikasi & pengaturan
                </Link>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
