/**
 * Lightweight, dependency-free SVG charts for the dashboard. Colours use
 * `currentColor` driven by Tailwind text utilities so they stay theme-aware.
 */

const SERIES = [
    'text-sky-500',
    'text-emerald-500',
    'text-amber-500',
    'text-violet-500',
    'text-rose-500',
    'text-cyan-500',
];

function ChartCard({ title, subtitle, children }: { title: string; subtitle?: string; children: React.ReactNode }) {
    return (
        <div className="flex flex-col gap-3 rounded-md border border-border bg-card p-4">
            <div>
                <h3 className="text-sm font-semibold text-foreground">{title}</h3>
                {subtitle && <p className="text-[12px] text-muted-foreground">{subtitle}</p>}
            </div>
            {children}
        </div>
    );
}

function LegendDot({ colorClass, dashed, label }: { colorClass: string; dashed?: boolean; label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 text-[12px] text-muted-foreground">
            <span className={`inline-block h-0.5 w-4 ${colorClass}`} style={{ borderTop: dashed ? '2px dashed currentColor' : '2px solid currentColor' }} />
            {label}
        </span>
    );
}

type SCurvePoint = { day: number; plan: number; real: number };

/** Cumulative planned-vs-realised S-curve. */
export function SCurveChart({ points, monthLabel }: { points: SCurvePoint[]; monthLabel: string }) {
    const W = 640;
    const H = 240;
    const pad = { l: 34, r: 14, t: 14, b: 26 };
    const innerW = W - pad.l - pad.r;
    const innerH = H - pad.t - pad.b;
    const days = points.length || 1;
    const max = Math.max(1, ...points.map((p) => Math.max(p.plan, p.real)));

    const x = (day: number) => pad.l + ((day - 1) / Math.max(1, days - 1)) * innerW;
    const y = (v: number) => pad.t + innerH - (v / max) * innerH;

    const line = (key: 'plan' | 'real') =>
        points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(p.day).toFixed(1)} ${y(p[key]).toFixed(1)}`).join(' ');
    const realArea = `${line('real')} L ${x(points[points.length - 1]?.day ?? 1).toFixed(1)} ${y(0).toFixed(1)} L ${x(1).toFixed(1)} ${y(0).toFixed(1)} Z`;
    const ticks = [1, Math.ceil(days / 2), days].filter((d, i, a) => a.indexOf(d) === i);
    const gridVals = [0, 0.5, 1].map((f) => Math.round(max * f));

    return (
        <ChartCard title="Kurva S — Rencana vs Realisasi Kegiatan K3" subtitle={`Kumulatif ${monthLabel}`}>
            <svg viewBox={`0 0 ${W} ${H}`} className="h-56 w-full" preserveAspectRatio="none" role="img">
                <g className="text-border">
                    {gridVals.map((v) => (
                        <line key={v} x1={pad.l} x2={W - pad.r} y1={y(v)} y2={y(v)} stroke="currentColor" strokeWidth={1} opacity={0.4} />
                    ))}
                </g>
                {gridVals.map((v) => (
                    <text key={v} x={pad.l - 6} y={y(v) + 3} textAnchor="end" className="fill-muted-foreground" fontSize={9}>
                        {v}
                    </text>
                ))}
                {ticks.map((d) => (
                    <text key={d} x={x(d)} y={H - 8} textAnchor="middle" className="fill-muted-foreground" fontSize={9}>
                        {d}
                    </text>
                ))}
                <path d={realArea} className="text-emerald-500" fill="currentColor" opacity={0.12} />
                <path d={line('plan')} className="text-sky-500" fill="none" stroke="currentColor" strokeWidth={2} strokeDasharray="5 4" />
                <path d={line('real')} className="text-emerald-500" fill="none" stroke="currentColor" strokeWidth={2.5} />
            </svg>
            <div className="flex gap-4">
                <LegendDot colorClass="text-sky-500" dashed label="Rencana" />
                <LegendDot colorClass="text-emerald-500" label="Realisasi" />
            </div>
        </ChartCard>
    );
}

type TrendPoint = { label: string; wo: number; sr: number };

/** Combined bar (WO) + line (SR) trend over months. */
export function ComboChart({ data }: { data: TrendPoint[] }) {
    const W = 520;
    const H = 240;
    const pad = { l: 30, r: 14, t: 14, b: 28 };
    const innerW = W - pad.l - pad.r;
    const innerH = H - pad.t - pad.b;
    const max = Math.max(1, ...data.map((d) => Math.max(d.wo, d.sr)));
    const n = data.length || 1;
    const slot = innerW / n;
    const barW = Math.min(34, slot * 0.5);
    const y = (v: number) => pad.t + innerH - (v / max) * innerH;
    const cx = (i: number) => pad.l + slot * i + slot / 2;
    const srPath = data.map((d, i) => `${i === 0 ? 'M' : 'L'} ${cx(i).toFixed(1)} ${y(d.sr).toFixed(1)}`).join(' ');

    return (
        <ChartCard title="Tren Work Order & Service Request" subtitle="6 bulan terakhir">
            <svg viewBox={`0 0 ${W} ${H}`} className="h-56 w-full" role="img">
                <g className="text-border">
                    {[0, 0.5, 1].map((f) => (
                        <line key={f} x1={pad.l} x2={W - pad.r} y1={y(max * f)} y2={y(max * f)} stroke="currentColor" strokeWidth={1} opacity={0.4} />
                    ))}
                </g>
                {data.map((d, i) => (
                    <rect
                        key={d.label}
                        x={cx(i) - barW / 2}
                        y={y(d.wo)}
                        width={barW}
                        height={Math.max(0, y(0) - y(d.wo))}
                        rx={2}
                        className="text-sky-500/70"
                        fill="currentColor"
                    />
                ))}
                <path d={srPath} className="text-amber-500" fill="none" stroke="currentColor" strokeWidth={2.5} />
                {data.map((d, i) => (
                    <circle key={d.label} cx={cx(i)} cy={y(d.sr)} r={3} className="text-amber-500" fill="currentColor" />
                ))}
                {data.map((d, i) => (
                    <text key={d.label} x={cx(i)} y={H - 9} textAnchor="middle" className="fill-muted-foreground" fontSize={9}>
                        {d.label}
                    </text>
                ))}
            </svg>
            <div className="flex gap-4">
                <LegendDot colorClass="text-sky-500" label="Work Order" />
                <LegendDot colorClass="text-amber-500" label="Service Request" />
            </div>
        </ChartCard>
    );
}

type Slice = { type: string; count: number };

/** Donut of Work Orders by maintenance type. */
export function DonutChart({ title, subtitle, data }: { title: string; subtitle?: string; data: Slice[] }) {
    const total = data.reduce((s, d) => s + d.count, 0);
    const r = 52;
    const c = 2 * Math.PI * r;
    const lens = data.map((d) => (total === 0 ? 0 : (d.count / total) * c));
    const offsets = lens.map((_, i) => lens.slice(0, i).reduce((s, v) => s + v, 0));

    return (
        <ChartCard title={title} subtitle={subtitle}>
            {total === 0 ? (
                <p className="py-8 text-center text-[13px] text-muted-foreground">Belum ada data.</p>
            ) : (
                <div className="flex flex-wrap items-center gap-6">
                    <svg viewBox="0 0 140 140" className="h-36 w-36 shrink-0" role="img">
                        <g transform="translate(70 70) rotate(-90)">
                            <circle r={r} cx={0} cy={0} fill="none" className="text-muted" stroke="currentColor" strokeWidth={16} opacity={0.3} />
                            {data.map((d, i) => (
                                <circle
                                    key={d.type}
                                    r={r}
                                    cx={0}
                                    cy={0}
                                    fill="none"
                                    className={SERIES[i % SERIES.length]}
                                    stroke="currentColor"
                                    strokeWidth={16}
                                    strokeDasharray={`${lens[i]} ${c - lens[i]}`}
                                    strokeDashoffset={-offsets[i]}
                                />
                            ))}
                        </g>
                        <text x={70} y={68} textAnchor="middle" className="fill-foreground" fontSize={20} fontWeight={700}>
                            {total}
                        </text>
                        <text x={70} y={84} textAnchor="middle" className="fill-muted-foreground" fontSize={9}>
                            Total
                        </text>
                    </svg>
                    <ul className="flex flex-col gap-1.5">
                        {data.map((d, i) => (
                            <li key={d.type} className="flex items-center gap-2 text-[13px]">
                                <span className={`size-2.5 rounded-full ${SERIES[i % SERIES.length]}`} style={{ backgroundColor: 'currentColor' }} />
                                <span className="font-medium text-foreground">{d.type}</span>
                                <span className="text-muted-foreground">
                                    {d.count} · {Math.round((d.count / total) * 100)}%
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </ChartCard>
    );
}

type Point = { label: string; value: number };

/** Single-series line + area (e.g. daily peak load over the month). */
export function LineChart({ title, subtitle, points, unit }: { title: string; subtitle?: string; points: Point[]; unit?: string }) {
    const W = 520;
    const H = 220;
    const pad = { l: 36, r: 14, t: 14, b: 24 };
    const innerW = W - pad.l - pad.r;
    const innerH = H - pad.t - pad.b;
    const n = points.length || 1;
    const max = Math.max(1, ...points.map((p) => p.value));
    const x = (i: number) => pad.l + (i / Math.max(1, n - 1)) * innerW;
    const y = (v: number) => pad.t + innerH - (v / max) * innerH;
    const path = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${x(i).toFixed(1)} ${y(p.value).toFixed(1)}`).join(' ');
    const area = `${path} L ${x(n - 1).toFixed(1)} ${y(0).toFixed(1)} L ${x(0).toFixed(1)} ${y(0).toFixed(1)} Z`;
    const ticks = [0, Math.floor((n - 1) / 2), n - 1].filter((d, i, a) => a.indexOf(d) === i);
    const hasData = points.some((p) => p.value > 0);

    return (
        <ChartCard title={title} subtitle={subtitle}>
            {!hasData ? (
                <p className="py-8 text-center text-[13px] text-muted-foreground">Belum ada data.</p>
            ) : (
                <svg viewBox={`0 0 ${W} ${H}`} className="h-52 w-full" preserveAspectRatio="none" role="img">
                    <g className="text-border">
                        {[0, 0.5, 1].map((f) => (
                            <line key={f} x1={pad.l} x2={W - pad.r} y1={y(max * f)} y2={y(max * f)} stroke="currentColor" strokeWidth={1} opacity={0.4} />
                        ))}
                    </g>
                    {[0, max].map((v) => (
                        <text key={v} x={pad.l - 6} y={y(v) + 3} textAnchor="end" className="fill-muted-foreground" fontSize={9}>
                            {v}
                        </text>
                    ))}
                    {ticks.map((i) => (
                        <text key={i} x={x(i)} y={H - 7} textAnchor="middle" className="fill-muted-foreground" fontSize={9}>
                            {points[i]?.label}
                        </text>
                    ))}
                    <path d={area} className="text-violet-500" fill="currentColor" opacity={0.12} />
                    <path d={path} className="text-violet-500" fill="none" stroke="currentColor" strokeWidth={2.5} />
                </svg>
            )}
            {unit && hasData && <p className="text-[11px] text-muted-foreground">Satuan: {unit}</p>}
        </ChartCard>
    );
}

const RUPIAH = (v: number) => 'Rp ' + Math.round(v).toLocaleString('id-ID');

/** Vertical bars with value labels (e.g. monthly maintenance cost). */
export function BarChart({ title, subtitle, data, format }: { title: string; subtitle?: string; data: Point[]; format?: 'number' | 'rupiah' }) {
    const W = 520;
    const H = 220;
    const pad = { l: 20, r: 14, t: 20, b: 28 };
    const innerW = W - pad.l - pad.r;
    const innerH = H - pad.t - pad.b;
    const max = Math.max(1, ...data.map((d) => d.value));
    const n = data.length || 1;
    const slot = innerW / n;
    const barW = Math.min(40, slot * 0.55);
    const y = (v: number) => pad.t + innerH - (v / max) * innerH;
    const cx = (i: number) => pad.l + slot * i + slot / 2;
    const fmt = (v: number) => (format === 'rupiah' ? RUPIAH(v) : v.toLocaleString('id-ID'));
    const hasData = data.some((d) => d.value > 0);

    return (
        <ChartCard title={title} subtitle={subtitle}>
            {!hasData ? (
                <p className="py-8 text-center text-[13px] text-muted-foreground">Belum ada data.</p>
            ) : (
                <svg viewBox={`0 0 ${W} ${H}`} className="h-52 w-full" role="img">
                    <g className="text-border">
                        <line x1={pad.l} x2={W - pad.r} y1={y(0)} y2={y(0)} stroke="currentColor" strokeWidth={1} opacity={0.5} />
                    </g>
                    {data.map((d, i) => (
                        <rect key={d.label} x={cx(i) - barW / 2} y={y(d.value)} width={barW} height={Math.max(0, y(0) - y(d.value))} rx={2} className="text-emerald-500/70" fill="currentColor" />
                    ))}
                    {data.map((d, i) => (
                        <text key={d.label} x={cx(i)} y={y(d.value) - 4} textAnchor="middle" className="fill-muted-foreground" fontSize={8}>
                            {d.value > 0 ? fmt(d.value) : ''}
                        </text>
                    ))}
                    {data.map((d, i) => (
                        <text key={d.label} x={cx(i)} y={H - 9} textAnchor="middle" className="fill-muted-foreground" fontSize={9}>
                            {d.label}
                        </text>
                    ))}
                </svg>
            )}
        </ChartCard>
    );
}

type Bar = { label: string; value: number };

/** Horizontal bar list of cross-module activity. */
export function BarList({ title, subtitle, data }: { title: string; subtitle?: string; data: Bar[] }) {
    const max = Math.max(1, ...data.map((d) => d.value));

    return (
        <ChartCard title={title} subtitle={subtitle}>
            {data.length === 0 ? (
                <p className="py-8 text-center text-[13px] text-muted-foreground">Belum ada data.</p>
            ) : (
                <ul className="flex flex-col gap-2.5">
                    {data.map((d, i) => (
                        <li key={d.label} className="flex items-center gap-3 text-[13px]">
                            <span className="w-28 shrink-0 text-muted-foreground">{d.label}</span>
                            <span className="flex h-4 flex-1 items-center overflow-hidden rounded-sm bg-muted/40">
                                <span
                                    className={`h-full rounded-sm ${SERIES[i % SERIES.length]}`}
                                    style={{ width: `${Math.max(2, (d.value / max) * 100)}%`, backgroundColor: 'currentColor', opacity: 0.75 }}
                                />
                            </span>
                            <span className="w-10 shrink-0 text-right font-semibold tabular-nums text-foreground">{d.value}</span>
                        </li>
                    ))}
                </ul>
            )}
        </ChartCard>
    );
}
