import PptxGenJS from 'pptxgenjs';
import { loadImageBase64 } from '@/lib/jadwal-excel';
import { kpiTrend, pngSize } from '@/lib/ul-report';
import type { UlPart, UlReportData, UlTable } from '@/lib/ul-report';

/** 16:9 slide, inches. */
const W = 13.333;
const H = 7.5;
const NAVY = '0B4F7A';
const DARK = '1E295C';
const ACCENT = 'F5B700';
const FONT = 'Calibri';

/** Rows of a table per slide (the rest continues on the next slide). */
const ROWS_PER_SLIDE = 12;

type Slide = PptxGenJS.Slide;

/**
 * PPT rutin bulanan of a UL (Laporan UL): one 16:9 deck with the cover, the
 * agenda, then for each report (Pengusahaan Pembangkit, Logistik, K3L) a
 * divider and per bagian the KPI slide with the arah data, the per-unit
 * comparison and the 3D charts (two per slide), the status of the units'
 * reports and the input completeness, and a closing slide. Made in the
 * browser from the JSON of LaporanUlController::pptData.
 */
export async function downloadUlPpt(
    reports: UlReportData[],
    filename: string,
): Promise<void> {
    const first = reports[0];
    const pptx = new PptxGenJS();
    pptx.layout = 'LAYOUT_WIDE';
    pptx.author = 'KIT-SIPP';
    pptx.company = 'PT PLN Nusantara Power — UP Kendari';
    pptx.title = `Laporan Bulanan ${first.ul.name} — ${first.period.label}`;

    const pln = await loadImageBase64('/logo/sidebar-logo.png');
    const mkp = await loadImageBase64('/logo/mkp.jpg');
    const logos = (slide: Slide, small = true) => {
        const h = small ? 0.42 : 0.8;

        if (pln) {
            slide.addImage({
                data: `data:image/png;base64,${pln}`,
                x: small ? 0.35 : 0.8,
                y: small ? 0.18 : 0.6,
                w: h * 3.4,
                h,
            });
        }

        if (mkp) {
            slide.addImage({
                data: `data:image/jpeg;base64,${mkp}`,
                x: W - (small ? 0.35 : 0.8) - h * 1.4,
                y: small ? 0.18 : 0.6,
                w: h * 1.4,
                h,
            });
        }
    };

    let page = 0;
    /** A content slide: logos, title bar, page footer. */
    const content = (title: string, subtitle?: string): Slide => {
        page++;
        const slide = pptx.addSlide();
        slide.background = { color: 'FFFFFF' };
        logos(slide);
        slide.addShape(pptx.ShapeType.rect, {
            x: 0,
            y: 0.75,
            w: W,
            h: 0.06,
            fill: { color: ACCENT },
            line: { color: ACCENT },
        });
        slide.addText(title, {
            x: 0.4,
            y: 0.88,
            w: W - 0.8,
            h: 0.5,
            fontFace: FONT,
            fontSize: 22,
            bold: true,
            color: DARK,
        });

        if (subtitle) {
            slide.addText(subtitle, {
                x: 0.4,
                y: 1.33,
                w: W - 0.8,
                h: 0.3,
                fontFace: FONT,
                fontSize: 12,
                color: '64748B',
            });
        }

        slide.addText(`${first.ul.name} · ${first.period.label}`, {
            x: 0.4,
            y: H - 0.4,
            w: 8,
            h: 0.3,
            fontFace: FONT,
            fontSize: 9,
            color: '94A3B8',
        });
        slide.addText(String(page), {
            x: W - 1.0,
            y: H - 0.4,
            w: 0.6,
            h: 0.3,
            fontFace: FONT,
            fontSize: 9,
            color: '94A3B8',
            align: 'right',
        });

        return slide;
    };

    const table = (t: UlTable, subtitle: string) => {
        const rows = [...t.rows, ...(t.footer ? [t.footer] : [])];

        for (let i = 0; i < Math.max(rows.length, 1); i += ROWS_PER_SLIDE) {
            const chunk = rows.slice(i, i + ROWS_PER_SLIDE);
            const slide = content(
                t.title + (i > 0 ? ' (lanjutan)' : ''),
                subtitle,
            );
            const head = t.columns.map((text) => ({
                text,
                options: {
                    bold: true,
                    fill: { color: 'FDE68A' },
                    color: '0F172A',
                    align: 'center' as const,
                },
            }));
            const body = chunk.map((row) =>
                row.map((text, c) => ({
                    text,
                    options: {
                        align: (c === 0 ? 'left' : 'center') as
                            'left' | 'center',
                        bold: t.footer !== undefined && row === t.footer,
                        fill: {
                            color:
                                t.footer !== undefined && row === t.footer
                                    ? 'F1F5F9'
                                    : 'FFFFFF',
                        },
                    },
                })),
            );

            slide.addTable(
                [
                    head,
                    ...(body.length
                        ? body
                        : [
                              [
                                  {
                                      text: 'Tidak ada data.',
                                      options: {
                                          colspan: t.columns.length,
                                          align: 'center' as const,
                                          italic: true,
                                      },
                                  },
                              ],
                          ]),
                ],
                {
                    x: 0.4,
                    y: 1.75,
                    w: W - 0.8,
                    fontFace: FONT,
                    fontSize: t.columns.length > 7 ? 9 : 11,
                    border: { type: 'solid', pt: 0.5, color: '94A3B8' },
                    autoPage: false,
                },
            );
        }
    };

    const charts = (part: UlPart, reportTitle: string) => {
        for (let i = 0; i < part.charts.length; i += 2) {
            const pair = part.charts.slice(i, i + 2);
            const slide = content(`${part.title} — Grafik`, reportTitle);
            pair.forEach((chart, j) => {
                const boxW = (W - 1.1) / 2;
                const { width, height } = pngSize(chart.image);
                const w = boxW;
                const h = Math.min((height / width) * w, H - 2.5);
                slide.addText(chart.title, {
                    x: 0.4 + j * (boxW + 0.3),
                    y: 1.75,
                    w: boxW,
                    h: 0.35,
                    fontFace: FONT,
                    fontSize: 12,
                    bold: true,
                    color: DARK,
                });
                slide.addImage({
                    data: chart.image,
                    x: 0.4 + j * (boxW + 0.3),
                    y: 2.15,
                    w,
                    h,
                });
            });
        }
    };

    // 1. Cover
    page++;
    const cover = pptx.addSlide();
    cover.background = { color: 'FFFFFF' };
    cover.addShape(pptx.ShapeType.rect, {
        x: 0,
        y: H - 2.2,
        w: W,
        h: 2.2,
        fill: { color: DARK },
        line: { color: DARK },
    });
    cover.addShape(pptx.ShapeType.rect, {
        x: 0,
        y: H - 2.3,
        w: W,
        h: 0.1,
        fill: { color: ACCENT },
        line: { color: ACCENT },
    });
    logos(cover, false);
    cover.addText('LAPORAN BULANAN UNIT LAYANAN', {
        x: 0.8,
        y: 2.0,
        w: W - 1.6,
        h: 0.7,
        fontFace: FONT,
        fontSize: 34,
        bold: true,
        color: DARK,
        align: 'center',
    });
    cover.addText(first.ul.name.toUpperCase(), {
        x: 0.8,
        y: 2.75,
        w: W - 1.6,
        h: 0.6,
        fontFace: FONT,
        fontSize: 26,
        bold: true,
        color: NAVY,
        align: 'center',
    });
    cover.addText(`Periode ${first.period.label}`, {
        x: 0.8,
        y: 3.4,
        w: W - 1.6,
        h: 0.45,
        fontFace: FONT,
        fontSize: 18,
        color: '334155',
        align: 'center',
    });
    cover.addText(first.units.map((u) => u.name).join(' · ') || '—', {
        x: 0.8,
        y: 3.9,
        w: W - 1.6,
        h: 0.4,
        fontFace: FONT,
        fontSize: 13,
        color: '64748B',
        align: 'center',
    });
    cover.addText(
        'PT PLN Nusantara Power — UP Kendari · Jasa Pendukung Teknis 6 Site Pembangkit',
        {
            x: 0.8,
            y: H - 1.4,
            w: W - 1.6,
            h: 0.5,
            fontFace: FONT,
            fontSize: 14,
            color: 'FFFFFF',
            align: 'center',
        },
    );

    // 2. Agenda
    const agenda = content('Agenda', 'Isi laporan bulan ini');
    agenda.addText(
        reports.map((report, i) => ({
            text: `${i + 1}.  ${report.title} — ${report.parts.map((p) => p.title).join(', ')}`,
            options: { breakLine: true },
        })),
        {
            x: 0.8,
            y: 1.9,
            w: W - 1.6,
            h: 3.5,
            fontFace: FONT,
            fontSize: 18,
            color: '0F172A',
            paraSpaceAfter: 12,
            valign: 'top',
        },
    );

    for (const [index, report] of reports.entries()) {
        // Divider
        page++;
        const divider = pptx.addSlide();
        divider.background = { color: DARK };
        divider.addText(String(index + 1).padStart(2, '0'), {
            x: 0.8,
            y: 2.2,
            w: 3,
            h: 1.2,
            fontFace: FONT,
            fontSize: 60,
            bold: true,
            color: ACCENT,
        });
        divider.addText(report.title.toUpperCase(), {
            x: 0.8,
            y: 3.4,
            w: W - 1.6,
            h: 0.8,
            fontFace: FONT,
            fontSize: 32,
            bold: true,
            color: 'FFFFFF',
        });
        divider.addText(`${report.ul.name} · ${report.period.label}`, {
            x: 0.8,
            y: 4.2,
            w: W - 1.6,
            h: 0.5,
            fontFace: FONT,
            fontSize: 16,
            color: 'CBD5E1',
        });

        for (const part of report.parts) {
            // KPI cards + arah data
            const slide = content(
                part.title,
                `${report.title} · akumulasi seluruh unit di bawah UL`,
            );
            const cardW = (W - 0.8 - 0.2 * 4) / 5;
            part.kpis.slice(0, 5).forEach((kpi, i) => {
                const x = 0.4 + i * (cardW + 0.2);
                slide.addShape(pptx.ShapeType.roundRect, {
                    x,
                    y: 1.75,
                    w: cardW,
                    h: 1.45,
                    fill: { color: 'F8FAFC' },
                    line: { color: 'CBD5E1' },
                    rectRadius: 0.08,
                });
                slide.addText(kpi.label.toUpperCase(), {
                    x: x + 0.1,
                    y: 1.8,
                    w: cardW - 0.2,
                    h: 0.35,
                    fontFace: FONT,
                    fontSize: 10,
                    color: '475569',
                });
                slide.addText(kpi.value, {
                    x: x + 0.1,
                    y: 2.15,
                    w: cardW - 0.2,
                    h: 0.55,
                    fontFace: FONT,
                    fontSize: 20,
                    bold: true,
                    color: DARK,
                    fit: 'shrink',
                });
                slide.addText(kpiTrend(kpi), {
                    x: x + 0.1,
                    y: 2.7,
                    w: cardW - 0.2,
                    h: 0.45,
                    fontFace: FONT,
                    fontSize: 9,
                    color:
                        kpi.trend === 'naik'
                            ? '047857'
                            : kpi.trend === 'turun'
                              ? 'B91C1C'
                              : '64748B',
                });
            });

            const tone: Record<string, string> = {
                success: '047857',
                danger: 'B91C1C',
                warning: 'B45309',
                info: '1D4ED8',
                neutral: '64748B',
            };
            slide.addText('Ringkasan Arah Data', {
                x: 0.4,
                y: 3.4,
                w: W - 0.8,
                h: 0.35,
                fontFace: FONT,
                fontSize: 14,
                bold: true,
                color: NAVY,
            });
            slide.addText(
                part.insights
                    .slice(0, 8)
                    .map((line) => ({
                        text: line.text,
                        options: {
                            bullet: { characterCode: '25CF' },
                            color: tone[line.tone] ?? '334155',
                            breakLine: true,
                        },
                    })),
                {
                    x: 0.5,
                    y: 3.8,
                    w: W - 1.0,
                    h: 3.1,
                    fontFace: FONT,
                    fontSize: 12,
                    color: '334155',
                    valign: 'top',
                    paraSpaceAfter: 4,
                },
            );

            if (part.unit_table) {
                table(
                    part.unit_table,
                    `${report.title} · perbandingan antar unit`,
                );
            }

            charts(part, report.title);
        }

        table(report.reports, report.title);
        table(report.completeness, report.title);
    }

    // Closing
    page++;
    const closing = pptx.addSlide();
    closing.background = { color: DARK };
    closing.addText('TERIMA KASIH', {
        x: 0.8,
        y: 2.8,
        w: W - 1.6,
        h: 1,
        fontFace: FONT,
        fontSize: 44,
        bold: true,
        color: 'FFFFFF',
        align: 'center',
    });
    closing.addText(`${first.ul.name} · ${first.period.label}`, {
        x: 0.8,
        y: 3.8,
        w: W - 1.6,
        h: 0.5,
        fontFace: FONT,
        fontSize: 18,
        color: ACCENT,
        align: 'center',
    });

    await pptx.writeFile({ fileName: filename });
}
