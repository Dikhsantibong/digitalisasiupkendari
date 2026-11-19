/**
 * Payload of a Laporan UL (App\Services\Reports\UlReport::build) as read by
 * the PPT bulanan (lib/ul-ppt.ts, from LaporanUlController::pptData).
 */

export type UlKpi = {
    label: string;
    value: string;
    previous: string;
    /** Average per unit under the UL (mean of the units' own values). */
    average?: string;
    delta: number | null;
    trend: 'naik' | 'turun' | 'tetap' | null;
    assessment: 'membaik' | 'memburuk' | 'stabil' | null;
};

export type UlChart = {
    title: string;
    subtitle?: string;
    type: string;
    /** 3D PNG (data URI) drawn by App\Services\Reports\Chart3d. */
    image: string;
};

export type UlTable = {
    title: string;
    columns: string[];
    rows: string[][];
    footer?: string[];
    note?: string;
};

export type UlPart = {
    key: string;
    title: string;
    kpis: UlKpi[];
    insights: {
        tone: 'success' | 'danger' | 'warning' | 'info' | 'neutral';
        text: string;
    }[];
    charts: UlChart[];
    unit_table: UlTable | null;
    sections: UlTable[];
};

export type UlReportData = {
    kind: string;
    title: string;
    ul: { id: number; name: string };
    units: { id: number; name: string }[];
    period: { month: number; year: number; label: string };
    meta: { label: string; value: string }[];
    parts: UlPart[];
    reports: UlTable;
    completeness: UlTable;
};

/** Width & height of a PNG data URI (read from its IHDR header). */
export function pngSize(dataUri: string): { width: number; height: number } {
    const base64 = dataUri.slice(
        dataUri.indexOf(',') + 1,
        dataUri.indexOf(',') + 1 + 44,
    );
    const bytes = atob(base64);
    const read = (offset: number) =>
        ((bytes.charCodeAt(offset) << 24) |
            (bytes.charCodeAt(offset + 1) << 16) |
            (bytes.charCodeAt(offset + 2) << 8) |
            bytes.charCodeAt(offset + 3)) >>>
        0;

    return { width: read(16) || 1000, height: read(20) || 440 };
}

/** "▲ 12,3% vs bulan lalu" for a KPI. */
export function kpiTrend(kpi: UlKpi): string {
    if (kpi.delta === null) {
        return `Bulan lalu: ${kpi.previous}`;
    }

    const arrow =
        kpi.trend === 'naik' ? '▲' : kpi.trend === 'turun' ? '▼' : '■';

    return `${arrow} ${Math.abs(kpi.delta).toLocaleString('id-ID', { maximumFractionDigits: 1 })}% vs bulan lalu (${kpi.previous})`;
}
