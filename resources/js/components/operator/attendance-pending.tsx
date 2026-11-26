import { usePage } from '@inertiajs/react';
import { Fingerprint } from 'lucide-react';
import type { LucideIcon, LucideProps } from 'lucide-react';
import { forwardRef } from 'react';

/**
 * Whether the signed-in user still owes an absen by their Jadwal Shift
 * (the shared `attendanceAlert`, see AttendanceReminders::pendingFor).
 */
export function useAttendancePending(): boolean {
    return Boolean(usePage().props.attendanceAlert);
}

/**
 * The Absensi menu icon with a red dot (user, 2026-11-26: "beri simbol di
 * icons menunya jika belum di inputkan") — drawn inside the same SVG, so the
 * sidebar sizes it like any other menu icon.
 */
export const FingerprintPending = forwardRef<SVGSVGElement, LucideProps>(
    (props, ref) => (
        <Fingerprint ref={ref} {...props}>
            <circle
                cx="20"
                cy="4"
                r="4"
                fill="#dc2626"
                stroke="#ffffff"
                strokeWidth="1.5"
            />
        </Fingerprint>
    ),
) as unknown as LucideIcon;

FingerprintPending.displayName = 'FingerprintPending';
