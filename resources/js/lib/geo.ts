/**
 * Small geolocation helpers for absen masuk/pulang and the Lokasi Absensi menu.
 * The server re-measures every distance; these only drive on-screen feedback.
 */

export type Coordinates = { latitude: number; longitude: number };

/** Great-circle (haversine) distance in metres — same formula as PresenceRecorder. */
export function distanceMeters(a: Coordinates, b: Coordinates): number {
    const toRad = (deg: number) => (deg * Math.PI) / 180;
    const dLat = toRad(b.latitude - a.latitude);
    const dLng = toRad(b.longitude - a.longitude);
    const h =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(toRad(a.latitude)) *
            Math.cos(toRad(b.latitude)) *
            Math.sin(dLng / 2) ** 2;

    return 6_371_000 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

/** Parses "lat, lng" as copied from Google Maps; null when invalid. */
export function parseCoordinates(text: string): Coordinates | null {
    const parts = text.split(',').map((part) => Number(part.trim()));

    if (parts.length !== 2 || parts.some((n) => Number.isNaN(n))) {
        return null;
    }

    const [latitude, longitude] = parts;

    if (Math.abs(latitude) > 90 || Math.abs(longitude) > 180) {
        return null;
    }

    return { latitude, longitude };
}

export function formatCoordinates(c: Coordinates): string {
    return `${c.latitude.toFixed(7)}, ${c.longitude.toFixed(7)}`;
}

export function googleMapsUrl(c: Coordinates): string {
    return `https://www.google.com/maps/search/?api=1&query=${c.latitude},${c.longitude}`;
}

/** A readable message for a browser geolocation error. */
export function geolocationErrorMessage(
    error: GeolocationPositionError,
): string {
    switch (error.code) {
        case error.PERMISSION_DENIED:
            return 'Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini di pengaturan browser.';
        case error.POSITION_UNAVAILABLE:
            return 'Lokasi tidak tersedia. Pastikan GPS aktif dan coba lagi.';
        case error.TIMEOUT:
            return 'Lokasi terlalu lama didapat. Coba lagi di area terbuka.';
        default:
            return 'Gagal membaca lokasi.';
    }
}
