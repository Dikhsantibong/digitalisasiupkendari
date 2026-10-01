import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCheck, ChevronLeft, ChevronRight, Save } from 'lucide-react';
import { useState } from 'react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import { NotificationItem } from '@/components/notifications/notification-item';
import { PushToggle } from '@/components/notifications/push-toggle';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NOTIFICATION_MODULES } from '@/lib/notifications';
import type { AppNotification } from '@/lib/notifications';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type Category = { key: string; label: string; description: string; allowed: boolean; enabled: boolean };

type Props = {
    notifications: {
        data: AppNotification[];
        current_page: number;
        last_page: number;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: { module: string | null; status: 'all' | 'unread' };
    unread_by_module: Record<string, number>;
    settings: { digest_time: string; categories: Category[] };
};

const MODULE_FILTERS = ['operasi', 'operator', 'har', 'k3', 'pdm', 'logistik'] as const;

export default function NotificationsIndex({ notifications, filters, unread_by_module, settings }: Props) {
    const [now] = useState(() => Date.now());
    const [enabled, setEnabled] = useState(() => Object.fromEntries(settings.categories.map((c) => [c.key, c.enabled])));
    const [digestTime, setDigestTime] = useState(settings.digest_time);
    const [saving, setSaving] = useState(false);
    const unreadTotal = Object.values(unread_by_module).reduce((sum, count) => sum + count, 0);
    const dirty = digestTime !== settings.digest_time || settings.categories.some((c) => enabled[c.key] !== c.enabled);

    const visit = (patch: Partial<Props['filters']>) =>
        router.get(
            NotificationController.index().url,
            { ...filters, ...patch, module: patch.module === undefined ? filters.module : patch.module, page: undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const saveSettings = () => {
        setSaving(true);
        router.patch(
            NotificationController.updateSettings().url,
            { disabled: settings.categories.filter((c) => !enabled[c.key]).map((c) => c.key), digest_time: digestTime },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <>
            <Head title="Notifikasi" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader title="Notifikasi" description="Pengingat jadwal & absen, kejadian di unit, WO/SR, dan persetujuan laporan — sesuai role, unit, dan hak akses akun Anda." />

                <PushToggle />

                <section className="flex flex-col gap-3 rounded-2xl border border-border bg-card p-3 md:p-4">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="flex rounded-lg bg-muted p-1" role="tablist">
                            {(['all', 'unread'] as const).map((status) => (
                                <button
                                    key={status}
                                    type="button"
                                    role="tab"
                                    aria-selected={filters.status === status}
                                    onClick={() => visit({ status })}
                                    className={cn(
                                        'rounded-md px-3 py-1.5 text-[13px] font-medium transition',
                                        filters.status === status ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground',
                                    )}
                                >
                                    {status === 'all' ? 'Semua' : `Belum dibaca${unreadTotal > 0 ? ` · ${unreadTotal}` : ''}`}
                                </button>
                            ))}
                        </div>
                        {unreadTotal > 0 && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => router.post(NotificationController.markAllRead().url, { module: filters.module }, { preserveScroll: true })}
                            >
                                <CheckCheck className="size-4" />
                                Tandai semua dibaca
                            </Button>
                        )}
                    </div>

                    <div className="-mx-3 flex gap-2 overflow-x-auto px-3 [scrollbar-width:none] md:-mx-4 md:px-4 [&::-webkit-scrollbar]:hidden">
                        {[null, ...MODULE_FILTERS].map((module) => {
                            const label = module === null ? 'Semua modul' : NOTIFICATION_MODULES[module].label;
                            const count = module === null ? 0 : (unread_by_module[module] ?? 0);

                            return (
                                <button
                                    key={module ?? 'all'}
                                    type="button"
                                    onClick={() => visit({ module })}
                                    aria-pressed={filters.module === module}
                                    className={cn(
                                        'flex h-9 shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-[13px] font-medium whitespace-nowrap transition',
                                        filters.module === module ? 'border-primary bg-primary text-primary-foreground' : 'border-border/70 bg-card text-foreground',
                                    )}
                                >
                                    {label}
                                    {count > 0 && <span className="rounded-full bg-red-500 px-1.5 text-[11px] leading-[18px] font-bold text-white">{count}</span>}
                                </button>
                            );
                        })}
                    </div>

                    {notifications.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 py-12 text-center">
                            <span className="flex size-12 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                                <Bell className="size-6" />
                            </span>
                            <p className="text-[13px] text-muted-foreground">
                                {filters.status === 'unread' ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.'}
                            </p>
                        </div>
                    ) : (
                        <div className="-mx-1 flex flex-col gap-0.5">
                            {notifications.data.map((notification) => (
                                <NotificationItem key={notification.id} notification={notification} now={now} />
                            ))}
                        </div>
                    )}

                    {notifications.last_page > 1 && (
                        <div className="flex items-center justify-between gap-2 border-t border-border pt-3 text-[13px] text-muted-foreground">
                            <Button size="sm" variant="outline" disabled={!notifications.prev_page_url} asChild={!!notifications.prev_page_url}>
                                {notifications.prev_page_url ? (
                                    <Link href={notifications.prev_page_url} preserveScroll>
                                        <ChevronLeft className="size-4" />
                                        Sebelumnya
                                    </Link>
                                ) : (
                                    <span>
                                        <ChevronLeft className="size-4" />
                                        Sebelumnya
                                    </span>
                                )}
                            </Button>
                            <span>
                                Hal. {notifications.current_page} / {notifications.last_page}
                            </span>
                            <Button size="sm" variant="outline" disabled={!notifications.next_page_url} asChild={!!notifications.next_page_url}>
                                {notifications.next_page_url ? (
                                    <Link href={notifications.next_page_url} preserveScroll>
                                        Berikutnya
                                        <ChevronRight className="size-4" />
                                    </Link>
                                ) : (
                                    <span>
                                        Berikutnya
                                        <ChevronRight className="size-4" />
                                    </span>
                                )}
                            </Button>
                        </div>
                    )}
                </section>

                <section className="flex flex-col gap-4 rounded-2xl border border-border bg-card p-4">
                    <div>
                        <h2 className="text-[15px] font-semibold text-foreground">Pengaturan pengingat</h2>
                        <p className="text-[13px] text-muted-foreground">Berlaku untuk akun Anda di semua perangkat.</p>
                    </div>

                    <div className="flex flex-col gap-2">
                        {settings.categories.map((category) => (
                            <label
                                key={category.key}
                                className={cn(
                                    'flex items-start gap-3 rounded-xl border border-border p-3',
                                    category.allowed ? 'cursor-pointer hover:bg-muted/40' : 'opacity-60',
                                )}
                            >
                                <Checkbox
                                    checked={category.allowed && enabled[category.key]}
                                    disabled={!category.allowed}
                                    onCheckedChange={(checked) => setEnabled((current) => ({ ...current, [category.key]: checked === true }))}
                                    className="mt-0.5"
                                />
                                <span className="min-w-0">
                                    <span className="block text-[14px] font-medium text-foreground">{category.label}</span>
                                    <span className="block text-[12.5px] text-muted-foreground">
                                        {category.allowed ? category.description : 'Tidak tersedia untuk role Anda (diatur Super Admin di Role & Akses).'}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>

                    <label className="flex flex-col gap-1.5 text-[13px] sm:flex-row sm:items-center sm:justify-between">
                        <span>
                            <span className="block font-medium text-foreground">Jam ringkasan jadwal harian</span>
                            <span className="block text-[12.5px] text-muted-foreground">Waktu WITA. Pengingat absen tetap mengikuti jam shift.</span>
                        </span>
                        <Input type="time" value={digestTime} onChange={(e) => setDigestTime(e.target.value)} className="h-10 w-full sm:w-36" step={300} />
                    </label>

                    <div className="flex justify-end">
                        <Button onClick={saveSettings} disabled={!dirty || saving || digestTime === ''}>
                            <Save className="size-4" />
                            {saving ? 'Menyimpan…' : 'Simpan pengaturan'}
                        </Button>
                    </div>
                </section>
            </div>
        </>
    );
}

NotificationsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Notifikasi', href: NotificationController.index() },
    ],
};
