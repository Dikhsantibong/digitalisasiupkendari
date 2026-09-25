import { Head, router } from '@inertiajs/react';
import { Crosshair, ExternalLink, MapPin, Save } from 'lucide-react';
import { useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    formatCoordinates,
    geolocationErrorMessage,
    googleMapsUrl,
    parseCoordinates,
} from '@/lib/geo';
import { dashboard } from '@/routes';
import attendanceLocations from '@/routes/admin/attendance-locations';

type UnitLocation = {
    id: number;
    code: string;
    name: string;
    location: string | null;
    service_unit: string | null;
    latitude: number | null;
    longitude: number | null;
    attendance_radius_m: number;
    is_active: boolean;
};

function LocationCard({ unit }: { unit: UnitLocation }) {
    const saved =
        unit.latitude !== null && unit.longitude !== null
            ? { latitude: unit.latitude, longitude: unit.longitude }
            : null;
    const [coordinates, setCoordinates] = useState(
        saved ? formatCoordinates(saved) : '',
    );
    const [radius, setRadius] = useState(String(unit.attendance_radius_m));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [locating, setLocating] = useState(false);
    const [saving, setSaving] = useState(false);

    const parsed =
        coordinates.trim() === '' ? null : parseCoordinates(coordinates);
    const invalid = coordinates.trim() !== '' && parsed === null;

    const useMyLocation = () => {
        setLocating(true);
        navigator.geolocation.getCurrentPosition(
            (p) => {
                setCoordinates(
                    formatCoordinates({
                        latitude: p.coords.latitude,
                        longitude: p.coords.longitude,
                    }),
                );
                setErrors({});
                setLocating(false);
            },
            (error) => {
                setErrors({ coordinates: geolocationErrorMessage(error) });
                setLocating(false);
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: 20_000 },
        );
    };

    const save = () => {
        setSaving(true);
        router.put(
            attendanceLocations.update(unit.id).url,
            {
                latitude: parsed?.latitude ?? null,
                longitude: parsed?.longitude ?? null,
                attendance_radius_m: Number(radius),
            },
            {
                preserveScroll: true,
                onSuccess: () => setErrors({}),
                onError: (e) => setErrors(e),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-xs">
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="truncate text-[14px] font-semibold text-foreground">
                        {unit.name}
                    </p>
                    <p className="truncate text-[11.5px] text-muted-foreground">
                        {unit.code}
                        {unit.service_unit && ` · ${unit.service_unit}`}
                        {unit.location && ` · ${unit.location}`}
                    </p>
                </div>
                <StatusBadge tone={saved ? 'success' : 'warning'}>
                    {saved ? 'Sudah diatur' : 'Belum diatur'}
                </StatusBadge>
            </div>

            <label className="flex flex-col gap-1 text-[12.5px]">
                <span className="text-muted-foreground">
                    Koordinat kantor (latitude, longitude)
                </span>
                <Input
                    value={coordinates}
                    onChange={(e) => setCoordinates(e.target.value)}
                    placeholder="-3.9778000, 122.5150000"
                    inputMode="decimal"
                />
                {invalid && (
                    <span className="text-[12px] text-rose-600">
                        Format: latitude, longitude — contoh -3.97780, 122.51500
                    </span>
                )}
                {(errors.coordinates ??
                    errors.latitude ??
                    errors.longitude) && (
                    <span className="text-[12px] text-rose-600">
                        {errors.coordinates ??
                            errors.latitude ??
                            errors.longitude}
                    </span>
                )}
            </label>

            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={useMyLocation}
                    disabled={locating}
                >
                    <Crosshair className="size-4" />
                    {locating ? 'Membaca lokasi…' : 'Pakai lokasi saya'}
                </Button>
                {parsed && (
                    <Button type="button" variant="outline" size="sm" asChild>
                        <a
                            href={googleMapsUrl(parsed)}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <ExternalLink className="size-4" />
                            Lihat di peta
                        </a>
                    </Button>
                )}
            </div>

            <div className="flex items-end gap-2">
                <label className="flex flex-1 flex-col gap-1 text-[12.5px]">
                    <span className="text-muted-foreground">
                        Radius absen (meter)
                    </span>
                    <Input
                        type="number"
                        min={10}
                        max={5000}
                        value={radius}
                        onChange={(e) => setRadius(e.target.value)}
                        inputMode="numeric"
                    />
                    {errors.attendance_radius_m && (
                        <span className="text-[12px] text-rose-600">
                            {errors.attendance_radius_m}
                        </span>
                    )}
                </label>
                <Button
                    type="button"
                    onClick={save}
                    disabled={saving || invalid || radius === ''}
                >
                    <Save className="size-4" />
                    {saving ? 'Menyimpan…' : 'Simpan'}
                </Button>
            </div>
        </div>
    );
}

export default function AttendanceLocations({
    units,
}: {
    units: UnitLocation[];
}) {
    const configured = units.filter(
        (u) => u.latitude !== null && u.longitude !== null,
    ).length;

    return (
        <>
            <Head title="Lokasi Absensi" />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Lokasi Absensi"
                    description="Titik kantor dan radius absen masuk/pulang untuk setiap unit. Salin koordinat dari Google Maps (klik kanan pada peta), atau tekan “Pakai lokasi saya” saat berada di kantor."
                />

                <div className="flex items-center gap-2 rounded-lg border border-border bg-card px-3 py-2 text-[13px]">
                    <MapPin className="size-4 text-primary" />
                    <span>
                        <span className="font-semibold">{configured}</span> dari{' '}
                        {units.length} unit sudah diatur lokasinya.
                    </span>
                </div>

                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {units.map((unit) => (
                        <LocationCard
                            key={`${unit.id}-${unit.latitude}-${unit.longitude}-${unit.attendance_radius_m}`}
                            unit={unit}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

AttendanceLocations.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Lokasi Absensi', href: attendanceLocations.index() },
    ],
};
