import { Head, router } from '@inertiajs/react';
import {
    CalendarDays,
    CheckCircle2,
    Clock,
    Lock,
    MapPin,
    UserPlus,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { MeetingAttendanceForm } from '@/components/har/meeting-attendance-form';
import { Button } from '@/components/ui/button';
import absensiMeeting from '@/routes/har/absensi-meeting';

type Props = {
    meeting: {
        token: string;
        unit: string | null;
        acara: string;
        hari_tanggal: string;
        waktu: string | null;
        tempat: string | null;
        dibuka: boolean;
        jumlah_hadir: number;
    };
};

type Hadir = { nama: string; hadir_pada: string };

/**
 * Public attendance page of a Daily Meeting Pemeliharaan, opened by scanning
 * the meeting QR code. No login needed: fill nama, asal perusahaan, jabatan
 * and sign on the canvas.
 */
export default function AbsensiMeetingPage({ meeting }: Props) {
    const [hadir, setHadir] = useState<Hadir | null>(null);

    useEffect(
        () =>
            router.on('flash', (event) => {
                const data = (event as CustomEvent).detail?.flash?.hadir as
                    Hadir | undefined;

                if (data) {
                    setHadir(data);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }),
        [],
    );

    return (
        <>
            <Head title={`Absensi — ${meeting.acara}`} />
            <div className="min-h-svh bg-muted/40 px-4 py-6 sm:py-10">
                <div className="mx-auto flex w-full max-w-md flex-col gap-4">
                    <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                        <div className="flex flex-col items-center gap-2 bg-primary px-5 py-5 text-center text-primary-foreground">
                            <img
                                src="/logo/mkp.jpg"
                                alt="MKP"
                                className="h-10 rounded bg-white object-contain p-1"
                            />
                            <p className="text-xs font-medium tracking-wide uppercase opacity-90">
                                Daftar Hadir Meeting Pemeliharaan
                                {meeting.unit ? ` · ${meeting.unit}` : ''}
                            </p>
                            <h1 className="text-xl leading-snug font-bold">
                                {meeting.acara}
                            </h1>
                        </div>
                        <div className="grid gap-2.5 px-5 py-4 text-sm">
                            <Info
                                icon={<CalendarDays className="size-4" />}
                                label="Hari / Tanggal"
                                value={meeting.hari_tanggal}
                            />
                            {meeting.waktu && (
                                <Info
                                    icon={<Clock className="size-4" />}
                                    label="Waktu"
                                    value={meeting.waktu}
                                />
                            )}
                            {meeting.tempat && (
                                <Info
                                    icon={<MapPin className="size-4" />}
                                    label="Tempat"
                                    value={meeting.tempat}
                                />
                            )}
                            <Info
                                icon={<Users className="size-4" />}
                                label="Sudah hadir"
                                value={`${meeting.jumlah_hadir} orang`}
                            />
                        </div>
                    </div>

                    {hadir ? (
                        <div className="flex flex-col items-center gap-3 rounded-2xl border border-emerald-500/40 bg-card px-5 py-8 text-center shadow-sm">
                            <CheckCircle2 className="size-16 text-emerald-500" />
                            <div>
                                <p className="text-lg font-semibold text-foreground">
                                    Terima kasih, {hadir.nama}!
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Kehadiran Anda tercatat pukul{' '}
                                    {new Date(
                                        hadir.hadir_pada,
                                    ).toLocaleTimeString('id-ID', {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                    .
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                onClick={() => setHadir(null)}
                                className="mt-2 gap-2"
                            >
                                <UserPlus className="size-4" />
                                Isi absensi peserta lain
                            </Button>
                        </div>
                    ) : meeting.dibuka ? (
                        <div className="rounded-2xl border border-border bg-card px-5 py-5 shadow-sm">
                            <h2 className="mb-1 text-base font-semibold text-foreground">
                                Form Absensi
                            </h2>
                            <p className="mb-4 text-sm text-muted-foreground">
                                Isi data diri Anda lalu tanda tangan di kotak
                                yang disediakan.
                            </p>
                            <MeetingAttendanceForm
                                action={absensiMeeting.store(meeting.token).url}
                            />
                        </div>
                    ) : (
                        <div className="flex flex-col items-center gap-3 rounded-2xl border border-border bg-card px-5 py-8 text-center shadow-sm">
                            <Lock className="size-12 text-muted-foreground" />
                            <p className="font-semibold text-foreground">
                                Absensi sudah ditutup
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Hubungi penyelenggara meeting bila Anda belum
                                tercatat hadir.
                            </p>
                        </div>
                    )}

                    <p className="text-center text-xs text-muted-foreground">
                        Digitalisasi Unit UP Kendari · Pemeliharaan
                    </p>
                </div>
            </div>
        </>
    );
}

function Info({
    icon,
    label,
    value,
}: {
    icon: ReactNode;
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-start gap-3">
            <span className="mt-0.5 text-muted-foreground">{icon}</span>
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="font-medium text-foreground">{value}</p>
            </div>
        </div>
    );
}
