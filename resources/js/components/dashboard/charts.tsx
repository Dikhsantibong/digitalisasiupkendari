/**
 * Dependency-free SVG charts for the executive dashboard. Colours come from
 * Tailwind text utilities driven through `currentColor`, so every chart follows
 * the corporate palette (--chart-*) and stays theme-aware.
 */
import { useId, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

const TAU = Math.PI * 2;

function useSvgUid(prefix: string): string {
    const raw = useId().replace(/[^a-zA-Z0-9_-]/g, '');

    return `${prefix}-${raw}`;
}

function intersectArcRanges(
    start: number,
    end: number,
    winStart: number,
    winEnd: number,
): [number, number][] {
    const spans: [number, number][] = [];

    for (let k = -1; k <= 2; k++) {
        const a = Math.max(start, winStart + k * TAU);
        const b = Math.min(end, winEnd + k * TAU);

        if (b - a > 1e-4) {
            spans.push([a, b]);
        }
    }

    return spans;
}

function ellipsePoint(
    cx: number,
    cy: number,
    rx: number,
    ry: number,
    angle: number,
): [number, number] {
    return [cx + rx * Math.cos(angle), cy + ry * Math.sin(angle)];
}

function fmtPt([x, y]: [number, number]): string {
    return `${x.toFixed(2)} ${y.toFixed(2)}`;
}

function donutWallPath(
    cx: number,
    cy: number,
    rx: number,
    ry: number,
    depth: number,
    a: number,
    b: number,
): string {
    const topA = ellipsePoint(cx, cy, rx, ry, a);
    const topB = ellipsePoint(cx, cy, rx, ry, b);
    const botA: [number, number] = [topA[0], topA[1] + depth];
    const botB: [number, number] = [topB[0], topB[1] + depth];

    return `M ${fmtPt(topA)} A ${rx} ${ry} 0 0 1 ${fmtPt(topB)} L ${fmtPt(botB)} A ${rx} ${ry} 0 0 0 ${fmtPt(botA)} Z`;
}

function donutCutWallPath(
    cx: number,
    cy: number,
    rx: number,
    ry: number,
    irx: number,
    iry: number,
    depth: number,
    angle: number,
): string {
    const outTop = ellipsePoint(cx, cy, rx, ry, angle);
    const inTop = ellipsePoint(cx, cy, irx, iry, angle);

    return `M ${fmtPt(inTop)} L ${fmtPt(outTop)} L ${outTop[0].toFixed(2)} ${(outTop[1] + depth).toFixed(2)} L ${inTop[0].toFixed(2)} ${(inTop[1] + depth).toFixed(2)} Z`;
}

function donutTopPath(
    cx: number,
    cy: number,
    rx: number,
    ry: number,
    irx: number,
    iry: number,
    start: number,
    end: number,
): { d: string; evenOdd: boolean } {
    const sweep = end - start;

    if (sweep >= TAU - 1e-4) {
        return {
            evenOdd: true,
            d: [
                `M ${cx + rx} ${cy}`,
                `A ${rx} ${ry} 0 1 1 ${cx - rx} ${cy}`,
                `A ${rx} ${ry} 0 1 1 ${cx + rx} ${cy} Z`,
                `M ${cx + irx} ${cy}`,
                `A ${irx} ${iry} 0 1 0 ${cx - irx} ${cy}`,
                `A ${irx} ${iry} 0 1 0 ${cx + irx} ${cy} Z`,
            ].join(' '),
        };
    }

    const large = sweep > Math.PI ? 1 : 0;
    const outStart = ellipsePoint(cx, cy, rx, ry, start);
    const outEnd = ellipsePoint(cx, cy, rx, ry, end);
    const inEnd = ellipsePoint(cx, cy, irx, iry, end);
    const inStart = ellipsePoint(cx, cy, irx, iry, start);

    return {
        evenOdd: false,
        d: `M ${fmtPt(outStart)} A ${rx} ${ry} 0 ${large} 1 ${fmtPt(outEnd)} L ${fmtPt(inEnd)} A ${irx} ${iry} 0 ${large} 0 ${fmtPt(inStart)} Z`,
    };
}

/** 3D stage floor slab used behind Bar, Line, and S-Curve SVG charts. */
function StageFloor3d({
    left,
    right,
    top,
    bottom,
    dx = 6,
    dy = -4.5,
}: {
    left: number;
    right: number;
    top: number;
    bottom: number;
    dx?: number;
    dy?: number;
}) {
    const backLeft = left + dx;
    const backRight = right + dx;
    const backBottom = bottom + dy;
    const backTop = top + dy;

    return (
        <g pointerEvents="none">
            <line
                x1={left}
                y1={bottom}
                x2={backLeft}
                y2={backBottom}
                className="text-border"
                stroke="currentColor"
                strokeOpacity={0.7}
            />
            <line
                x1={backLeft}
                y1={backTop}
                x2={backLeft}
                y2={backBottom}
                className="text-border"
                stroke="currentColor"
                strokeOpacity={0.5}
                strokeDasharray="2 3"
            />
            <polygon
                points={`${left},${bottom} ${right},${bottom} ${backRight},${backBottom} ${backLeft},${backBottom}`}
                className="text-muted"
                fill="currentColor"
                opacity={0.65}
            />
            <rect
                x={left}
                y={bottom}
                width={Math.max(0, right - left)}
                height={3}
                className="text-border"
                fill="currentColor"
                opacity={0.75}
            />
        </g>
    );
}

/** Single 3D extruded prism bar supporting grouped and stacked towers. */
function BarPrism({
    x,
    top,
    width,
    height,
    dx,
    dy,
    colorClass,
    opacity,
    uid,
}: {
    x: number;
    top: number;
    width: number;
    height: number;
    dx: number;
    dy: number;
    colorClass: string;
    opacity: number;
    uid: string;
}) {
    if (height <= 0.2 || width <= 0.2) {
        return null;
    }

    const x2 = x + width;
    const bottom = top + height;
    const topPoly = `${x},${top} ${x + dx},${top + dy} ${x2 + dx},${top + dy} ${x2},${top}`;
    const sidePoly = `${x2},${top} ${x2 + dx},${top + dy} ${x2 + dx},${bottom + dy} ${x2},${bottom}`;

    return (
        <g className={colorClass} opacity={opacity}>
            <polygon points={sidePoly} fill="currentColor" />
            <polygon points={sidePoly} fill={`url(#${uid}-side)`} />

            <polygon points={topPoly} fill="currentColor" />
            <polygon points={topPoly} fill={`url(#${uid}-top)`} />

            <rect
                x={x}
                y={top}
                width={width}
                height={height}
                fill="currentColor"
            />
            <rect
                x={x}
                y={top}
                width={width}
                height={height}
                fill={`url(#${uid}-front)`}
            />
            <line
                x1={x}
                y1={top + 0.4}
                x2={x2}
                y2={top + 0.4}
                stroke="#ffffff"
                strokeOpacity={0.42}
                strokeWidth={0.8}
            />
        </g>
    );
}

export const SERIES = [
    'text-chart-1',
    'text-chart-4',
    'text-emerald-500',
    'text-chart-5',
    'text-rose-500',
    'text-chart-3',
];

export type Datum = { label: string; value: number };
export type MultiSeries = {
    labels: string[];
    series: { name: string; values: number[] }[];
};
export type ValueFormat =
    'number' | 'decimal' | 'decimal3' | 'rupiah' | 'percent';

export function formatValue(
    value: number,
    format: ValueFormat = 'number',
): string {
    switch (format) {
        case 'rupiah':
            return formatRupiahShort(value);
        case 'decimal':
            return value.toLocaleString('id-ID', {
                minimumFractionDigits: 1,
                maximumFractionDigits: 1,
            });
        case 'decimal3':
            return value.toLocaleString('id-ID', {
                minimumFractionDigits: 3,
                maximumFractionDigits: 3,
            });
        case 'percent':
            return `${value.toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`;
        default:
            return Math.round(value).toLocaleString('id-ID');
    }
}

/** Compact rupiah, e.g. "Rp 486,8 Jt" / "Rp 3,85 M". */
export function formatRupiahShort(value: number): string {
    if (Math.abs(value) >= 1e9) {
        return `Rp ${(value / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 2 })} M`;
    }

    if (Math.abs(value) >= 1e6) {
        return `Rp ${(value / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 1 })} Jt`;
    }

    return `Rp ${Math.round(value).toLocaleString('id-ID')}`;
}

/** Rounds an axis maximum up to a tidy number. */
function niceMax(value: number): number {
    if (value <= 0) {
        return 1;
    }

    const magnitude = 10 ** Math.floor(Math.log10(value));
    const step = [1, 2, 2.5, 5, 10].find((s) => s * magnitude >= value) ?? 10;

    return step * magnitude;
}

export function Panel({
    title,
    subtitle,
    action,
    className,
    children,
}: {
    title: string;
    subtitle?: string;
    action?: ReactNode;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            className={cn(
                'flex min-w-0 flex-col gap-3 rounded-md border border-border bg-card p-4',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h3 className="truncate text-[13px] font-semibold text-foreground">
                        {title}
                    </h3>
                    {subtitle && (
                        <p className="truncate text-[11.5px] text-muted-foreground">
                            {subtitle}
                        </p>
                    )}
                </div>
                {action}
            </div>
            {children}
        </div>
    );
}

export function Legend({
    items,
}: {
    items: { label: string; colorClass: string; dashed?: boolean }[];
}) {
    return (
        <div className="flex flex-wrap gap-x-4 gap-y-1">
            {items.map((item) => (
                <span
                    key={item.label}
                    className="inline-flex items-center gap-1.5 text-[11.5px] text-muted-foreground"
                >
                    <span
                        className={cn(
                            'inline-block h-2 w-3.5 rounded-[2px]',
                            item.colorClass,
                        )}
                        style={
                            item.dashed
                                ? {
                                      borderTop: '2px dashed currentColor',
                                      height: 0,
                                  }
                                : { backgroundColor: 'currentColor' }
                        }
                    />
                    {item.label}
                </span>
            ))}
        </div>
    );
}

function Tooltip({
    x,
    y,
    width,
    children,
}: {
    x: number;
    y: number;
    width: number;
    children: ReactNode;
}) {
    const pct = (x / width) * 100;
    const alignX =
        pct < 12
            ? '-translate-x-2'
            : pct > 88
              ? '-translate-x-[calc(100%-0.5rem)]'
              : '-translate-x-1/2';

    return (
        <div
            className={cn(
                'pointer-events-none absolute z-10 -translate-y-full rounded-md border border-border bg-popover px-2.5 py-1.5 text-[11px] whitespace-nowrap text-popover-foreground shadow-md',
                alignX,
            )}
            style={{ left: `${pct}%`, top: y }}
        >
            {children}
        </div>
    );
}

/** A tiny 3D ribbon trend line for KPI tiles. */
export function Sparkline({
    values,
    className,
}: {
    values: number[];
    className?: string;
}) {
    const uid = useSvgUid('spark');
    const W = 100;
    const H = 28;

    if (values.length === 0) {
        return <div className={cn('h-7 w-full', className)} aria-hidden />;
    }

    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    const x = (i: number) => (i / Math.max(1, values.length - 1)) * W;
    const y = (v: number) => H - 4 - ((v - min) / range) * (H - 7);
    const pts = values.map((v, i) => [x(i), y(v)] as const);
    const path = pts
        .map(
            ([px, py], i) =>
                `${i === 0 ? 'M' : 'L'}${px.toFixed(1)} ${py.toFixed(1)}`,
        )
        .join(' ');
    const shadowPath = pts
        .map(
            ([px, py], i) =>
                `${i === 0 ? 'M' : 'L'}${(px + 1.4).toFixed(1)} ${(py + 2.2).toFixed(1)}`,
        )
        .join(' ');
    const ribbonSide =
        pts.length >= 2
            ? `${path} L${pts
                  .map(
                      ([px, py]) =>
                          `${(px + 1.4).toFixed(1)} ${(py + 2.2).toFixed(1)}`,
                  )
                  .reverse()
                  .join(' L')} Z`
            : '';

    return (
        <svg
            viewBox={`0 0 ${W} ${H}`}
            className={cn('h-7 w-full', className)}
            preserveAspectRatio="none"
            aria-hidden
        >
            <defs>
                <linearGradient id={`${uid}-area`} x1="0" y1="0" x2="0" y2="1">
                    <stop
                        offset="0%"
                        stopColor="currentColor"
                        stopOpacity={0.28}
                    />
                    <stop
                        offset="100%"
                        stopColor="currentColor"
                        stopOpacity={0.02}
                    />
                </linearGradient>
            </defs>
            <path
                d={`${path} L${W} ${H} L0 ${H} Z`}
                fill={`url(#${uid}-area)`}
            />
            <path
                d={shadowPath}
                fill="none"
                stroke="#000000"
                strokeOpacity={0.14}
                strokeWidth={2}
                vectorEffect="non-scaling-stroke"
            />
            {ribbonSide && (
                <>
                    <path d={ribbonSide} fill="currentColor" opacity={0.85} />
                    <path d={ribbonSide} fill="#000000" opacity={0.24} />
                </>
            )}
            <path
                d={path}
                fill="none"
                stroke="currentColor"
                strokeWidth={1.8}
                vectorEffect="non-scaling-stroke"
            />
        </svg>
    );
}

/** Tilted 3D donut chart with centred total and a percentage legend. */
export function Donut({
    data,
    centerLabel = 'Total',
    colors = SERIES,
}: {
    data: Datum[];
    centerLabel?: string;
    colors?: string[];
}) {
    const uid = useSvgUid('donut');
    const [active, setActive] = useState<number | null>(null);
    const total = data.reduce((s, d) => s + d.value, 0);
    const focus = active !== null ? data[active] : null;

    if (total === 0) {
        return (
            <p className="py-8 text-center text-[13px] text-muted-foreground">
                Belum ada data.
            </p>
        );
    }

    const cx = 66;
    const cy = 54;
    const rx = 56;
    const ry = 39;
    const irx = 37;
    const iry = 25;
    const depth = 11;
    const valueText = (focus?.value ?? total).toLocaleString('id-ID');
    const labelText = focus?.label ?? centerLabel;
    const valueFontSize =
        valueText.length <= 3
            ? 17
            : valueText.length === 4
              ? 15.5
              : valueText.length <= 6
                ? 13.5
                : valueText.length <= 8
                  ? 11.5
                  : 10;
    const labelFontSize = labelText.length > 14 ? 7.5 : 8.5;

    type Slice3d = {
        index: number;
        label: string;
        value: number;
        colorClass: string;
        start: number;
        end: number;
        ox: number;
        oy: number;
        innerSpans: [number, number][];
        outerSpans: [number, number][];
        top: { d: string; evenOdd: boolean };
    };

    const single = data.filter((d) => d.value > 0).length <= 1;
    const slices: Slice3d[] = [];
    let angle = -Math.PI / 2;

    data.forEach((d, i) => {
        if (d.value <= 0) {
            return;
        }

        const sweep = (d.value / total) * TAU;
        const start = angle;
        const end = angle + sweep;
        angle = end;

        const mid = (start + end) / 2;
        const lift = !single && active === i ? 1 : 0;
        const ox = lift * Math.cos(mid) * 4.2;
        const oy = lift * Math.sin(mid) * 2.8;

        slices.push({
            index: i,
            label: d.label,
            value: d.value,
            colorClass: colors[i % colors.length],
            start,
            end,
            ox,
            oy,
            innerSpans: intersectArcRanges(start, end, Math.PI, TAU),
            outerSpans: intersectArcRanges(start, end, 0, Math.PI),
            top: donutTopPath(cx + ox, cy + oy, rx, ry, irx, iry, start, end),
        });
    });

    const activeSlice = slices.find((s) => s.index === active) ?? null;
    const passiveSlices = slices.filter((s) => s.index !== active);
    const activeMidSin = activeSlice
        ? Math.sin((activeSlice.start + activeSlice.end) / 2)
        : 0;
    const orderedSlices =
        activeSlice && activeMidSin < 0
            ? [activeSlice, ...passiveSlices]
            : activeSlice
              ? [...passiveSlices, activeSlice]
              : slices;

    const renderSliceInnerWall = (sl: Slice3d) =>
        sl.innerSpans.map(([a, b], k) => {
            const pathD = donutWallPath(
                cx + sl.ox,
                cy + sl.oy,
                irx,
                iry,
                depth,
                a,
                b,
            );

            return (
                <g
                    key={`in-${sl.index}-${k}`}
                    className={cn(sl.colorClass, 'cursor-pointer')}
                    opacity={active === null || active === sl.index ? 1 : 0.4}
                    onMouseEnter={() => setActive(sl.index)}
                    onMouseLeave={() => setActive(null)}
                >
                    <path d={pathD} fill="currentColor" />
                    <path d={pathD} fill={`url(#${uid}-inner)`} />
                </g>
            );
        });

    const renderSliceCutWalls = (sl: Slice3d) => {
        if (sl.end - sl.start >= TAU - 1e-4) {
            return null;
        }

        const cuts: string[] = [];

        if (Math.cos(sl.start) < 0) {
            cuts.push(
                donutCutWallPath(
                    cx + sl.ox,
                    cy + sl.oy,
                    rx,
                    ry,
                    irx,
                    iry,
                    depth,
                    sl.start,
                ),
            );
        }

        if (Math.cos(sl.end) > 0) {
            cuts.push(
                donutCutWallPath(
                    cx + sl.ox,
                    cy + sl.oy,
                    rx,
                    ry,
                    irx,
                    iry,
                    depth,
                    sl.end,
                ),
            );
        }

        return cuts.map((pathD, k) => (
            <g key={`cut-${sl.index}-${k}`} className={sl.colorClass}>
                <path d={pathD} fill="currentColor" />
                <path d={pathD} fill="#000000" fillOpacity={0.28} />
            </g>
        ));
    };

    const renderSliceOuterAndTop = (sl: Slice3d) => (
        <g
            key={`face-${sl.index}`}
            className={cn(sl.colorClass, 'cursor-pointer transition-opacity')}
            opacity={active === null || active === sl.index ? 1 : 0.4}
            onMouseEnter={() => setActive(sl.index)}
            onMouseLeave={() => setActive(null)}
        >
            {sl.outerSpans.map(([a, b], k) => {
                const pathD = donutWallPath(
                    cx + sl.ox,
                    cy + sl.oy,
                    rx,
                    ry,
                    depth,
                    a,
                    b,
                );

                return (
                    <g key={`out-${sl.index}-${k}`}>
                        <path d={pathD} fill="currentColor" />
                        <path d={pathD} fill={`url(#${uid}-outer)`} />
                    </g>
                );
            })}
            <path
                d={sl.top.d}
                fillRule={sl.top.evenOdd ? 'evenodd' : undefined}
                fill="currentColor"
            />
            <path
                d={sl.top.d}
                fillRule={sl.top.evenOdd ? 'evenodd' : undefined}
                fill={`url(#${uid}-top)`}
                stroke="#ffffff"
                strokeOpacity={0.28}
                strokeWidth={0.65}
            />
        </g>
    );

    return (
        <div className="flex items-center gap-4">
            <svg viewBox="0 0 132 132" className="size-32 shrink-0" role="img">
                <defs>
                    <radialGradient
                        id={`${uid}-shadow`}
                        cx="50%"
                        cy="50%"
                        r="50%"
                    >
                        <stop offset="0%" stopColor="#000000" stopOpacity={0} />
                        <stop
                            offset="48%"
                            stopColor="#000000"
                            stopOpacity={0}
                        />
                        <stop
                            offset="72%"
                            stopColor="#000000"
                            stopOpacity={0.18}
                        />
                        <stop
                            offset="100%"
                            stopColor="#000000"
                            stopOpacity={0}
                        />
                    </radialGradient>
                    <radialGradient id={`${uid}-top`} cx="34%" cy="28%" r="74%">
                        <stop
                            offset="0%"
                            stopColor="#ffffff"
                            stopOpacity={0.34}
                        />
                        <stop
                            offset="56%"
                            stopColor="#ffffff"
                            stopOpacity={0.05}
                        />
                        <stop
                            offset="100%"
                            stopColor="#000000"
                            stopOpacity={0.1}
                        />
                    </radialGradient>
                    <linearGradient
                        id={`${uid}-outer`}
                        gradientUnits="userSpaceOnUse"
                        x1={cx - rx}
                        y1={0}
                        x2={cx + rx}
                        y2={0}
                    >
                        <stop
                            offset="0%"
                            stopColor="#000000"
                            stopOpacity={0.26}
                        />
                        <stop
                            offset="28%"
                            stopColor="#000000"
                            stopOpacity={0.12}
                        />
                        <stop
                            offset="72%"
                            stopColor="#000000"
                            stopOpacity={0.32}
                        />
                        <stop
                            offset="100%"
                            stopColor="#000000"
                            stopOpacity={0.48}
                        />
                    </linearGradient>
                    <linearGradient
                        id={`${uid}-inner`}
                        gradientUnits="userSpaceOnUse"
                        x1={cx - irx}
                        y1={0}
                        x2={cx + irx}
                        y2={0}
                    >
                        <stop
                            offset="0%"
                            stopColor="#000000"
                            stopOpacity={0.46}
                        />
                        <stop
                            offset="70%"
                            stopColor="#000000"
                            stopOpacity={0.18}
                        />
                        <stop
                            offset="100%"
                            stopColor="#000000"
                            stopOpacity={0.34}
                        />
                    </linearGradient>
                </defs>
                <ellipse
                    cx={cx}
                    cy={cy + depth + 5}
                    rx={rx + 2}
                    ry={ry}
                    fill={`url(#${uid}-shadow)`}
                />
                {orderedSlices.map(renderSliceInnerWall)}
                {activeSlice && renderSliceCutWalls(activeSlice)}
                {orderedSlices.map(renderSliceOuterAndTop)}
                <text
                    x={cx}
                    y={61.5}
                    textAnchor="middle"
                    className="fill-foreground"
                    stroke="var(--card)"
                    strokeWidth={3}
                    strokeLinejoin="round"
                    style={{ paintOrder: 'stroke fill' }}
                    fontSize={valueFontSize}
                    fontWeight={700}
                >
                    {valueText}
                </text>
                <text
                    x={cx}
                    y={72.5}
                    textAnchor="middle"
                    className="fill-muted-foreground"
                    stroke="var(--card)"
                    strokeWidth={2.2}
                    strokeLinejoin="round"
                    style={{ paintOrder: 'stroke fill' }}
                    fontSize={labelFontSize}
                >
                    {labelText}
                </text>
            </svg>
            <ul className="flex min-w-0 flex-1 flex-col gap-1.5">
                {data.map((d, i) => (
                    <li
                        key={d.label}
                        className="flex items-center gap-2 text-[12px]"
                        onMouseEnter={() => setActive(i)}
                        onMouseLeave={() => setActive(null)}
                    >
                        <span
                            className={cn(
                                'size-2.5 shrink-0 rounded-sm shadow-xs',
                                colors[i % colors.length],
                            )}
                            style={{
                                backgroundColor: 'currentColor',
                                backgroundImage:
                                    'linear-gradient(180deg, rgba(255,255,255,0.38), rgba(0,0,0,0.18))',
                            }}
                        />
                        <span className="truncate text-foreground">
                            {d.label}
                        </span>
                        <span className="ml-auto font-semibold text-foreground tabular-nums">
                            {d.value.toLocaleString('id-ID')}
                        </span>
                        <span className="w-9 text-right text-muted-foreground tabular-nums">
                            {Math.round((d.value / total) * 100)}%
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

type BarMode = 'grouped' | 'stacked';

/**
 * Vertical bars for one or more series (grouped or stacked), optionally with
 * the last series drawn as a line on a secondary axis.
 */
export function BarsChart({
    data,
    mode = 'grouped',
    lineLast = false,
    format = 'number',
    lineFormat = 'number',
    colors = SERIES,
    height = 'h-52',
    viewWidth = 560,
    rotateLabels = false,
}: {
    data: MultiSeries;
    mode?: BarMode;
    lineLast?: boolean;
    format?: ValueFormat;
    lineFormat?: ValueFormat;
    colors?: string[];
    height?: string;
    viewWidth?: number;
    rotateLabels?: boolean;
}) {
    const uid = useSvgUid('bars');
    const [hover, setHover] = useState<number | null>(null);
    const W = viewWidth;
    const H = rotateLabels ? 264 : 230;
    const pad = {
        l: rotateLabels ? 54 : format === 'decimal3' ? 48 : 44,
        r: lineLast ? 44 : 12,
        t: 12,
        b: rotateLabels ? 64 : 24,
    };
    const innerW = W - pad.l - pad.r;
    const innerH = H - pad.t - pad.b;
    const barSeries = lineLast ? data.series.slice(0, -1) : data.series;
    const lineSeries = lineLast ? data.series[data.series.length - 1] : null;
    const n = data.labels.length || 1;
    const slot = innerW / n;

    const barTotals = data.labels.map((_, i) =>
        mode === 'stacked'
            ? barSeries.reduce((s, se) => s + (se.values[i] ?? 0), 0)
            : Math.max(...barSeries.map((se) => se.values[i] ?? 0)),
    );
    const max = niceMax(Math.max(0, ...barTotals));
    const lineMax = lineSeries ? niceMax(Math.max(0, ...lineSeries.values)) : 1;
    const y = (v: number) => pad.t + innerH - (v / max) * innerH;
    const yLine = (v: number) => pad.t + innerH - (v / lineMax) * innerH;
    const cx = (i: number) => pad.l + slot * i + slot / 2;
    const groupW = Math.min(
        slot * 0.72,
        mode === 'stacked' ? 30 : 16 * barSeries.length,
    );
    const barW = mode === 'stacked' ? groupW : groupW / barSeries.length;
    const labelEvery = rotateLabels ? 1 : Math.ceil(n / 12);
    const grid = [0, 0.25, 0.5, 0.75, 1];
    const short = (v: number, f: ValueFormat) =>
        f === 'rupiah'
            ? formatRupiahShort(v).replace('Rp ', '')
            : formatValue(v, f);
    const stageDx = 6;
    const stageDy = -4.5;
    const linePts = lineSeries
        ? lineSeries.values.map((v, i) => [cx(i), yLine(v)] as const)
        : [];
    const linePath = linePts
        .map(
            ([px, py], i) =>
                `${i === 0 ? 'M' : 'L'}${px.toFixed(1)} ${py.toFixed(1)}`,
        )
        .join(' ');
    const lineShadowPath = linePts
        .map(
            ([px, py], i) =>
                `${i === 0 ? 'M' : 'L'}${(px + 1.5).toFixed(1)} ${(py + 2.4).toFixed(1)}`,
        )
        .join(' ');

    return (
        <div className="flex flex-col gap-2">
            <div className="relative">
                <svg
                    viewBox={`0 0 ${W} ${H}`}
                    className={cn('w-full', height)}
                    preserveAspectRatio="none"
                    role="img"
                    onMouseLeave={() => setHover(null)}
                >
                    <defs>
                        <linearGradient
                            id={`${uid}-front`}
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="0%"
                                stopColor="#ffffff"
                                stopOpacity={0.22}
                            />
                            <stop
                                offset="45%"
                                stopColor="#ffffff"
                                stopOpacity={0.03}
                            />
                            <stop
                                offset="100%"
                                stopColor="#000000"
                                stopOpacity={0.2}
                            />
                        </linearGradient>
                        <linearGradient
                            id={`${uid}-top`}
                            x1="0"
                            y1="0"
                            x2="1"
                            y2="1"
                        >
                            <stop
                                offset="0%"
                                stopColor="#ffffff"
                                stopOpacity={0.44}
                            />
                            <stop
                                offset="100%"
                                stopColor="#ffffff"
                                stopOpacity={0.24}
                            />
                        </linearGradient>
                        <linearGradient
                            id={`${uid}-side`}
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="0%"
                                stopColor="#000000"
                                stopOpacity={0.26}
                            />
                            <stop
                                offset="100%"
                                stopColor="#000000"
                                stopOpacity={0.44}
                            />
                        </linearGradient>
                    </defs>
                    <StageFloor3d
                        left={pad.l}
                        right={W - pad.r}
                        top={pad.t}
                        bottom={pad.t + innerH}
                        dx={stageDx}
                        dy={stageDy}
                    />
                    {grid.map((f) => {
                        const gy = y(max * f);

                        return (
                            <g key={f}>
                                <path
                                    d={`M${pad.l} ${gy} L${pad.l + stageDx} ${gy + stageDy} L${W - pad.r + stageDx} ${gy + stageDy}`}
                                    fill="none"
                                    className="text-border"
                                    stroke="currentColor"
                                    strokeOpacity={f === 0 ? 0.85 : 0.7}
                                    strokeDasharray={
                                        f === 0 ? undefined : '3 3'
                                    }
                                />
                                <text
                                    x={pad.l - 6}
                                    y={gy + 3}
                                    textAnchor="end"
                                    className="fill-muted-foreground"
                                    fontSize={9}
                                >
                                    {short(max * f, format)}
                                </text>
                                {lineSeries && (
                                    <text
                                        x={W - pad.r + 8}
                                        y={yLine(lineMax * f) + 3}
                                        className="fill-muted-foreground"
                                        fontSize={9}
                                    >
                                        {short(lineMax * f, lineFormat)}
                                    </text>
                                )}
                            </g>
                        );
                    })}
                    {hover !== null && (
                        <polygon
                            points={`${pad.l + slot * hover},${pad.t + innerH} ${pad.l + slot * (hover + 1)},${pad.t + innerH} ${pad.l + slot * (hover + 1) + stageDx},${pad.t + innerH + stageDy} ${pad.l + slot * hover + stageDx},${pad.t + innerH + stageDy}`}
                            className="text-muted"
                            fill="currentColor"
                            opacity={0.75}
                        />
                    )}
                    {hover !== null && (
                        <rect
                            x={pad.l + slot * hover + stageDx}
                            y={pad.t + stageDy}
                            width={slot}
                            height={innerH}
                            className="text-muted"
                            fill="currentColor"
                            opacity={0.35}
                        />
                    )}
                    {data.labels.map((label, i) => {
                        let stackBase = 0;

                        return (
                            <g key={label}>
                                {barSeries.map((se, s) => {
                                    const v = se.values[i] ?? 0;
                                    const x =
                                        mode === 'stacked'
                                            ? cx(i) - barW / 2
                                            : cx(i) - groupW / 2 + s * barW;
                                    const top =
                                        mode === 'stacked'
                                            ? y(stackBase + v)
                                            : y(v);
                                    const bottom =
                                        mode === 'stacked'
                                            ? y(stackBase)
                                            : y(0);
                                    stackBase += v;

                                    if (v <= 0) {
                                        return null;
                                    }

                                    const drawW = Math.max(
                                        1.5,
                                        barW - (mode === 'grouped' ? 2 : 0),
                                    );
                                    const drawH = Math.max(0, bottom - top);
                                    const dx = Math.max(
                                        2,
                                        Math.min(6, drawW * 0.42),
                                    );
                                    const dy = -Math.max(
                                        1.8,
                                        Math.min(4.8, dx * 0.8),
                                    );

                                    return (
                                        <BarPrism
                                            key={se.name}
                                            x={x + (mode === 'grouped' ? 1 : 0)}
                                            top={top}
                                            width={drawW}
                                            height={drawH}
                                            dx={dx}
                                            dy={dy}
                                            colorClass={
                                                colors[s % colors.length]
                                            }
                                            opacity={
                                                hover === null || hover === i
                                                    ? 0.96
                                                    : 0.55
                                            }
                                            uid={uid}
                                        />
                                    );
                                })}
                                {i % labelEvery === 0 &&
                                    (rotateLabels ? (
                                        <text
                                            x={cx(i)}
                                            y={pad.t + innerH + 13}
                                            textAnchor="end"
                                            transform={`rotate(-26 ${cx(i)} ${pad.t + innerH + 13})`}
                                            className="fill-muted-foreground"
                                            fontSize={9}
                                        >
                                            {label.length > 18
                                                ? `${label.slice(0, 17)}…`
                                                : label}
                                        </text>
                                    ) : (
                                        <text
                                            x={cx(i)}
                                            y={H - 8}
                                            textAnchor="middle"
                                            className="fill-muted-foreground"
                                            fontSize={9}
                                        >
                                            {label}
                                        </text>
                                    ))}
                                <rect
                                    x={pad.l + slot * i}
                                    y={pad.t}
                                    width={slot}
                                    height={innerH}
                                    fill="transparent"
                                    onMouseEnter={() => setHover(i)}
                                />
                            </g>
                        );
                    })}
                    {lineSeries && (
                        <g
                            className={colors[barSeries.length % colors.length]}
                            pointerEvents="none"
                        >
                            <path
                                d={lineShadowPath}
                                fill="none"
                                stroke="#000000"
                                strokeOpacity={0.22}
                                strokeWidth={2.4}
                                vectorEffect="non-scaling-stroke"
                            />
                            <path
                                d={linePath}
                                fill="none"
                                stroke="currentColor"
                                strokeWidth={2.4}
                                vectorEffect="non-scaling-stroke"
                            />
                            {lineSeries.values.map((v, i) => {
                                const r = hover === i ? 4.4 : n > 16 ? 0 : 3.2;

                                if (r === 0) {
                                    return null;
                                }

                                const px = cx(i);
                                const py = yLine(v);

                                return (
                                    <g key={i}>
                                        <circle
                                            cx={px + 0.8}
                                            cy={py + 1.2}
                                            r={r}
                                            fill="#000000"
                                            fillOpacity={0.24}
                                        />
                                        <circle
                                            cx={px}
                                            cy={py}
                                            r={r}
                                            fill="currentColor"
                                        />
                                        <circle
                                            cx={px - r * 0.28}
                                            cy={py - r * 0.28}
                                            r={r * 0.34}
                                            fill="#ffffff"
                                            fillOpacity={0.55}
                                        />
                                    </g>
                                );
                            })}
                        </g>
                    )}
                </svg>
                {hover !== null && (
                    <Tooltip x={cx(hover)} y={8} width={W}>
                        <p className="mb-0.5 font-semibold">
                            {data.labels[hover]}
                        </p>
                        {data.series.map((se, s) => (
                            <p
                                key={se.name}
                                className="flex items-center gap-1.5"
                            >
                                <span
                                    className={cn(
                                        'size-2 rounded-sm',
                                        colors[s % colors.length],
                                    )}
                                    style={{ backgroundColor: 'currentColor' }}
                                />
                                {se.name}:{' '}
                                <span className="font-semibold tabular-nums">
                                    {formatValue(
                                        se.values[hover] ?? 0,
                                        lineSeries &&
                                            s === data.series.length - 1
                                            ? lineFormat
                                            : format,
                                    )}
                                </span>
                            </p>
                        ))}
                    </Tooltip>
                )}
            </div>
            <Legend
                items={data.series.map((se, s) => ({
                    label: se.name,
                    colorClass: colors[s % colors.length],
                }))}
            />
        </div>
    );
}

/** Multi-series 3D extruded ribbon line chart with an optional alarm threshold. */
export function LinesChart({
    data,
    threshold,
    thresholdLabel,
    colors = SERIES,
}: {
    data: MultiSeries;
    threshold?: number;
    thresholdLabel?: string;
    colors?: string[];
}) {
    const uid = useSvgUid('lines');
    const [hover, setHover] = useState<number | null>(null);
    const W = 560;
    const H = 220;
    const pad = { l: 34, r: 12, t: 12, b: 24 };
    const innerW = W - pad.l - pad.r;
    const innerH = H - pad.t - pad.b;
    const n = data.labels.length;
    const max = niceMax(
        Math.max(threshold ?? 0, ...data.series.flatMap((s) => s.values)) *
            1.08,
    );
    const x = (i: number) => pad.l + (i / Math.max(1, n - 1)) * innerW;
    const y = (v: number) => pad.t + innerH - (v / max) * innerH;
    const stageDx = 6;
    const stageDy = -4.5;
    const ribDx = 3.6;
    const ribDy = -2.6;

    return (
        <div className="flex flex-col gap-2">
            <div className="relative">
                <svg
                    viewBox={`0 0 ${W} ${H}`}
                    className="h-52 w-full"
                    preserveAspectRatio="none"
                    role="img"
                    onMouseLeave={() => setHover(null)}
                >
                    <defs>
                        <linearGradient
                            id={`${uid}-danger`}
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="0%"
                                stopColor="currentColor"
                                stopOpacity={0.12}
                            />
                            <stop
                                offset="100%"
                                stopColor="currentColor"
                                stopOpacity={0.03}
                            />
                        </linearGradient>
                    </defs>
                    <StageFloor3d
                        left={pad.l}
                        right={W - pad.r}
                        top={pad.t}
                        bottom={pad.t + innerH}
                        dx={stageDx}
                        dy={stageDy}
                    />
                    {[0, 0.25, 0.5, 0.75, 1].map((f) => {
                        const gy = y(max * f);

                        return (
                            <g key={f}>
                                <path
                                    d={`M${pad.l} ${gy} L${pad.l + stageDx} ${gy + stageDy} L${W - pad.r + stageDx} ${gy + stageDy}`}
                                    fill="none"
                                    className="text-border"
                                    stroke="currentColor"
                                    strokeOpacity={0.7}
                                    strokeDasharray={
                                        f === 0 ? undefined : '3 3'
                                    }
                                />
                                <text
                                    x={pad.l - 6}
                                    y={gy + 3}
                                    textAnchor="end"
                                    className="fill-muted-foreground"
                                    fontSize={9}
                                >
                                    {formatValue(max * f, 'decimal')}
                                </text>
                            </g>
                        );
                    })}
                    {threshold !== undefined && (
                        <g className="text-rose-500">
                            <rect
                                x={pad.l + stageDx}
                                y={pad.t + stageDy}
                                width={innerW}
                                height={Math.max(0, y(threshold) - pad.t)}
                                fill={`url(#${uid}-danger)`}
                            />
                            <path
                                d={`M${pad.l} ${y(threshold)} L${pad.l + stageDx} ${y(threshold) + stageDy} L${W - pad.r + stageDx} ${y(threshold) + stageDy}`}
                                fill="none"
                                stroke="currentColor"
                                strokeWidth={1.4}
                                strokeDasharray="6 4"
                            />
                            <text
                                x={W - pad.r - 4}
                                y={y(threshold) + stageDy - 4}
                                textAnchor="end"
                                fill="currentColor"
                                fontSize={9}
                                fontWeight={600}
                            >
                                {thresholdLabel ?? 'Batas'} {threshold}
                            </text>
                        </g>
                    )}
                    {hover !== null && (
                        <line
                            x1={x(hover)}
                            x2={x(hover)}
                            y1={pad.t}
                            y2={pad.t + innerH}
                            className="text-muted-foreground"
                            stroke="currentColor"
                            strokeDasharray="2 2"
                        />
                    )}
                    {data.series.map((se, s) => {
                        const pts = se.values.map(
                            (v, i) => [x(i), y(v)] as const,
                        );
                        const front = pts
                            .map(
                                ([px, py], i) =>
                                    `${i === 0 ? 'M' : 'L'}${px.toFixed(1)} ${py.toFixed(1)}`,
                            )
                            .join(' ');
                        const shadow = pts
                            .map(
                                ([px, py], i) =>
                                    `${i === 0 ? 'M' : 'L'}${(px + 1.5).toFixed(1)} ${(py + 2.5).toFixed(1)}`,
                            )
                            .join(' ');
                        const ribbon =
                            pts.length >= 2
                                ? `${front} L${pts
                                      .map(
                                          ([px, py]) =>
                                              `${(px + ribDx).toFixed(1)} ${(py + ribDy).toFixed(1)}`,
                                      )
                                      .reverse()
                                      .join(' L')} Z`
                                : '';

                        return (
                            <g
                                key={se.name}
                                className={colors[s % colors.length]}
                            >
                                {pts.length >= 2 && (
                                    <>
                                        <path
                                            d={shadow}
                                            fill="none"
                                            stroke="#000000"
                                            strokeOpacity={0.14}
                                            strokeWidth={2.5}
                                            vectorEffect="non-scaling-stroke"
                                        />
                                        <path
                                            d={ribbon}
                                            fill="currentColor"
                                            opacity={0.85}
                                        />
                                        <path
                                            d={ribbon}
                                            fill="#ffffff"
                                            opacity={0.28}
                                        />
                                        <path
                                            d={front}
                                            fill="none"
                                            stroke="currentColor"
                                            strokeWidth={2}
                                            vectorEffect="non-scaling-stroke"
                                        />
                                    </>
                                )}
                                {hover !== null && (
                                    <g>
                                        <circle
                                            cx={x(hover) + 0.8}
                                            cy={y(se.values[hover] ?? 0) + 1.2}
                                            r={4}
                                            fill="#000000"
                                            fillOpacity={0.24}
                                        />
                                        <circle
                                            cx={x(hover)}
                                            cy={y(se.values[hover] ?? 0)}
                                            r={4}
                                            fill="currentColor"
                                        />
                                        <circle
                                            cx={x(hover) - 1.1}
                                            cy={y(se.values[hover] ?? 0) - 1.1}
                                            r={1.3}
                                            fill="#ffffff"
                                            fillOpacity={0.6}
                                        />
                                    </g>
                                )}
                            </g>
                        );
                    })}
                    {data.labels.map((label, i) => (
                        <g key={label}>
                            {(i % Math.ceil(n / 12) === 0 || i === n - 1) && (
                                <text
                                    x={x(i)}
                                    y={H - 8}
                                    textAnchor="middle"
                                    className="fill-muted-foreground"
                                    fontSize={9}
                                >
                                    {label}
                                </text>
                            )}
                            <rect
                                x={x(i) - innerW / Math.max(1, n - 1) / 2}
                                y={pad.t}
                                width={innerW / Math.max(1, n - 1)}
                                height={innerH}
                                fill="transparent"
                                onMouseEnter={() => setHover(i)}
                            />
                        </g>
                    ))}
                </svg>
                {hover !== null && (
                    <Tooltip x={x(hover)} y={8} width={W}>
                        <p className="mb-0.5 font-semibold">
                            {data.labels[hover]}
                        </p>
                        {data.series.map((se, s) => (
                            <p
                                key={se.name}
                                className="flex items-center gap-1.5"
                            >
                                <span
                                    className={cn(
                                        'size-2 rounded-sm',
                                        colors[s % colors.length],
                                    )}
                                    style={{ backgroundColor: 'currentColor' }}
                                />
                                {se.name}:{' '}
                                <span className="font-semibold tabular-nums">
                                    {formatValue(
                                        se.values[hover] ?? 0,
                                        'decimal',
                                    )}
                                </span>
                            </p>
                        ))}
                    </Tooltip>
                )}
            </div>
            <Legend
                items={[
                    ...data.series.map((se, s) => ({
                        label: se.name,
                        colorClass: colors[s % colors.length],
                    })),
                    ...(threshold !== undefined
                        ? [
                              {
                                  label: thresholdLabel ?? 'Batas',
                                  colorClass: 'text-rose-500',
                                  dashed: true,
                              },
                          ]
                        : []),
                ]}
            />
        </div>
    );
}

type SCurvePoint = { day: number; plan: number; real: number | null };

/** Cumulative plan-vs-realisation 3D S-curve; realisation stops at today. */
export function SCurve({ points }: { points: SCurvePoint[] }) {
    const uid = useSvgUid('scurve');
    const W = 640;
    const H = 220;
    const pad = { l: 34, r: 12, t: 12, b: 24 };
    const innerW = W - pad.l - pad.r;
    const innerH = H - pad.t - pad.b;
    const days = points.length || 1;
    const max = niceMax(
        Math.max(1, ...points.map((p) => Math.max(p.plan, p.real ?? 0))),
    );
    const x = (day: number) =>
        pad.l + ((day - 1) / Math.max(1, days - 1)) * innerW;
    const y = (v: number) => pad.t + innerH - (v / max) * innerH;
    const real = points.filter(
        (p): p is { day: number; plan: number; real: number } =>
            p.real !== null,
    );
    const planPath = points
        .map(
            (p, i) =>
                `${i === 0 ? 'M' : 'L'}${x(p.day).toFixed(1)} ${y(p.plan).toFixed(1)}`,
        )
        .join(' ');
    const planShadowPath = points
        .map(
            (p, i) =>
                `${i === 0 ? 'M' : 'L'}${(x(p.day) + 1.5).toFixed(1)} ${(y(p.plan) + 2.2).toFixed(1)}`,
        )
        .join(' ');
    const realPts = real.map((p) => [x(p.day), y(p.real)] as const);
    const realPath = realPts
        .map(
            ([px, py], i) =>
                `${i === 0 ? 'M' : 'L'}${px.toFixed(1)} ${py.toFixed(1)}`,
        )
        .join(' ');
    const depthX = 5.5;
    const depthY = -4;
    const realRibbonTop =
        realPts.length >= 2
            ? `${realPath} L${realPts
                  .map(
                      ([px, py]) =>
                          `${(px + depthX).toFixed(1)} ${(py + depthY).toFixed(1)}`,
                  )
                  .reverse()
                  .join(' L')} Z`
            : '';
    const last = real[real.length - 1];
    const pct = last
        ? Math.round((last.real / Math.max(1, last.plan)) * 100)
        : 0;

    return (
        <div className="flex flex-col gap-2">
            <svg
                viewBox={`0 0 ${W} ${H}`}
                className="h-52 w-full"
                preserveAspectRatio="none"
                role="img"
            >
                <defs>
                    <linearGradient
                        id={`${uid}-real`}
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            stopColor="currentColor"
                            stopOpacity={0.34}
                        />
                        <stop
                            offset="100%"
                            stopColor="currentColor"
                            stopOpacity={0.04}
                        />
                    </linearGradient>
                </defs>
                <StageFloor3d
                    left={pad.l}
                    right={W - pad.r}
                    top={pad.t}
                    bottom={pad.t + innerH}
                    dx={depthX}
                    dy={depthY}
                />
                {[0, 0.25, 0.5, 0.75, 1].map((f) => {
                    const gy = y(max * f);

                    return (
                        <g key={f}>
                            <path
                                d={`M${pad.l} ${gy} L${pad.l + depthX} ${gy + depthY} L${W - pad.r + depthX} ${gy + depthY}`}
                                fill="none"
                                className="text-border"
                                stroke="currentColor"
                                strokeOpacity={0.7}
                                strokeDasharray={f === 0 ? undefined : '3 3'}
                            />
                            <text
                                x={pad.l - 6}
                                y={gy + 3}
                                textAnchor="end"
                                className="fill-muted-foreground"
                                fontSize={9}
                            >
                                {Math.round(max * f)}
                            </text>
                        </g>
                    );
                })}
                {[1, 8, 15, 22, days].map((d) => (
                    <text
                        key={d}
                        x={x(d)}
                        y={H - 8}
                        textAnchor="middle"
                        className="fill-muted-foreground"
                        fontSize={9}
                    >
                        {d}
                    </text>
                ))}
                <path
                    d={planShadowPath}
                    fill="none"
                    stroke="#000000"
                    strokeOpacity={0.14}
                    strokeWidth={2}
                    strokeDasharray="6 4"
                    vectorEffect="non-scaling-stroke"
                />
                <path
                    d={planPath}
                    className="text-chart-5"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2}
                    strokeDasharray="6 4"
                    vectorEffect="non-scaling-stroke"
                />
                {last && (
                    <g className="text-emerald-500">
                        <path
                            d={`${realPath} L${x(last.day)} ${y(0)} L${x(1)} ${y(0)} Z`}
                            fill={`url(#${uid}-real)`}
                        />
                        {realRibbonTop && (
                            <>
                                <path
                                    d={realRibbonTop}
                                    fill="currentColor"
                                    opacity={0.9}
                                />
                                <path
                                    d={realRibbonTop}
                                    fill="#ffffff"
                                    opacity={0.28}
                                />
                            </>
                        )}
                        <polygon
                            points={`${x(last.day)},${y(last.real)} ${x(last.day) + depthX},${y(last.real) + depthY} ${x(last.day) + depthX},${y(0) + depthY} ${x(last.day)},${y(0)}`}
                            fill="currentColor"
                            opacity={0.75}
                        />
                        <polygon
                            points={`${x(last.day)},${y(last.real)} ${x(last.day) + depthX},${y(last.real) + depthY} ${x(last.day) + depthX},${y(0) + depthY} ${x(last.day)},${y(0)}`}
                            fill="#000000"
                            opacity={0.26}
                        />
                        <path
                            d={realPath}
                            fill="none"
                            stroke="currentColor"
                            strokeWidth={2.5}
                            vectorEffect="non-scaling-stroke"
                        />
                        <line
                            x1={x(last.day)}
                            x2={x(last.day)}
                            y1={pad.t}
                            y2={pad.t + innerH}
                            className="text-muted-foreground"
                            stroke="currentColor"
                            strokeDasharray="2 3"
                        />
                        <circle
                            cx={x(last.day) + 0.8}
                            cy={y(last.real) + 1.2}
                            r={4.5}
                            fill="#000000"
                            fillOpacity={0.25}
                        />
                        <circle
                            cx={x(last.day)}
                            cy={y(last.real)}
                            r={4.5}
                            fill="currentColor"
                        />
                        <circle
                            cx={x(last.day) - 1.3}
                            cy={y(last.real) - 1.3}
                            r={1.5}
                            fill="#ffffff"
                            fillOpacity={0.65}
                        />
                    </g>
                )}
            </svg>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <Legend
                    items={[
                        {
                            label: 'Rencana kumulatif',
                            colorClass: 'text-chart-5',
                            dashed: true,
                        },
                        {
                            label: 'Realisasi kumulatif',
                            colorClass: 'text-emerald-500',
                        },
                    ]}
                />
                {last && (
                    <span className="text-[11.5px] text-muted-foreground">
                        Hari ke-{last.day}:{' '}
                        <span className="font-semibold text-foreground">
                            {last.real}
                        </span>{' '}
                        / {last.plan} kegiatan ·{' '}
                        <span
                            className={cn(
                                'font-semibold',
                                pct >= 95
                                    ? 'text-emerald-600'
                                    : pct >= 80
                                      ? 'text-amber-600'
                                      : 'text-rose-600',
                            )}
                        >
                            {pct}%
                        </span>
                    </span>
                )}
            </div>
        </div>
    );
}

/** Semicircle 3D cylindrical gauge against a target; `good` says which direction is healthy. */
export function Gauge({
    label,
    value,
    target,
    good = 'up',
    max = 100,
}: {
    label: string;
    value: number;
    target: number;
    good?: 'up' | 'down';
    max?: number;
}) {
    const ok = good === 'up' ? value >= target : value <= target;
    const r = 42;
    const half = Math.PI * r;
    const fill = Math.min(1, Math.max(0, value / max)) * half;
    const tAngle = Math.PI * (1 - Math.min(1, target / max));
    const tx = 50 + Math.cos(tAngle) * r;
    const ty = 52 - Math.sin(tAngle) * r;
    const arcD = `M ${50 - r} 52 A ${r} ${r} 0 0 1 ${50 + r} 52`;

    return (
        <div className="flex flex-col items-center">
            <svg
                viewBox="0 0 100 60"
                className="h-20 w-full max-w-36"
                role="img"
            >
                <path
                    d={arcD}
                    transform="translate(0 1.8)"
                    fill="none"
                    stroke="#000000"
                    strokeOpacity={0.14}
                    strokeWidth={9.5}
                    strokeLinecap="round"
                />
                <path
                    d={arcD}
                    fill="none"
                    className="text-muted"
                    stroke="currentColor"
                    strokeWidth={9}
                    strokeLinecap="round"
                />
                <path
                    d={arcD}
                    fill="none"
                    stroke="#000000"
                    strokeOpacity={0.1}
                    strokeWidth={3}
                    strokeLinecap="round"
                />
                {fill > 0.1 && (
                    <g className={ok ? 'text-emerald-500' : 'text-amber-500'}>
                        <g transform="translate(0 1.8)">
                            <path
                                d={arcD}
                                fill="none"
                                stroke="currentColor"
                                strokeWidth={9}
                                strokeLinecap="round"
                                strokeDasharray={`${fill} ${half}`}
                            />
                            <path
                                d={arcD}
                                fill="none"
                                stroke="#000000"
                                strokeOpacity={0.28}
                                strokeWidth={9}
                                strokeLinecap="round"
                                strokeDasharray={`${fill} ${half}`}
                            />
                        </g>
                        <path
                            d={arcD}
                            fill="none"
                            stroke="currentColor"
                            strokeWidth={9}
                            strokeLinecap="round"
                            strokeDasharray={`${fill} ${half}`}
                        />
                        <path
                            d={arcD}
                            transform="translate(0 -1.1)"
                            fill="none"
                            stroke="#ffffff"
                            strokeOpacity={0.36}
                            strokeWidth={2.4}
                            strokeLinecap="round"
                            strokeDasharray={`${fill} ${half}`}
                        />
                    </g>
                )}
                <circle
                    cx={tx}
                    cy={ty + 1}
                    r={2.9}
                    fill="#000000"
                    fillOpacity={0.26}
                />
                <circle cx={tx} cy={ty} r={2.8} className="fill-foreground" />
                <circle
                    cx={tx - 0.8}
                    cy={ty - 0.8}
                    r={0.95}
                    fill="#ffffff"
                    fillOpacity={0.65}
                />
                <text
                    x={50}
                    y={48}
                    textAnchor="middle"
                    className="fill-foreground"
                    fontSize={15}
                    fontWeight={700}
                >
                    {formatValue(value, 'decimal')}%
                </text>
            </svg>
            <p className="text-[12px] font-medium text-foreground">{label}</p>
            <p className="text-[10.5px] text-muted-foreground">
                Target {good === 'up' ? '≥' : '≤'} {target}% ·{' '}
                <span className={ok ? 'text-emerald-600' : 'text-amber-600'}>
                    {ok ? 'Tercapai' : 'Belum'}
                </span>
            </p>
        </div>
    );
}

/** Circular 3D tubular progress ring, for per-module health scores. */
export function Ring({
    value,
    colorClass,
    size = 64,
}: {
    value: number;
    colorClass: string;
    size?: number;
}) {
    const r = 26;
    const c = 2 * Math.PI * r;
    const len = (Math.min(100, Math.max(0, value)) / 100) * c;

    return (
        <svg
            viewBox="0 0 64 64"
            style={{ width: size, height: size }}
            className="shrink-0"
            role="img"
        >
            <circle
                cx={32}
                cy={33.8}
                r={r}
                fill="none"
                stroke="#000000"
                strokeOpacity={0.14}
                strokeWidth={6.5}
            />
            <circle
                cx={32}
                cy={32}
                r={r}
                fill="none"
                className="text-muted"
                stroke="currentColor"
                strokeWidth={6}
            />
            <circle
                cx={32}
                cy={32}
                r={r}
                fill="none"
                stroke="#000000"
                strokeOpacity={0.1}
                strokeWidth={2}
            />
            {len > 0.1 && (
                <g className={colorClass}>
                    <g transform="translate(0 1.6)">
                        <circle
                            cx={32}
                            cy={32}
                            r={r}
                            fill="none"
                            stroke="currentColor"
                            strokeWidth={6}
                            strokeLinecap="round"
                            strokeDasharray={`${len} ${c}`}
                            transform="rotate(-90 32 32)"
                        />
                        <circle
                            cx={32}
                            cy={32}
                            r={r}
                            fill="none"
                            stroke="#000000"
                            strokeOpacity={0.28}
                            strokeWidth={6}
                            strokeLinecap="round"
                            strokeDasharray={`${len} ${c}`}
                            transform="rotate(-90 32 32)"
                        />
                    </g>
                    <circle
                        cx={32}
                        cy={32}
                        r={r}
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={6}
                        strokeLinecap="round"
                        strokeDasharray={`${len} ${c}`}
                        transform="rotate(-90 32 32)"
                    />
                    <circle
                        cx={32}
                        cy={31.2}
                        r={r}
                        fill="none"
                        stroke="#ffffff"
                        strokeOpacity={0.38}
                        strokeWidth={1.8}
                        strokeLinecap="round"
                        strokeDasharray={`${len} ${c}`}
                        transform="rotate(-90 32 31.2)"
                    />
                </g>
            )}
            <text
                x={32}
                y={36}
                textAnchor="middle"
                className="fill-foreground"
                fontSize={13}
                fontWeight={700}
            >
                {Math.round(value)}
            </text>
        </svg>
    );
}

/** Horizontal ranked 3D cylindrical bars. */
export function BarList({
    data,
    colorClass = 'text-chart-1',
    format = 'number',
}: {
    data: Datum[];
    colorClass?: string;
    format?: ValueFormat;
}) {
    const max = Math.max(1, ...data.map((d) => d.value));

    return (
        <ul className="flex flex-col gap-2">
            {data.map((d, i) => (
                <li
                    key={d.label}
                    className="grid grid-cols-[1.25rem_1fr_auto] items-center gap-2 text-[12px]"
                >
                    <span className="text-[11px] text-muted-foreground tabular-nums">
                        {i + 1}
                    </span>
                    <div className="min-w-0">
                        <div className="mb-1 flex justify-between gap-2">
                            <span className="truncate text-foreground">
                                {d.label}
                            </span>
                        </div>
                        <div
                            className="h-1.5 overflow-hidden rounded-full bg-muted"
                            style={{
                                boxShadow:
                                    'inset 0 1.5px 2px rgba(0,0,0,0.16), inset 0 -0.5px 0 rgba(255,255,255,0.2)',
                            }}
                        >
                            <div
                                className={cn(
                                    'h-full rounded-full',
                                    colorClass,
                                )}
                                style={{
                                    width: `${(d.value / max) * 100}%`,
                                    backgroundColor: 'currentColor',
                                    backgroundImage:
                                        'linear-gradient(180deg, rgba(255,255,255,0.48) 0%, rgba(255,255,255,0.08) 45%, rgba(0,0,0,0.26) 100%)',
                                    boxShadow: '0 1px 2px rgba(0,0,0,0.2)',
                                }}
                            />
                        </div>
                    </div>
                    <span className="self-end font-semibold text-foreground tabular-nums">
                        {formatValue(d.value, format)}
                    </span>
                </li>
            ))}
        </ul>
    );
}

/** Level bars (e.g. tank fill %) styled as 3D cylindrical tubes, coloured by threshold. */
export function LevelBars({
    data,
    suffix,
}: {
    data: (Datum & { capacity?: number })[];
    suffix?: (d: Datum & { capacity?: number }) => string;
}) {
    return (
        <ul className="flex flex-col gap-3">
            {data.map((d) => {
                const tone =
                    d.value < 35
                        ? 'bg-rose-500'
                        : d.value < 55
                          ? 'bg-amber-500'
                          : 'bg-emerald-500';

                return (
                    <li key={d.label} className="text-[12px]">
                        <div className="mb-1 flex items-baseline justify-between gap-2">
                            <span className="truncate text-foreground">
                                {d.label}
                            </span>
                            <span className="shrink-0 text-muted-foreground">
                                <span className="font-semibold text-foreground tabular-nums">
                                    {d.value}%
                                </span>
                                {suffix && <> · {suffix(d)}</>}
                            </span>
                        </div>
                        <div
                            className="relative h-2.5 overflow-hidden rounded-full bg-muted"
                            style={{
                                boxShadow:
                                    'inset 0 2px 3px rgba(0,0,0,0.2), inset 0 -1px 0 rgba(255,255,255,0.22)',
                            }}
                        >
                            <div
                                className={cn('h-full rounded-full', tone)}
                                style={{
                                    width: `${d.value}%`,
                                    backgroundImage:
                                        'linear-gradient(180deg, rgba(255,255,255,0.48) 0%, rgba(255,255,255,0.12) 42%, rgba(0,0,0,0.26) 100%)',
                                    boxShadow:
                                        'inset -2px 0 3px rgba(0,0,0,0.22), 0 1px 2px rgba(0,0,0,0.18)',
                                }}
                            />
                            <span className="absolute inset-y-0 left-[35%] w-px bg-background/85 shadow-xs" />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

/** Row × column 3D beveled keycap heatmap of percentages. */
export function Heatmap({
    columns,
    rows,
}: {
    columns: string[];
    rows: { label: string; values: (number | null)[] }[];
}) {
    const shade = (v: number | null) =>
        v === null
            ? 'bg-muted text-muted-foreground'
            : v >= 90
              ? 'bg-emerald-500 text-white'
              : v >= 80
                ? 'bg-emerald-400/70 text-emerald-950'
                : v >= 70
                  ? 'bg-amber-300/80 text-amber-950'
                  : 'bg-rose-400/80 text-rose-950';

    const tileStyle = (v: number | null) =>
        v === null
            ? { boxShadow: 'inset 0 1px 2px rgba(0,0,0,0.12)' }
            : {
                  backgroundImage:
                      'linear-gradient(180deg, rgba(255,255,255,0.32) 0%, rgba(255,255,255,0) 52%, rgba(0,0,0,0.14) 100%)',
                  boxShadow:
                      'inset 0 1px 0 rgba(255,255,255,0.45), inset 0 -2px 0 rgba(0,0,0,0.22), 0 1px 2px rgba(0,0,0,0.12)',
              };

    const swatchStyle = {
        backgroundImage:
            'linear-gradient(180deg, rgba(255,255,255,0.32), rgba(0,0,0,0.14))',
        boxShadow:
            'inset 0 0.5px 0 rgba(255,255,255,0.4), inset 0 -1px 0 rgba(0,0,0,0.2)',
    };

    return (
        <div className="flex flex-col gap-2">
            <div
                className="grid gap-1"
                style={{
                    gridTemplateColumns: `minmax(6rem, 1.4fr) repeat(${columns.length}, minmax(0, 1fr))`,
                }}
            >
                <span />
                {columns.map((c) => (
                    <span
                        key={c}
                        className="text-center text-[10.5px] font-medium text-muted-foreground"
                    >
                        {c}
                    </span>
                ))}
                {rows.map((row) => (
                    <div key={row.label} className="contents">
                        <span className="self-center truncate text-[12px] text-foreground">
                            {row.label}
                        </span>
                        {row.values.map((v, i) => (
                            <span
                                key={i}
                                className={cn(
                                    'rounded-[4px] py-1.5 text-center text-[11px] font-semibold tabular-nums transition-transform hover:-translate-y-px',
                                    shade(v),
                                )}
                                style={tileStyle(v)}
                                title={`${row.label} · ${columns[i]}: ${v === null ? 'tidak ada rencana' : `${v}%`}`}
                            >
                                {v ?? '—'}
                            </span>
                        ))}
                    </div>
                ))}
            </div>
            <div className="flex flex-wrap gap-3 text-[10.5px] text-muted-foreground">
                <span className="inline-flex items-center gap-1">
                    <span
                        className="size-2 rounded-sm bg-emerald-500"
                        style={swatchStyle}
                    />
                    ≥ 90%
                </span>
                <span className="inline-flex items-center gap-1">
                    <span
                        className="size-2 rounded-sm bg-emerald-400/70"
                        style={swatchStyle}
                    />
                    80–89%
                </span>
                <span className="inline-flex items-center gap-1">
                    <span
                        className="size-2 rounded-sm bg-amber-300/80"
                        style={swatchStyle}
                    />
                    70–79%
                </span>
                <span className="inline-flex items-center gap-1">
                    <span
                        className="size-2 rounded-sm bg-rose-400/80"
                        style={swatchStyle}
                    />
                    &lt; 70%
                </span>
            </div>
        </div>
    );
}
