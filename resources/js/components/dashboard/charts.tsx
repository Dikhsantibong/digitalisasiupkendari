/**
 * Dependency-free SVG charts for the executive dashboard. Colours come from
 * Tailwind text utilities driven through `currentColor`, so every chart follows
 * the corporate palette (--chart-*) and stays theme-aware.
 */
import { useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

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
                'flex min-w-0 flex-col gap-3 rounded-lg border border-border bg-card p-4 shadow-xs',
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
    return (
        <div
            className="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full rounded-md border border-border bg-popover px-2.5 py-1.5 text-[11px] whitespace-nowrap text-popover-foreground shadow-md"
            style={{ left: `${(x / width) * 100}%`, top: y }}
        >
            {children}
        </div>
    );
}

/** A tiny trend line for KPI tiles. */
export function Sparkline({
    values,
    className,
}: {
    values: number[];
    className?: string;
}) {
    const W = 100;
    const H = 28;
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    const x = (i: number) => (i / Math.max(1, values.length - 1)) * W;
    const y = (v: number) => H - 2 - ((v - min) / range) * (H - 4);
    const path = values
        .map(
            (v, i) =>
                `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)} ${y(v).toFixed(1)}`,
        )
        .join(' ');

    return (
        <svg
            viewBox={`0 0 ${W} ${H}`}
            className={cn('h-7 w-full', className)}
            preserveAspectRatio="none"
            aria-hidden
        >
            <path
                d={`${path} L${W} ${H} L0 ${H} Z`}
                fill="currentColor"
                opacity={0.12}
            />
            <path
                d={path}
                fill="none"
                stroke="currentColor"
                strokeWidth={1.6}
                vectorEffect="non-scaling-stroke"
            />
        </svg>
    );
}

/** Donut with centred total and a percentage legend. */
export function Donut({
    data,
    centerLabel = 'Total',
    colors = SERIES,
}: {
    data: Datum[];
    centerLabel?: string;
    colors?: string[];
}) {
    const [active, setActive] = useState<number | null>(null);
    const total = data.reduce((s, d) => s + d.value, 0);
    const r = 50;
    const c = 2 * Math.PI * r;
    const gap = data.length > 1 ? 2 : 0;
    const lens = data.map((d) => (total === 0 ? 0 : (d.value / total) * c));
    const offsets = lens.map((_, i) =>
        lens.slice(0, i).reduce((s, v) => s + v, 0),
    );
    const focus = active !== null ? data[active] : null;

    if (total === 0) {
        return (
            <p className="py-8 text-center text-[13px] text-muted-foreground">
                Belum ada data.
            </p>
        );
    }

    return (
        <div className="flex items-center gap-4">
            <svg viewBox="0 0 132 132" className="size-32 shrink-0" role="img">
                <g transform="translate(66 66) rotate(-90)">
                    <circle
                        r={r}
                        fill="none"
                        className="text-muted"
                        stroke="currentColor"
                        strokeWidth={14}
                    />
                    {data.map((d, i) => (
                        <circle
                            key={d.label}
                            r={r}
                            fill="none"
                            className={cn(
                                colors[i % colors.length],
                                'cursor-pointer transition-opacity',
                            )}
                            stroke="currentColor"
                            strokeWidth={active === i ? 18 : 14}
                            strokeDasharray={`${Math.max(0, lens[i] - gap)} ${c - Math.max(0, lens[i] - gap)}`}
                            strokeDashoffset={-offsets[i]}
                            opacity={active === null || active === i ? 1 : 0.35}
                            onMouseEnter={() => setActive(i)}
                            onMouseLeave={() => setActive(null)}
                        />
                    ))}
                </g>
                <text
                    x={66}
                    y={64}
                    textAnchor="middle"
                    className="fill-foreground"
                    fontSize={22}
                    fontWeight={700}
                >
                    {(focus?.value ?? total).toLocaleString('id-ID')}
                </text>
                <text
                    x={66}
                    y={80}
                    textAnchor="middle"
                    className="fill-muted-foreground"
                    fontSize={9.5}
                >
                    {focus?.label ?? centerLabel}
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
                                'size-2.5 shrink-0 rounded-sm',
                                colors[i % colors.length],
                            )}
                            style={{ backgroundColor: 'currentColor' }}
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
}: {
    data: MultiSeries;
    mode?: BarMode;
    lineLast?: boolean;
    format?: ValueFormat;
    lineFormat?: ValueFormat;
    colors?: string[];
    height?: string;
}) {
    const [hover, setHover] = useState<number | null>(null);
    const W = 560;
    const H = 230;
    const pad = { l: 44, r: lineLast ? 40 : 10, t: 12, b: 24 };
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
        slot * 0.7,
        mode === 'stacked' ? 30 : 16 * barSeries.length,
    );
    const barW = mode === 'stacked' ? groupW : groupW / barSeries.length;
    const labelEvery = Math.ceil(n / 12);
    const grid = [0, 0.25, 0.5, 0.75, 1];
    const short = (v: number, f: ValueFormat) =>
        f === 'rupiah'
            ? formatRupiahShort(v).replace('Rp ', '')
            : formatValue(v, f === 'decimal3' ? 'decimal' : f);

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
                    {grid.map((f) => (
                        <g key={f}>
                            <line
                                x1={pad.l}
                                x2={W - pad.r}
                                y1={y(max * f)}
                                y2={y(max * f)}
                                className="text-border"
                                stroke="currentColor"
                                strokeDasharray={f === 0 ? undefined : '3 3'}
                            />
                            <text
                                x={pad.l - 6}
                                y={y(max * f) + 3}
                                textAnchor="end"
                                className="fill-muted-foreground"
                                fontSize={9}
                            >
                                {short(max * f, format)}
                            </text>
                            {lineSeries && (
                                <text
                                    x={W - pad.r + 6}
                                    y={yLine(lineMax * f) + 3}
                                    className="fill-muted-foreground"
                                    fontSize={9}
                                >
                                    {short(lineMax * f, lineFormat)}
                                </text>
                            )}
                        </g>
                    ))}
                    {hover !== null && (
                        <rect
                            x={pad.l + slot * hover}
                            y={pad.t}
                            width={slot}
                            height={innerH}
                            className="text-muted"
                            fill="currentColor"
                            opacity={0.6}
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

                                    return (
                                        <rect
                                            key={se.name}
                                            x={x + (mode === 'grouped' ? 1 : 0)}
                                            y={top}
                                            width={Math.max(
                                                1,
                                                barW -
                                                    (mode === 'grouped'
                                                        ? 2
                                                        : 0),
                                            )}
                                            height={Math.max(0, bottom - top)}
                                            rx={mode === 'stacked' ? 0 : 2}
                                            className={
                                                colors[s % colors.length]
                                            }
                                            fill="currentColor"
                                            opacity={
                                                hover === null || hover === i
                                                    ? 0.9
                                                    : 0.55
                                            }
                                        />
                                    );
                                })}
                                {i % labelEvery === 0 && (
                                    <text
                                        x={cx(i)}
                                        y={H - 8}
                                        textAnchor="middle"
                                        className="fill-muted-foreground"
                                        fontSize={9}
                                    >
                                        {label}
                                    </text>
                                )}
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
                                d={lineSeries.values
                                    .map(
                                        (v, i) =>
                                            `${i === 0 ? 'M' : 'L'}${cx(i).toFixed(1)} ${yLine(v).toFixed(1)}`,
                                    )
                                    .join(' ')}
                                fill="none"
                                stroke="currentColor"
                                strokeWidth={2.2}
                                vectorEffect="non-scaling-stroke"
                            />
                            {lineSeries.values.map((v, i) => (
                                <circle
                                    key={i}
                                    cx={cx(i)}
                                    cy={yLine(v)}
                                    r={hover === i ? 4 : n > 16 ? 0 : 2.8}
                                    fill="currentColor"
                                />
                            ))}
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

/** Multi-series line chart with an optional alarm threshold. */
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
    const [hover, setHover] = useState<number | null>(null);
    const W = 560;
    const H = 220;
    const pad = { l: 34, r: 10, t: 12, b: 24 };
    const innerW = W - pad.l - pad.r;
    const innerH = H - pad.t - pad.b;
    const n = data.labels.length;
    const max = niceMax(
        Math.max(threshold ?? 0, ...data.series.flatMap((s) => s.values)) *
            1.08,
    );
    const x = (i: number) => pad.l + (i / Math.max(1, n - 1)) * innerW;
    const y = (v: number) => pad.t + innerH - (v / max) * innerH;

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
                    {[0, 0.25, 0.5, 0.75, 1].map((f) => (
                        <g key={f}>
                            <line
                                x1={pad.l}
                                x2={W - pad.r}
                                y1={y(max * f)}
                                y2={y(max * f)}
                                className="text-border"
                                stroke="currentColor"
                                strokeDasharray={f === 0 ? undefined : '3 3'}
                            />
                            <text
                                x={pad.l - 6}
                                y={y(max * f) + 3}
                                textAnchor="end"
                                className="fill-muted-foreground"
                                fontSize={9}
                            >
                                {formatValue(max * f, 'decimal')}
                            </text>
                        </g>
                    ))}
                    {threshold !== undefined && (
                        <g className="text-rose-500">
                            <rect
                                x={pad.l}
                                y={pad.t}
                                width={innerW}
                                height={Math.max(0, y(threshold) - pad.t)}
                                fill="currentColor"
                                opacity={0.06}
                            />
                            <line
                                x1={pad.l}
                                x2={W - pad.r}
                                y1={y(threshold)}
                                y2={y(threshold)}
                                stroke="currentColor"
                                strokeWidth={1.4}
                                strokeDasharray="6 4"
                            />
                            <text
                                x={W - pad.r - 4}
                                y={y(threshold) - 4}
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
                    {data.series.map((se, s) => (
                        <g key={se.name} className={colors[s % colors.length]}>
                            <path
                                d={se.values
                                    .map(
                                        (v, i) =>
                                            `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)} ${y(v).toFixed(1)}`,
                                    )
                                    .join(' ')}
                                fill="none"
                                stroke="currentColor"
                                strokeWidth={2.2}
                                vectorEffect="non-scaling-stroke"
                            />
                            {hover !== null && (
                                <circle
                                    cx={x(hover)}
                                    cy={y(se.values[hover] ?? 0)}
                                    r={3.5}
                                    fill="currentColor"
                                />
                            )}
                        </g>
                    ))}
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

/** Cumulative plan-vs-realisation S-curve; realisation stops at today. */
export function SCurve({ points }: { points: SCurvePoint[] }) {
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
    const realPath = real
        .map(
            (p, i) =>
                `${i === 0 ? 'M' : 'L'}${x(p.day).toFixed(1)} ${y(p.real).toFixed(1)}`,
        )
        .join(' ');
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
                {[0, 0.25, 0.5, 0.75, 1].map((f) => (
                    <g key={f}>
                        <line
                            x1={pad.l}
                            x2={W - pad.r}
                            y1={y(max * f)}
                            y2={y(max * f)}
                            className="text-border"
                            stroke="currentColor"
                            strokeDasharray={f === 0 ? undefined : '3 3'}
                        />
                        <text
                            x={pad.l - 6}
                            y={y(max * f) + 3}
                            textAnchor="end"
                            className="fill-muted-foreground"
                            fontSize={9}
                        >
                            {Math.round(max * f)}
                        </text>
                    </g>
                ))}
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
                    d={planPath}
                    className="text-chart-5"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2}
                    strokeDasharray="6 4"
                    vectorEffect="non-scaling-stroke"
                />
                {last && (
                    <>
                        <path
                            d={`${realPath} L${x(last.day)} ${y(0)} L${x(1)} ${y(0)} Z`}
                            className="text-emerald-500"
                            fill="currentColor"
                            opacity={0.14}
                        />
                        <path
                            d={realPath}
                            className="text-emerald-500"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth={2.6}
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
                            cx={x(last.day)}
                            cy={y(last.real)}
                            r={4}
                            className="text-emerald-500"
                            fill="currentColor"
                        />
                    </>
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

/** Semicircle gauge against a target; `good` says which direction is healthy. */
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

    return (
        <div className="flex flex-col items-center">
            <svg
                viewBox="0 0 100 60"
                className="h-20 w-full max-w-36"
                role="img"
            >
                <path
                    d={`M ${50 - r} 52 A ${r} ${r} 0 0 1 ${50 + r} 52`}
                    fill="none"
                    className="text-muted"
                    stroke="currentColor"
                    strokeWidth={9}
                    strokeLinecap="round"
                />
                <path
                    d={`M ${50 - r} 52 A ${r} ${r} 0 0 1 ${50 + r} 52`}
                    fill="none"
                    className={ok ? 'text-emerald-500' : 'text-amber-500'}
                    stroke="currentColor"
                    strokeWidth={9}
                    strokeLinecap="round"
                    strokeDasharray={`${fill} ${half}`}
                />
                <circle cx={tx} cy={ty} r={2.6} className="fill-foreground" />
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

/** Circular progress ring, for per-module health scores. */
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

    return (
        <svg
            viewBox="0 0 64 64"
            style={{ width: size, height: size }}
            className="shrink-0"
            role="img"
        >
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
                className={colorClass}
                stroke="currentColor"
                strokeWidth={6}
                strokeLinecap="round"
                strokeDasharray={`${(Math.min(100, value) / 100) * c} ${c}`}
                transform="rotate(-90 32 32)"
            />
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

/** Horizontal ranked bars. */
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
                        <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                            <div
                                className={cn(
                                    'h-full rounded-full',
                                    colorClass,
                                )}
                                style={{
                                    width: `${(d.value / max) * 100}%`,
                                    backgroundColor: 'currentColor',
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

/** Level bars (e.g. tank fill %), coloured by threshold. */
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
                        <div className="relative h-2.5 overflow-hidden rounded-full bg-muted">
                            <div
                                className={cn('h-full rounded-full', tone)}
                                style={{ width: `${d.value}%` }}
                            />
                            <span className="absolute inset-y-0 left-[35%] w-px bg-background/80" />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

/** Row × column heatmap of percentages. */
export function Heatmap({
    columns,
    rows,
}: {
    columns: string[];
    rows: { label: string; values: number[] }[];
}) {
    const shade = (v: number) =>
        v >= 90
            ? 'bg-emerald-500 text-white'
            : v >= 80
              ? 'bg-emerald-400/70 text-emerald-950'
              : v >= 70
                ? 'bg-amber-300/80 text-amber-950'
                : 'bg-rose-400/80 text-rose-950';

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
                                    'rounded-[4px] py-1.5 text-center text-[11px] font-semibold tabular-nums',
                                    shade(v),
                                )}
                                title={`${row.label} · ${columns[i]}: ${v}%`}
                            >
                                {v}
                            </span>
                        ))}
                    </div>
                ))}
            </div>
            <div className="flex flex-wrap gap-3 text-[10.5px] text-muted-foreground">
                <span className="inline-flex items-center gap-1">
                    <span className="size-2 rounded-sm bg-emerald-500" />≥ 90%
                </span>
                <span className="inline-flex items-center gap-1">
                    <span className="size-2 rounded-sm bg-emerald-400/70" />
                    80–89%
                </span>
                <span className="inline-flex items-center gap-1">
                    <span className="size-2 rounded-sm bg-amber-300/80" />
                    70–79%
                </span>
                <span className="inline-flex items-center gap-1">
                    <span className="size-2 rounded-sm bg-rose-400/80" />
                    &lt; 70%
                </span>
            </div>
        </div>
    );
}
