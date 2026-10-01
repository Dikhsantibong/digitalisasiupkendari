import { Link } from '@inertiajs/react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import { notificationModule, timeAgo } from '@/lib/notifications';
import type { AppNotification } from '@/lib/notifications';
import { cn } from '@/lib/utils';

/**
 * One notification row: module icon, title, body and time. Tapping it goes
 * through the "open" route, which marks it read and redirects to its page.
 */
export function NotificationItem({
    notification,
    now,
    onOpen,
    dense = false,
}: {
    notification: AppNotification;
    now: number;
    onOpen?: (notification: AppNotification) => void;
    dense?: boolean;
}) {
    const meta = notificationModule(notification.module, notification.category);
    const Icon = meta.icon;

    return (
        <Link
            href={NotificationController.open(notification.id).url}
            onClick={() => onOpen?.(notification)}
            className={cn(
                'relative flex gap-3 rounded-md text-left transition outline-none hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring active:bg-muted',
                dense ? 'px-2.5 py-2.5' : 'px-3 py-3',
                !notification.read && 'bg-primary/[0.04]',
            )}
        >
            <span className={cn('mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-md', meta.tone)}>
                <Icon className="size-[18px]" strokeWidth={1.9} />
            </span>
            <span className="min-w-0 flex-1">
                <span className="flex items-start gap-2">
                    <span className={cn('min-w-0 flex-1 text-[13.5px] leading-snug text-foreground', notification.read ? 'font-medium' : 'font-semibold')}>
                        {notification.title}
                    </span>
                    {!notification.read && <span className="mt-1.5 size-2 shrink-0 rounded-full bg-primary" aria-label="Belum dibaca" />}
                </span>
                {notification.body && (
                    <span className={cn('mt-0.5 block text-[12.5px] leading-snug text-muted-foreground', dense && 'line-clamp-2')}>{notification.body}</span>
                )}
                <span className="mt-1 block text-[11.5px] text-muted-foreground/80">
                    {meta.label} · {timeAgo(notification.created_at, now)}
                </span>
            </span>
        </Link>
    );
}
