import { Head, router, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    Crosshair,
    LogIn,
    LogOut,
    MapPin,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { distanceMeters, geolocationErrorMessage } from '@/lib/geo';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import presensi from '@/routes/operator/presensi';

type Presence = {
    work_date: string;
    shift_code: string | null;
    late_minutes: number | null;
    check_in_note: string | null;
    check_in_at: string;
    check_in_distance_m: number;
    check_out_at: string | null;
    check_out_distance_m: number | null;
};

type Props = {
    employee: {
        name: string;
        position: string | null;
        regu: string | null;
        is_shift_leader: boolean;
    } | null;
    shift: {
        work_date: string;
        code: string | null;
        label: string | null;
        jam_mulai: string | null;
        jam_selesai: string | null;
        is_working: boolean;
    } | null;
    office: {
        unit: string;
        latitude: number | null;
        longitude: number | null;
        radius_m: number;
    } | null;
    today: Presence | null;
    open: Presence | null;
    timezone: string;
};

type Position = { latitude: number; longitude: number; accuracy: number };

export default function Presensi({
    employee,
    shift,
    office,
    today,
    open,
    timezone,
}: Props) {
    const errors = usePage().props.errors as Record<string, string>;
    const [now, setNow] = useState(() => new Date());
    const [position, setPosition] = useState<Position | null>(null);
    const [geoError, setGeoError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const [note, setNote] = useState('');

    const geoSupported =
        typeof navigator !== 'undefined' && 'geolocation' in navigator;
    const hasOffice =
        office !== null &&
        office.latitude !== null &&
        office.longitude !== null;

    useEffect(() => {
        const timer = window.setInterval(() => setNow(new Date()), 1000);

        return () => window.clearInterval(timer);
    }, []);

    useEffect(() => {
        if (!hasOffice || !geoSupported) {
            return;
        }

        const watch = navigator.geolocation.watchPosition(
            (p) => {
                setGeoError(null);
                setPosition({
                    latitude: p.coords.latitude,
                    longitude: p.coords.longitude,
                    accuracy: p.coords.accuracy,
                });
            },
            (error) => setGeoError(geolocationErrorMessage(error)),
            { enableHighAccuracy: true, maximumAge: 10_000, timeout: 20_000 },
        );

        return () => navigator.geolocation.clearWatch(watch);
    }, [hasOffice, geoSupported]);

    const refresh = () => {
        setPosition(null);
        navigator.geolocation.getCurrentPosition(
            (p) => {
                setGeoError(null);
                setPosition({
                    latitude: p.coords.latitude,
                    longitude: p.coords.longitude,
                    accuracy: p.coords.accuracy,
                });
            },
            (error) => setGeoError(geolocationErrorMessage(error)),
            { enableHighAccuracy: true, maximumAge: 0, timeout: 20_000 },
        );
    };

    const time = (iso: string | null) =>
        iso
            ? new Intl.DateTimeFormat('id-ID', {
                  timeZone: timezone,
                  hour: '2-digit',
                  minute: '2-digit',
              }).format(new Date(iso))
            : '—';

    if (employee === null || office === null) {
        return (
            <>
                <Head title="Absensi" />
                <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                    <PageHeader title="Absensi" />
                    <EmptyState
                        title="Akun belum terhubung ke data pegawai"
                        description="Minta admin menautkan akun Anda ke data pegawai pada unit Anda."
                    />
                </div>
            </>
        );
    }

    const distance =
        hasOffice && position
            ? Math.round(
                  distanceMeters(position, {
                      latitude: office.latitude as number,
                      longitude: office.longitude as number,
                  }),
              )
            : null;
    const withinRadius = distance !== null && distance <= office.radius_m;
    const mode: 'in' | 'out' | 'done' = open ? 'out' : today ? 'done' : 'in';
    const serverError =
        errors.location ?? errors.presence ?? errors.note ?? errors.latitude;
    const needsNote = mode === 'in' && !(shift?.is_working ?? false);
    const record = open ?? today;
    const hours = (value: string | null) => value?.slice(0, 5) ?? '';
    const workDateLabel = shift
        ? new Intl.DateTimeFormat('id-ID', {
              weekday: 'long',
              day: 'numeric',
              month: 'long',
          }).format(new Date(`${shift.work_date}T00:00:00`))
        : '';

    const submit = () => {
        if (!position) {
            return;
        }

        setSubmitting(true);
        router.post(
            mode === 'out' ? presensi.checkOut().url : presensi.checkIn().url,
            {
                latitude: position.latitude,
                longitude: position.longitude,
                accuracy: position.accuracy,
                ...(mode === 'in' ? { note } : {}),
            },
            { preserveScroll: true, onFinish: () => setSubmitting(false) },
        );
    };

    return (
        <>
            <Head title="Absensi" />
            <div className="mx-auto flex w-full max-w-md flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Absensi"
                    description={`Absen hanya bisa dilakukan dalam radius ${office.radius_m} m dari kantor ${office.unit}.`}
                />

                <div className="flex flex-col items-center gap-1 rounded-2xl bg-linear-to-br from-chart-5 via-[#0b6aa2] to-chart-1 p-5 text-center text-white shadow-md">
                    <p className="text-[12.5px] text-white/75">
                        {new Intl.DateTimeFormat('id-ID', {
                            timeZone: timezone,
                            weekday: 'long',
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric',
                        }).format(now)}
                    </p>
                    <p className="text-4xl font-semibold tracking-tight tabular-nums">
                        {new Intl.DateTimeFormat('id-ID', {
                            timeZone: timezone,
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                        }).format(now)}
                    </p>
                    <p className="text-[12px] text-white/75">
                        {employee.name}
                        {employee.regu ? ` · Regu ${employee.regu}` : ''}
                        {employee.is_shift_leader ? ' (Leader Shift)' : ''}
                    </p>
                </div>

                {shift && (
                    <div
                        className={cn(
                            'flex items-center gap-3 rounded-xl border p-3',
                            shift.is_working
                                ? 'border-border bg-card'
                                : 'border-amber-300 bg-amber-50 dark:bg-amber-500/10',
                        )}
                    >
                        <span
                            className={cn(
                                'flex h-11 min-w-11 shrink-0 items-center justify-center rounded-lg px-2 text-[13px] font-bold',
                                shift.code === 'S'
                                    ? 'bg-sky-500 text-white'
                                    : shift.code === 'M'
                                      ? 'bg-slate-500 text-white'
                                      : shift.code === 'P'
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-amber-500 text-white',
                            )}
                        >
                            {shift.code ?? '—'}
                        </span>
                        <div className="min-w-0 text-[13px]">
                            <p className="text-[11.5px] text-muted-foreground">
                                Jadwal shift · {workDateLabel}
                            </p>
                            {shift.is_working ? (
                                <p className="font-semibold text-foreground">
                                    Shift {shift.label} ·{' '}
                                    {hours(shift.jam_mulai)}–
                                    {hours(shift.jam_selesai)} WITA
                                </p>
                            ) : (
                                <p className="font-semibold text-amber-800 dark:text-amber-300">
                                    {shift.code === null
                                        ? 'Belum dijadwalkan'
                                        : shift.label}{' '}
                                    — absen wajib disertai catatan
                                </p>
                            )}
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-2 gap-3">
                    <div className="rounded-xl border border-border bg-card p-3">
                        <p className="flex items-center gap-1.5 text-[11.5px] font-medium text-muted-foreground">
                            <LogIn className="size-3.5 text-emerald-500" />{' '}
                            Masuk
                        </p>
                        <p className="mt-1 text-xl font-semibold text-foreground tabular-nums">
                            {time(record?.check_in_at ?? null)}
                        </p>
                        {record?.late_minutes ? (
                            <p className="text-[11.5px] font-medium text-rose-600">
                                Terlambat {record.late_minutes} menit
                            </p>
                        ) : record?.late_minutes === 0 ? (
                            <p className="text-[11.5px] font-medium text-emerald-600">
                                Tepat waktu
                            </p>
                        ) : null}
                    </div>
                    <div className="rounded-xl border border-border bg-card p-3">
                        <p className="flex items-center gap-1.5 text-[11.5px] font-medium text-muted-foreground">
                            <LogOut className="size-3.5 text-rose-500" /> Pulang
                        </p>
                        <p className="mt-1 text-xl font-semibold text-foreground tabular-nums">
                            {time(open ? null : (today?.check_out_at ?? null))}
                        </p>
                    </div>
                </div>

                {record?.check_in_note && (
                    <p className="rounded-lg border border-border bg-card px-3 py-2 text-[12.5px] text-muted-foreground">
                        <span className="font-medium text-foreground">
                            Catatan:
                        </span>{' '}
                        {record.check_in_note}
                    </p>
                )}

                {!hasOffice ? (
                    <div className="flex items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 p-3 text-[13px] text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0" />
                        Lokasi kantor {office.unit} belum diatur. Hubungi Super
                        Admin.
                    </div>
                ) : mode === 'done' ? (
                    <div className="flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-[14px] font-medium text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
                        <CheckCircle2 className="size-5 shrink-0" />
                        Absensi hari ini sudah lengkap. Terima kasih!
                    </div>
                ) : (
                    <>
                        <div
                            className={cn(
                                'flex items-start gap-3 rounded-xl border p-3',
                                distance === null
                                    ? 'border-border bg-card'
                                    : withinRadius
                                      ? 'border-emerald-300 bg-emerald-50 dark:bg-emerald-500/10'
                                      : 'border-rose-300 bg-rose-50 dark:bg-rose-500/10',
                            )}
                        >
                            <MapPin
                                className={cn(
                                    'mt-0.5 size-5 shrink-0',
                                    distance === null
                                        ? 'text-muted-foreground'
                                        : withinRadius
                                          ? 'text-emerald-600'
                                          : 'text-rose-600',
                                )}
                            />
                            <div className="min-w-0 flex-1 text-[13px]">
                                {!geoSupported ? (
                                    <p className="text-rose-700 dark:text-rose-300">
                                        Browser ini tidak mendukung lokasi.
                                    </p>
                                ) : geoError ? (
                                    <p className="text-rose-700 dark:text-rose-300">
                                        {geoError}
                                    </p>
                                ) : distance === null ? (
                                    <p className="text-muted-foreground">
                                        Mendeteksi lokasi Anda…
                                    </p>
                                ) : (
                                    <>
                                        <p
                                            className={cn(
                                                'font-semibold',
                                                withinRadius
                                                    ? 'text-emerald-800 dark:text-emerald-300'
                                                    : 'text-rose-800 dark:text-rose-300',
                                            )}
                                        >
                                            {withinRadius
                                                ? 'Anda berada di area kantor'
                                                : 'Anda di luar area kantor'}
                                        </p>
                                        <p className="text-muted-foreground">
                                            Jarak{' '}
                                            {distance.toLocaleString('id-ID')} m
                                            · batas {office.radius_m} m
                                            {position &&
                                                ` · akurasi ±${Math.round(position.accuracy)} m`}
                                        </p>
                                    </>
                                )}
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-8 shrink-0"
                                onClick={refresh}
                                aria-label="Perbarui lokasi"
                            >
                                <Crosshair className="size-4" />
                            </Button>
                        </div>

                        {needsNote && (
                            <label className="flex flex-col gap-1.5 text-[13px]">
                                <span className="font-medium text-foreground">
                                    Catatan alasan absen{' '}
                                    <span className="text-rose-600">*</span>
                                </span>
                                <textarea
                                    value={note}
                                    onChange={(e) => setNote(e.target.value)}
                                    maxLength={255}
                                    rows={2}
                                    placeholder="Contoh: ganti shift dengan Budi (Regu B)"
                                    className="rounded-lg border border-input bg-background px-3 py-2 text-[14px] outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                />
                            </label>
                        )}

                        {serverError && (
                            <p className="rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-[13px] text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">
                                {serverError}
                            </p>
                        )}

                        <Button
                            size="lg"
                            onClick={submit}
                            disabled={
                                !withinRadius ||
                                submitting ||
                                (needsNote && note.trim() === '')
                            }
                            className={cn(
                                'h-16 rounded-2xl text-lg font-semibold shadow-md',
                                mode === 'out'
                                    ? 'bg-rose-600 hover:bg-rose-700'
                                    : 'bg-emerald-600 hover:bg-emerald-700',
                            )}
                        >
                            {mode === 'out' ? (
                                <LogOut className="size-6" />
                            ) : (
                                <LogIn className="size-6" />
                            )}
                            {submitting
                                ? 'Menyimpan…'
                                : mode === 'out'
                                  ? 'Absen Pulang'
                                  : 'Absen Masuk'}
                        </Button>
                        {!withinRadius && distance !== null && (
                            <p className="-mt-2 text-center text-[12px] text-muted-foreground">
                                Tombol aktif setelah Anda berada dalam radius
                                kantor.
                            </p>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

Presensi.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Absensi', href: presensi.index() },
    ],
};
